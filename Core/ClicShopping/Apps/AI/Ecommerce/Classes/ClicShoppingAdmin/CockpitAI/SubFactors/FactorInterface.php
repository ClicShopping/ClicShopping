<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors;

/**
 * FactorInterface
 *
 * Formal contract for all scoring factors in the CockpitAI dual-axis system.
 * Supports four types: boolean, count, ratio, score.
 * Status values: 'valid', 'missing', 'not_analyzed'
 */
interface FactorInterface
{
  /**
   * Normalize the factor value to a [0.0..1.0] range.
   *
   * @return float Normalized value in [0.0..1.0]
   */
  public function normalize(): float;

  /**
   * Return the factor type: 'boolean', 'count', 'ratio', or 'score'
   *
   * @return string Factor type
   */
  public function getType(): string;

  /**
   * Return the factor status: 'valid', 'missing', or 'not_analyzed'
   *
   * @return string Factor status
   */
  public function getStatus(): string;

  /**
   * Return the raw (un-normalized) factor value.
   *
   * @return mixed Raw value
   */
  public function getValue(): mixed;
}
