<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary instructions for loading Cronjob definitions
   * and installing the menu administration.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Cronjob = Registry::get('Cronjob');
    $CLICSHOPPING_Cronjob->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs an administration menu entry for the Cronjob application.
   *
   * This method checks the existence of a specific application code in the `administrator_menu` table.
   * If it does not exist, it inserts a new menu entry with the associated details.
   * Additionally, it inserts corresponding menu descriptions for all available languages
   * into the `administrator_menu_description` table and clears the administrator menu cache.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Cronjob = Registry::get('Cronjob');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_cronjob']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Tools\Cronjob&Cronjob',
        'image' => 'menu.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_cronjob'
      ];

      $insert_sql_data = ['parent_id' => 178];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);
      $id = $CLICSHOPPING_Db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Cronjob->getDef('title_menu')];

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