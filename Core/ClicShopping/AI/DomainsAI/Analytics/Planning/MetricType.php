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
 * MetricType
 *
 * The closed set of metric natures, and the one rule that depends on it: a RATE varies in
 * PERCENTAGE POINTS, everything else varies in percent. Agnostic by construction — the nature
 * of a quantity is not e-commerce knowledge, only its name is.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
final class MetricType
{
  public const AMOUNT = 'amount';
  public const COUNT = 'count';
  public const RATE = 'rate';
  public const DURATION = 'duration';

  /**
   * @return array<int, string> Every valid type
   */
  public static function all(): array
  {
    return [self::AMOUNT, self::COUNT, self::RATE, self::DURATION];
  }

  /**
   * @param string $type Candidate type
   * @return bool True when the type belongs to the closed set
   */
  public static function isValid(string $type): bool
  {
    return in_array($type, self::all(), true);
  }

  /**
   * Dividing one rate by another is a category error: 70% to 72.73% is +2.73 points, never +3.9%.
   *
   * @param string $type Metric type
   * @return bool True when the variation must be expressed in percentage points
   */
  public static function variesInPoints(string $type): bool
  {
    return $type === self::RATE;
  }
}
