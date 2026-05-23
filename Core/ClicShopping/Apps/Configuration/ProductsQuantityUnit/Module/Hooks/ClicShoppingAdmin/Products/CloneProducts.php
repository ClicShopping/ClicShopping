<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsQuantityUnit\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ProductsQuantityUnit\ProductsQuantityUnit as ProductsQuantityUnitApp;

class CloneProducts implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the class by checking if the 'ProductsQuantityUnit' exists in the registry.
   * If it does not exist, it creates a new instance of ProductsQuantityUnitApp and sets it in the registry.
   * Retrieves the 'ProductsQuantityUnit' instance from the registry and assigns it to the $app property.
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
   * Executes the cloning process of product data based on provided parameters.
   *
   * Checks if the required `Update` GET parameter and `clone_categories_id_to` POST parameter are set.
   * Retrieves product data from the database for the specified product ID.
   * Updates the products database table with the quantity unit ID of the cloned product and the cloned product ID.
   *
   * @param array $parameters
   * @return mixed
   */
  public function execute(array $parameters): mixed
  {
    if (isset($_GET['Update'], $_POST['clone_categories_id_to'])) {
      $clone_products_id = $parameters['products_id'] ?? null;

      $Qproducts = $this->app->db->prepare('select *
                                            from :table_products
                                            where products_id = :products_id
                                           ');
      $Qproducts->bindInt(':products_id', $_GET['pID']);

      $Qproducts->execute();

      $sql_array = ['products_quantity_unit_id' => (int)$Qproducts->valueInt('products_quantity_unit_id')];
      $insert_array = ['products_id' => $clone_products_id];

      $this->app->db->save('products', $sql_array, $insert_array);
    }

    return true;
  }
}