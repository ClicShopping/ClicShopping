<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\CatalogNormalization;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\CommercialScoreAxis;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\Context;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\NormalizationValidator;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\ProductScoreAxis;
use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

/**
 * ScoringEngine
 *
 * Deterministic dual-axis scoring engine for CockpitAI.
 *
 * Responsibilities:
 *  - Compute catalog-wide normalization values with full distribution stats
 *  - Validate distribution quality via NormalizationValidator (plan item 3)
 *  - Compute Score_X and Score_Y via the reweighted formula
 *  - Resolve per-product dynamic thresholds when sufficient history exists (plan 6.1/6.2)
 *  - Classify the resulting (Score_X, Score_Y) pair into a strategic quadrant
 *
 * Reweighting formula (Requirements 3.4):
 *   Score = Sum(wi * fi  for valid factors) / Sum(wi for valid factors) * 100
 *
 * Only factors with status === 'valid' participate in the calculation.
 * Factors with status 'missing' or 'not_analyzed' are excluded from both
 * numerator and denominator, ensuring comparable scores despite missing data.
 */
class ScoringEngine
{
  private mixed                $db;
  private NormalizationValidator $validator;
  private bool                 $debug;

  // Tracks the sample size of the last computeCatalogNormalization() call
  // so computeScores() can include it in the result for reporting.
  private int $lastSampleSize = 0;

  public function __construct()
  {
    $this->db        = Registry::get('Db');
    $this->validator = new NormalizationValidator();
    $this->debug     = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
  }

