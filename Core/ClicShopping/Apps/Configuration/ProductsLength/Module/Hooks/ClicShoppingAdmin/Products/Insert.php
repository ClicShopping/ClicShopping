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

class Insert implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method that initializes the ProductsLength application.
   *
   * Ensures that the 'ProductsLength' application is registered in the Registry.
   * If not present, it sets a new instance of ProductsLengthApp in the Registry.
   * Assigns the registered instance to the $app property.
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
   * Executes the process of inserting or updating product dimensions and volume details
   * for a product if specific conditions are met.
   *
   * @return bool Returns false if the application status is inactive, otherwise performs
   *              the database operations as necessary.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') || CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS == 'False') {
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