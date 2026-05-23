<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin\AdministratorMenu as AdministratorMenuClass;

class Status
{
  protected int $status;
  protected int $id;

  /**
   * Updates the status of the administrator menu and its children based on the provided status.
   *
   * @param int $id The ID of the administrator menu item to be updated.
   * @param int $status The new status to be applied (1 for active, 0 for inactive).
   * @return int Returns -1 if an invalid status is provided, otherwise no value is returned.
   */
  public static function getAministratorMenuStatus(int $id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    Registry::set('AdministratorMenuClass', new AdministratorMenuClass());
    $CLICSHOPPING_AdministratorMenuClass = Registry::get('AdministratorMenuClass');

    if ($status == 1) {
      $data = ['id' => $id];

      foreach (array_merge(array($data['id']), $CLICSHOPPING_AdministratorMenuClass->getChildren($data['id'])) as $c) {
        $sql_array = ['status' => 1];
        $update_array = ['id' => (int)$c];

        $CLICSHOPPING_Db->save('administrator_menu', $sql_array, $update_array);
      }

    } elseif ($status == 0) {
      $data = ['id' => $id];

      foreach (array_merge(array($data['id']), $CLICSHOPPING_AdministratorMenuClass->getChildren($data['id'])) as $c) {
        $sql_array = ['status' => 0];
        $update_array = ['id' => (int)$c];

        $CLICSHOPPING_Db->save('administrator_menu', $sql_array, $update_array);
      }
    } else {
      return -1;
    }
  }
}