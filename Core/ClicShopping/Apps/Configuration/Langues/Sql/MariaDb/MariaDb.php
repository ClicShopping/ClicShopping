<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Langues\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the specified module.
   *
   * This method loads the required language definitions and calls the
   * function to install the menu administration component in the database.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Langues = Registry::get('Langues');
    $CLICSHOPPING_Langues->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

  /**
   * Installs the database menu administration entry for the application.
   *
   * Checks if the specific menu entry for the application does not already exist in the `administrator_menu` table.
   * If not, it inserts the menu data along with its associated descriptions for each language.
   * Clears the administrator menu cache after successful modification.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Langues = Registry::get('Langues');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_langues']);

    if ($Qcheck->fetch() === false) {

      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Configuration\Langues&Langues',
        'image' => 'languages.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_configuration_langues'
      ];

      $insert_sql_data = ['parent_id' => 20];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Langues->getDef('title_menu')];

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