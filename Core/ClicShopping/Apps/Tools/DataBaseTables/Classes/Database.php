<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\DataBaseTables\Classes;

use ClicShopping\OM\Registry;

class Database
{
  /**
   * Retrieves a list of table names from the database.
   *
   * @return array An array containing the names of all tables in the database.
   */
  public static function getDtTables(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $result = [];

    $Qtables = $CLICSHOPPING_Db->query('show table status');

    while ($Qtables->fetch()) {
      $result[] = $Qtables->value('Name');
    }

    return $result;
  }
}