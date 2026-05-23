<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation procedure for the backup module by loading appropriate definitions
   * and installing the administration menu.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Backup = Registry::get('Backup');
    $CLICSHOPPING_Backup->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu item for the Backup application in the administrator menu if it does not already exist.
   * The method adds entries into the `administrator_menu` and `administrator_menu_description` tables,
   * and clears the related cache for the administrator menu.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Backup = Registry::get('Backup');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_backup']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 3,
        'link' => 'index.php?A&Tools\Backup&Backup',
        'image' => 'backup.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_backup'
      ];

      $insert_sql_data = ['parent_id' => 164];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Backup->getDef('title_menu')];

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