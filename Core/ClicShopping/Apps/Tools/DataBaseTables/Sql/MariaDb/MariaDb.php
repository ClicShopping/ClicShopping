<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\DataBaseTables\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary steps to load database table definitions and installs the menu administration.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_DataBaseTables = Registry::get('DataBaseTables');
    $CLICSHOPPING_DataBaseTables->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu item for administration purposes in the administrator menu.
   *
   * This method checks if an entry with the specified application code exists in the administrator menu.
   * If the entry does not exist, it inserts the new menu item along with its configuration and language-specific descriptions.
   * After the insertion, it clears the administrator menu cache to reflect the changes.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_DataBaseTables = Registry::get('DataBaseTables');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_data_base_tables']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 3,
        'link' => 'index.php?A&Tools\DataBaseTables&DataBaseTables',
        'image' => 'database_analyse.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_data_base_tables'
      ];

      $insert_sql_data = ['parent_id' => 164];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_DataBaseTables->getDef('title_menu')];

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