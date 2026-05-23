<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for TotalShipping module, including loading necessary definitions
   * and setting up administration menu configurations.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_TotalShipping = Registry::get('TotalShipping');
    $CLICSHOPPING_TotalShipping->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu administration entry for the Total Shipping module.
   *
   * This method checks if the menu entry for the app_order_total_shipping application
   * already exists in the administrator menu. If it does not exist, it creates the entry,
   * assigns the necessary attributes, and populates the menu descriptions for all available
   * languages. After the data is inserted, it clears the administrator menu cache to reflect
   * the changes immediately.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_TotalShipping = Registry::get('TotalShipping');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_order_total_shipping']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 4,
        'link' => 'index.php?A&OrderTotal\TotalShipping&Configure',
        'image' => 'modules_order_total.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_order_total_shipping'
      ];

      $insert_sql_data = ['parent_id' => 451];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_TotalShipping->getDef('title_menu')];

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