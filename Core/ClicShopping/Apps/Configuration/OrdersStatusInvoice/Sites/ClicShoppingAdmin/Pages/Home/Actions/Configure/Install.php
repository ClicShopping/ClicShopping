<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatusInvoice\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

/**
 * Install action for Sites module configuration.
 * Handles the Install process with centralized functionality.
 */
class Install extends \ClicShopping\OM\Domains\ConfigureActionsAbstract
{

    /**
   * Execute the installation process for Sites module
   */
  public function execute()
  {
    $this->init();
    
    $current_module = $this->getCurrentModule();
    
    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/install');
    
    $m = $this->getConfigModule($current_module);
    $m->install();
    
    // Install database menu if method exists
    if (method_exists($this, 'installDbMenuAdministration')) {
      self::installDbMenuAdministration();
    }
    
    self::installProductsOrdersStatusInvoiceDb();
	
    $this->addSuccessMessage($this->app->getDef('alert_module_install_success'));
    $this->redirectToConfigure($current_module);
  }

  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_OrdersStatusInvoice = Registry::get('OrdersStatusInvoice');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_orders_status_invoice']);

    if ($Qcheck->fetch() === false) {

      $sql_data_array = ['sort_order' => 7,
        'link' => 'index.php?A&Configuration\OrdersStatusInvoice&OrdersStatusInvoice',
        'image' => 'configuration_26.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_configuration_orders_status_invoice'
      ];

      $insert_sql_data = ['parent_id' => 14];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {

        $language_id = $languages[$i]['id'];

        $sql_data_array = ['label' => $CLICSHOPPING_OrdersStatusInvoice->getDef('title_menu')];

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


  private static function installProductsOrdersStatusInvoiceDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_orders_status_invoice"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_orders_status_invoice (
  orders_status_invoice_id int default(0) NOT NULL,
  language_id int default(1) NOT NULL,
  orders_status_invoice_name varchar(32) NOT NULL
  PRIMARY KEY (orders_status_invoice_id) language_id,
  KEY idx_orders_status_invoice_name (orders_status_invoice_name)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}
