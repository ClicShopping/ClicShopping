<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Suppliers\Classes\ClicShoppingAdmin\SupplierAdmin;
use ClicShopping\Apps\Catalog\Suppliers\Suppliers as SuppliersApp;
class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  protected $supplierAdmin;

  /**
   * Constructor method for initializing the Suppliers and SupplierAdmin components.
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
   * Executes the logic for linking a product and a supplier if the required parameters are provided.
   * Retrieves the latest product ID, determines the supplier ID based on the supplier name,
   * and updates the product record with the corresponding supplier information.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Insert'], $_GET['Products'])) {
      $Qproducts = $this->app->db->prepare('select products_id
                                              from :table_products
                                              order by products_id desc
                                               limit 1
                                              ');
      $Qproducts->execute();

      $id = $Qproducts->valueInt('products_id');

      $suppliers_id = $this->supplierAdmin->getSupplierId($_POST['suppliers_name']);

      $sql_data_array = ['suppliers_id' => (int)$suppliers_id];

      $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
    }
  }
}