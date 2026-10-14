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
   * Executes the process to clone product dimensions and related data to another product ID.
   *
   * @return mixed Returns false if the application status for 'CLICSHOPPING_APP_PROUCTS_LENGTH_PL' is not defined or set to 'False'.
   */
  public function execute(array $parameters): mixed
  {
    if (!\defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') || CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Update'], $_POST['clone_categories_id_to'], $_GET['pID'])) {
      $clone_products_id = $parameters['products_id'] ?? null;

      $Qproducts = $this->app->db->prepare('select products_length_class_id,
                                                      products_dimension_width,
                                                      products_dimension_height,
                                                      products_dimension_depth,
                                                      products_volume
                                              from :table_products
                                              where products_id = :products_id
                                             ');
      $Qproducts->bindInt(':products_id', $_GET['pID']);

      $Qproducts->execute();

      $sql_array = ['products_length_class_id' => (int)$Qproducts->valueInt('products_length_class_id'),
        'products_dimension_width' => (float)$Qproducts->valueInt('products_dimension_width'),
        'products_dimension_height' => (float)$Qproducts->valueInt('products_dimension_height'),
        'products_dimension_depth' => (float)$Qproducts->valueInt('products_dimension_depth'),
        'products_volume' => $Qproducts->value('products_volume')
      ];

      $insert_array = ['products_id' => $clone_products_id];

      $this->app->db->save('products', $sql_array, $insert_array);
    }

    return true;
  }
}