<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Item\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the menu administration.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Item = Registry::get('Item');
    $CLICSHOPPING_Item->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the necessary menu administration entries for the shipping item module
   * within the administrator menu. It ensures the menu is properly configured with
   * the required database entries and descriptions for all available languages.
   *
   * The method checks if the menu entry already exists before proceeding with the
   * insertion. After adding the entries, a cache clear operation is performed to
   * update the administrator menu.
   *
   * @return void This function does not return any value.
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Item = Registry::get('Item');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_shipping_item']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 4,
        'link' => 'index.php?A&Shipping\Item&Configure&module=IT',
        'image' => 'modules_shipping.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_shipping_item'
      ];

      $insert_sql_data = ['parent_id' => 449];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Item->getDef('title_menu')];

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