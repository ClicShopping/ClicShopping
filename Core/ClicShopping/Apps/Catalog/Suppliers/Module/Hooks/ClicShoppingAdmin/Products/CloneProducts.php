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
   * Copies this App's columns from the source product onto its clone (ProductCloner, one call per clone).
   *
   * @param array $parameters ['clone_products_id' => int, 'source_products_id' => int]
   * @return mixed
   */
  public function execute(array $parameters): mixed
  {
    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      return false;
    }

    $cloneProductsId = (int)($parameters['clone_products_id'] ?? 0);
    $sourceProductsId = (int)($parameters['source_products_id'] ?? 0);

    if ($cloneProductsId < 1 || $sourceProductsId < 1) {
      return false;
    }

    $Qproducts = $this->app->db->prepare('select suppliers_id
                                          from :table_products
                                          where products_id = :products_id
                                         ');
    $Qproducts->bindInt(':products_id', $sourceProductsId);
    $Qproducts->execute();

    if ($Qproducts->fetch() === false) {
      return false;
    }

    $sql_array = [
      'suppliers_id' => $Qproducts->valueInt('suppliers_id')
    ];

    $this->app->db->save('products', $sql_array, ['products_id' => $cloneProductsId]);

    return true;
  }
}
