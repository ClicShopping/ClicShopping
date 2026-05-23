<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Report\StatsProductsNotification\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the main logic for registering and installing
   * menu configurations and loading necessary definitions
   * for the StatsProductsNotification component.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_StatsProductsNotification = Registry::get('StatsProductsNotification');
    $CLICSHOPPING_StatsProductsNotification->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs a menu item in the administrator menu for the Stats Products Notification application.
   * The method checks if the menu item already exists, and if not, it inserts the menu data and associated language descriptions
   * into the database. It also clears the administrator menu cache after successful insertion.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_StatsProductsNotification = Registry::get('StatsProductsNotification');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_report_stats_products_notification']);

    if ($Qcheck->fetch() === false) {

      $sql_data_array = [
        'sort_order' => 5,
        'link' => 'index.php?A&Report\StatsProductsNotification&StatsProductsNotification',
        'image' => 'client.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_report_stats_products_notification'
      ];

      $insert_sql_data = ['parent_id' => 107];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_StatsProductsNotification->getDef('title_menu')];

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