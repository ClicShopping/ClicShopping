<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * CompareWindowFilter
 *
 * Deterministic post-generation check for comparison plans (`periods.compare` set): inside one
 * SELECT block, the WHERE must admit every plan window the select list reads. A WHERE kept on one
 * window silently zeroes the other side of the comparison while every value still looks plausible.
 *
 * Widens the WHERE date range to cover both windows when it carries exactly two date literals
 * (one range); any other shape is only reported, never rewritten.
 */
final class CompareWindowFilter
{
  private const string DATE_LITERAL = "/'(\d{4}-\d{2}-\d{2})/";

  /**
   * @param string $sql Executable SQL
   * @param array $periods Resolved plan periods (`PeriodResolver::resolve()` output)
   * @return array{sql: string, flagged: bool, corrected: bool, reason: string}
   */
  public static function check(string $sql, array $periods): array
  {
    $result = ['sql' => $sql, 'flagged' => false, 'corrected' => false, 'reason' => ''];
    $windowDates = self::windowDates($periods);

    if ($windowDates === []) {
      return $result;
    }

    ['blockAt' => $blockAt, 'specs' => $specs] = SqlSelectBlocks::parse($sql);

    preg_match_all(self::DATE_LITERAL, $sql, $dateMatches, PREG_OFFSET_CAPTURE);
    $dates = [];
    foreach ($dateMatches[1] as [$date, $pos]) {
      $dates[] = ['date' => $date, 'pos' => $pos, 'block' => $blockAt[$pos]];
    }

    $replacements = [];
    $reasons = [];

    foreach ($specs as $spec) {
      $read = [];
      $filtered = [];

      foreach ($dates as $d) {
        if ($d['block'] !== $spec['block']) {
          continue;
        }

        if ($d['pos'] > $spec['select'] && $d['pos'] < $spec['from'] && isset($windowDates[$d['date']])) {
          $read[] = $d['date'];
        } elseif ($spec['where'] !== null && $d['pos'] > $spec['where'] && $d['pos'] < $spec['whereEnd']) {
          $filtered[] = $d;
        }
      }

      if ($read === [] || $filtered === []) {
        continue;
      }

      $filteredDates = array_column($filtered, 'date');
      $lo = min($filteredDates);
      $hi = max($filteredDates);
      $excluded = array_filter($read, static fn(string $d): bool => $d < $lo || $d > $hi);

      if ($excluded === []) {
        continue;
      }

      $result['flagged'] = true;
      $reasons[] = 'WHERE keeps ' . $lo . '..' . $hi . ' but the select list reads ' . implode(', ', array_unique($excluded));

      if (count($filtered) !== 2 || $lo === $hi) {
        continue;
      }

      foreach ($filtered as $d) {
        $replacements[$d['pos']] = $d['date'] === $lo ? min($lo, min($read)) : max($hi, max($read));
      }
    }

    if ($replacements !== []) {
      krsort($replacements);
      foreach ($replacements as $pos => $date) {
        $sql = substr_replace($sql, $date, $pos, 10);
      }
      $result['sql'] = $sql;
      $result['corrected'] = true;
    }

    $result['reason'] = implode('; ', $reasons);

    return $result;
  }

  /**
   * Date literals of a comparison SQL that are neither a window bound nor a date the plan itself
   * carries (e.g. a filter): a shifted comparison year reads another period in silence.
   *
   * @param string $sql Executable SQL
   * @param array $plan Resolved analysis plan (`periods`, `filters`, …)
   * @return list<string> Foreign dates, Y-m-d
   */
  public static function foreignDates(string $sql, array $plan): array
  {
    $allowed = self::windowDates($plan['periods'] ?? []);

    if ($allowed === []) {
      return [];
    }

    preg_match_all("/\\d{4}-\\d{2}-\\d{2}/", (string)json_encode($plan), $planDates);
    $allowed += array_fill_keys($planDates[0], true);

    preg_match_all(self::DATE_LITERAL, $sql, $matches);

    return array_values(array_unique(array_filter($matches[1], static fn(string $d): bool => !isset($allowed[$d]))));
  }

  /**
   * Bounds of both plan windows, plus the exclusive upper bound (`to` + 1 day) a `<` filter uses.
   *
   * @param array $periods Resolved plan periods
   * @return array<string, true>
   */
  private static function windowDates(array $periods): array
  {
    if (($periods['compare'] ?? 'none') === 'none' || !isset($periods['current']['from'], $periods['previous']['from'])) {
      return [];
    }

    $dates = [];
    foreach (['current', 'previous'] as $window) {
      $from = (string)$periods[$window]['from'];
      $to = (string)($periods[$window]['to'] ?? '');
      $dates[$from] = true;

      if ($to !== '') {
        $dates[$to] = true;
        $dates[(new \DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d')] = true;
      }
    }

    return $dates;
  }
}
