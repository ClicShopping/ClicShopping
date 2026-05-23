<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Weight\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Weight\Weight as WeightApp;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
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
   * Executes the weight application logic for handling product weight class updates.
   *
   * This method checks whether the weight application module is enabled, and if it is,
   * processes the weight class update when an 'Insert' action is detected in the request.
   * It retrieves the latest product ID, sanitizes the input, and updates the product's weight class.
   *
   * @return bool Returns false if the weight application module is disabled, otherwise handles the logic and returns void.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_WEIGHT_WE_STATUS') || CLICSHOPPING_APP_WEIGHT_WE_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Insert'])) {
      $Qproducts = $this->app->db->prepare('select products_id 
                                              from :table_products                                            
                                              order by products_id desc
                                               limit 1 
                                              ');
      $Qproducts->execute();

      $id = $Qproducts->valueInt('products_id');

      $sql_data_array = ['products_weight_class_id' => (int)HTML::sanitize($_POST['products_weight_class_id'])];

      $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
    }
  }
}