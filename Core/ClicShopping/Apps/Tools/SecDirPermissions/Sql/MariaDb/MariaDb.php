<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\SecDirPermissions\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary operations for managing directory permissions and
   * installing administrative menu configurations.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_SecDirPermissions = Registry::get('SecDirPermissions');
    $CLICSHOPPING_SecDirPermissions->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu entry for the SecDirPermissions application in the administrator menu.
   *
   * This method checks if the menu entry for the application 'app_tools_sec_dir_permissions' exists in the
   * 'administrator_menu' table. If the menu entry does not exist, it is created along with language-specific
   * descriptions. After insertion, the administrator menu cache is cleared to reflect the changes.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_SecDirPermissions = Registry::get('SecDirPermissions');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_sec_dir_permissions']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 30,
        'link' => 'index.php?A&Tools\SecDirPermissions&SecDirPermissions',
        'image' => 'file_manager.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_sec_dir_permissions'
      ];

      $insert_sql_data = ['parent_id' => 178];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_SecDirPermissions->getDef('title_menu')];

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