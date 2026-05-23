<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the groups module.
   * This method loads the necessary definitions and initializes
   * the database setup for the administrative menu and module-specific data.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Groups = Registry::get('Groups');
    $CLICSHOPPING_Groups->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries for the administration menu related to customer groups.
   *
   * This method checks if the necessary entries for the "app_customers_groups" administration menu
   * already exist in the database. If not, it creates the appropriate records in both the
   * administrator menu table and its description table. Additionally, it clears the related cache
   * to reflect the updated menu.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Groups = Registry::get('Groups');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Groups->db->get('administrator_menu', 'app_code', ['app_code' => 'app_customers_groups']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 3,
        'link' => 'index.php?A&Customers\Groups&Groups',
        'image' => 'group_client.gif',
        'b2b_menu' => 1,
        'access' => 0,
        'app_code' => 'app_customers_groups'
      ];

      $insert_sql_data = ['parent_id' => 4];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Groups->db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Groups->db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Groups->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Groups->db->save('administrator_menu_description', $sql_data_array);
      }

      Cache::clear('menu-administrator');
    }
  }

  /**
   * Installs the necessary database tables for managing customer groups, group-to-category relationships,
   * and product group-specific configurations. Checks for the presence of each table and creates it
   * if it does not already exist.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_customers_groups"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_customers_groups (
customers_group_id int(11) NOT NULL auto_increment,
customers_group_name varchar(32) NOT NULL,
customers_group_discount decimal(11,2) default(0.00) NOT NULL,
color_bar varchar(8) default('#FFFFFF') NOT NULL,
group_order_taxe tinyint(1) default(0) NOT NULL,
group_payment_unallowed varchar(255) default('cc'),
group_shipping_unallowed varchar(255),
group_tax varchar(5) default('false') NOT NULL,
customers_group_quantity_default int(4) default(0) NOT NULL
PRIMARY KEY customers_group_id,
KEY idx_customers_group_name (customers_group_name)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_groups_to_categories"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_groups_to_categories (
customers_group_id int default(0) NOT NULL,
categories_id int default(0) NOT NULL,
discount decimal(11,2) default(0.00) NOT NULL
PRIMARY KEY customers_group_id (categories_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }


    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_groups"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_products_groups (
customers_group_id int default(0) NOT NULL,
customers_group_price decimal(15,4) default(0.0000) NOT NULL,
products_id int default(0) NOT NULL,
products_price decimal(15,4) default(0.0000) NOT NULL,
price_group_view char(1) default(1) NOT NULL,
products_group_view char(1) default(1) NOT NULL,
orders_group_view char(1) default(1) NOT NULL,
products_quantity_unit_id_group int(5) default(0) NOT NULL,
products_model_group varchar(255),
products_quantity_fixed_group int default(1) NOT NULL
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}