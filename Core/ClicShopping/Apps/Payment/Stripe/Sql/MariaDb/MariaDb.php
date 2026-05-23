<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;
/**
 * Class MariaDb
 *
 * This class handles the setup and management of Stripe integration within the
 * ClicShopping application, specifically to install and configure the administration menu
 * for the Stripe module.
 */
class MariaDb
{
  /**
   * Executes the installation process for the Stripe module within the ClicShopping Admin interface.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Stripe = Registry::get('Stripe');
    $CLICSHOPPING_Stripe->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the Stripe module menu into the administrator menu system of the ClicShopping Admin interface.
   * This includes adding the necessary menu entry and associated descriptions for all available languages.
   * Clears the cached administrator menu data upon successful insertion.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Stripe = Registry::get('Stripe');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_payment_stripe']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 4,
        'link' => 'index.php?A&Payment\Stripe&Configure',
        'image' => 'modules_payment.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_payment_stripe'
      ];

      $insert_sql_data = ['parent_id' => 186];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Stripe->getDef('title_menu')];

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