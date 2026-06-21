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

class CopyConfirm implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the ProductsLength application.
   *
   * Ensures the ProductsLength instance is registered in the Registry before retrieving it
   * and assigning it to the $app property.
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
   * Executes the process to handle product length and dimension data during product operations.
   *
   * The method verifies if the product length module is active, and processes the dimensions
   * and volume of a product identified by its ID. If a copy confirmation is identified,
   * the product's length and dimension data are retrieved and copied to the most recent product entry.
   *
   * @return bool Returns false if the product length module is not enabled; otherwise performs the operation with no return.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') || CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS == 'False') {
      return false;
    }

    if (isset($_POST['products_id'])) {
      $current_products_id = HTML::sanitize($_POST['products_id']);

      if (isset($current_products_id, $_GET['CopyConfirm'])) {
        $products_length = $this->app->db->prepare('select products_length_class_id,
                                                              products_dimension_width,
                                                              products_dimension_height,
                                                              products_dimension_depth,
                                                              products_volume
                                                       from :table_products
                                                       where products_id = :products_id
                                                      ');
        $products_length->bindInt(':products_id', $current_products_id);
        $products_length->execute();

        $products_length_class_id = $products_length->valueInt('products_length_class_id');
        $products_dimension_width = $products_length->valueInt('products_dimension_width');
        $products_dimension_height = $products_length->valueInt('products_dimension_height');
        $products_dimension_depth = $products_length->valueInt('products_dimension_depth');
        $products_volume = $products_length->value('products_volume');

        $Qproducts = $this->app->db->prepare('select products_id 
                                                from :table_products                                            
                                                order by products_id desc
                                                limit 1 
                                               ');
        $Qproducts->execute();

        $id = $Qproducts->valueInt('products_id');

        $sql_data_array = ['products_length_class_id' => (int)$products_length_class_id,
          'products_dimension_width' => (float)$products_dimension_width,
          'products_dimension_height' => (float)$products_dimension_height,
          'products_dimension_depth' => (float)$products_dimension_depth,
          'products_volume' => $products_volume
        ];

        $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
      }
    }
  }
}