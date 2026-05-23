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

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method.
   *
   * Ensures that the 'Weight' key exists in the Registry. If not, it initializes it with a new instance of WeightApp.
   * Retrieves the 'Weight' application from the Registry and assigns it to the $app property.
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
   * Executes the weight application logic.
   *
   * Checks if the weight application status is active and defined. If the application is active,
   * it processes the update request for a product's weight class by sanitizing the input and updating
   * the database with the new weight class ID for the specified product ID.
   *
   * @return bool Returns false if the weight application is not active or defined; otherwise, no return value.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_WEIGHT_WE_STATUS') || CLICSHOPPING_APP_WEIGHT_WE_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Update'], $_GET['pID'])) {
      $id = HTML::sanitize($_GET['pID']);

      $sql_data_array = ['products_weight_class_id' => (int)HTML::sanitize($_POST['products_weight_class_id'])];

      $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
    }
  }
}