<?php
/**
 * ClicShopping AI™
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\Helper;

/**
 * FuturePeriodMask
 *
 * A full calendar year is a legitimate visual context; the months it has no data for are not
 * worth zero. Rendered as `0` they read as a collapse, and nothing tells them apart from a
 * measured zero once the table is drawn. Rows whose period starts after the observation date
 * therefore carry the out-of-scope marker instead of a number.
 *
 * A row whose year cannot be established is left untouched: a bare month number belongs to no
 * year, and guessing one would mask a past series.
 *
 * @package ClicShopping\AI\Helper
 */
final class FuturePeriodMask
{
  private const PERIOD_COLUMN = '/(date|month|period|day|week|quarter|year)/i';

  /**
   * @param array $row One rendered row, column => value
   * @param string $marker Out-of-scope label rendered in place of every numeric value
   * @param \DateTimeImmutable|null $observedAt Observation date; defaults to now. Injected by tests only.
   * @return array The row, its measures replaced by the marker when its period is still to come
   */
  public static function apply(array $row, string $marker, ?\DateTimeImmutable $observedAt = null): array
  {
    $start = self::periodStart($row);

    if ($start === null || $start <= ($observedAt ?? new \DateTimeImmutable())->setTime(0, 0)) {
      return $row;
    }

    foreach ($row as $key => $value) {
      if (is_numeric($value) && !preg_match(self::PERIOD_COLUMN, (string)$key)) {
        $row[$key] = $marker;
      }
    }

    return $row;
  }

  /**
   * First instant covered by the row, or null when its year is not determinable.
   */
  private static function periodStart(array $row): ?\DateTimeImmutable
  {
    $year = null;
    $month = null;
    $day = null;

    foreach ($row as $key => $value) {
      if (!is_scalar($value) || !preg_match(self::PERIOD_COLUMN, (string)$key)) {
        continue;
      }

      $raw = trim((string)$value);

      // A formatted date carries every part at once and wins over the scattered columns.
      if (preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?/', $raw, $m)) {
        return self::atMidnight((int)$m[1], (int)$m[2], isset($m[3]) ? (int)$m[3] : 1);
      }

      if (preg_match('/year/i', (string)$key) && preg_match('/^\d{4}$/', $raw)) {
        $year = (int)$raw;
      } elseif (preg_match('/month/i', (string)$key) && preg_match('/^(1[0-2]|[1-9])$/', $raw)) {
        $month = (int)$raw;
      } elseif (preg_match('/day/i', (string)$key) && preg_match('/^(3[01]|[12][0-9]|[1-9])$/', $raw)) {
        $day = (int)$raw;
      }
    }

    return $year === null ? null : self::atMidnight($year, $month ?? 1, $day ?? 1);
  }

  private static function atMidnight(int $year, int $month, int $day): \DateTimeImmutable
  {
    return (new \DateTimeImmutable())->setDate($year, $month, $day)->setTime(0, 0);
  }
}
