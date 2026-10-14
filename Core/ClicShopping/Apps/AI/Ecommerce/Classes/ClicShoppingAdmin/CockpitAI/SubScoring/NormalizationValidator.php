<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

/**
 * NormalizationValidator
 *
 * Two responsibilities, cleanly separated:
 *
 * ── 1. Distribution validation (plan item 3) ─────────────────────────────────
 *
 * Called after ScoringEngine::computeCatalogNormalization() to verify that the
 * computed distribution statistics are statistically sound before they are used
 * for scoring. If the distribution is degenerate, a ValidationResult is returned
 * with a confidence score < 1.0 and a list of warnings — the caller can then
 * decide to fall back to defaults or proceed with reduced confidence.
 *
 * Checks performed:
 *   - Outlier dominance : p95 / median > OUTLIER_RATIO_MAX  (outliers compress the rest)
 *   - Zero variance     : std === 0.0                        (all products identical)
 *   - Degenerate p95    : p95 <= 0.0                         (calculation failed)
 *   - Tiny sample       : sample_size < MIN_SAMPLE_SIZE      (not enough products)
 *
 * ── 2. Dynamic thresholds per product (plan items 6.1/6.2) ───────────────────
 *
 * Called during scoring to decide whether a product has enough historical analyses
 * in products_cockpit_ai_embedding  to replace the global fixed thresholds (T_high, T_low)
 * with thresholds derived from that product's own score history.
 *
 * Activation condition:
 *   COUNT(*) in products_cockpit_ai_embedding  WHERE entity_id = $productId >= $minAnalyses
 *
 * $minAnalyses defaults to constant CLICSHOPPING_APP_ECOMMERCE_CAI_DYNAMIC_THRESHOLD_MIN
 * (fallback: 100).
 *
 * When activated, T_high = P75 of historical score_y, T_low = P25 of historical score_y.
 * P75/P25 are computed from the product's own embedding history — each product's
 * thresholds reflect its own commercial trajectory, not the global catalog average.
 *
 * Why P75/P25 on score_y only:
 *   - Quadrant classification is primarily driven by score_y (commercial performance)
 *   - score_x (quality) is slower to change and less sensitive to threshold shifts
 *   - P75/P25 produces a natural distribution split: top 25% = Stars, bottom 25% = Issues
 *
 * ── Evolutionary design ────────────────────────────────────────────────────────
 *
 * This class is designed for extension without modification:
 *   - Add new validation checks: implement a private check*() method + add to CHECKS array
 *   - Change threshold formula: override computeDynamicThresholds() in a subclass
 *   - Add score_x dynamic thresholds: extend ThresholdResult to carry both axes
 *   - Add catalog-level thresholds: add a computeCatalogThresholds() method
 *
 * ── Usage in ScoringEngine ────────────────────────────────────────────────────
 *
 *   $validator  = new NormalizationValidator();
 *
 *   // After computeCatalogNormalization():
 *   $validation = $validator->validateDistribution($normalization, sampleSize: $n);
 *   if (!$validation->isConfident()) {
 *     // log warning, use defaults or proceed with reduced confidence
 *   }
 *
 *   // During computeScores(), before classifyQuadrant():
 *   $thresholds = $validator->resolveThresholds($productId, $languageId, $context->thresholds);
 *   $quadrant   = $this->classifyQuadrant($scoreX, $scoreY, $thresholds);
 */
class NormalizationValidator
{
  // ── Distribution validation constants ─────────────────────────────────────

  /** p95 / median ratio above which the distribution is considered outlier-dominated */
  private const OUTLIER_RATIO_MAX = 50.0;

  /** Minimum sample size for reliable distribution statistics */
  private const MIN_SAMPLE_SIZE = 10;

  // ── Dynamic thresholds constants ──────────────────────────────────────────

  /** Percentile used for T_high when dynamic thresholds are active */
  private const DYNAMIC_T_HIGH_PERCENTILE = 75;

  /** Percentile used for T_low when dynamic thresholds are active */
  private const DYNAMIC_T_LOW_PERCENTILE  = 25;

  /** Minimum gap between T_high and T_low (safety guard) */
  private const MIN_THRESHOLD_GAP = 10.0;

  /** Cache TTL in minutes for per-product threshold data */
  private const THRESHOLD_CACHE_TTL = '60';

  private mixed $db;

  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  // ── Public API ─────────────────────────────────────────────────────────────

