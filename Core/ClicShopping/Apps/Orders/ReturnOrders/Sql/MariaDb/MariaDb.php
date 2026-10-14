<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the necessary installation procedures for the ReturnOrders module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
    $CLICSHOPPING_ReturnOrders->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the menu structure for the return orders administration section.
   * This includes adding entries to the administrator menu and menu descriptions
   * for different languages. Additionally, clears the administrator menu cache
   * after the menu setup is complete.
   *
   * @return void
   */
  public static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_orders_return_orders']);

    if ($Qcheck->fetch() === false) {
      $insert_sql_data = ['parent_id' => 4];
      $sql_data_array = [
        'sort_order' => 2,
        'link' => 'index.php?A&Orders\ReturnOrders&ReturnOrders',
        'image' => 'return_orders.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_orders_return_orders'
      ];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_ReturnOrders->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

// 2nd menu
      $insert_sql_data = ['parent_id' => 14];

      $sql_data_array = [
        'sort_order' => 2,
        'link' => 'index.php?A&Orders\\ReturnOrders&Configure',
        'image' => 'return_orders.png',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_orders_return_orders'
      ];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_ReturnOrders->getDef('title_menu_return_orders_status')];

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
   * Installs the database structure required for managing return orders.
   *
   * Creates the necessary tables if they do not already exist, including:
   * - return_orders
   * - return_orders_history
   * - return_orders_reason
   * - return_orders_status
   * - return_orders_action
   *
   * Inserts default data for some of the tables such as return reasons, statuses, and actions
   * to ensure proper functionality of the return order system.
   *
   * @return void
   */
  public static function installDb(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_return_orders"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_return_orders (
  return_id int(11) NOT NULL AUTO_INCREMENT,
  return_ref varchar(32) NOT NULL,
  order_id int(11) NOT NULL,
  product_id int(11) NOT NULL,
  customer_id int(11) NOT NULL,
  customer_firstname varchar(32) NOT NULL,
  customer_lastname varchar(32) NOT NULL,
  customer_email varchar(96) NOT NULL,
  customer_telephone varchar(32) NOT NULL,
  product_name varchar(255) NOT NULL,
  product_model varchar(64) NOT NULL,
  quantity int(4) NOT NULL,
  opened tinyint(1) NOT NULL,
  return_reason_id int(11) NOT NULL,
  return_action_id int(11) NOT NULL,
  return_status_id int(11) NOT NULL,
  comment text NOT NULL,
  date_ordered date NOT NULL,
  date_added datetime NOT NULL,
  date_modified datetime NOT NULL,
  archive tinyint(1) Default(0) NOT NULL,
  PRIMARY KEY (return_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_return_orders_history"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_return_orders_history (
  return_history_id int(11) NOT NULL AUTO_INCREMENT,
  return_id int(11) NOT NULL,
  return_status_id int(11) NOT NULL,
  notify tinyint(1) NOT NULL,
  comment text NOT NULL,
  date_added datetime NOT NULL,
  admin_user_name varchar(64) default NULL,
  PRIMARY KEY (return_history_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_return_orders_reason"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_return_orders_reason (
  return_reason_id int(11) NOT NULL,
  language_id int(11) NOT NULL DEFAULT (0),
  name varchar(128) NOT NULL,
  PRIMARY KEY (return_reason_id,language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

INSERT INTO :table_return_orders_reason VALUES(1, 1, 'Non-compliant package');
INSERT INTO :table_return_orders_reason VALUES(2, 1, 'Received Wrong Item');
INSERT INTO :table_return_orders_reason VALUES(3, 1, 'Order Error');
INSERT INTO :table_return_orders_reason VALUES(4, 1, 'Do not meet my expectations');
INSERT INTO :table_return_orders_reason VALUES(5, 1, 'Others');

INSERT INTO :table_return_orders_reason VALUES(1, 2, 'Colis non conforme');
INSERT INTO :table_return_orders_reason VALUES(2, 2, 'Mauvais produit reçu');
INSERT INTO :table_return_orders_reason VALUES(3, 2, 'Erreur commande');
INSERT INTO :table_return_orders_reason VALUES(4, 2, 'Ne répond pas à mes attentes');
INSERT INTO :table_return_orders_reason VALUES(5, 2, 'Autres');

ALTER TABLE :table_return_orders_reason MODIFY return_reason_id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
EOD;

      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_return_orders_status"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_return_orders_status (
  return_status_id int(11) NOT NULL,
  language_id int(11) NOT NULL DEFAULT (0),
  name varchar(32) NOT NULL,
  PRIMARY KEY (return_status_id,language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;


INSERT INTO :table_return_orders_status VALUES(1, 1, 'Pending');
INSERT INTO :table_return_orders_status VALUES(2, 1, 'Awaiting Products');
INSERT INTO :table_return_orders_status VALUES(3, 1, 'Complete');

INSERT INTO :table_return_orders_status VALUES(1, 2, 'En attente');
INSERT INTO :table_return_orders_status VALUES(2, 2, 'en attente du produit');
INSERT INTO :table_return_orders_status VALUES(3, 2, 'Complété');

ALTER TABLE :table_return_orders_status MODIFY return_status_id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_return_orders_action"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_return_orders_action (
  return_action_id int(11) NOT NULL,
  language_id int(11) NOT NULL DEFAULT (0),
  name varchar(64) NOT NULL,
  PRIMARY KEY (return_action_id,language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

INSERT INTO :table_return_orders_action VALUES(1, 1, 'no action');
INSERT INTO :table_return_orders_action VALUES(2, 1, 'Refunded');
INSERT INTO :table_return_orders_action VALUES(3, 1, 'Credit Issued');
INSERT INTO :table_return_orders_action VALUES(4, 1, 'Replacement Sent');

INSERT INTO :table_return_orders_action VALUES(1, 2, 'Aucune action');
INSERT INTO :table_return_orders_action VALUES(2, 2, 'Remboursé');
INSERT INTO :table_return_orders_action VALUES(3, 2, 'Problème crédit');
INSERT INTO :table_return_orders_action VALUES(4, 2, 'Remplacement envoyé');

ALTER TABLE :table_return_orders_action MODIFY return_action_id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}