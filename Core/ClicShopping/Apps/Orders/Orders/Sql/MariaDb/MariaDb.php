<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  public function execute()
  {
    $CLICSHOPPING_Orders = Registry::get('Orders');
    $CLICSHOPPING_Orders->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
  }

/**
* @return void
 */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Orders = Registry::get('Orders');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_orders_orders']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 0,
        'link' => 'index.php?A&Orders\Orders&Orders',
        'image' => 'orders.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_orders_orders'
      ];

      $insert_sql_data = ['parent_id' => 4];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Orders->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      Cache::clear('menu-administrator');
    }


    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_orders_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
    CREATE TABLE IF NOT EXISTS clic_orders_embedding (
          id SERIAL PRIMARY KEY,
          content text DEFAULT NULL,
          type text DEFAULT NULL,
          sourcetype text default 'manual',
          sourcename text default 'manual',
          embedding vector(3072) NOT NULL,
          chunknumber int default(128),
          date_modified datetime DEFAULT NULL,
          entity_id INT
        );
        
      CREATE VECTOR INDEX embedding_index ON clic_orders_embedding (embedding);
   EOD;

      $CLICSHOPPING_Db->exec($sql);
}

  }
}