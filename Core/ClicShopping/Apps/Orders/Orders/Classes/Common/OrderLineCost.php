<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Common;

use ClicShopping\OM\Registry;

/**
 * Cost basis frozen on an order line (orders_products.products_unit_cost) when it is written,
 * so a past margin is never recomputed at today's cost.
 */
final class OrderLineCost
{
  /**
   * Cost basis per unit of a product today: products_cost plus products_handling.
   *
   * @param int $productsId
   * @return float|null Null when the product carries no cost (an uncosted line stays out of margins)
   */
  public static function unitCost(int $productsId): ?float
  {
    $Qcost = Registry::get('Db')->get('products', ['products_cost', 'products_handling'], ['products_id' => $productsId]);

    if ($Qcost->fetch() === false || (float)$Qcost->value('products_cost') <= 0) {
      return null;
    }

    return (float)$Qcost->value('products_cost') + (float)$Qcost->value('products_handling');
  }
}
