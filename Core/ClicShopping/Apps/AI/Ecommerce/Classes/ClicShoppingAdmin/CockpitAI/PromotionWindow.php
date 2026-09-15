<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

/**
 * PromotionWindow
 *
 * How long an automated marketing action stays on screen.
 *
 * The window grows with the discount: a product that needs 15 % off is a product that is not
 * selling, and one week does not settle that. The reference is the smallest configured tier
 * (CAI_PROMO_P1) — a discount twice as deep is displayed twice as long.
 */
class PromotionWindow
{
  /** Window applied at the smallest tier, until CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_WINDOW_DAYS is installed. */
  public const DEFAULT_WINDOW_DAYS = 7;

  /** Used when no rate reaches this class: the smallest tier, never a bare literal. */
  public static function defaultRate(): float
  {
    return \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P1')
      ? max(0.01, (float)CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P1)
      : 5.0;
  }

  /** Window in days for a discount rate, in percent. */
  public static function days(float $rate): int
  {
    $base = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_WINDOW_DAYS')
      ? (int)CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_WINDOW_DAYS
      : self::DEFAULT_WINDOW_DAYS;

    $base = max(1, $base);
    $ref  = self::defaultRate();

    if ($rate <= $ref) {
      return $base;
    }

    return (int)max($base, round($base * $rate / $ref));
  }
}
