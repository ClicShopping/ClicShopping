<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors;

/**
 * ScoreFactor
 *
 * Represents a score-based factor (e.g. seo_score/100, description quality score).
 * normalize() divides raw value by max_score, clamped to [0.0..1.0].
 * A null value is treated as 'missing'. A null or zero max_score is treated as 'missing'.
 * A not-yet-computed score (e.g. SEO not analyzed) uses status 'not_analyzed'.
 */
class ScoreFactor implements FactorInterface
{
  private ?float $value;
  private ?float $maxScore;
  private bool $notAnalyzed;

  /**
   * @param float|null $value        The raw score value
   * @param float|null $maxScore     The maximum possible score (denominator)
   * @param bool       $notAnalyzed  True when the score has not been computed yet (e.g. SEO not run)
   */
  public function __construct(?float $value, ?float $maxScore, bool $notAnalyzed = false)
  {
    $this->value = $value;
    $this->maxScore = $maxScore;
    $this->notAnalyzed = $notAnalyzed;
  }

  public function normalize(): float
  {
    if ($this->notAnalyzed || $this->value === null || $this->maxScore === null || $this->maxScore <= 0.0) {
      return 0.0;
    }

    $normalized = $this->value / $this->maxScore;

    return (float) max(0.0, min(1.0, $normalized));
  }

  public function getType(): string
  {
    return 'score';
  }

  public function getStatus(): string
  {
    if ($this->notAnalyzed) {
      return 'not_analyzed';
    }

    if ($this->value === null || $this->maxScore === null || $this->maxScore <= 0.0) {
      return 'missing';
    }

    return 'valid';
  }

  public function getValue(): mixed
  {
    return $this->value;
  }
}
