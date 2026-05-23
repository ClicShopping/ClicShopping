<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Suppliers\Suppliers as SuppliersApp;

class CopyConfirm implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Suppliers application and ensures it is registered in the Registry.
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
   * Executes the function to handle product supplier association based on the provided parameters.
   *
   * @return bool Returns false if the supplier feature is disabled or required data is missing. Returns void otherwise.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      return false;
    }

    if (isset($_POST['products_id']) && isset($_GET['Products'])) {
      $current_products_id = HTML::sanitize($_POST['products_id']);

      if (isset($current_products_id, $_GET['CopyConfirm'])) {
        $Qsuppliers = $this->app->db->prepare('select suppliers_id
                                                 from :table_products
                                                 where products_id = :products_id
                                                ');
        $Qsuppliers->bindInt(':products_id', $current_products_id);
        $Qsuppliers->execute();

        $suppliers_id = $Qsuppliers->valueInt('suppliers_id');

        $Qproducts = $this->app->db->prepare('select products_id 
                                                from :table_products                                            
                                                order by products_id desc
                                                limit 1 
                                               ');
        $Qproducts->execute();

        $id = $Qproducts->valueInt('products_id');

        $sql_data_array = ['suppliers_id' => (int)$suppliers_id];

        $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
      }
    }
  }
}