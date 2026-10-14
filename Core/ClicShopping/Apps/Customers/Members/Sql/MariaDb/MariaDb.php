<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Members\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the Members module by loading
   * necessary definitions and initializing database menu administration setup.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Members = Registry::get('Members');
    $CLICSHOPPING_Members->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

  /**
   * Installs the database entries necessary for the administration menu related to customer members.
   *
   * This method checks whether the 'app_customers_members' entry exists in the 'administrator_menu' table.
   * If not, it creates the necessary entry with its translations and updates the cache for the administrator menu.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Members = Registry::get('Members');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Members->db->get('administrator_menu', 'app_code', ['app_code' => 'app_customers_members']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 4,
        'link' => 'index.php?A&Customers\Members&Members',
        'image' => 'client_attente.gif',
        'b2b_menu' => 1,
        'access' => 0,
        'app_code' => 'app_customers_members'
      ];

      $insert_sql_data = ['parent_id' => 4];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Members->db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Members->db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Members->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Members->db->save('administrator_menu_description', $sql_data_array);
      }

      Cache::clear('menu-administrator');
    }
  }
}