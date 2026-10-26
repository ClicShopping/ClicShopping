<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsLength\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ProductsLength\ProductsLength as ProductsLengthApp;

class CloneProducts implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the ProductsLength application component.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ProductsLength')) {
      Registry::set('ProductsLength', new ProductsLengthApp());
    }

    $this->app = Registry::get('ProductsLength');
  }

  /**
   * Copies this App's columns from the source product onto its clone (ProductCloner, one call per clone).
   *
   * @param array $parameters ['clone_products_id' => int, 'source_products_id' => int]
   * @return mixed
   */
  public function execute(array $parameters): mixed
  {
    if (!\defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') || CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS == 'False') {
      return false;
    }

    $cloneProductsId = (int)($parameters['clone_products_id'] ?? 0);
    $sourceProductsId = (int)($parameters['source_products_id'] ?? 0);

    if ($cloneProductsId < 1 || $sourceProductsId < 1) {
      return false;
    }

    $Qproducts = $this->app->db->prepare('select products_length_class_id,
                                                 products_dimension_width,
                                                 products_dimension_height,
                                                 products_dimension_depth,
                                                 products_volume
                                          from :table_products
                                          where products_id = :products_id
                                         ');
    $Qproducts->bindInt(':products_id', $sourceProductsId);
    $Qproducts->execute();

    if ($Qproducts->fetch() === false) {
      return false;
    }

    $sql_array = [
      'products_length_class_id' => $Qproducts->valueInt('products_length_class_id'),
      'products_dimension_width' => (float)$Qproducts->value('products_dimension_width'),
      'products_dimension_height' => (float)$Qproducts->value('products_dimension_height'),
      'products_dimension_depth' => (float)$Qproducts->value('products_dimension_depth'),
      'products_volume' => $Qproducts->value('products_volume')
    ];

    $this->app->db->save('products', $sql_array, ['products_id' => $cloneProductsId]);

    return true;
  }
}
