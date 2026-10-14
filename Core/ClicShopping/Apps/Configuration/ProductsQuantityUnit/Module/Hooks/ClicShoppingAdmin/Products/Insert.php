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

class Insert implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the `ProductsQuantityUnit` application within the registry if it does not already exist.
   * Also retrieves and assigns the application instance to the `$app` property.
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
   * Executes the logic to insert a new product's quantity and quantity unit into the database.
   *
   * This method checks if the required parameters ('Insert', 'products_quantity', 'products_quantity_unit_id')
   * are available in GET and POST requests. If valid, it retrieves the most recent product ID from the database,
   * sanitizes the input data, and saves the new product quantity details into the database.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Insert'], $_POST['products_quantity'], $_POST['products_quantity_unit_id'])) {
      $Qproducts = $this->app->db->prepare('select products_id 
                                              from :table_products                                            
                                              order by products_id desc
                                              limit 1 
                                            ');
      $Qproducts->execute();

      $id = $Qproducts->valueInt('products_id');

      $sql_data_array = ['products_quantity' => (int)HTML::sanitize($_POST['products_quantity']),
        'products_quantity_unit_id' => (int)HTML::sanitize($_POST['products_quantity_unit_id']),
      ];

      $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
    }
  }
}