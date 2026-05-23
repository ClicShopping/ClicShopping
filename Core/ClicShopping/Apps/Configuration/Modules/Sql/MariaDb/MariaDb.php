<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Modules\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the ClicShopping modules.
   *
   * This method retrieves the Modules registry, loads specific definitions
   * required for the installation, and performs the database menu
   * administration setup.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Modules = Registry::get('Modules');
    $CLICSHOPPING_Modules->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

  /**
   * Installs a new entry in the administrator menu database table for module configuration.
   *
   * This method checks if an entry with the specific application code (`app_configuration_modules`) already exists in the database.
   * If it does not exist, the method will insert the required data into the `administrator_menu` table,
   * create the corresponding multilingual descriptions in the `administrator_menu_description` table,
   * and clear the administrator menu cache for updates to take effect.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Modules = Registry::get('Modules');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_modules']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Configuration\Modules&Modules',
        'image' => '',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_configuration_modules'
      ];

      $insert_sql_data = ['parent_id' => 20];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Modules->getDef('title_menu')];

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