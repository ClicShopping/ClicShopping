<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  public function execute()
  {
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');
    $CLICSHOPPING_Manufacturers->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Adds database entries for the administrator menu related to manufacturers and their stock.
   * These entries include menu links and their associated language descriptions.
   * Additionally, clears the administrator menu cache after updating the database.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_catalog_manufacturers']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 6,
        'link' => 'index.php?A&Catalog\Manufacturers&Manufacturers',
        'image' => 'manufacturers.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_catalog_manufacturers'
      ];

      $insert_sql_data = ['parent_id' => 3];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Manufacturers->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      $sql_data_array = [
        'sort_order' => 6,
        'link' => 'index.php?A&Catalog\Manufacturers&Stock',
        'image' => 'manufacturers.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_catalog_manufacturers'
      ];

      $insert_sql_data = ['parent_id' => 103];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);
      $id = $CLICSHOPPING_Db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Manufacturers->getDef('title_menu_stock')];

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
   * Installs the database tables required for the manufacturers feature.
   *
   * This method checks if the required `manufacturers` and `manufacturers_info` tables exist in the database.
   * If they do not exist, it creates them with the necessary structure and fields.
   *
   * @return void
   */
  private static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_manufacturers"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_manufacturers (
  manufacturers_id int NOT NULL auto_increment,
  manufacturers_name varchar(64) NOT NULL,
  manufacturers_image varchar(255),
  date_added datetime,
  last_modified datetime,
  manufacturers_status int(1) NOT NULL DEFAULT 0,
  suppliers_id int(11) NULL 
  PRIMARY KEY (manufacturers_id),
  ADD KEY idx_manufacturers_name (manufacturers_name)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_manufacturers_info"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_manufacturers_info (
  manufacturers_id int  NOT NULL,
  languages_id int  NOT NULL,
  manufacturers_url varchar(255)  NOT NULL,
  url_clicked int(5) default(0)  NOT NULL,
  date_last_click datetime,
  manufacturer_description text,
  manufacturer_seo_title varchar(70) Null,
  manufacturer_seo_description varchar(255) Null,
  manufacturer_seo_keyword text  Null
  PRIMARY KEY manufacturers_id (languages_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}