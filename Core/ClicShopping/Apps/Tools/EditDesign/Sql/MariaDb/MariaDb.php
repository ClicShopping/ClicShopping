<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditDesign\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the main logic for loading definitions and installing menu administration.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_EditDesign = Registry::get('EditDesign');
    $CLICSHOPPING_EditDesign->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs a new menu entry for the administration section in the database if it does not already exist.
   * This method performs the following actions:
   * - Checks if the menu entry with a specific app code exists in the `administrator_menu` table.
   * - Inserts a new menu entry if it does not already exist, with predefined attributes such as sort order, link, image, and access level.
   * - Associates language-specific labels for the menu entry in the `administrator_menu_description` table.
   * - Clears the administrator menu cache to reflect the changes.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_EditDesign = Registry::get('EditDesign');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_design']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 3,
        'link' => 'index.php?A&Tools\EditDesign&EditDesign',
        'image' => '',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_design'
      ];

      $insert_sql_data = ['parent_id' => 116];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_EditDesign->getDef('title_menu')];

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