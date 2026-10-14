<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Suppliers\Classes\ClicShoppingAdmin\SupplierAdmin;
use ClicShopping\Apps\Catalog\Suppliers\Suppliers as SuppliersApp;

class Update implements HooksInterface
{
  public mixed $app;
  protected $supplierAdmin;

  /**
   * Initializes the Suppliers and SupplierAdmin components and registers them within the application registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Suppliers')) {
      Registry::set('Suppliers', new SuppliersApp());
    }

    $this->app = Registry::get('Suppliers');

    if (!Registry::exists('SupplierAdmin')) {
      Registry::set('SupplierAdmin', new SupplierAdmin());
    }

    $this->supplierAdmin = Registry::get('SupplierAdmin');
  }

  /**
   * Executes the method functionality for the Suppliers module.
   *
   * @return bool Returns false if the application supplier status is not defined or is set to 'False'.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Update'], $_GET['Products'])) {
      $id = HTML::sanitize($_GET['pID']);

      $suppliers_id = $this->supplierAdmin->getSupplierId($_POST['suppliers_name']);

      $sql_data_array = ['suppliers_id' => (int)$suppliers_id];

      $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
    }
  }
}