<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
/**
 * Class Status
 *
 * This class provides functionalities related to updating the status of suppliers in the system.
 */
class Status
{
  /**
   * Updates the status of a supplier within the database based on the provided status.
   *
   * @param int $suppliers_id The ID of the supplier whose status will be updated.
   * @param int $status The status to be set for the supplier. Accepts 1 for active, 0 for inactive, and other integers for invalid input.
   *
   * @return mixed Returns the result of the database save operation if the status is 1 or 0, or -1 if an invalid status is provided.
   */
  public static function getSuppliersStatus(int $suppliers_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('suppliers', [
        'suppliers_status' => 1,
        'date_added' => 'null',
        'last_modified' => 'null'
      ],
        ['suppliers_id' => (int)$suppliers_id]
      );
    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('suppliers', [
        'suppliers_status' => 0,
        'last_modified' => 'now()'
      ],
        ['suppliers_id' => (int)$suppliers_id]
      );
    } else {
      return -1;
    }
  }
}