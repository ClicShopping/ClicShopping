<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
/**
 * Updates the status of a product attribute.
 *
 * This method sets the status of a product attribute to active (1) or inactive (0)
 * based on the provided status parameter. If the status parameter is invalid, it
 * returns -1 without updating the database.
 *
 * @param int $products_attributes_id The ID of the product attribute to update.
 * @param int $status The new status of the product attribute (1 for active, 0 for inactive).
 * @return int Returns the result of the database save operation or -1 on invalid status.
 */
class ProductsAttributesStatusAdmin
{
  /**
   * Updates the status of a product attribute in the database.
   *
   * @param int $products_attributes_id The ID of the product attribute to update.
   * @param int $status The status to set for the product attribute. Expected values are 1 (active) or 0 (inactive).
   *
   * @return int|mixed Returns the result of the database save operation, or -1 if the status is invalid.
   */
  public static function getStatus(int $products_attributes_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('products_attributes', ['status' => 1], ['products_attributes_id' => (int)$products_attributes_id]);

    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('products_attributes', ['status' => 0], ['products_attributes_id' => (int)$products_attributes_id]);

    } else {
      return -1;
    }
  }
}