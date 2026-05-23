<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

/**
 * Class ProductsStatusAdmin
 *
 * This class provides methods to interact with and manage the status of products
 * in the database within the ClicShoppingAdmin application.
 */
class ProductsStatusAdmin
{
  /**
   * Updates the status of a product in the database based on the provided status value.
   *
   * @param int $products_id The ID of the product whose status needs to be updated.
   * @param int $status The status to set for the product (1 for active, 0 for inactive).
   * @return mixed Returns the result of the database operation or -1 if the provided status is invalid.
   */
  public static function getProductStatus(int $products_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('products', [
        'products_status' => 1,
        'products_last_modified' => 'now()'
      ],
        ['products_id' => (int)$products_id]
      );

    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('products', [
        'products_status' => 0,
        'products_last_modified' => 'now()'
      ],
        ['products_id' => (int)$products_id]
      );

    } else {
      return -1;
    }
  }

  /**
   * Checks the status of a product based on its ID.
   *
   * @param int|null $products_id The ID of the product. If null, the function will return false.
   * @return bool Returns true if the product status is active (not 0), and false otherwise.
   */
  public static function checkProductStatus( int|null $products_id): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qstatus = $CLICSHOPPING_Db->get('products', 'products_status', ['products_id' => $products_id]);

    if ($Qstatus->fetch()) {
      if ($Qstatus->valueInt('products_status') == 0) {
        return false;
      } else {
        return true;
      }
    } else {
      return false;
    }
  }
}