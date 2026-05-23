<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors;

/**
 * RatioFactor
 *
 * Normalizes a ratio metric that is already in [0..1] (conversion rate, return rate,
 * stockout probability, velocity ratio, etc.).
 *
 * ── Non-linearity transform (plan 5.1) ────────────────────────────────────────
 *
 * By default, RatioFactor passes the value through as-is (linear, identity transform).
 * This is correct for most ratios — but conversion rate and return rate benefit from
 * a sqrt transform: a jump from 0% to 1% conversion is far more meaningful than a jump
 * from 9% to 10%, yet linear scoring treats them identically.
 *
 * Available transforms:
 *
 *   'linear'  — f(x) = x              (default, backward compatible)
 *   'sqrt'    — f(x) = sqrt(x)        (rewards low-end improvement more)
 *   'square'  — f(x) = x²             (penalizes mid-range values, rewards extremes)
 *   'log'     — f(x) = log(1+x*9)/log(10)  (log base-10 compressed to [0..1])
 *
 * Usage in CommercialScoreAxis:
 *
 *   // conversion: improvement from 0%→1% counts much more than 9%→10%
 *   $factors['conversion'] = new RatioFactor($convRate, transform: 'sqrt');
 *
 *   // returns (inverted): same logic — reducing returns from 20% to 10% is huge
 *   $factors['returns'] = new RatioFactor($invertedReturns, transform: 'sqrt');
 *
 *   // velocity and stockout_risk: linear is appropriate (already normalized)
 *   $factors['velocity']      = new RatioFactor($normalizedVelocity);
 *   $factors['stockout_risk'] = new RatioFactor($stockoutRisk);
 *
 * ── Status rules ──────────────────────────────────────────────────────────────
 *
 *   value === null  → status 'missing'   (excluded from weighted score)
 *   value ∈ [0..1]  → status 'valid'
 *   value outside   → clamped to [0..1] then status 'valid'
 *
 * ── Backward compatibility ────────────────────────────────────────────────────
 *
 * Default transform is 'linear', so all existing call sites that do not pass
 * a transform parameter behave identically to the original implementation.
 */
class RatioFactor implements FactorInterface
{
  private string $status;
  private float  $normalizedValue;

  /**
   * @param float|null $value      Ratio value [0..1], or null when unavailable
   * @param string     $transform  Non-linearity transform: 'linear'|'sqrt'|'square'|'log'
   */
  public function __construct(
    private readonly ?float $value,
    private readonly string $transform = 'linear',
  ) {
    $this->status          = 'missing';
    $this->normalizedValue = 0.0;

    $this->compute();
  }

  // ── FactorInterface implementation ─────────────────────────────────────────

  public function getType(): string
  {
    return 'ratio';
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

  private function compute(): void
  {
    if ($this->value === null) {
      $this->status = 'missing';
      return;
    }

    $this->status = 'valid';

    // Clamp to [0..1] before any transform
    $clamped = max(0.0, min(1.0, $this->value));

    $this->normalizedValue = match ($this->transform) {
      // sqrt: rewards marginal improvements at the low end
      // f(0)=0, f(0.25)=0.5, f(1)=1 — convex curve
      'sqrt'   => sqrt($clamped),

      // square: penalizes mid-range, rewards extremes
      // f(0)=0, f(0.5)=0.25, f(1)=1 — concave curve
      'square' => $clamped ** 2,

      // log (base-10 compressed): log(1 + x*9) / log(10)
      // f(0)=0, f(0.1)≈0.32, f(1)=1 — strong sub-linear reward
      'log'    => log(1.0 + $clamped * 9.0) / log(10.0),

      // linear (default): identity, backward compatible
      default  => $clamped,
    };
  }
}
