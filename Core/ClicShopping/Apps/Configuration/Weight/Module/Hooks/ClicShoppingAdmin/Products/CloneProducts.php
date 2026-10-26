<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Weight\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Weight\Weight as WeightApp;

class CloneProducts implements HooksInterface
{
  public mixed $app;

  /**
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Weight')) {
      Registry::set('Weight', new WeightApp());
    }

    $this->app = Registry::get('Weight');
  }

  /**
   * Copies this App's columns from the source product onto its clone (ProductCloner, one call per clone).
   *
   * @param array $parameters ['clone_products_id' => int, 'source_products_id' => int]
   * @return mixed
   */
  public function execute(array $parameters): mixed
  {
    if (!\defined('CLICSHOPPING_APP_WEIGHT_WE_STATUS') || CLICSHOPPING_APP_WEIGHT_WE_STATUS == 'False') {
      return false;
    }

    $cloneProductsId = (int)($parameters['clone_products_id'] ?? 0);
    $sourceProductsId = (int)($parameters['source_products_id'] ?? 0);

    if ($cloneProductsId < 1 || $sourceProductsId < 1) {
      return false;
    }

    $Qproducts = $this->app->db->prepare('select products_weight_class_id
                                          from :table_products
                                          where products_id = :products_id
                                         ');
    $Qproducts->bindInt(':products_id', $sourceProductsId);
    $Qproducts->execute();

    if ($Qproducts->fetch() === false) {
      return false;
    }

    $sql_array = [
      'products_weight_class_id' => $Qproducts->valueInt('products_weight_class_id')
    ];

    $this->app->db->save('products', $sql_array, ['products_id' => $cloneProductsId]);

    return true;
  }
}
