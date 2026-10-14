<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring;

/**
 * CatalogNormalization
 *
 * Carries catalog-wide distribution statistics used for count-type factor normalization.
 * Computed once per analysis pipeline run (Step 2) and shared across both scoring axes.
 *
 * Properties per dimension
 *
 * Each catalog dimension (views, orders, reviews, tracking) exposes:
 *
 *   {dim}Max     — MAX value (kept for backward compat + fallback detection)
 *   {dim}P95     — 95th percentile (primary normalization anchor, plan 1.1/1.2)
 *   {dim}Median  — Median (P50) value
 *   {dim}Mean    — Arithmetic mean
 *   {dim}Std     — Population standard deviation
 *
 * CountFactor now applies winsorization then log scaling:
 *
 *   winsorized = min(x, p95)
 *   normalized = log(1 + winsorized) / log(1 + p95)
 *
 * Replaces linear max-scaling (x / max) which compressed 95% of products
 * into a narrow band dominated by outliers. With p95 anchoring, every product
 * in the mainstream distribution gets a fair [0..1] score; the top 5% are
 * capped at 1.0 via winsorization.
 *
 * ── Backward compatibility ────────────────────────────────────────────────────
 *
 * All {dim}Max properties are preserved. ScoringEngine's isDefault() check
 * (normalization_fallback_used flag) continues to work via viewsMax === 1000.0.
 *
 * ── Default values ────────────────────────────────────────────────────────────
 *
 * Conservative estimates calibrated for a small catalog (~100 active products).
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring;

/**
 * CatalogNormalization
 *
 * Carries catalog-wide distribution statistics used for count-type factor normalization.
 */
readonly class CatalogNormalization
{
  public function __construct(
    // ── Views (products_description.products_viewed) ──────────────────────
    public float $viewsMax    = 1000.0,
    public float $viewsP95    = 500.0,
    public float $viewsMedian = 50.0,
    public float $viewsMean   = 120.0,
    public float $viewsStd    = 180.0,

    // ── Orders (products.products_ordered) ────────────────────────────────
    public float $ordersMax    = 100.0,
    public float $ordersP95    = 50.0,
    public float $ordersMedian = 5.0,
    public float $ordersMean   = 12.0,
    public float $ordersStd    = 18.0,

    // ── Reviews (COUNT(*) from :table_reviews per product) ────────────────
    public float $reviewsMax    = 50.0,
    public float $reviewsP95    = 25.0,
    public float $reviewsMedian = 2.0,
    public float $reviewsMean   = 5.0,
    public float $reviewsStd    = 7.0,

    // ── Tracking (COUNT(*) from :table_products_notifications per product) ─
    public float $trackingMax    = 200.0,
    public float $trackingP95    = 100.0,
    public float $trackingMedian = 8.0,
    public float $trackingMean   = 22.0,
    public float $trackingStd    = 35.0,
    public float $ageMax         = 365.0
  ) {
  }

  /**
   * Return a CatalogNormalization with conservative default fallback values.
   *
   * Used when Step 2 SQL query fails or times out.
   * Detected by isDefault() to set the normalization_fallback_used flag.
   */
  public static function defaults(): self
  {
    return new self();
  }


  /**
   * Restore from a cached associative array (produced by toCacheArray()).
   *
   * @param array $data
   * @return self
   */
  public static function fromCacheArray(array $data): self
  {
    return new self(
      viewsMax:       (float) ($data['views_max']       ?? 1000.0),
      viewsP95:       (float) ($data['views_p95']       ?? 500.0),
      viewsMedian:    (float) ($data['views_median']    ?? 50.0),
      viewsMean:      (float) ($data['views_mean']      ?? 120.0),
      viewsStd:       (float) ($data['views_std']       ?? 180.0),

      ordersMax:      (float) ($data['orders_max']      ?? 100.0),
      ordersP95:      (float) ($data['orders_p95']      ?? 50.0),
      ordersMedian:   (float) ($data['orders_median']   ?? 5.0),
      ordersMean:     (float) ($data['orders_mean']     ?? 12.0),
      ordersStd:      (float) ($data['orders_std']      ?? 18.0),

      reviewsMax:     (float) ($data['reviews_max']     ?? 50.0),
      reviewsP95:     (float) ($data['reviews_p95']     ?? 25.0),
      reviewsMedian:  (float) ($data['reviews_median']  ?? 2.0),
      reviewsMean:    (float) ($data['reviews_mean']    ?? 5.0),
      reviewsStd:     (float) ($data['reviews_std']     ?? 7.0),

      trackingMax:    (float) ($data['tracking_max']    ?? 200.0),
      trackingP95:    (float) ($data['tracking_p95']    ?? 100.0),
      trackingMedian: (float) ($data['tracking_median'] ?? 8.0),
      trackingMean:   (float) ($data['tracking_mean']   ?? 22.0),
      trackingStd:    (float) ($data['tracking_std']    ?? 35.0),

      ageMax:         (float) ($data['age_max']         ?? 365.0)
    );
  }

  /**
   * Serialize to a flat array suitable for cache storage.
   *
   * @return array<string, float>
   */
  public function toCacheArray(): array
  {
    return [
      'views_max'       => $this->viewsMax,
      'views_p95'       => $this->viewsP95,
      'views_median'    => $this->viewsMedian,
      'views_mean'      => $this->viewsMean,
      'views_std'       => $this->viewsStd,

      'orders_max'      => $this->ordersMax,
      'orders_p95'      => $this->ordersP95,
      'orders_median'   => $this->ordersMedian,
      'orders_mean'     => $this->ordersMean,
      'orders_std'      => $this->ordersStd,

      'reviews_max'     => $this->reviewsMax,
      'reviews_p95'     => $this->reviewsP95,
      'reviews_median'  => $this->reviewsMedian,
      'reviews_mean'    => $this->reviewsMean,
      'reviews_std'     => $this->reviewsStd,

      'tracking_max'    => $this->trackingMax,
      'tracking_p95'    => $this->trackingP95,
      'tracking_median' => $this->trackingMedian,
      'tracking_mean'   => $this->trackingMean,
      'tracking_std'    => $this->trackingStd,
      'age_max'         => $this->ageMax
    ];
  }

  /**
   * Whether this instance holds default fallback values.
   *
   * Used by ScoringEngine::computeScores() to set normalization_fallback_used.
   *
   * @return bool
   */
  public function isDefault(): bool
  {
    return $this->viewsMax === 1000.0 && $this->ordersMax === 100.0;
  }
}
