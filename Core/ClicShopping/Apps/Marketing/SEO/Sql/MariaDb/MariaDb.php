<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Marketing\SEO\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary operations to load SEO definitions and install database components
   * required for the administration menu and other functionalities.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_SEO = Registry::get('SEO');
    $CLICSHOPPING_SEO->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the administration menu entry for the SEO application.
   *
   * This method checks whether the administration menu entry for the SEO application
   * already exists in the database. If not, it creates the necessary entry in the
   * `administrator_menu` table and its descriptions in the `administrator_menu_description`
   * table for all available languages. It also clears the administrator menu cache
   * to ensure the modifications take effect.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_SEO = Registry::get('SEO');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_marketing_seo']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 7,
        'link' => 'index.php?A&Marketing\SEO&SEO',
        'image' => 'referencement.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_marketing_seo'
      ];

      $insert_sql_data = ['parent_id' => 5];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_SEO->getDef('title_menu')];

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
   * Installs the database structure required for the SEO module if it does not already exist.
   *
   * This method checks if the SEO-related database table exists and, if not, creates the necessary table
   * with predefined columns to store SEO configuration and data.
   *
   * @return void
   */
  private static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_seo"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_seo (
seo_id int NOT NULL default(1),
language_id int NOT NULL default(1),
seo_defaut_language_title varchar(255),
seo_defaut_language_keywords text,
seo_defaut_language_description varchar(255),
seo_defaut_language_footer text,
seo_language_products_info_title varchar(255),
seo_language_products_info_keywords text,
seo_language_products_info_description varchar(255),
seo_language_products_new_title varchar(255),
seo_language_products_new_keywords text,
seo_language_products_new_description varchar(255),
seo_language_special_title varchar(255),
seo_language_special_keywords text,
seo_language_special_description varchar(255),
seo_language_reviews_title varchar(255),
seo_language_reviews_keywords text,
seo_language_reviews_description varchar(255),
seo_language_favorites_title varchar(255),
seo_language_favorites_keywords text,
seo_language_favorites_description varchar(255),
seo_language_featured_title varchar(255),
seo_language_featured_keywords text,
seo_language_featured_description varchar(255),
seo_language_recommendations_title varchar(255),
seo_language_recommendations_keywords text,
seo_language_recommendations_description varchar(255),
  PRIMARY KEY (seo_id, language_id),
  KEY idx_seo_seo_id (seo_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}