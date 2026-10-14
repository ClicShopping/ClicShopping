<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;

class StatsProductsOutOfStock implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Products application.
   *
   * This method checks if the 'Products' entry exists within the Registry.
   * If it does not exist, it creates a new instance of ProductsApp and sets it in the Registry.
   * Finally, it retrieves the 'Products' entry from the Registry and assigns it to the app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Products')) {
      Registry::set('Products', new ProductsApp());
    }

    $this->app = Registry::get('Products');
  }

  /**
   * Retrieves the count of products that are out of stock.
   *
   * This method executes a database query to count the number of
   * products with a quantity less than or equal to zero.
   *
   * @return int The number of out-of-stock products.
   */
  private function getOutOfStock()
  {

    $Qproducts = $this->app->db->prepare('select count(products_id) as count
                                            from :table_products
                                            where products_quantity <= 0
                                          ');
    $Qproducts->execute();

    return $Qproducts->valueInt('count');
  }

  /**
   * Retrieves the count of products that are currently offline (with products_status set to 0) from the database.
   *
   * @return int The count of offline products.
   */
  private function getProductsOffLine()
  {

    $Qproducts = $this->app->db->prepare('select count(products_id) as count
                                            from :table_products
                                            where products_status = 0
                                          ');
    $Qproducts->execute();

    return $Qproducts->valueInt('count');
  }

  /**
   * Displays information about products that are out of stock or offline.
   *
   * @return string|false The HTML output containing the information or false if the functionality is disabled.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/stats_products_out_of_stock');

    if ($this->getOutOfStock() == 0 && $this->getProductsOffLine() == 0) {
      $output = '';
    } else {
      $output = '
 <div class="col-md-2 col-12">
    <div class="card bg-danger">
     <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('text_products_stock') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-cone-striped text-white"></i>
          </span>
          <span class="float-end">
              <div class="col-sm-12 text-white">' . $this->getOutOfStock() . ' - ' . $this->app->getDef('text_products_out_of_stock') . '</div>
              <div class="col-sm-12 text-white">' . $this->getProductsOffLine() . ' - ' . $this->app->getDef('text_products_offline') . '</div>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>  
      ';
    }

    return $output;
  }
}