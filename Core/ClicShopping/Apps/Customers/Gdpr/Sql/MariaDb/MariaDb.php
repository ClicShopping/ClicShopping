<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary steps for initializing GDPR-related definitions
   * and configuring the administrative database menu during installation.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Gdpr = Registry::get('Gdpr');
    $CLICSHOPPING_Gdpr->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

  /**
   * Installs the GDPR menu item in the administrator's database menu if it does not already exist.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Gdpr = Registry::get('Gdpr');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Gdpr->db->get('administrator_menu', 'app_code', ['app_code' => 'app_customers_gdpr']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 8,
        'link' => 'index.php?A&Customers\Gdpr&Gdpr',
        'image' => 'gdpr.gif',
        'b2b_menu' => 1,
        'access' => 0,
        'app_code' => 'app_customers_gdpr'
      ];

      $insert_sql_data = ['parent_id' => 4];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Gdpr->db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Gdpr->db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Gdpr->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Gdpr->db->save('administrator_menu_description', $sql_data_array);
      }

      Cache::clear('menu-administrator');
    }
  }
}