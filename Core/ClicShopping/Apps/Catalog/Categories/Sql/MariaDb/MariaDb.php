<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the ImportExport module by loading necessary definitions
   * and performing database setup tasks, such as installing administrative menu entries and database schema.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Categories = Registry::get('ImportExport');
    $CLICSHOPPING_Categories->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries related to the administration menu for the categories module.
   *
   * This method checks if the necessary menu entry for the categories module exists in the `administrator_menu` table.
   * If it does not exist, it creates the menu entry and associates localized descriptions
   * for the entry in the `administrator_menu_description` table. Finally, it clears the related cache.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Categories = Registry::get('Categories');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_catalog_categories']);

    if ($Qcheck->fetch() === false) {

      $sql_data_array = ['sort_order' => 0,
        'link' => 'index.php?A&Catalog\Categories&Categories',
        'image' => 'categorie.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_catalog_categories'
      ];

      $insert_sql_data = ['parent_id' => 3];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Categories->getDef('title_menu')];

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
   * Creates the necessary database tables for categories and categories descriptions
   * if they do not already exist. This method checks for the presence of the tables
   * by querying the database and executes SQL statements to create the tables if
   * they are missing.
   *
   * @return void
   */
  private static function installDb():void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_categories"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_categories (
  categories_id int NOT NULL auto_increment,
  categories_image varchar(255),
  parent_id int UNSIGNED default(0) NOT NULL,
  sort_order int(3),
  date_added datetime,
  last_modified datetime,
  virtual_categories tinyint(1) default(0) NOT NULL,
  status tinyint(0) default(0) NOT NULL,
  customers_group_id int default (99) not null,
  PRIMARY KEY categories_id,
  KEY idx_categories_parent_id (parent_id)
  ) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_categories_description"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_categories_description (
  categories_id int default(0) NOT NULL,
  language_id int default(1) NOT NULL,
  categories_name varchar(255) NOT NULL,
  categories_description text,
  categories_seo_url varchar(255),
  categories_head_title_tag varchar(255),
  categories_head_desc_tag varchar(255),
  categories_head_keywords_tag varchar(255),

 PRIMARY KEY categories_id (language_id),
 KEY idx_categories_name (categories_name)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_categories_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
       CREATE TABLE IF NOT EXISTS :table_categories_embedding (
          id SERIAL PRIMARY KEY,
          content text DEFAULT NULL,
          type text DEFAULT NULL,
          sourcetype text default 'manual',
          sourcename text default 'manual',
          embedding vector(3072) NOT NULL,
          chunknumber int default(128),
          date_modified datetime DEFAULT NULL,
          entity_id INT,
          language_id INT  
        );

        CREATE VECTOR INDEX embedding_index ON :table_categories_embedding (embedding);
      EOD;

      $CLICSHOPPING_Db->exec($sql);
    }    
  }
}