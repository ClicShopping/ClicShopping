<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\COD\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

/**
 * Class MariaDb
 *
 * Handles the execution process for the COD payment module, including installing
 * necessary administration menu entries in the database and clearing cache when required.
 */
class MariaDb
{
  /**
   * Executes the primary functionality of the method, which includes
   * loading the required definitions and installing the menu administration.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_COD = Registry::get('COD');
    $CLICSHOPPING_COD->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu administration entry for the Cash on Delivery (COD) payment module.
   * This includes adding a new menu entry to the database and its associated descriptions
   * for all available languages, if it does not already exist. Additionally, clears
   * the administrator menu cache after updates.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_COD = Registry::get('COD');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_payment_cod']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 4,
        'link' => 'index.php?A&Payment\COD&Configure',
        'image' => 'modules_payment.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_payment_cod'
      ];

      $insert_sql_data = ['parent_id' => 186];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_COD->getDef('title_menu')];

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