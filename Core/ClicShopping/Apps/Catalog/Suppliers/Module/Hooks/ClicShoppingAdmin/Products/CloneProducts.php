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

use ClicShopping\Apps\Catalog\Suppliers\Suppliers as SuppliersApp;

class CloneProducts implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Suppliers application by checking if it exists in the registry.
   * If not, it creates a new instance and stores it in the registry.
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
   * Executes the logic for handling the suppliers' application state and cloning product information.
   *
   * @return mixed Returns false if the suppliers' application status is disabled or undefined. Otherwise, no explicit return value is provided.
   */
  public function execute(array $parameters): mixed
  {
    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Update'], $_POST['clone_categories_id_to'])) {
      $clone_products_id = $parameters['products_id'] ?? null;

      $Qproducts = $this->app->db->prepare('select *
                                            from :table_products
                                            where products_id = :products_id
                                           ');
      $Qproducts->bindInt(':products_id', $_GET['pID']);

      $Qproducts->execute();

      $sql_array = ['manufacturers_id' => (int)$Qproducts->valueInt('manufacturers_id')];
      $insert_array = ['products_id' => $clone_products_id];

      $this->app->db->save('products', $sql_array, $insert_array);
    }
    
    return true;
  }
}