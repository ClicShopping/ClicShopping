<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\Hooks\ClicShoppingAdmin\TopDashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;

class dashboardProductsWarning implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Products')) {
      Registry::set('Products', new ProductsApp());
    }

    $this->app = Registry::get('Products');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/TopDashboard/dashboard_products_warning');
  }

  public function Display(): string
  {
    $Qproducts = $this->app->db->prepare('select count(products_id) as count 
                                              from :table_products 
                                              where products_quantity <= :products_quantity
                                              and products_quantity > 0
                                             ');
    $Qproducts->bindInt(':products_quantity', STOCK_REORDER_LEVEL);
    $Qproducts->execute();

    $number_products_out_of_stock = $Qproducts->valueInt('count');

    $output = '';

    if ($number_products_out_of_stock > 0 && STOCK_CHECK == 'true') {
      $text = $this->app->getDef('text_number_products_stock_warning');
      $text_view = $this->app->getDef('text_view');

      $output = '
<div class="col-md-2 col-12 m-1">
    <div class="card bg-warning">
      <div class="card-body">
        <div class="row">
          <h6 class="card-title text-white"><i class="bi bi-exclamation-diamond-fill"></i> ' . $text . '</h6>
        </div>
        <div class="col-md-12">
          <span class="text-white"><strong>' . $number_products_out_of_stock . '</strong></span>
          <span><small class="text-white">' . HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&StatsProductsLowStock'), $text_view, 'class="text-white"') . '</small></span>
        </div>
      </div>
    </div>
</div>
';
    }

    return $output;
  }
}