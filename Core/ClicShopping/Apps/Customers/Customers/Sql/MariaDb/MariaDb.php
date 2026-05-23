<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the Customers module by loading
   * language definitions and performing database setup operations.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Customers = Registry::get('Customers');
    $CLICSHOPPING_Customers->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries for the administrator menu related to customer management.
   *
   * Checks if specific menu entries associated with customer-related functionality
   * already exist in the `administrator_menu` table. If not, adds the entries along
   * with their descriptions for all supported languages. Clears the administrator menu cache upon completion.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customers = Registry::get('Customers');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_customers_customers']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Customers\Customers&Customers',
        'image' => 'client.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_customers_customers'
      ];

      $insert_sql_data = ['parent_id' => 4];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Customers->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_report_stats_customers']);

      if ($Qcheck->fetch() === false) {
        $sql_data_array = ['sort_order' => 0,
          'link' => 'index.php?A&Customers\Customers&StatsCustomers',
          'image' => 'stats_customers.gif',
          'b2b_menu' => 0,
          'access' => 0,
          'app_code' => 'app_report_stats_customers'
        ];

        $insert_sql_data = ['parent_id' => 103];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Db->lastInsertId();
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          $language_id = $languages[$i]['id'];
          $sql_data_array = ['label' => $CLICSHOPPING_Customers->getDef('title_menu')];

          $insert_sql_data = [
            'id' => (int)$id,
            'language_id' => (int)$language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
        }
      }

      Cache::clear('menu-administrator');
    }
  }

  /**
   * Installs the database table for customers if it does not already exist.
   *
   * Checks the database for the presence of the customers table. If it does not exist,
   * the method creates the table with the specified schema, defining various customer-related
   * fields, constraints, and default values.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_customers"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_customers (
customers_id int NOT NULL auto_increment,
customers_company varchar(255),
customers_siret varchar(14),
customers_ape varchar(4),
customers_tva_intracom varchar(14),
customers_tva_intracom_code_iso char(2),
customers_gender char(1),
customers_firstname varchar(255) NOT NULL,
customers_lastname varchar(255) NOT NULL,
customers_dob datetime,
customers_email_address varchar(255) NOT NULL,
customers_default_address_id int,
customers_telephone varchar(255),
customers_password varchar(255) NOT NULL,
customers_newsletter char(1) default(0),
languages_id int default(1)  NOT NULL,
customers_group_id int default(0) NOT NULL,
member_level int(1) default(0) NOT NULL,
customers_options_order_taxe tinyint(1) default(0) NOT NULL,
customers_modify_company tinyint(1) default(1)  NOT NULL,
customers_modify_address_default tinyint(1) default(1) NOT NULL,
customers_add_address tinyint(1) default(1) NOT NULL,
customers_cellular_phone varchar(255) NULL,
customers_email_validation int(1) default(0) NOT NULL,
customer_discount decimal(4,2) default(0.00) NOT NULL,
client_computer_ip varchar(15) NULL,
provider_name_client varchar(64)  NULL,
customer_website_company varchar(64) NULL,
customer_guest_account tinyint(1) default(0) NOT NULL,
gdpr tinyint default(0) NOT NULL
PRIMARY KEY customers_id,
KEY idx_customers_email_address (idx_customers_email_address)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}