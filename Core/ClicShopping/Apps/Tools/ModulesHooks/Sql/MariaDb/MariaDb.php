<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ModulesHooks\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for administrative menu hooks.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_ModulesHooks = Registry::get('ModulesHooks');
    $CLICSHOPPING_ModulesHooks->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu administration entry for the Modules Hooks application.
   *
   * This method checks if the 'app_tools_modules_hooks' menu item already exists in the administrator menu.
   * If it does not exist, it creates a new entry with the necessary configuration, including its position,
   * link, image, and access level. Additionally, it inserts descriptions for the menu item in all available
   * languages in the 'administrator_menu_description' table. After execution, the administrator menu cache
   * is cleared to reflect the change.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ModulesHooks = Registry::get('ModulesHooks');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_modules_hooks']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 2,
        'link' => 'index.php?A&Tools\ModulesHooks&ModulesHooks',
        'image' => 'hooks.png',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_modules_hooks'
      ];

      $insert_sql_data = ['parent_id' => 727];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_ModulesHooks->getDef('title_menu')];

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