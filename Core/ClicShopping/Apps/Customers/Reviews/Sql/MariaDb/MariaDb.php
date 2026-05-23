<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary operations for initializing the Reviews module.
   *
   * This method retrieves the Reviews module from the registry,
   * loads its necessary definitions, and then calls the helper methods
   * to install the database menu administration and the database itself.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Reviews->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries for the administrator menu related to the Reviews module,
   * including menu items, descriptions, and links for different functionalities such as reviews,
   * sentiment analysis, and statistical voting. The function checks if the entries already exist
   * before proceeding with the installation. It also supports multiple languages by saving
   * menu descriptions for each available language.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Reviews->db->get('administrator_menu', 'app_code', ['app_code' => 'app_customers_reviews']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 6,
        'link' => '',
        'image' => 'reviews.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_customers_reviews',
        'status' => 1
      ];

      $insert_sql_data = ['parent_id' => 4];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Reviews->db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Reviews->db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Reviews->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Reviews->db->save('administrator_menu_description', $sql_data_array);

// reviews
        $sql_data_array = [
          'sort_order' => 6,
          'link' => 'index.php?A&Customers\\Reviews&Reviews',
          'image' => 'reviews.gif',
          'b2b_menu' => 0,
          'access' => 0,
          'app_code' => 'app_customers_reviews',
          'status' => 1
        ];

        $insert_sql_data = ['parent_id' => 587];
        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Reviews->db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Reviews->db->lastInsertId();
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          $language_id = $languages[$i]['id'];
          $sql_data_array = ['label' => $CLICSHOPPING_Reviews->getDef('title_menu')];

          $insert_sql_data = [
            'id' => (int)$id,
            'language_id' => (int)$language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_Reviews->db->save('administrator_menu_description', $sql_data_array);
        }

//sentiment
        $sql_data_array = [
          'sort_order' => 7,
          'link' => 'index.php?A&Customers\\Reviews&ReviewsSentiment',
          'image' => 'reviews.gif',
          'b2b_menu' => 0,
          'access' => 0,
          'app_code' => 'app_customers_reviews',
          'status' => 1
        ];

        $insert_sql_data = ['parent_id' => 587];
        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Reviews->db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Reviews->db->lastInsertId();
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          $language_id = $languages[$i]['id'];
          $sql_data_array = ['label' => $CLICSHOPPING_Reviews->getDef('title_menu_sentiment')];

          $insert_sql_data = [
            'id' => (int)$id,
            'language_id' => (int)$language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_Reviews->db->save('administrator_menu_description', $sql_data_array);
        }

//Statistics vote
        $sql_data_array = [
          'sort_order' => 4,
          'link' => 'index.php?A&Customers\\Reviews&StatsCustomersVote',
          'image' => 'reviews.gif',
          'b2b_menu' => 0,
          'access' => 0,
          'app_code' => 'app_customers_reviews',
          'status' => 1
        ];

        $insert_sql_data = ['parent_id' => 98];
        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Reviews->db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Reviews->db->lastInsertId();
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          $language_id = $languages[$i]['id'];
          $sql_data_array = ['label' => $CLICSHOPPING_Reviews->getDef('title_menu_statistics_vote')];

          $insert_sql_data = [
            'id' => (int)$id,
            'language_id' => (int)$language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_Reviews->db->save('administrator_menu_description', $sql_data_array);
        }
      }

      Cache::clear('menu-administrator');
    }
  }

  /**
   * Installs the necessary database tables for the Reviews module if they do not already exist.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    $Qcheck = $CLICSHOPPING_Reviews->db->query('show tables like ":table_reviews"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_reviews (
  reviews_id int(11) NOT NULL auto_increment,
  products_id int(11) NOT NULL,
  customers_id int(11) DEFAULT NULL,
  customers_name varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  reviews_rating int(1) DEFAULT NULL,
  date_added datetime DEFAULT NULL,
  last_modified datetime DEFAULT NULL,
  reviews_read int(5) NOT NULL DEFAULT (0),
  status tinyint(1) NOT NULL DEFAULT (0),
  customers_group_id int(11) NOT NULL DEFAULT (0),
  customers_tag varchar(255) DEFAULT NULL
  PRIMARY KEY reviews_id
  KEY idx_reviews_products_id products_id
      idx_reviews_customers_id customers_id
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Reviews->db->exec($sql);

      $Qcheck = $CLICSHOPPING_Reviews->db->query('show tables like ":table_reviews_description"');

      if ($Qcheck->fetch() === false) {
        $sql = <<<EOD
CREATE TABLE :table_reviews_description (
  reviews_id int(11) NOT NULL,
  languages_id int(11) NOT NULL,
  reviews_text text COLLATE utf8mb4_unicode_ci NOT NULL
  PRIMARY KEY (reviews_id, languages_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
        $CLICSHOPPING_Reviews->db->exec($sql);
      }
    }
  }
}