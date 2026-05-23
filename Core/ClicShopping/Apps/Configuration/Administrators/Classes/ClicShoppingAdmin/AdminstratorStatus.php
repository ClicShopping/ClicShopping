<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class AdminstratorStatus
{
  /**
   * Updates the administrator status based on the provided ID and status.
   *
   * @param int $id The unique identifier of the administrator.
   * @param int $status The status to set for the administrator. Valid values are 1 (activate) or 0 (deactivate).
   * @return mixed Returns the result of the database save operation or -1 for an invalid status.
   */
  public static function getAdministratorStatus(int $id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status === 1) {
      return $CLICSHOPPING_Db->save('administrators', [
        'status' => 1,
        'date_modified' => 'now()'
      ],
        ['id' => (int)$id]
      );

    } elseif ($status === 0) {
      return $CLICSHOPPING_Db->save('administrators', [
        'status' => 0,
        'date_modified' => 'now()'
      ],
        ['id' => (int)$id]
      );
    } else {
      return -1;
    }
  }
}