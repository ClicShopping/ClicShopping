<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the method to initialize and install necessary database configurations.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Favorites = Registry::get('Favorites');
    $CLICSHOPPING_Favorites->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs entries for the Favorites application into the administrator menu database table.
   *
   * This method checks if the application's menu entries are already present in the administrator
   * menu database. If not, it adds the required rows, including the parent-child relationships
   * and associated language descriptions across all supported languages. After insertion,
   * the menu administrator cache is cleared to ensure changes are reflected.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Favorites = Registry::get('Favorites');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_marketing_favorites']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Marketing\Favorites&Favorites',
        'image' => 'products_favorites.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_favorites'
      ];

      $insert_sql_data = ['parent_id' => 5];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Favorites->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Marketing\Favorites&Favorites',
        'link' => 'index.php?A&Configuration\Modules&Modules&set=modules_products_favorites',
        'image' => 'products_favorites.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_favorites'
      ];

      $insert_sql_data = ['parent_id' => 117];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Favorites->getDef('title_menu')];

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

  /**
   * Installs the database table necessary for the products favorites feature.
   *
   * This method checks if the database table ":table_products_favorites" exists.
   * If the table does not exist, it creates the table with the required columns and indexes.
   * The table is used to store information related to product favorites functionality.
   *
   * @return void
   */
  private static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_favorites"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_products_favorites (
  products_favorites_id int(11) NOT NULL,
  products_id int NOT NULL default(0),
  products_favorites_date_added datetime,
  products_favorites_last_modified datetime,
  scheduled_date datetime,
  expires_date datetime,
  date_status_change datetime,
  status tinyint(1) NOT NULL default(1),
  customers_group_id int NOT NULL default(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE :table_products_favorites ADD PRIMARY KEY (products_favorites_id),  ADD KEY idx_products_favorites_id (products_id);
ALTER TABLE :table_products_favorites MODIFY products_favorites_id int(11) NOT NULL AUTO_INCREMENT;
EOD;

      $CLICSHOPPING_Db->exec($sql);
    }
  }
}