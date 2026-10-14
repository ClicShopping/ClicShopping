<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\MoneyOrder\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

/**
 * Handles the setup and database logic for the Money Order payment module in MariaDb.
 * Provides functionality to install necessary configurations in the administrator menu.
 */
class MariaDb
{
  /**
   * Executes the installation process for the MoneyOrder module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_MoneyOrder = Registry::get('MoneyOrder');
    $CLICSHOPPING_MoneyOrder->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu administration entry for the Money Order payment module.
   * The method inserts a new record into the `administrator_menu` table if the entry does not already exist.
   * It also adds language-specific descriptions for the newly created menu entry.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_MoneyOrder = Registry::get('MoneyOrder');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_payment_moneyorder']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 4,
        'link' => 'index.php?A&Payment\MoneyOrder&Configure',
        'image' => 'modules_payment.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_payment_moneyorder'
      ];

      $insert_sql_data = ['parent_id' => 186];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_MoneyOrder->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      Cache::clear('menu-administrator');
    }
}
}