<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Manufacturers\Manufacturers as ManufacturersApp;

class CopyConfirm implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Manufacturers')) {
      Registry::set('Manufacturers', new ManufacturersApp());
    }

    $this->app = Registry::get('Manufacturers');
  }

  /**
   * Executes the manufacturer copying functionality for a product if specific conditions are met.
   *
   * The method checks if the manufacturer copying module is enabled and verifies the presence of required
   * input parameters (`products_id` and query string `Products`). If conditions are met, it retrieves the
   * associated manufacturer information for the specified product and applies it to the latest product in
   * the database.
   *
   * @return bool Returns false if the manufacturer copying module is disabled or input conditions are not met.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS') || CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS == 'False') {
      return false;
    }

    if (isset($_POST['products_id']) && isset($_GET['Products'])) {
      $current_products_id = HTML::sanitize($_POST['products_id']);

      if (isset($current_products_id, $_GET['CopyConfirm'])) {
        $Qmanufacturers = $this->app->db->prepare('select manufacturers_id
                                                   from :table_products
                                                   where products_id = :products_id
                                                  ');
        $Qmanufacturers->bindInt(':products_id', $current_products_id);
        $Qmanufacturers->execute();

        $manufacturers_id = $Qmanufacturers->valueInt('manufacturers_id');

        $Qproducts = $this->app->db->prepare('select products_id 
                                                from :table_products                                            
                                                order by products_id desc
                                                limit 1 
                                               ');
        $Qproducts->execute();

        $id = $Qproducts->valueInt('products_id');

        $sql_data_array = ['manufacturers_id' => (int)$manufacturers_id];

        $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
      }
    }
  }
}