<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\Hooks\ClicShoppingAdmin\Manufacturers;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Suppliers\Suppliers as SuppliersApp;

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Suppliers application by checking the Registry for its existence.
   * If not found, it creates and registers a new instance of SuppliersApp.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Suppliers')) {
      Registry::set('Suppliers', new SuppliersApp());
    }

    $this->app = Registry::get('Suppliers');
  }

  /**
   * Processes the execution logic for updating the manufacturers table based on the supplied data.
   * Validates the application status and input parameters before performing update operations.
   *
   * @return bool Returns false if the application status indicates it is disabled; otherwise, nothing is explicitly returned.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Manufacturers'])) {
      $suppliers_id = HTML::sanitize($_POST['suppliers_id']);
      if (empty($suppliers_id)) $suppliers_id = 0;

      $sql_data_array = ['suppliers_id' => $suppliers_id];

      if (isset($_GET['mID'])) {
        $manufacturers_id = HTML::sanitize($_GET['mID']);
        $update_sql_data = ['manufacturers_id' => (int)$manufacturers_id];

      } else {
        $Qmanufacturers = $this->app->db->prepare('select manufacturers_id 
                                                     from :table_manufacturers
                                                     order by manufacturers_id desc
                                                     limit 1
                                                    ');
        $Qmanufacturers->execute();

        $update_sql_data = ['manufacturers_id' => $Qmanufacturers->valueInt('manufacturers_id')];
      }

      $sql_data_array = array_merge($sql_data_array, $update_sql_data);

      $this->app->db->save('manufacturers', $sql_data_array, $update_sql_data);
    }
  }
}