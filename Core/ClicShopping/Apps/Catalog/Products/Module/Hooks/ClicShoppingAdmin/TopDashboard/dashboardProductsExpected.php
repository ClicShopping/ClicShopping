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
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;

class dashboardProductsExpected implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Products')) {
      Registry::set('Products', new ProductsApp());
    }

    $this->app = Registry::get('Products');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/TopDashboard/dashboard_products_expected');
  }

  public function Display(): string
  {
    $Qproducts = $this->app->db->prepare('select count(products_id) as count 
                                            from :table_products 
                                            where products_date_available <> null
                                            ');
    $Qproducts->execute();

    $number_products_expected = $Qproducts->valueInt('count');

    $output = '';

    if ($number_products_expected != 0) {
      $text = $this->app->getDef('text_number_products_expected');
      $text_view = $this->app->getDef('text_view');

      $output = '
<div class="col-md-2 col-12 m-1">
  <div class="card bg-primary">
    <div class="card-body">
      <div class="row">
        <h6 class="card-title text-white"><i class="bi bi-command"></i> ' . $text . '</h6>
      </div>
      <div class="col-md-12">
        <span class="text-white"><strong>' . $number_products_expected . '</strong></span>
        <span><small class="text-white">' . HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&StatsProductsExpected'), $text_view, 'class="text-white"') . '</small></span>
      </div>
    </div>
  </div>
</div>
';
    }

    return $output;
  }
}