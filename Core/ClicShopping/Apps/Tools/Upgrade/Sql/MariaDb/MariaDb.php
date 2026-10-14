<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary installation process for the upgrade module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Upgrade = Registry::get('Upgrade');
    $CLICSHOPPING_Upgrade->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
    self::installDb();
  }

  /**
   * Installs a new entry in the administration menu for the upgrade tool if it does not already exist.
   * This includes adding the main menu entry and its corresponding descriptions for all languages.
   * Clears the cache after insertion to ensure menu updates are reflected immediately.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Upgrade = Registry::get('Upgrade');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_upgrade']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Tools\Upgrade&Upgrade',
        'image' => 'menu.png',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_upgrade'
      ];

      $insert_sql_data = ['parent_id' => 163];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);
      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Upgrade->getDef('title_menu')];

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
   * Installs the required database tables for the marketplace functionality if they do not already exist.
   *
   * This method checks for the existence of specific tables in the database.
   * If the tables are not found, they will be created with their respective structure, indexes, and primary keys.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_marketplace_categories"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_marketplace_categories (
 id int(11) NOT NULL,
 categories_id int(11) NOT NULL,
 parent_id int(11) NOT NULL DEFAULT 0,
 categories_name text DEFAULT NULL,
 url text DEFAULT NULL,
 date_added date DEFAULT NULL,
 date_modified date DEFAULT NULL,
 sort_order int(3) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE :table_marketplace_categories ADD PRIMARY KEY (Id),  ADD KEY idx_parent_id (parent_id);
ALTER TABLE :table_marketplace_categories MODIFY Id int(11) NOT NULL AUTO_INCREMENT;

CREATE TABLE :table_marketplace_files (
  id int(11) NOT NULL,
  file_id int(11) NOT NULL,
  file_categories_id int(11) NOT NULL,
  file_name varchar(255) DEFAULT NULL,
  file_url text DEFAULT NULL,
  file_description text DEFAULT NULL,
  file_author varchar(255) NOT NULL,
  file_photo_url text NOT NULL,
  file_profil_url text NOT NULL,
  date_added date DEFAULT NULL,
  date_modified date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE :table_marketplace_files  ADD PRIMARY KEY (id), ADD KEY file_id (file_id);
ALTER TABLE :table_marketplace_files MODIFY id int(11) NOT NULL AUTO_INCREMENT;

CREATE TABLE :table_marketplace_file_informations (
  id int(11) NOT NULL,
  file_id int(11) NOT NULL,
  file_name varchar(255) DEFAULT NULL,
  date_created date DEFAULT NULL,
  date_updated date DEFAULT NULL,
  file_version varchar(255) DEFAULT NULL,
  file_downloads int(11) DEFAULT NULL,
  file_rating int(11) DEFAULT NULL,
  file_prices decimal(15,4) DEFAULT NULL,
  file_date_added date DEFAULT NULL,
  file_url_screenshot text DEFAULT NULL,
  file_url_download text DEFAULT NULL,
  is_installed tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE :table_marketplace_file_informations ADD PRIMARY KEY (id), ADD KEY index_file_id (file_id);
ALTER TABLE :table_marketplace_file_informations MODIFY id int(11) NOT NULL AUTO_INCREMENT;

EOD;

      $CLICSHOPPING_Db->exec($sql);
    }
  }
}