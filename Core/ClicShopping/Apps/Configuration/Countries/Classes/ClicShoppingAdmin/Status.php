<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Countries\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class Status
{
  /**
   * @param int $countries_id
   * @param int $status
   * @return int
   */
  public static function getCountriesStatus(int $countries_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('countries', ['status' => 1],
        ['countries_id' => (int)$countries_id]
      );

    } elseif ($status == 0) {

      return $CLICSHOPPING_Db->save('countries', ['status' => 0],
        ['countries_id' => (int)$countries_id]
      );

    } else {
      return -1;
    }
  }
}
