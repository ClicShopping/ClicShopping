<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment;

use function count;

/**
 * SentimentMetrics — deterministic sentiment metrics computed from star ratings.
 *
 * Distribution and dispersion come ONLY from numeric star ratings (never the LLM),
 * so the figures are reproducible and cannot be hallucinated.
 */
class SentimentMetrics
{
  public const CONFIDENCE_VERY_LOW = 'very_low';
  public const CONFIDENCE_LOW      = 'low';
  public const CONFIDENCE_MEDIUM   = 'medium';
  public const CONFIDENCE_HIGH     = 'high';

  /** Std-dev (on the 1..5 scale) at/above which the corpus is flagged polarized. */
  public const POLARIZATION_STDDEV_THRESHOLD = 1.2;

  /**
   * @param array<int,int|string> $ratings Star ratings (1..5).
   * @return array{count:int,positive_pct:int,neutral_pct:int,negative_pct:int,rating_stddev:float,confidence:string,polarized:bool}
   */
  public static function compute(array $ratings): array
  {
    $count = count($ratings);

    if ($count === 0) {
      return [
        'count' => 0, 'positive_pct' => 0, 'neutral_pct' => 0, 'negative_pct' => 0,
        'rating_stddev' => 0.0, 'confidence' => self::CONFIDENCE_VERY_LOW, 'polarized' => false,
      ];
    }

    $pos = $neg = 0;
    $sum = 0.0;

    foreach ($ratings as $r) {
      $r = (int)$r;
      $sum += $r;
      if ($r >= 4) {
        $pos++;
      } elseif ($r <= 2) {
        $neg++;
      }
    }

    $positive_pct = (int)round($pos * 100 / $count);
    $negative_pct = (int)round($neg * 100 / $count);
    $neutral_pct  = 100 - $positive_pct - $negative_pct; // remainder guarantees sum = 100

    $mean = $sum / $count;
    $variance = 0.0;
    foreach ($ratings as $r) {
      $variance += (((int)$r) - $mean) ** 2;
    }
    $stddev = sqrt($variance / $count);

    return [
      'count'         => $count,
      'positive_pct'  => $positive_pct,
      'neutral_pct'   => $neutral_pct,
      'negative_pct'  => $negative_pct,
      'rating_stddev' => round($stddev, 3),
      'confidence'    => self::confidenceLevel($count),
      'polarized'     => $stddev >= self::POLARIZATION_STDDEV_THRESHOLD,
    ];
  }

  /**
   * Confidence gradation by review volume (backlog-defined bands).
   */
  public static function confidenceLevel(int $count): string
  {
    if ($count <= 5)   return self::CONFIDENCE_VERY_LOW;
    if ($count <= 20)  return self::CONFIDENCE_LOW;
    if ($count <= 100) return self::CONFIDENCE_MEDIUM;
    return self::CONFIDENCE_HIGH;
  }
}
