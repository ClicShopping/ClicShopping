<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for managing database menu administration entries
   * and loads the necessary definitions for the Products module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');
    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs and configures the database entries for administration menu items
   * related to catalog products and associated reports.
   *
   * Verifies if specific menu items and their descriptions exist in the database.
   * If they do not exist, it inserts them along with their necessary metadata
   * in different languages, establishing the hierarchical position and attributes
   * for administrative purposes.
   *
   * Clears the administrator menu cache after updating the database.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Products = Registry::get('Products');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_catalog_products']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Catalog\Products&Products',
        'image' => 'priceupdate.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_catalog_products'
      ];

      $insert_sql_data = ['parent_id' => 3];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Products->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_report_stats_products_viewed']);

      if ($Qcheck->fetch() === false) {
        $sql_data_array =
          ['sort_order' => 5,
          'link' => 'index.php?A&Catalog\Products&StatsProductsViewed',
          'image' => 'stats_products_viewed.gif',
          'b2b_menu' => 0,
          'access' => 0,
          'app_code' => 'app_report_stats_products_viewed'
        ];

        $insert_sql_data = ['parent_id' => 98];
        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Db->lastInsertId();
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          $language_id = $languages[$i]['id'];
          $sql_data_array = ['label' => $CLICSHOPPING_Products->getDef('title_menu')];

          $insert_sql_data = [
            'id' => (int)$id,
            'language_id' => (int)$language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
        }
      }

      $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_report_stats_low_stock']);

      if ($Qcheck->fetch() === false) {
        $sql_data_array =
          ['sort_order' => 5,
          'link' => 'index.php?A&Catalog\Products&StatsProductsLowStock',
          'image' => 'stats_customers.gif',
          'b2b_menu' => 0,
          'access' => 0,
          'app_code' => 'app_report_stats_low_stock'
        ];

        $insert_sql_data = ['parent_id' => 107];
        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Db->lastInsertId();
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          $language_id = $languages[$i]['id'];
          $sql_data_array = ['label' => $CLICSHOPPING_Products->getDef('title_menu')];

          $insert_sql_data = [
            'id' => (int)$id,
            'language_id' => (int)$language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
        }
      }

      $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_report_stats_products_expected']);

      if ($Qcheck->fetch() === false) {
        $sql_data_array =
          ['sort_order' => 5,
          'link' => 'index.php?A&Catalog\Products&StatsProductsExpected',
          'image' => 'products_expected.gif',
          'b2b_menu' => 0,
          'access' => 0,
          'app_code' => 'app_report_stats_products_expected'
        ];

        $insert_sql_data = ['parent_id' => 107];
        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Db->lastInsertId();
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          $language_id = $languages[$i]['id'];

          $sql_data_array = ['label' => $CLICSHOPPING_Products->getDef('title_menu')];

          $insert_sql_data = [
            'id' => (int)$id,
            'language_id' => (int)$language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
        }
      }

      $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_report_stats_products_purchased']);

      if ($Qcheck->fetch() === false) {
        $sql_data_array = [
          'sort_order' => 5,
          'link' => 'index.php?A&Catalog\Products&StatsProductsPurchased',
          'image' => 'stats_products_purchased.gif',
          'b2b_menu' => 0,
          'access' => 0,
          'app_code' => 'app_report_stats_products_purchased'
        ];

        $insert_sql_data = ['parent_id' => 98];
        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Db->lastInsertId();
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          $language_id = $languages[$i]['id'];
          $sql_data_array = ['label' => $CLICSHOPPING_Products->getDef('title_menu')];

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

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
       CREATE TABLE IF NOT EXISTS :table_products_embedding (
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

        CREATE VECTOR INDEX embedding_index ON :table_products_embedding (embedding);
      EOD;

      $CLICSHOPPING_Db->exec($sql);
    }
  }
}