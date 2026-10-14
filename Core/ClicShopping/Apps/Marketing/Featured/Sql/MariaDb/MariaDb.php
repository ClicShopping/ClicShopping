<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the Featured module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Featured = Registry::get('Featured');
    $CLICSHOPPING_Featured->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database menu entries for the administration panel specific to the Featured module.
   *
   * This method ensures that the required menu items for the Featured module are added to the
   * administrator menu in the database. If they do not already exist, they are inserted alongside
   * their respective translations for all available languages. The method also clears the cache
   * for the administrator menu to reflect the changes.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Featured = Registry::get('Featured');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_marketing_featured']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Marketing\Featured&Featured',
        'image' => 'products_featured.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_featured'
      ];

      $insert_sql_data = ['parent_id' => 5];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Featured->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      $sql_data_array = [
        'sort_  order' => 1,
        'link' => 'index.php?A&Marketing\Featured&Featured',
        'link' => 'index.php?A&Configuration\Modules&Modules&set=modules_products_featured',
        'image' => 'products_featured.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_featured'
      ];

      $insert_sql_data = ['parent_id' => 117];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Featured->getDef('title_menu')];

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
   * Installs the database table for managing featured products if it does not already exist.
   *
   * This method checks for the existence of the `:table_products_featured` table in the database.
   * If the table does not exist, it creates the table with the appropriate columns, keys, and constraints.
   *
   * @return void
   */
  private static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_featured"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_products_featured (
  products_featured_id int(11) NOT NULL,
  products_id int NOT NULL default(0),
  products_featured_date_added datetime,
  products_featured_last_modified datetime,
  scheduled_date datetime,
  expires_date datetime,
  date_status_change datetime,
  status tinyint(1) NOT NULL default(1),
  customers_group_id int NOT NULL default(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE :table_products_featured ADD PRIMARY KEY (products_featured_id),  ADD KEY idx_products_featured_id (products_id);
ALTER TABLE :table_products_featured MODIFY products_featured_id int(11) NOT NULL AUTO_INCREMENT;
EOD;

      $CLICSHOPPING_Db->exec($sql);
    }
  }
}