  /**
   * Validate the statistical quality of a computed CatalogNormalization.
   *
   * Returns a ValidationResult carrying:
   *   - confidence [0.0 .. 1.0]  — 1.0 = fully reliable, < 1.0 = degraded
   *   - warnings[]               — human-readable descriptions of detected issues
   *   - isValid bool             — false means caller SHOULD fall back to defaults
   *
   * @param CatalogNormalization $norm       The normalization to validate
   * @param int                  $sampleSize Number of products used in the calculation
   * @return ValidationResult
   */
  public function validateDistribution(CatalogNormalization $norm, int $sampleSize): ValidationResult
  {
    $warnings   = [];
    $confidence = 1.0;

    // ── Check 1: sample size ──────────────────────────────────────────────
    if ($sampleSize < self::MIN_SAMPLE_SIZE) {
      $warnings[]  = "Sample too small ({$sampleSize} products < " . self::MIN_SAMPLE_SIZE . " minimum). Distribution unreliable.";
      $confidence *= 0.5;
    }

    // ── Check 2: degenerate p95 ───────────────────────────────────────────
    foreach (['views', 'orders', 'reviews', 'tracking'] as $dim) {
      $p95Property = $dim . 'P95';
      $p95 = $norm->$p95Property;

      if ($p95 <= 0.0) {
        $warnings[]  = "Degenerate p95 for '{$dim}' (value={$p95}). Log scaling will fall back to linear.";
        $confidence *= 0.8;
      }
    }

    // ── Check 3: zero variance ────────────────────────────────────────────
    foreach (['views', 'orders', 'reviews', 'tracking'] as $dim) {
      $stdProperty = $dim . 'Std';
      if ($norm->$stdProperty === 0.0) {
        $warnings[] = "Zero standard deviation for '{$dim}'. All products are identical on this dimension.";
        $confidence *= 0.9;
      }
    }

    // ── Check 4: outlier dominance ────────────────────────────────────────
    foreach (['views', 'orders', 'reviews', 'tracking'] as $dim) {
      $p95Property    = $dim . 'P95';
      $medianProperty = $dim . 'Median';
      $p95    = $norm->$p95Property;
      $median = $norm->$medianProperty;

      if ($median > 0.0) {
        $ratio = $p95 / $median;
        if ($ratio > self::OUTLIER_RATIO_MAX) {
          $warnings[] = "Outlier dominance on '{$dim}': p95/median ratio = " . round($ratio, 1)
            . " (max=" . self::OUTLIER_RATIO_MAX . "). Winsorization will mitigate this.";
          $confidence *= 0.85;
        }
      }
    }

    $confidence = max(0.0, min(1.0, $confidence));
    $isValid    = $confidence >= 0.5 && $sampleSize >= self::MIN_SAMPLE_SIZE;

    return new ValidationResult($confidence, $warnings, $isValid, $sampleSize);
  }

  /**
   * Resolve thresholds for a specific product + language.
   *
   * If the product has enough historical analyses in products_cockpit_ai_embedding 
   * (≥ $minAnalyses), computes dynamic T_high/T_low from P75/P25 of that
   * product's own score_y history.
   *
   * Otherwise, returns the static thresholds from $context unchanged.
   *
   * @param int   $productId      Product to resolve thresholds for
   * @param int   $languageId     Language filter for embedding history
   * @param array $staticThresholds Current ['T_high' => float, 'T_low' => float]
   * @param int|null $minAnalyses Minimum analyses required (null = use constant/default)
   * @return array  Resolved ['T_high' => float, 'T_low' => float, 'dynamic' => bool, 'analysis_count' => int]
   */
  public function resolveThresholds(
    int   $productId,
    int   $languageId,
    array $staticThresholds,
    ?int  $minAnalyses = null
  ): array {
    $minAnalyses ??= $this->resolveMinAnalyses();

    // Try cache first
    $cacheKey = "thresholds_{$productId}_{$languageId}";
    $cached   = $this->readThresholdCache($cacheKey);
    if ($cached !== null) {
      return $cached;
    }

    // Fetch product's score_y history from products_cockpit_ai_embedding 
    $history = $this->fetchProductScoreHistory($productId, $languageId);
    $count   = count($history);

    if ($count < $minAnalyses) {
      // Not enough data — use static thresholds
      $result = [
        'T_high'         => (float) ($staticThresholds['T_high'] ?? 70.0),
        'T_low'          => (float) ($staticThresholds['T_low']  ?? 30.0),
        'dynamic'        => false,
        'analysis_count' => $count,
        'min_required'   => $minAnalyses,
      ];
      // Cache briefly (10 min) to avoid repeated DB hits for new products
      $this->writeThresholdCache($cacheKey, $result, '10');
      return $result;
    }

    // Compute dynamic thresholds from P75/P25 of historical score_y values
    $tHigh = $this->percentile($history, self::DYNAMIC_T_HIGH_PERCENTILE);
    $tLow  = $this->percentile($history, self::DYNAMIC_T_LOW_PERCENTILE);

    // Safety guard: ensure minimum gap between thresholds
    if (($tHigh - $tLow) < self::MIN_THRESHOLD_GAP) {
      // Expand symmetrically from the midpoint
      $mid   = ($tHigh + $tLow) / 2.0;
      $tHigh = min(95.0, $mid + self::MIN_THRESHOLD_GAP / 2.0);
      $tLow  = max(5.0,  $mid - self::MIN_THRESHOLD_GAP / 2.0);
    }

    $result = [
      'T_high'         => round($tHigh, 1),
      'T_low'          => round($tLow,  1),
      'dynamic'        => true,
      'analysis_count' => $count,
      'min_required'   => $minAnalyses,
    ];

    $this->writeThresholdCache($cacheKey, $result, self::THRESHOLD_CACHE_TTL);

    return $result;
  }

