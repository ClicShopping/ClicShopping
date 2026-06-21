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

class Update implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method to initialize the ProductsLength application instance.
   *
   * Ensures that a ProductsLength instance is registered in the Registry.
   * If not already registered, a new instance of ProductsLengthApp is created and added to the Registry.
   * Finally, retrieves and assigns the registered ProductsLength instance to the $app property.
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
   * Executes the update operation for product dimensions if the appropriate conditions are met.
   *
   * @return bool Returns false if the application status is not enabled or a required constant is not defined.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') || CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Update'], $_GET['pID'])) {
      $id = HTML::sanitize($_GET['pID']);

      $sql_data_array = [
        'products_length_class_id' => (int)HTML::sanitize($_POST['products_length_class_id']),
        'products_dimension_width' => (float)HTML::sanitize($_POST['products_dimension_width']),
        'products_dimension_height' => (float)HTML::sanitize($_POST['products_dimension_height']),
        'products_dimension_depth' => (float)HTML::sanitize($_POST['products_dimension_height']),
        'products_volume' => HTML::sanitize($_POST['products_volume'])
      ];

      $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
    }
  }
}