<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring;

/**
 * ValidationResult
 *
 * Immutable value object returned by NormalizationValidator::validateDistribution().
 *
 * Carries the outcome of all statistical checks on a CatalogNormalization:
 *   - confidence  : float [0..1] — overall quality score (1.0 = fully reliable)
 *   - warnings    : string[]    — human-readable descriptions of detected issues
 *   - isValid     : bool        — false means caller should fall back to defaults
 *   - sampleSize  : int         — number of products used in the calculation
 *
 * Usage:
 *
 *   $result = $validator->validateDistribution($normalization, $sampleSize);
 *
 *   if (!$result->isValid) {
 *     error_log('[CockpitAI] ' . $result->summary());
 *     return CatalogNormalization::defaults();
 *   }
 *
 *   if (!$result->isConfident()) {
 *     // Log warnings but proceed — reduced confidence, not invalid
 *     foreach ($result->warnings as $w) { error_log('[CockpitAI] Warning: ' . $w); }
 *   }
 */
readonly class ValidationResult
{
  /**
   * @param float    $confidence  Overall quality score [0..1]
   * @param string[] $warnings    List of detected issues
   * @param bool     $isValid     Whether the normalization is usable at all
   * @param int      $sampleSize  Number of products in the distribution
   */
  public function __construct(
    public float $confidence,
    public array $warnings,
    public bool  $isValid,
    public int   $sampleSize,
  ) {
  }

  /**
   * Whether the normalization is reliable enough to use without qualification.
   * Returns true when confidence >= 0.8 (no major issues detected).
   */
  public function isConfident(): bool
  {
    return $this->confidence >= 0.8;
  }

  /**
   * Return a single-line summary for logging.
   */
  public function summary(): string
  {
    $status = $this->isValid ? ($this->isConfident() ? 'OK' : 'DEGRADED') : 'INVALID';
    $wCount = count($this->warnings);

    return sprintf(
      'NormalizationValidator: %s | confidence=%.2f | sample=%d | warnings=%d',
      $status,
      $this->confidence,
      $this->sampleSize,
      $wCount
    );
  }

  /**
   * Return warnings as a single newline-separated string.
   */
  public function warningsAsString(): string
  {
    return implode("\n", $this->warnings);
  }
}
