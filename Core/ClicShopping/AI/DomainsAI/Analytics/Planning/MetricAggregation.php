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
 * MetricAggregation
 *
 * The closed set of ways a metric rolls up across a grain: only an ADDITIVE metric may be summed
 * from finer rows; a ratio of sums is recomputed from its summed parts, never summed or averaged.
 * Agnostic by construction, like MetricType.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
final class MetricAggregation
{
  public const ADDITIVE = 'additive';
  public const RATIO_OF_SUMS = 'ratio_of_sums';
  public const AVERAGE = 'average';
  public const DISTINCT_COUNT = 'distinct_count';

  /**
   * @return array<int, string> Every valid aggregation
   */
  public static function all(): array
  {
    return [self::ADDITIVE, self::RATIO_OF_SUMS, self::AVERAGE, self::DISTINCT_COUNT];
  }

  /**
   * @param string $aggregation Candidate aggregation
   * @return bool True when the aggregation belongs to the closed set
   */
  public static function isValid(string $aggregation): bool
  {
    return in_array($aggregation, self::all(), true);
  }
}