  /**
   * Compute catalog-wide maximum values for count-type factor normalization.
   * Called in pipeline Step 2.
   *
   * Results are cached for 1 hour (TTL 3600s) to avoid repeated expensive SQL queries.
   * Cache key: 'CockpitAI_catalog_norm' in namespace 'CockpitAI'.
   * On cache miss, runs SQL queries and stores the result.
   * Falls back to CatalogNormalization::defaults() if any query fails.
   * Timeout: 3s (enforced by orchestrator)
   *
   * Requirements: 10.2, 7.1, 7.2, 7.3, 7.4, 7.5, 23.3
   *
   * @return CatalogNormalization
   */
  public function computeCatalogNormalization(): CatalogNormalization
  {
    // Cache TTL: 60 minutes (1 hour) — requirement 23.3
    $cacheTtlMinutes = '60';
    $cache = new Cache('CockpitAI_catalog_norm', 'CockpitProductAI');

    if ($cache->exists($cacheTtlMinutes)) {
      $cached = $cache->get();

      if (is_array($cached) && isset($cached['views_p95'])) {
        // New cache format (includes p95 + distribution stats)
        return CatalogNormalization::fromCacheArray($cached);
      }
    }

    $startTime = microtime(true);

    try {
      // ── Query 1: per-product raw values for distribution stats ────────────
      //
      // We fetch the per-product aggregates (max view per product, total orders,
      // review count, notification count) into PHP so we can compute p95, median,
      // mean and std entirely in PHP without relying on MariaDB's non-standard
      // percentile functions (PERCENTILE_CONT is not universally available).
      //
      // views   : MAX(products_viewed) across languages per product
      // orders  : products_ordered from clic_products (cumulative)
      // reviews : COUNT(*) from clic_reviews per product
      // tracking: COUNT(*) from clic_products_notifications per product

      $Qdist = $this->db->prepare('SELECT p.products_id,
                                          p.products_date_added,
                                          DATEDIFF(NOW(), p.products_date_added) AS age_days, -- REQ-SC-01: Calcul du count
                                          COALESCE(pd_max.products_viewed, 0) AS views_val,
                                          COALESCE(p.products_ordered, 0)     AS orders_val,
                                          COALESCE(rv.review_count, 0)        AS reviews_val,
                                          COALESCE(nt.notif_count, 0)         AS tracking_val
                                  FROM :table_products p
                                        LEFT JOIN (
                                          SELECT products_id, MAX(products_viewed) AS products_viewed
                                          FROM :table_products_description
                                          GROUP BY products_id
                                        ) pd_max ON p.products_id = pd_max.products_id
                                        LEFT JOIN (
                                          SELECT products_id, COUNT(*) AS review_count
                                          FROM :table_reviews
                                          GROUP BY products_id
                                        ) rv ON p.products_id = rv.products_id
                                        LEFT JOIN (
                                          SELECT products_id, COUNT(*) AS notif_count
                                          FROM :table_products_notifications
                                          GROUP BY products_id
                                        ) nt ON p.products_id = nt.products_id
                                        WHERE p.products_status = 1
                                      ');

      $Qdist->execute();

      // Check timeout after first query
      if ((microtime(true) - $startTime) > 3.0) {
        throw new \Exception("Catalog normalization query exceeded timeout (3s)");
      }

      $viewsVals    = [];
      $ordersVals   = [];
      $reviewsVals  = [];
      $trackingVals = [];
      $ageVals = []; // Créez ce tableau

      while ($row = $Qdist->fetch()) {
        $ageVals[] = (float) $row['age_days'];
        $viewsVals[]    = (float) $row['views_val'];
        $ordersVals[]   = (float) $row['orders_val'];
        $reviewsVals[]  = (float) $row['reviews_val'];
        $trackingVals[] = (float) $row['tracking_val'];
      }

      if (empty($viewsVals)) {
        return CatalogNormalization::defaults();
      }

      $sampleSize = count($viewsVals);
      $ageMax = !empty($ageVals) ? max(1.0, max($ageVals)) : 365;

      // ── PHP distribution calculation ──────────────────────────────────────
      $normalization = new CatalogNormalization(
        viewsMax:    max(1.0, max($viewsVals)),
        viewsP95:    max(1.0, $this->percentile($viewsVals, 95)),
        viewsMedian: max(0.0, $this->percentile($viewsVals, 50)),
        viewsMean:   max(0.0, $this->mean($viewsVals)),
        viewsStd:    max(0.0, $this->std($viewsVals)),

        ordersMax:    max(1.0, max($ordersVals)),
        ordersP95:    max(1.0, $this->percentile($ordersVals, 95)),
        ordersMedian: max(0.0, $this->percentile($ordersVals, 50)),
        ordersMean:   max(0.0, $this->mean($ordersVals)),
        ordersStd:    max(0.0, $this->std($ordersVals)),

        reviewsMax:    max(1.0, max($reviewsVals)),
        reviewsP95:    max(1.0, $this->percentile($reviewsVals, 95)),
        reviewsMedian: max(0.0, $this->percentile($reviewsVals, 50)),
        reviewsMean:   max(0.0, $this->mean($reviewsVals)),
        reviewsStd:    max(0.0, $this->std($reviewsVals)),

        trackingMax:    max(1.0, max($trackingVals)),
        trackingP95:    max(1.0, $this->percentile($trackingVals, 95)),
        trackingMedian: max(0.0, $this->percentile($trackingVals, 50)),
        trackingMean:   max(0.0, $this->mean($trackingVals)),
        trackingStd:    max(0.0, $this->std($trackingVals)),
        ageMax:         $ageMax
    );

      // ── NormalizationValidator (plan item 3) ─────────────────────────────
      // Validate statistical quality before accepting the computed distribution.
      // On invalid result, fall back to defaults and log details.
      // On degraded result, proceed but log warnings.
      $validation = $this->validator->validateDistribution($normalization, $sampleSize);

      if ($this->debug || !$validation->isConfident()) {
        error_log('[CockpitAI] ' . $validation->summary());
        foreach ($validation->warnings as $warning) {
          error_log('[CockpitAI] NormalizationWarning: ' . $warning);
        }
      }

      if (!$validation->isValid) {
        // Distribution is unusable — fall back to safe defaults
        return CatalogNormalization::defaults();
      }

      // Store sample size for propagation to computeScores()
      $this->lastSampleSize = $sampleSize;

      // Store in cache (new flat format via toCacheArray)
      $cache->save($normalization->toCacheArray());

      return $normalization;

    } catch (\Throwable $e) {
      return CatalogNormalization::defaults();
    }
  }

  /**
   * Compute the p-th percentile of a numeric array using linear interpolation.
   *
   * Equivalent to numpy.percentile(data, p, interpolation='linear').
   *
   * @param float[] $values Non-empty array of numeric values
   * @param int     $p      Percentile in [0..100]
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

    // Position in [0 .. n-1]
    $pos = ($p / 100.0) * ($n - 1);
    $low = (int) floor($pos);
    $high = (int) ceil($pos);
    $frac = $pos - $low;

    if ($low === $high) {
      return $values[$low];
    }

    return $values[$low] * (1.0 - $frac) + $values[$high] * $frac;
  }

  /**
   * Compute arithmetic mean of a numeric array.
   *
   * @param float[] $values
   * @return float
   */
  private function mean(array $values): float
  {
    if (empty($values)) {
      return 0.0;
    }

    return array_sum($values) / count($values);
  }

  /**
   * Compute population standard deviation of a numeric array.
   *
   * @param float[] $values
   * @return float
   */
  private function std(array $values): float
  {
    $n = count($values);

    if ($n < 2) {
      return 0.0;
    }

    $mean = $this->mean($values);
    $sumSq = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values));

