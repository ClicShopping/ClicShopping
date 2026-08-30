<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\DomainsAI\Analytics\Planning;

/**
 * PeriodResolver
 *
 * Computes the comparison window instead of letting the model write it. Comparing a partial
 * current period with a whole previous one makes every variation wrong, and wrong in the
 * direction that reassures.
 *
 * One idea governs both rules below: the CURRENT window defines the scope of observation, and
 * the previous one is built as its homologue — never the other way round.
 *
 * 1. Asking for a comparison bounds the current window at the observation date. "This year vs
 *    the same period last year" is year-to-date against year-to-date, not a calendar year whose
 *    remaining months hold no data against a complete one. A window lying entirely in the future
 *    is left alone: bounding it would produce an empty interval.
 * 2. `previous_year` is the CALENDAR homologue (same bounds, one year back), not an equal-length
 *    one. 29 February has no homologue: PHP overflows to 1 March, the convention is the last day
 *    of the month. The two windows may then differ by one day in length, which is accepted —
 *    equal length made the previous window overlap the current one by a day on leap years.
 * 3. `previous_period` is unchanged and remains a DURATION convention: the same span, immediately
 *    before. No calendar homologue is involved.
 * 4. `previous_year_comparable_days` is the WEEKDAY homologue: the same window shifted back 52 weeks,
 *    so both windows hold the same count of Mondays, Saturdays and business days. 19 August 2026 is a
 *    Wednesday, 19 August 2025 a Tuesday - on a weekday-sensitive activity that composition gap weighs
 *    more than 29 February. It is a SECOND metric next to the calendar one, never a replacement: the
 *    two answer different questions and are never summed or substituted for one another.
 * An unknown convention degrades to `none`: no comparison is better than a window nobody asked for.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
final class PeriodResolver
{
  public const COMPARE_NONE = 'none';
  public const COMPARE_PREVIOUS_YEAR = 'previous_year';
  public const COMPARE_PREVIOUS_PERIOD = 'previous_period';
  public const COMPARE_PREVIOUS_YEAR_COMPARABLE_DAYS = 'previous_year_comparable_days';

  /** 52 weeks: the shift that keeps the day of week, hence the count of business days. */
  private const COMPARABLE_DAYS_SHIFT = '-364 days';

  /**
   * @param array $periods `{current: {from, to}, compare: none|previous_year|previous_year_comparable_days|previous_period}`
   * @param \DateTimeImmutable|null $observedAt Observation date; defaults to now. Injected by tests only.
   * @return array Allow-listed `{current: {from, to}, compare, previous?: {from, to}}`, every bound Y-m-d,
   *               or `{current: {from: null, to: null}, compare: none, period_missing: true}`
   * @throws \InvalidArgumentException When the current window is reversed or contains unreadable dates
   */
  public static function resolve(array $periods, ?\DateTimeImmutable $observedAt = null): array
  {
    $from = (string)($periods['current']['from'] ?? '');
    $to = (string)($periods['current']['to'] ?? '');

    if ($from === '' || $to === '') {
      return ['current' => ['from' => null, 'to' => null], 'compare' => self::COMPARE_NONE, 'period_missing' => true];
    }

    try {
      $start = new \DateTimeImmutable($from);
      $end = new \DateTimeImmutable($to);
    } catch (\Throwable $e) {
      throw new \InvalidArgumentException('The plan carries an unreadable period bound: ' . $e->getMessage());
    }

    if ($start > $end) {
      throw new \InvalidArgumentException('The current period ends before it starts');
    }

    $compare = (string)($periods['compare'] ?? self::COMPARE_NONE);

    if (!in_array($compare, [self::COMPARE_NONE, self::COMPARE_PREVIOUS_YEAR,
                             self::COMPARE_PREVIOUS_YEAR_COMPARABLE_DAYS, self::COMPARE_PREVIOUS_PERIOD], true)) {
      $compare = self::COMPARE_NONE;
    }

    $today = ($observedAt ?? new \DateTimeImmutable())->setTime(0, 0);

    // Comparing bounds the current window at the observation date: the months it has no data
    // for would otherwise be weighed against a complete previous window. A window entirely in
    // the future is left alone - bounding it would invert its two ends.
    if ($compare !== self::COMPARE_NONE && $end > $today && $start <= $today) {
      $end = $today;
    }

    // Allow-listed output: an unknown sibling key inside the model's `periods` object
    // (or an unformatted bound) never survives into the "trusted" plan.
    $resolved = [
      'current' => [
        'from' => $start->format('Y-m-d'),
        'to' => $end->format('Y-m-d'),
      ],
      'compare' => $compare,
    ];

    if ($compare === self::COMPARE_NONE) {
      return $resolved;
    }

    if ($compare === self::COMPARE_PREVIOUS_YEAR) {
      $previousStart = self::sameDayLastYear($start);
      $previousEnd = self::sameDayLastYear($end);
    } elseif ($compare === self::COMPARE_PREVIOUS_YEAR_COMPARABLE_DAYS) {
      $previousStart = $start->modify(self::COMPARABLE_DAYS_SHIFT);
      $previousEnd = $end->modify(self::COMPARABLE_DAYS_SHIFT);
    } else {
      // Duration convention: the same span, ending the day before the current window opens.
      $lengthInDays = (int)$start->diff($end)->days;
      $previousStart = $start->modify('-' . ($lengthInDays + 1) . ' days');
      $previousEnd = $previousStart->modify('+' . $lengthInDays . ' days');
    }

    $resolved['previous'] = [
      'from' => $previousStart->format('Y-m-d'),
      'to' => $previousEnd->format('Y-m-d'),
    ];

    return $resolved;
  }

  /**
   * The same calendar day one year earlier. 29 February has no homologue: `modify('-1 year')`
   * overflows into March, and the convention is the last day of February instead.
   *
   * @param \DateTimeImmutable $date Bound of the current window
   * @return \DateTimeImmutable Its calendar homologue one year back
   */
  private static function sameDayLastYear(\DateTimeImmutable $date): \DateTimeImmutable
  {
    $shifted = $date->modify('-1 year');

    return $shifted->format('n') === $date->format('n')
      ? $shifted
      : $shifted->modify('last day of previous month');
  }
}
