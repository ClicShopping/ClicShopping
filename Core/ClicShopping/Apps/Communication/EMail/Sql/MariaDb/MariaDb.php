<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Email\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process by loading the necessary email definitions
   * and installing the database menu administration data.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Email = Registry::get('Email');
    $CLICSHOPPING_Email->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

  /**
   * Installs a database entry for the email administration menu in the administrator menu table.
   *
   * This method checks whether the necessary entry for the email administration module exists in
   * the `administrator_menu` table. If not, it adds the required entry with details like
   * sort order, link, image, and application code. It also inserts corresponding multilingual
   * descriptions into the `administrator_menu_description` table for all available languages.
   * Finally, it clears the menu administrator cache.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_EMail = Registry::get('EMail');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_communication_email']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 6,
        'link' => 'index.php?A&Communication\EMail&EMail',
        'image' => 'email.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_communication_email'
      ];

      $insert_sql_data = ['parent_id' => 6];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_EMail->getDef('title_menu')];

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
}