    return sqrt($sumSq / $n);
  }

  /**
   * Compute both Score_X and Score_Y for the given product and context.
   * Called in pipeline Step 3.
   *
   * Dynamic thresholds (plan 6.1/6.2):
   *   If the product has ≥ CLICSHOPPING_APP_ECOMMERCE_CAI_DYNAMIC_THRESHOLD_MIN
   *   historical analyses in products_cockpit_ai_embedding , T_high and T_low are derived
   *   from P75/P25 of that product's own score_y history instead of the fixed values.
   *
   * NormalizationValidator (plan 3):
   *   The 'normalization_sample_size' key in the result carries the number of products
   *   used in the distribution calculation for downstream reporting.
   *
   * @param array   $product Raw product data array (from DataCollector)
   * @param Context $context Pipeline context (SEO status, catalog norms, thresholds)
   * @return array{
   *   score_x: float,
   *   score_y: float,
   *   quadrant: string,
   *   factors_x: array,
   *   factors_y: array,
   *   normalization_fallback_used: bool,
   *   normalization_sample_size: int,
   *   thresholds_dynamic: bool,
   *   thresholds_analysis_count: int,
   *   T_high: float,
   *   T_low: float
   * }
   */
  public function computeScores(array $product, Context $context): array
  {
    $axisX = new ProductScoreAxis();
    $axisY = new CommercialScoreAxis();

    $factorsX = $axisX->getFactors($product, $context);
    $factorsY = $axisY->getFactors($product, $context);

    $scoreX = $axisX->computeScore($factorsX);
    $scoreY = $axisY->computeScore($factorsY);

    // Clamp to [0..100] for safety
    $scoreX = max(0.0, min(100.0, $scoreX));
    $scoreY = max(0.0, min(100.0, $scoreY));

    // ── Dynamic thresholds per product (plan 6.1/6.2) ─────────────────────
    // NormalizationValidator queries products_cockpit_ai_embedding  for this product's
    // analysis history. If enough analyses exist, T_high/T_low are replaced
    // by P75/P25 of the product's own score_y history.
    $productId  = (int) ($product['product_id'] ?? $product['products_id'] ?? 0);
    $languageId = (int) ($product['language_id'] ?? 1);

    $resolvedThresholds = $this->validator->resolveThresholds(
      $productId,
      $languageId,
      $context->thresholds
    );

    if ($this->debug && $resolvedThresholds['dynamic']) {
      error_log(sprintf(
        '[CockpitAI] Dynamic thresholds for product %d: T_high=%.1f, T_low=%.1f (from %d analyses)',
        $productId,
        $resolvedThresholds['T_high'],
        $resolvedThresholds['T_low'],
        $resolvedThresholds['analysis_count']
      ));
    }

    $quadrant = $this->classifyQuadrant($scoreX, $scoreY, $resolvedThresholds);

    // Detect whether catalog normalization used hardcoded defaults.
    // Delegates to CatalogNormalization::isDefault() which checks viewsMax + ordersMax.
    $normalizationFallbackUsed = $context->catalog->isDefault();

    return [
      'score_x'                     => $scoreX,
      'score_y'                     => $scoreY,
      'quadrant'                    => $quadrant,
      'factors_x'                   => $this->serializeFactors($factorsX),
      'factors_y'                   => $this->serializeFactors($factorsY),
      // Normalization confidence (plan 3 + 4.1)
      'normalization_fallback_used' => $normalizationFallbackUsed,
      'normalization_sample_size'   => $this->lastSampleSize,
      // Threshold provenance (plan 6.1/6.2)
      'T_high'                      => $resolvedThresholds['T_high'],
      'T_low'                       => $resolvedThresholds['T_low'],
      'thresholds_dynamic'          => $resolvedThresholds['dynamic'],
      'thresholds_analysis_count'   => $resolvedThresholds['analysis_count'],
    ];
  }

  /**
   * Classify the (Score_X, Score_Y) pair into a strategic quadrant.
   * Guarantees complete partition: every possible combination maps to exactly one quadrant.
   *
   * Q1 (Scaling)        : X >= T_high AND Y >= T_high
   * Q2 (Acquisition)    : X >= T_high AND Y < T_low
   * Q3 (Rework/Kill)    : X < T_low  AND Y < T_low
   * Q4 (Optimization)   : X < T_low  AND Y >= T_high
   * Q_intermediate      : all remaining cases (transition zone)
   *
   * @param float $scoreX     Score_X value [0..100]
   * @param float $scoreY     Score_Y value [0..100]
   * @param array $thresholds ['T_high' => float, 'T_low' => float]
   * @return string  Quadrant code: 'Q1'|'Q2'|'Q3'|'Q4'|'Q_intermediate'
   */
  public function classifyQuadrant(float $scoreX, float $scoreY, array $thresholds = []): string
  {
    $tHigh = (float) ($thresholds['T_high'] ?? 70.0);
    $tLow  = (float) ($thresholds['T_low']  ?? 30.0);

    if ($scoreX >= $tHigh && $scoreY >= $tHigh) {
      return 'Q1';
    }

    if ($scoreX >= $tHigh && $scoreY < $tLow) {
      return 'Q2';
    }

    if ($scoreX < $tLow && $scoreY < $tLow) {
      return 'Q3';
    }

    if ($scoreX < $tLow && $scoreY >= $tHigh) {
      return 'Q4';
    }

    return 'Q_intermediate';
  }

  /**
   * Serialize a factors array to a simple array for JSON report inclusion.
   * Each entry: ['code' => …, 'type' => …, 'status' => …, 'value' => …, 'normalized' => …]
   *
   * @param array<string, \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\FactorInterface> $factors
   * @return array
   */
  private function serializeFactors(array $factors): array
  {
    $result = [];
    foreach ($factors as $code => $factor) {
      $result[$code] = [
        'code'       => $code,
        'type'       => $factor->getType(),
        'status'     => $factor->getStatus(),
        'value'      => $factor->getValue(),
        'normalized' => $factor->getStatus() === 'valid' ? $factor->normalize() : null,
      ];
    }
    return $result;
  }
}
