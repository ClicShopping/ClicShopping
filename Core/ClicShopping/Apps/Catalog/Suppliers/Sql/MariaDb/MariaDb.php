<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the Suppliers module by loading necessary definitions
   * and performing database-related setup actions.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Suppliers = Registry::get('Suppliers');
    $CLICSHOPPING_Suppliers->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries for the administration menu related to the Suppliers module.
   * This includes:
   * - Adding the menu entry to the `administrator_menu` table if it does not already exist.
   * - Linking the menu entry to the relevant language descriptions in the `administrator_menu_description` table.
   * - Clearing the administrative menu cache to reflect changes.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Suppliers = Registry::get('Suppliers');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_catalog_suppliers']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 7,
        'link' => 'index.php?A&Catalog\Suppliers&Suppliers',
        'image' => 'suppliers.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_catalog_suppliers'
      ];

      $insert_sql_data = ['parent_id' => 3];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];

        $sql_data_array = ['label' => $CLICSHOPPING_Suppliers->getDef('title_menu')];

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
   * Installs the required database tables for the suppliers module if they do not already exist.
   *
   * @return void
   */
  private static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_suppliers"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_suppliers (
  suppliers_id int NOT NULL auto_increment,
  suppliers_name varchar(32) NOT NULL,
  suppliers_image varchar(255),
  date_added datetime,
  last_modified datetime,
  suppliers_manager varchar(64),
  suppliers_phone varchar(32),
  suppliers_email_address varchar(96),
  suppliers_fax varchar(32),
  suppliers_address varchar(64),
  suppliers_suburb varchar(32),
  suppliers_postcode varchar(10),
  suppliers_city varchar(32),
  suppliers_states varchar(32),
  suppliers_country_id int NOT NULL DEFAULT 0,
  suppliers_notes text,
  suppliers_status int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (suppliers_id),
  ADD KEY idx_suppliers_name (suppliers_name)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_suppliers_info"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_suppliers_info (
  suppliers_id int NOT NULL DEFAULT 0,
  languages_id int NOT NULL DEFAULT 0,
  suppliers_url varchar(255),
  url_clicked int(5) NOT NULL DEFAULT 0,
  date_last_click datetime
  PRIMARY KEY suppliers_id (languages_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}