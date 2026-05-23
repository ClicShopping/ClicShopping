<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatus\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the application.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_OrdersStatus = Registry::get('OrdersStatus');
    $CLICSHOPPING_OrdersStatus->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries required for the administrator menu related to order statuses.
   *
   * This method checks if the specified application code exists in the administrator menu database table.
   * If it does not exist, it will insert a new entry and its corresponding descriptions for multiple languages.
   * The cache for the administrator menu is cleared after the entries have been created.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_OrdersStatus = Registry::get('OrdersStatus');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_orders_status']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 6,
        'link' => 'index.php?A&Configuration\OrdersStatus&OrdersStatus',
        'image' => 'order_status.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_configuration_orders_status'
      ];

      $insert_sql_data = ['parent_id' => 14];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);
      $id = $CLICSHOPPING_Db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_OrdersStatus->getDef('title_menu')];

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
   * Installs the database table for the orders status if it does not already exist.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_orders_status"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_orders_status (
  orders_status_id int default(0) NOT NULL,
  language_id int default(1) NOT NULL,
  orders_status_name varchar(255) NOT NULL,
  public_flag tinyint(1) default(1),
  downloads_flag tinyint(1) default(0),
  support_orders_flag int(1) default(0),
  authorize_to_delete_order tinyint(1) default(1)  
  PRIMARY KEY (orders_status_id) language_id,
  KEY idx_orders_status_name (orders_status_name)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}