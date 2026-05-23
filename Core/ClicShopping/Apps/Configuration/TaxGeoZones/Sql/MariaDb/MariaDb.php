<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxGeoZones\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary operations for setting up the Tax Geo Zones module.
   *
   * This method handles loading the required definitions and
   * installing the database menu administration and schema.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_TaxGeoZones = Registry::get('TaxGeoZones');
    $CLICSHOPPING_TaxGeoZones->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database menu entry for the TaxGeoZones administration module, if it does not already exist.
   *
   * This method checks for the existence of a specific entry under the administrator menu table using the app_code.
   * If not found, it inserts a new entry into the administrator menu table with the appropriate configuration,
   * including a default link, image, sort order, and other attributes. Once the menu entry is added, it
   * creates corresponding language-specific labels in the administrator_menu_description table for all
   * available languages. Finally, it clears the administrator menu cache to ensure the changes are reflected immediately.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_TaxGeoZones = Registry::get('TaxGeoZones');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_tax_geo_zones']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 5,
        'link' => 'index.php?A&Configuration\TaxGeoZones&TaxGeoZones',
        'image' => 'geo_zones.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_configuration_tax_geo_zones'
      ];

      $insert_sql_data = ['parent_id' => 19];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_TaxGeoZones->getDef('title_menu')];

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
   * Installs the necessary database tables for the geo zone and zones-to-geo-zones features.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_geo_zones"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_geo_zones (
   geo_zone_id int NOT NULL auto_increment,
  geo_zone_name varchar(255) NOT NULL,
  geo_zone_description varchar(255) NOT NULL,
  last_modified datetime,
  date_added datetime NOT NULL
  PRIMARY KEY geo_zone_id
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_zones_to_geo_zones"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_zones_to_geo_zones (
  association_id int NOT NULL auto_increment,
  zone_country_id int NOT NULL,
  zone_id int,
  geo_zone_id int,
  last_modified datetime,
  date_added datetime NOT NULL
  PRIMARY KEY association_id,
  idx_zones_to_geo_zones_country_id (zone_country_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}