  /**
   * Read the minimum analyses threshold from the module constant or use default.
   */
  private function resolveMinAnalyses(): int
  {
    return \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DYNAMIC_THRESHOLD_MIN') ? max(10, (int)CLICSHOPPING_APP_ECOMMERCE_CAI_DYNAMIC_THRESHOLD_MIN) : 100;
  }

  /**
   * Read a cached threshold result.
   *
   * @return array|null
   */
  private function readThresholdCache(string $key): ?array
  {
    try {
      $cache = new Cache($key, 'CockpitAI');
      if ($cache->exists(self::THRESHOLD_CACHE_TTL)) {
        $data = $cache->get();
        if (is_array($data) && isset($data['T_high'])) {
          return $data;
        }
      }
    } catch (\Throwable) {
    }
    return null;
  }

  /**
   * Fetch all historical score_y values for a product from products_cockpit_ai_embedding .
   *
   * Reads JSON_EXTRACT(metadata, '$.scores.score_y') ordered by date_modified DESC.
   * Returns a flat array of floats for percentile computation.
   *
   * @return float[]
   */
  private function fetchProductScoreHistory(int $productId, int $languageId): array
  {
    try {
      $Qhistory = $this->db->prepare('
        SELECT JSON_EXTRACT(metadata, \'$.scores.score_y\') AS score_y
        FROM :table_products_cockpit_ai_embedding 
        WHERE JSON_EXTRACT(metadata, \'$.entity_id\') = :entity_id
          AND language_id = :language_id
          AND JSON_EXTRACT(metadata, \'$.scores.score_y\') IS NOT NULL
        ORDER BY date_modified DESC
      ');

      $Qhistory->bindInt(':entity_id', $productId);
      $Qhistory->bindInt(':language_id', $languageId);
      $Qhistory->execute();

      $scores = [];
      while ($row = $Qhistory->fetch()) {
        $val = $row['score_y'];
        if ($val !== null && is_numeric($val)) {
          $scores[] = (float) $val;
        }
      }

      return $scores;

    } catch (\Throwable) {
      return [];
    }
  }

  /**
   * Write a threshold result to cache.
   *
   * @param string $key
   * @param array  $data
   * @param string $ttlMinutes
   */
  private function writeThresholdCache(string $key, array $data, string $ttlMinutes): void
  {
    try {
      $cache = new \ClicShopping\OM\Cache($key, 'CockpitAI');
      $cache->save($data);
    } catch (\Throwable) {
    }
  }

  /**
   * Compute the p-th percentile of a numeric array (linear interpolation).
   *
   * @param float[] $values
   * @param int     $p  Percentile [0..100]
   * @return float
   */
  private function percentile(array $values, int $p): float
  {
    if (empty($values)) {
      return 0.0;
    }

    sort($values);
    $n = count($values);

    if ($n === 1) {
      return $values[0];
    }

    $pos  = ($p / 100.0) * ($n - 1);
    $low  = (int) floor($pos);
    $high = (int) ceil($pos);
    $frac = $pos - $low;

    if ($low === $high) {
      return $values[$low];
    }

    return $values[$low] * (1.0 - $frac) + $values[$high] * $frac;
  }
}
