<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors;

/**
 * CountFactor
 *
 * Normalizes a raw count metric (views, orders, reviews, notifications) against
 * catalog-wide distribution statistics using log scaling with winsorization.
 *
 * ── Normalization formula (plan 1.1/1.2) ─────────────────────────────────────
 *
 *   Step 1 — Winsorization (plan 1.2):
 *     winsorized = min(value, p95)
 *     Caps outliers at the 95th percentile so the top 5% of products do not
 *     compress the remaining 95% into a narrow band.
 *
 *   Step 2 — Log scaling (plan 1.1):
 *     normalized = log(1 + winsorized) / log(1 + p95)
 *     Applies a logarithmic transform that gives meaningful differentiation
 *     at low counts (0→5 views matters more than 500→505) while preserving
 *     the ordering of all values.
 *
 * Result is always in [0.0 .. 1.0].
 *
 * ── Comparison with old linear scaling ───────────────────────────────────────
 *
 *   Old:  normalized = value / max
 *     Problem: one product with 10 000 views sets max=10000, so a product
 *     with 500 views scores 0.05 — indistinguishable from 50 views (0.005).
 *
 *   New:  winsorized = min(500, p95=500) = 500
 *         normalized = log(501) / log(501) = 1.0   ← properly rewarded
 *
 * ── Graceful degradation ─────────────────────────────────────────────────────
 *
 *   - If value is null  → status 'missing', factor excluded from scoring.
 *   - If p95 ≤ 0        → falls back to linear max scaling (value / max).
 *   - If max ≤ 0        → status 'missing'.
 *
 * ── Usage ────────────────────────────────────────────────────────────────────
 *
 *   $factors['views'] = new CountFactor(
 *     value: $views,
 *     max:   $catalog->viewsMax,
 *     p95:   $catalog->viewsP95,
 *   );
 *
 *   // Legacy call (no p95) — falls back to linear scaling, backward compat:
 *   $factors['views'] = new CountFactor($views, $catalog->viewsMax);
 */
class CountFactor implements FactorInterface
{
  private string $status;
  private float  $normalizedValue;

  /**
   * @param float|null $value  Raw count for this product (null = missing)
   * @param float      $max    Catalog-wide maximum (used for linear fallback and fallback detection)
   * @param float|null $p95    95th percentile value (null = use linear scaling for backward compat)
   */
  public function __construct(
    private readonly ?float $value,
    private readonly float  $max,
    private readonly ?float $p95 = null,
  ) {
    $this->status          = 'missing';
    $this->normalizedValue = 0.0;

    $this->compute();
  }

  // ── FactorInterface implementation ─────────────────────────────────────────

  public function getType(): string
  {
    return 'count';
  }

  public function getStatus(): string
  {
    return $this->status;
  }

  public function getValue(): ?float
  {
    return $this->value;
  }

  public function normalize(): float
  {
    return $this->normalizedValue;
  }

  // ── Private computation ────────────────────────────────────────────────────

  /**
   * Compute the normalized value and set status.
   *
   * Called once in the constructor — result is cached in $normalizedValue.
   */
  private function compute(): void
  {
    if ($this->value === null) {
      $this->status = 'missing';
      return;
    }

    if ($this->max <= 0.0) {
      $this->status = 'missing';
      return;
    }

    $this->status = 'valid';

    // ── Path A: p95-based log scaling with winsorization ───────────────────
    if ($this->p95 !== null && $this->p95 > 0.0) {
      // Step 1 — winsorization: cap outliers at p95 (plan 1.2)
      $winsorized = min($this->value, $this->p95);

      // Step 2 — log scaling: log(1+x) / log(1+p95) (plan 1.1)
      // log(1+0) = 0  →  score 0 for products with no activity
      // log(1+p95) / log(1+p95) = 1.0  →  full score at p95
      $denominator = log(1.0 + $this->p95);

      if ($denominator <= 0.0) {
        // Degenerate: p95 ≈ 0, fall through to linear
        $this->normalizedValue = min(1.0, $this->value / $this->max);
        return;
      }

      $this->normalizedValue = min(1.0, log(1.0 + $winsorized) / $denominator);
      return;
    }

    // ── Path B: linear max scaling (no p95, backward compat) ──────────────
    $this->normalizedValue = min(1.0, $this->value / $this->max);
  }
}
