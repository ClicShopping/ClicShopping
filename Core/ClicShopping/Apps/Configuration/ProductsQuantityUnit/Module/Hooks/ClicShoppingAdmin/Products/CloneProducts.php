<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsQuantityUnit\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ProductsQuantityUnit\ProductsQuantityUnit as ProductsQuantityUnitApp;

class CloneProducts implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the class by checking if the 'ProductsQuantityUnit' exists in the registry.
   * If it does not exist, it creates a new instance of ProductsQuantityUnitApp and sets it in the registry.
   * Retrieves the 'ProductsQuantityUnit' instance from the registry and assigns it to the $app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ProductsQuantityUnit')) {
      Registry::set('ProductsQuantityUnit', new ProductsQuantityUnitApp());
    }

    $this->app = Registry::get('ProductsQuantityUnit');
  }

  /**
   * Copies this App's columns from the source product onto its clone (ProductCloner, one call per clone).
   *
   * @param array $parameters ['clone_products_id' => int, 'source_products_id' => int]
   * @return mixed
   */
  public function execute(array $parameters): mixed
  {
    $cloneProductsId = (int)($parameters['clone_products_id'] ?? 0);
    $sourceProductsId = (int)($parameters['source_products_id'] ?? 0);

    if ($cloneProductsId < 1 || $sourceProductsId < 1) {
      return false;
    }

    $Qproducts = $this->app->db->prepare('select products_quantity_unit_id
                                          from :table_products
                                          where products_id = :products_id
                                         ');
    $Qproducts->bindInt(':products_id', $sourceProductsId);
    $Qproducts->execute();

    if ($Qproducts->fetch() === false) {
      return false;
    }

    $sql_array = [
      'products_quantity_unit_id' => $Qproducts->valueInt('products_quantity_unit_id')
    ];

    $this->app->db->save('products', $sql_array, ['products_id' => $cloneProductsId]);

    return true;
  }
}
