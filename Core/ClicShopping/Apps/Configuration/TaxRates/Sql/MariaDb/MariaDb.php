<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxRates\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary methods to initialize and install database-related configurations.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_TaxRates = Registry::get('TaxRates');
    $CLICSHOPPING_TaxRates->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries required for the administration menu related to tax rates configuration.
   *
   * This method checks if the necessary entries for the tax rates administration menu
   * already exist in the database. If they do not exist, it inserts the required data
   * into the `administrator_menu` and `administrator_menu_description` tables, ensuring
   * the menu item is properly configured for all supported languages. It also clears
   * the administrator menu cache after the installation process.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_TaxRates = Registry::get('TaxRates');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_tax_rates']);

    if ($Qcheck->fetch() === false) {

      $sql_data_array = ['sort_order' => 4,
        'link' => 'index.php?A&Configuration\TaxRates&TaxRates',
        'image' => 'tax_rates.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_configuration_tax_rates'
      ];

      $insert_sql_data = ['parent_id' => 19];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_TaxRates->getDef('title_menu')];

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
   * Installs the database table for tax rates if it does not already exist.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_tax_rates"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_tax_rates (
  tax_rates_id int NOT NULL auto_increment,
  tax_zone_id int NOT NULL,
  tax_class_id int NOT NULL,
  tax_priority int(5) default(1),
  tax_rate decimal(7,4) NOT NULL,
  tax_description varchar(255) NOT NULL,
  last_modified datetime,
  date_added datetime NOT NULL,
  code_tax_erp varchar(15) null
  PRIMARY KEY tax_rates_id
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}