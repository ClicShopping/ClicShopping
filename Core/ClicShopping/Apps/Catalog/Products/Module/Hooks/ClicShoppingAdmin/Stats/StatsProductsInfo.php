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

class StatsProductsInfo implements HooksInterface
{
  public mixed $app;

  /**
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
   *
   */
  private function getProductsArchive()
  {

    $Qproducts = $this->app->db->prepare('select count(products_id) as count
                                            from :table_products
                                            where products_archive = 1
                                          ');
    $Qproducts->execute();

    return $Qproducts->valueInt('count');
  }

  /**
   * Retrieves the total number of products from the database.
   *
   * Executes a prepared SQL query to count the number of product IDs
   * in the products table and returns the count as an integer.
   *
   * @return int The total number of products.
   */
  private function getNumberOfProducts()
  {
    $Qproducts = $this->app->db->prepare('select count(products_id) as count
                                            from :table_products
                                          ');
    $Qproducts->execute();

    return $Qproducts->valueInt('count');
  }

  /**
   * Displays information about the products archive and total number of products.
   *
   * This method checks the status of the `CLICSHOPPING_APP_CATALOG_PRODUCTS_PD` constant
   * and loads the required definitions. If both the products archive count and total products
   * count are zero, it returns an empty string. Otherwise, it generates and returns a formatted
   * HTML output with the products archive and total product information.
   *
   * @return string|false The formatted HTML output containing product statistics, or false
   *                      if the status is disabled.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/stats_products_info');

    if ($this->getProductsArchive() == 0 && $this->getNumberOfProducts() == 0) {
      $output = '';
    } else {
      $output = '
<div class="col-md-2 col-12">
    <div class="card bg-info">
     <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('text_products_info_title') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-archive-fill text-white"></i>
          </span>
          <span class="float-end">
            <div class="text-white">' . $this->getProductsArchive() . ' - ' . $this->app->getDef('text_products_info_archive') . '</div>
            <div class="text-white">' . $this->getNumberOfProducts() . ' - ' . $this->app->getDef('text_products_info_total') . '</div>
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