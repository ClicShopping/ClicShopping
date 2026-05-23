<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary actions to initialize the TemplateEmail definitions and database configurations.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_TemplateEmail = Registry::get('TemplateEmail');
    $CLICSHOPPING_TemplateEmail->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database menu entry for the administration panel related to the Template Email configuration.
   *
   * Checks if the 'app_configuration_template_email' menu entry already exists in the administrator menu.
   * If the entry does not exist, it creates a new menu entry along with its associated descriptions
   * for all available languages. Clears the cache for the administrator menu after installation.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_TemplateEmail = Registry::get('TemplateEmail');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_template_email']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 3,
        'link' => 'index.php?A&Configuration\TemplateEmail&TemplateEmail',
        'image' => 'mail.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_configuration_template_email'
      ];

      $insert_sql_data = ['parent_id' => 20];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_TemplateEmail->getDef('title_menu')];

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
   * Creates the necessary database tables for the template email functionality if they do not already exist.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_template_email"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_template_email (
  template_email_id int NOT NULL auto_increment,
  template_email_variable varchar(250) NOT NULL,
  customers_group_id int(2) default(0) NOT NULL,
  template_email_type smallint(1) default(0) NOT NULL
  PRIMARY KEY (template_email_id),
  KEY idx_template_email_id (template_email_id)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_template_email_description"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_template_email_description (
  template_email_id int NOT NULL,
  language_id int NOT NULL,
  template_email_name varchar(250),
  template_email_short_description varchar(250),
  template_email_description longtext
  PRIMARY KEY (template_email_id) (language_id),
  KEY idx_template_email_name (idx_template_email_name)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}