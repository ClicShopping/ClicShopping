<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class Status
{
  /**
   * Updates the administrator's status in the database based on the provided status value.
   *
   * @param int $id The unique identifier of the administrator.
   * @param int $status The new status to be set for the administrator. Accepts 1 for active status
   *                    and 0 for inactive status. Returns -1 for invalid status values.
   *
   * @return mixed The result of the save operation if the status is valid, or -1 if the status is invalid.
   */
  public static function getAdministratorStatus(int $id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('administrators', [
        'status' => 1,
        'last_modified' => 'now()'
      ],
        ['id' => (int)$id]
      );

    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('administrators', [
        'status' => 0,
        'last_modified' => 'now()'
      ],
        ['id' => (int)$id]
      );

    } else {
      return -1;
    }
  }
}