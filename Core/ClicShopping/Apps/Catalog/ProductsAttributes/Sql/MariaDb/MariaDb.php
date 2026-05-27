<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the ProductsAttributes component.
   * Loads necessary definitions and performs database setup.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');
    $CLICSHOPPING_ProductsAttributes->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries for the administration menu specific to the Products Attributes application.
   *
   * This method checks if the menu entry for the Products Attributes application already exists in the
   * administrator_menu table. If it does not exist, it adds the entry with relevant details, including sort order,
   * link, image, menu visibility, access level, and application code. Additionally, it inserts entries for each
   * language in the administrator_menu_description table, associating a label with the menu entry.
   * After the insertion of new entries, it clears the cache for the administrator menu.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_catalog_products_attributes']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 7,
        'link' => 'index.php?A&Catalog\ProductsAttributes&ProductsAttributes',
        'image' => 'products_option.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_catalog_products_attributes'
      ];

      $insert_sql_data = ['parent_id' => 3];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_ProductsAttributes->getDef('title_menu')];

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
* @return void
 */
  private static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcheckField = $CLICSHOPPING_Db->query("show columns from :table_products_attributes like 'status'");

    if ($QcheckField->fetch() === false) {
      $sql = <<<EOD
ALTER TABLE :table_products_attributes ADD status TINYINT(1) NOT NULL DEFAULT '1' AFTER `products_attributes_image`;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    self::installBridgeUniqueKey();
  }

  /**
   * Adds a UNIQUE key on (products_options_id, products_options_values_id)
   * for the bridge table so duplicate option-value pairings cannot be
   * inserted (legacy AddProductOptionValues could create them on
   * double-submit). Pre-existing duplicates are pruned before the ALTER
   * since MariaDB rejects UNIQUE creation on a table that already has
   * collisions. Idempotent: skips if the index already exists.
   */
  private static function installBridgeUniqueKey(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcheckIndex = $CLICSHOPPING_Db->query("show index from :table_products_options_values_to_products_options where Key_name = 'unique_option_value'");

    if ($QcheckIndex->fetch() !== false) {
      return;
    }

    $cleanup = <<<EOD
DELETE pov2po1
  FROM :table_products_options_values_to_products_options pov2po1
  INNER JOIN :table_products_options_values_to_products_options pov2po2
          ON pov2po1.products_options_id = pov2po2.products_options_id
         AND pov2po1.products_options_values_id = pov2po2.products_options_values_id
         AND pov2po1.products_options_values_to_products_options_id > pov2po2.products_options_values_to_products_options_id
EOD;
    $CLICSHOPPING_Db->exec($cleanup);

    $alter = <<<EOD
ALTER TABLE :table_products_options_values_to_products_options
  ADD UNIQUE KEY unique_option_value (products_options_id, products_options_values_id)
EOD;
    $CLICSHOPPING_Db->exec($alter);
  }
}