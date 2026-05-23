<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the PageManager module.
   * This method loads necessary definitions and initializes the database setup.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_PageManager = Registry::get('PageManager');
    $CLICSHOPPING_PageManager->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries for the administration menu related to the Page Manager module.
   *
   * This method checks if the required menu entry exists in the 'administrator_menu' table.
   * If the entry does not exist, it creates a new entry with its corresponding metadata and language-specific descriptions.
   * Once the entries are added, it clears the administrator menu cache to ensure the changes are applied.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_PageManager = Registry::get('PageManager');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_communication_page_manager']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 0,
        'link' => 'index.php?A&Communication\PageManager&PageManager',
        'image' => 'page_manager.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_communication_page_manager'
      ];

      $insert_sql_data = ['parent_id' => 6];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_PageManager->getDef('title_menu')];

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
   * Installs the necessary database tables required for the Page Manager module if they do not already exist.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_pages_manager"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
  CREATE TABLE :table_pages_manager (
    pages_id int(11) NOT NULL auto_increment,
    links_target varchar(6) default('_self') not null,
    sort_order int(3),
    status int(1) default(1) NOT NULL,
    page_type int(1) DEFAULT 0) NOT NULL,
    page_box int(1) DEFAULT 0 NOT NULL,
    page_time varchar(4) NOT NULL,
    page_date_start datetime,
    page_date_closed datetime,
    date_added datetime NOT NULL,
    last_modified datetime,
    date_status_change datetime,
    customers_group_id int DEFAULT 0 NOT NULL,
    page_general_condition int(1) DEFAULT 0 NOT NULL
  PRIMARY KEY (pages_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_pages_manager_description"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
  CREATE TABLE :table_pages_manager_description (
    pages_id int default(0) NOT NULL,
    pages_title varchar(255) NOT NULL,
    pages_html_text longtext,
    externallink varchar(255),
    language_id int default(1) NOT NULL,
    page_manager_head_title_tag varchar(255),
    page_manager_head_desc_tag varchar(255),
    page_manager_head_keywords_tag varchar(255),
  PRIMARY KEY pages_id (language_id),
  ADD KEY idx_pages_title (pages_title)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
    
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_page_manager_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD

CREATE TABLE IF NOT EXISTS :table_pages_manager_embedding (
    id SERIAL PRIMARY KEY,
    content TEXT DEFAULT NULL,
    type TEXT DEFAULT NULL,
    sourcetype TEXT DEFAULT 'manual',
    sourcename TEXT DEFAULT 'manual',
    embedding VECTOR(3072) NOT NULL,
    chunknumber INT DEFAULT 128,
    date_modified DATETIME DEFAULT NULL,
    entity_id INT,
    language_id INT 
);

CREATE VECTOR INDEX embedding_index ON :table_page_manager_embedding (embedding);
EOD;
       
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}