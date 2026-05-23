<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the administrator menu and database setup.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');
    $CLICSHOPPING_AdministratorMenu->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the administrator menu, including database entries and language-specific descriptions.
   *
   * This method checks whether the specified administrator menu entry already exists in the database.
   * If it does not, it inserts the necessary data into the `administrator_menu` and `administrator_menu_description`
   * tables. Additionally, it clears the cache for the administrator menu to ensure the new menu is properly loaded.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_administrator_menu']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Tools\AdministratorMenu&AdministratorMenu',
        'image' => 'menu.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_configuration_administrator_menu'
      ];

      $insert_sql_data = ['parent_id' => 170];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_AdministratorMenu->getDef('title_menu')];

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
   * Installs the necessary database tables for the administrator menu if they do not already exist.
   *
   * @return void
   */
  private static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_administrator_menu"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_administrator_menu (
  administrator_menu_id int NOT NULL auto_increment,
  administrator_menu_variable varchar(250) NOT NULL,
  customers_group_id int(2) default(0) NOT NULL,
  administrator_menu_type smallint(1) default(0) NOT NULL,
  status tinyInt default(1) NOT NULL
  PRIMARY KEY (administrator_menu_id),
  KEY idx_administrator_menu_id (administrator_menu_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_administrator_menu_description"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_administrator_menu_description (
  administrator_menu_id int NOT NULL,
  language_id int NOT NULL,
  administrator_menu_name varchar(250),
  administrator_menu_short_description varchar(250),
  administrator_menu_description longtext
  PRIMARY KEY (administrator_menu_id) (language_id),
  KEY idx_administrator_menu_name (idx_administrator_menu_name)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}