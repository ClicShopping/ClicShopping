<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  public function execute()
  {
    $CLICSHOPPING_Specials = Registry::get('Specials');
    $CLICSHOPPING_Specials->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs a database menu for administration by adding necessary entries into
   * the 'administrator_menu' and 'administrator_menu_description' tables. It checks
   * if the specified menu entry already exists and inserts it if absent. Also updates
   * the cache after making changes.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Specials = Registry::get('Specials');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_marketing_specials']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Marketing\Specials&Specials',
        'image' => 'specials.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_specials'
      ];

      $insert_sql_data = ['parent_id' => 5];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Specials->getDef('title_menu')];

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
   * Installs the necessary database table for managing specials.
   *
   * This method checks if the specials table exists in the database.
   * If it does not exist, it creates the table with the necessary schema,
   * including fields for product information, pricing, dates, and status.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_specials"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_specials (
  specials_id int(11) NOT NULL,
  products_id int NOT NULL,
  specials_new_products_price decimal(15,4) not null,
  specials_date_added datetime,
  specials_last_modified datetime,
  expires_date datetime,
  status int(1) default(1) NOT NULL,
  scheduled_date datetime,
  customers_group_id int NOT NULL default(0),
  flash_discount int(1) NOT NULL  default(0)
  PRIMARY KEY (specials_id),
  KEY idx_specials_products_id (products_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}