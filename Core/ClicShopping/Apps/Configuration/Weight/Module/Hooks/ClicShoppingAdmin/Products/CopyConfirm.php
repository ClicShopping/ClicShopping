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

class CopyConfirm implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for the class.
   *
   * Initializes the class by ensuring the 'Weight' instance exists
   * within the Registry. If not, it sets a new instance of WeightApp.
   * Retrieves the 'Weight' instance from the Registry and assigns
   * it to the $app property.
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
   * Executes the necessary operations related to weight management during product copy processing.
   *
   * The method checks if the weight management application is active, validates product-related
   * parameters, retrieves relevant database records for weight class information, and updates
   * the newly copied product record with the associated weight class data.
   *
   * @return bool Returns false if the weight management application is inactive.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_WEIGHT_WE_STATUS') || CLICSHOPPING_APP_WEIGHT_WE_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['products_id'])) {
      $current_products_id = HTML::sanitize($_POST['products_id']);

      if (isset($current_products_id, $_GET['CopyConfirm'])) {
        $weight = $this->app->db->prepare('select products_weight_class_id
                                             from :table_products
                                             where products_id = :products_id
                                            ');
        $weight->bindInt(':products_id', $current_products_id);
        $weight->execute();

        $products_weight_class_id = $weight->valueInt('products_weight_class_id');

        $Qproducts = $this->app->db->prepare('select products_id 
                                                from :table_products                                            
                                                order by products_id desc
                                                limit 1 
                                               ');
        $Qproducts->execute();

        $id = $Qproducts->valueInt('products_id');

        $sql_data_array = ['products_weight_class_id' => (int)$products_weight_class_id];

        $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
      }
    }
  }
}