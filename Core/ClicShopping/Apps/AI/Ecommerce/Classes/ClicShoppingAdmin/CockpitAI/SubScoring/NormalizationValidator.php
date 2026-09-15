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
use ClicShopping\Apps\AI\Ecommerce\Config\EcommerceDefaults;

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

  // ── Dynamic thresholds constants ──────────────────────────────────────────

  /** Percentile used for T_high when dynamic thresholds are active */
  private const DYNAMIC_T_HIGH_PERCENTILE = 75;

  /** Percentile used for T_low when dynamic thresholds are active */
  private const DYNAMIC_T_LOW_PERCENTILE  = 25;

  /** Minimum gap between T_high and T_low (safety guard) */

  /** Minutes the catalogue median is cached: it only moves when the catalogue is re-analysed. */

  /** Cache TTL in minutes for per-product threshold data */

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
    if ($sampleSize < EcommerceDefaults::int('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MIN_SAMPLE_SIZE')) {
      $warnings[]  = "Sample too small ({$sampleSize} products < " . EcommerceDefaults::int('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MIN_SAMPLE_SIZE') . " minimum). Distribution unreliable.";
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
    $isValid    = $confidence >= 0.5 && $sampleSize >= EcommerceDefaults::int('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MIN_SAMPLE_SIZE');

    return new ValidationResult($confidence, $warnings, $isValid, $sampleSize);
  }

  /**
   * Resolve thresholds for a specific product + language.
   *
   * If the product has enough historical analyses in products_cockpit_ai_embedding
   * (≥ $minAnalyses), computes dynamic thresholds from P75/P25 of that product's own
   * history — ONE PAIR PER AXIS. A quality scale and a performance scale are different
   * distributions: a threshold read off one says nothing about the other.
   *
   * Otherwise, returns the static thresholds from $context unchanged, on both axes.
   *
   * @param int   $productId      Product to resolve thresholds for
   * @param int   $languageId     Language filter for embedding history
   * @param array $staticThresholds Current ['T_high' => float, 'T_low' => float]
   * @param int|null $minAnalyses Minimum analyses required (null = use constant/default)
   * @return array  ['x' => ['T_high','T_low'], 'y' => ['T_high','T_low'],
   *                 'T_high','T_low' (the y axis, for readers that know a single pair),
   *                 'dynamic' => bool, 'analysis_count' => int]
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

    $history = $this->fetchProductScoreHistory($productId, $languageId);
    $count   = min(count($history['x']), count($history['y']));

    $staticPair = [
      'T_high' => (float) ($staticThresholds['T_high'] ?? 70.0),
      'T_low'  => (float) ($staticThresholds['T_low']  ?? 30.0),
    ];

    if ($count < $minAnalyses) {
      // Not enough data — the same static pair on both axes
      $result = $staticPair + [
        'x'              => $staticPair,
        'y'              => $staticPair,
        'dynamic'        => false,
        'analysis_count' => $count,
        'min_required'   => $minAnalyses,
      ];
      // Cache briefly (10 min) to avoid repeated DB hits for new products
      $this->writeThresholdCache($cacheKey, $result, '10');
      return $result;
    }

    $xPair = $this->percentilePair($history['x']);
    $yPair = $this->percentilePair($history['y']);

    // T_high/T_low at the root stay the y axis: commercial performance is what the
    // quadrant vocabulary (Stars / Issues) has always been named after.
    $result = $yPair + [
      'x'              => $xPair,
      'y'              => $yPair,
      'dynamic'        => true,
      'analysis_count' => $count,
      'min_required'   => $minAnalyses,
    ];

    $this->writeThresholdCache($cacheKey, $result, EcommerceDefaults::get('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_THRESHOLD_CACHE_TTL'));

    return $result;
  }

  /**
   * P75/P25 of one axis, with the minimum-gap guard.
   *
   * @param float[] $values
   * @return array{T_high: float, T_low: float}
   */
  private function percentilePair(array $values): array
  {
    $tHigh = $this->percentile($values, self::DYNAMIC_T_HIGH_PERCENTILE);
    $tLow  = $this->percentile($values, self::DYNAMIC_T_LOW_PERCENTILE);

    if (($tHigh - $tLow) < EcommerceDefaults::float('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MIN_THRESHOLD_GAP')) {
      // Expand symmetrically from the midpoint
      $mid   = ($tHigh + $tLow) / 2.0;
      $tHigh = min(95.0, $mid + EcommerceDefaults::float('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MIN_THRESHOLD_GAP') / 2.0);
      $tLow  = max(5.0,  $mid - EcommerceDefaults::float('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MIN_THRESHOLD_GAP') / 2.0);
    }

    return ['T_high' => round($tHigh, 1), 'T_low' => round($tLow, 1)];
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
      if ($cache->exists(EcommerceDefaults::get('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_THRESHOLD_CACHE_TTL'))) {
        $data = $cache->get();
        // Keyed on the per-axis shape: an entry written before it must be recomputed.
        if (is_array($data) && isset($data['x']['T_high'], $data['y']['T_high'])) {
          return $data;
        }
      }
    } catch (\Throwable) {
    }
    return null;
  }

  /**
   * Median of the CURRENT score_y across the catalogue, for one language.
   *
   * The performance axis is judged against the shop, never against an absolute bar: score_y is
   * already a third catalogue-relative by construction, and three of its thirteen factors are
   * constants that keep it inside a narrow band (see BACKLOG_ARCHIVE, CAI-QUAD2).
   *
   * @return float|null null when no analysis exists yet — a product cannot be positioned
   *                    against a catalogue that has not been measured.
   */
  public function catalogScoreMedian(int $languageId): ?float
  {
    $cacheKey = "catalog_score_y_median_{$languageId}";

    try {
      $cache = new Cache($cacheKey, 'CockpitAI');
      if ($cache->exists(EcommerceDefaults::get('CLICSHOPPING_APP_ECOMMERCE_EC_CAI_CATALOG_MEDIAN_TTL'))) {
        $cached = $cache->get();
        if (is_array($cached) && array_key_exists('median', $cached)) {
          return $cached['median'] === null ? null : (float)$cached['median'];
        }
      }
    } catch (\Throwable) {
    }

    $median = null;

    try {
      // Latest analysis per product only: the store keeps every generation.
      $Q = $this->db->prepare('
        SELECT JSON_EXTRACT(e.metadata, \'$.scores.score_y\') AS score_y
        FROM :table_products_cockpit_ai_embedding e
        INNER JOIN (
          SELECT entity_id, MAX(id) AS last_id
          FROM :table_products_cockpit_ai_embedding
          WHERE language_id = :language_id
          GROUP BY entity_id
        ) l ON l.last_id = e.id
        WHERE JSON_EXTRACT(e.metadata, \'$.scores.score_y\') IS NOT NULL
      ');
      $Q->bindInt(':language_id', $languageId);
      $Q->execute();

      $scores = [];
      while ($row = $Q->fetch()) {
        if (is_numeric($row['score_y'])) {
          $scores[] = (float) $row['score_y'];
        }
      }

      if ($scores !== []) {
        sort($scores);
        $n = count($scores);
        $median = $n % 2 === 1
          ? $scores[intdiv($n, 2)]
          : ($scores[$n / 2 - 1] + $scores[$n / 2]) / 2.0;
        $median = round($median, 2);
      }
    } catch (\Throwable) {
      return null;
    }

    try {
      (new Cache($cacheKey, 'CockpitAI'))->save(['median' => $median]);
    } catch (\Throwable) {
    }

    return $median;
  }

  /**
   * Fetch the historical score_x and score_y of a product, one pass.
   *
   * @return array{x: float[], y: float[]}
   */
  private function fetchProductScoreHistory(int $productId, int $languageId): array
  {
    $scores = ['x' => [], 'y' => []];

    try {
      $Qhistory = $this->db->prepare('
        SELECT JSON_EXTRACT(metadata, \'$.scores.score_x\') AS score_x,
               JSON_EXTRACT(metadata, \'$.scores.score_y\') AS score_y
        FROM :table_products_cockpit_ai_embedding
        WHERE JSON_EXTRACT(metadata, \'$.entity_id\') = :entity_id
          AND language_id = :language_id
          AND JSON_EXTRACT(metadata, \'$.scores.score_x\') IS NOT NULL
          AND JSON_EXTRACT(metadata, \'$.scores.score_y\') IS NOT NULL
        ORDER BY date_modified DESC
      ');

      $Qhistory->bindInt(':entity_id', $productId);
      $Qhistory->bindInt(':language_id', $languageId);
      $Qhistory->execute();

      while ($row = $Qhistory->fetch()) {
        if (is_numeric($row['score_x']) && is_numeric($row['score_y'])) {
          $scores['x'][] = (float) $row['score_x'];
          $scores['y'][] = (float) $row['score_y'];
        }
      }
    } catch (\Throwable) {
    }

    return $scores;
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
