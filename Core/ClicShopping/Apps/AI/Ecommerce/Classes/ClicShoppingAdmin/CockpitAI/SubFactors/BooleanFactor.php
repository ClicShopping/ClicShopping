<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors;

/**
 * BooleanFactor
 *
 * Represents a boolean scoring factor.
 * normalize() returns 1.0 when value is true, 0.0 when false.
 * A null value is treated as 'missing'.
 */
class BooleanFactor implements FactorInterface
{
  private ?bool $value;

  public function __construct(?bool $value)
  {
    $this->value = $value;
  }

  public function normalize(): float
  {
    if ($this->value === null) {
      return 0.0;
    }

    return $this->value ? 1.0 : 0.0;
  }

  public function getType(): string
  {
    return 'boolean';
  }

  public function getStatus(): string
  {
    if ($this->value === null) {
      return 'missing';
    }

    return 'valid';
  }

  public function getValue(): mixed
  {
    return $this->value;
  }
}
