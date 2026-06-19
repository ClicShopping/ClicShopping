<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\ProductsAttributes\ProductsAttributes as ProductsAttributesApp;

class RemoveProduct implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method to initialize the application.
   *
   * Checks if 'ProductsAttributesApp' exists in the Registry. If not, it creates and sets a new instance
   * of ProductsAttributesApp in the Registry. The app property is then initialized by retrieving
   * the 'ProductsAttributes' instance from the Registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ProductsAttributesApp')) {
      Registry::set('ProductsAttributes', new ProductsAttributesApp());
    }

    $this->app = Registry::get('ProductsAttributes');
  }

  /**
   * Removes products with the specified ID from the products_attributes table.
   *
   * @param int $id The ID of the product to be removed.
   * @return void
   */
  private function removeProducts(int $id): void
  {
    $this->app->db->delete('products_attributes', ['products_id' => $id]);
  }

  /**
   * Executes the removal process for a product attribute.
   *
   * @return bool Returns false if the Products Attributes application is disabled or not properly defined;
   *              otherwise void if the operation proceeds.
   */
  public function execute(array $parameters): mixed
  {
    if (!\defined('CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS') || CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS === 'False') {
      return false;
    }

    $products_id = $parameters['products_id'] ?? null;

    if (empty($products_id)) {
      return false;
    }

    $this->removeProducts($products_id);

    return true;
  }
}