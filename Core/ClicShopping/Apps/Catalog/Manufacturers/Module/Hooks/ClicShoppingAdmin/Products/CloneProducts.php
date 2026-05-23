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

class CloneProducts implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Manufacturers application.
   *
   * Ensures the Manufacturers module is available in the Registry.
   * If it does not exist, it creates and registers a new ManufacturersApp instance.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Manufacturers')) {
      Registry::set('Manufacturers', new ManufacturersApp());
    }

    $this->app = Registry::get('Manufacturers');
  }


  /**
   * Executes the method logic to handle updates related to product manufacturers.
   *
   * If the application constant `CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS` is either undefined or set to 'False',
   * the method returns false and no further action is taken. Otherwise, if valid update and cloning parameters
   * (`Update` and `clone_categories_id_to`) are set, it processes a database operation to associate
   * a new manufacturer ID with a cloned product.
   *
   * @return mixed Returns false if the application status constant is not defined or disabled;
   *              otherwise, executes the database logic without returning a value.
   */

  public function execute(array $parameters): mixed
  {
    if (!\defined('CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS') || CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Update'], $_POST['clone_categories_id_to'])) {
      $clone_products_id = $parameters['products_id'] ?? null;
      $products_id = HTML::sanitize( $_GET['pID']);

      $Qproducts = $this->app->db->prepare('select *
                                            from :table_products
                                            where products_id = :products_id
                                           ');
      $Qproducts->bindInt(':products_id', $products_id);

      $Qproducts->execute();

      $sql_array = ['manufacturers_id' => (int)HTML::sanitize($_POST['manufacturers_id'])];
      $insert_array = ['products_id' => HTML::sanitize($clone_products_id)];

      $this->app->db->save('products', $sql_array, $insert_array);
    }

    return true;
  }
}