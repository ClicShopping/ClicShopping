<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Archive\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the Archive module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Archive = Registry::get('Archive');
    $CLICSHOPPING_Archive->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

  /**
   * Installs the database entries required for the Archive module's administration menu.
   *
   * This method interacts with the database to add the necessary menu entries for the
   * Archive module under the administrator menu. It checks if the entries already exist,
   * inserts new entries if they do not, and includes translations for supported languages.
   * Also clears the administrator menu cache after installation.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Archive = Registry::get('Archive');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_products_archive']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 10,
        'link' => 'index.php?A&Catalog\Archive&Archive',
        'image' => 'archive.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_products_archive'
      ];

      $insert_sql_data = ['parent_id' => 3];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Archive->getDef('title_menu')];

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