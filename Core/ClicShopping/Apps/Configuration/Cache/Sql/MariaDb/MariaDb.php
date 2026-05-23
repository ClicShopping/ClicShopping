<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Cache\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation procedure by loading necessary cache definitions
   * and performing the database menu administration setup.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Cache = Registry::get('Cache');
    $CLICSHOPPING_Cache->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

  /**
   * Installs the database entries for the administrative menu related to the cache configuration app.
   *
   * It checks if the menu entry for the 'app_configuration_cache' already exists in the 'administrator_menu' table.
   * If it does not exist, it inserts a new entry with predefined data, including sorting order, link, image, and access level.
   * Language-specific labels are then added into the 'administrator_menu_description' table for each available language.
   * Finally, the menu-related cache is cleared to reflect the changes.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Cache = Registry::get('Cache');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_cache']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Configuration\Cache&Cache',
        'image' => 'cache.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_configuration_cache'
      ];

      $insert_sql_data = ['parent_id' => 21];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Cache->getDef('title_menu')];

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