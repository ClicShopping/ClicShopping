<?php
/**
 * Domain-specific helpers for AI Ecommerce
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

class Domain
{
  /**
   * Return domain-specific metadata fields, ordered by priority.
   *
   * @return array
   */
  public static function getPossibleFields(): array
  {
    return [
      // specific ecommerce
      'order_name',
      'product_name',
      'customer_name',
      'supplier_name',
      'category_name',
      'brand_name',
    ];
  }
}
