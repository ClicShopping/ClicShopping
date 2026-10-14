<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the Antispam module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Antispam = Registry::get('Antispam');
    $CLICSHOPPING_Antispam->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

  /**
   * Creates and inserts an entry for the Antispam module into the administrator menu database table.
   * If the entry does not already exist, it adds the module menu item as a child under a specific parent menu.
   * Also inserts language-specific descriptions for the menu item and clears the administrator menu cache.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Antispam = Registry::get('Antispam');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_antispam']);

    if ($Qcheck->fetch() === false) {

      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Configuration\Antispam&Configure',
        'image' => 'antispam.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_configuration_antispam'
      ];

      $insert_sql_data = ['parent_id' => 15];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Antispam->getDef('title_menu')];

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