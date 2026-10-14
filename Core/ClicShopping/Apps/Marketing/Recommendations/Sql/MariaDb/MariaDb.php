<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendation\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary steps to initialize the Recommendation module installation.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Recommendation = Registry::get('Recommendation');
    $CLICSHOPPING_Recommendation->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries required for the administration menu of the Recommendations module.
   *
   * This method performs the following operations:
   * - Checks if the required entries already exist in the `administrator_menu` table.
   * - Inserts the necessary menu entries into the `administrator_menu` table if not present.
   * - Associates the menu entries with language-specific labels in the `administrator_menu_description` table.
   * - Clears the cache for the administrator menu to ensure the changes take effect immediately.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Recommendations = Registry::get('Recommendations');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_marketing_recommendations']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Marketing\Recommendations&Recommendations',
        'image' => 'products_recommendations.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_recommendations'
      ];

      $insert_sql_data = ['parent_id' => 107];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Recommendations->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Configuration\Modules&Modules&set=modules_products_recommendations',
        'image' => 'products_recommendations.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_recommendations'
      ];

      $insert_sql_data = ['parent_id' => 117];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Recommendations->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Marketing\Recommendations&ProductsRecommendation',
        'image' => 'products_recommendations.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_recommendations'
      ];

      $insert_sql_data = ['parent_id' => 5];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Recommendations->getDef('title_menu')];

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
   * Installs the necessary database tables for the product recommendations module.
   *
   * @return void
   */
  private static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_recommendations"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_products_recommendations (
  id INT(11) NOT NULL AUTO_INCREMENT,
  customers_id int(11) NOT NULL,
  products_id int(11) NOT NULL,
  score float DEFAULT NULL,
  recommendation_date date DEFAULT NULL,
  products_tag varchar(255) DEFAULT NULL,
  customers_group_id int(11) default (0) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE :table_products_recommendations_to_categories (
  id INT(11) NOT NULL AUTO_INCREMENT,
  products_id INT(11) NOT NULL,
  categories_id INT(11) NOT NULL,
  PRIMARY KEY (id),
  INDEX idx_products_id (products_id),
  INDEX idx_categories_id (categories_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}