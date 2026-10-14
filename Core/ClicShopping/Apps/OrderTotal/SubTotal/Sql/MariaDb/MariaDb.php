<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\SubTotal\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary operations for loading definitions and installing the administration menu.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_SubTotal = Registry::get('SubTotal');
    $CLICSHOPPING_SubTotal->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu administration for the SubTotal module in the administrator interface.
   *
   * This method verifies if the menu configuration for the app_code 'app_order_total_subtotal' exists in the
   * administrator menu. If it does not exist, it creates a new menu item with its corresponding descriptions
   * in multiple languages. After the insertion, it clears the cache for the administrator menu.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_SubTotal = Registry::get('SubTotal');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_order_total_subtotal']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&OrderTotal\SubTotal&Configure&module=ST',
        'image' => 'modules_order_total.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_order_total_subtotal'
      ];

      $insert_sql_data = ['parent_id' => 451];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_SubTotal->getDef('title_menu')];

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