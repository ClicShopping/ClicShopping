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

class StatsProductsAlert implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Products application.
   *
   * Ensures that the 'Products' application instance is registered in the Registry.
   * If not already registered, it creates a new instance of ProductsApp and registers it.
   * Sets the 'app' property to the instance retrieved from the Registry.
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
   * Retrieves the number of products with a quantity less than or equal to the defined stock reorder level.
   *
   * This method executes a database query to count the products that meet the stock threshold condition.
   *
   * @return int The count of products that match the reorder level criteria.
   */
  private function getProductsAlert()
  {

    $Qproducts = $this->app->db->prepare('select count(products_id) as count
                                            from :table_products
                                            where products_quantity <= :products_quantity
                                          ');
    $Qproducts->bindInt(':products_quantity', STOCK_REORDER_LEVEL);
    $Qproducts->execute();

    return $Qproducts->valueInt('count');
  }

  /**
   * Retrieves the count of products that have not been viewed.
   *
   * Executes a database query to count the number of products where the
   * `products_view` column is set to 0, indicating that the product has
   * not been viewed.
   *
   * @return int The count of products not viewed.
   */
  private function getProductsNotView()
  {

    $Qproducts = $this->app->db->prepare('select count(products_id) as count
                                            from :table_products
                                            where products_view = 0
                                          ');
    $Qproducts->execute();

    return $Qproducts->valueInt('count');
  }

  /**
   * Displays a formatted output related to product alerts and stock status.
   *
   * This method checks if the product alerts feature is enabled. If it's disabled, it returns false.
   * If the product alerts feature is enabled, it loads language definitions and generates
   * either an empty string or an HTML formatted card displaying product stock alerts and other information.
   *
   * @return string|bool Returns false if the product alerts feature is disabled.
   *                     Returns a formatted HTML string if there are product alerts,
   *                     or an empty string if there are no alerts.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/stats_products_alert');

    if ($this->getProductsAlert() == 0 && $this->getProductsNotView() == 0) {
      $output = '';
    } else {
      $output = '
<div class="col-md-2 col-12">
    <div class="card bg-warning">
     <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('text_products_alert_stock') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-bell-fill text-white"></i>
          </span>
          <span class="float-end">
            <div class="col-sm-12 text-white">' . $this->getProductsAlert() . ' - ' . $this->app->getDef('text_products_alert_quantity') . '</div>
            <div class="col-sm-12 text-white">' . $this->getProductsNotView() . ' - ' . $this->app->getDef('text_products_not_view') . '</div>
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