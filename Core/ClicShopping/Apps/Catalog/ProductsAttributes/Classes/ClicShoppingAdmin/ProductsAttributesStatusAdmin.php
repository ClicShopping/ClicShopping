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
 * Helper to mutate a product attribute's active flag (status column).
 */
class ProductsAttributesStatusAdmin
{
  /**
   * Updates the status of a product attribute in the database.
   *
   * @param int $products_attributes_id The ID of the product attribute to update.
   * @param int $status The status to set (1 for active, 0 for inactive).
   *
   * @return int|mixed Returns the result of the database save operation, or -1 if the status is invalid.
   */
  public static function setStatus(int $products_attributes_id, int $status)
  {
    if ($status !== 0 && $status !== 1) {
      return -1;
    }

    return Registry::get('ProductsAttributes')->db->save(
      'products_attributes',
      ['status' => $status],
      ['products_attributes_id' => $products_attributes_id]
    );
  }
}