<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\Hooks\ClicShoppingAdmin\StatsDashboard;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;

class PageTabContent implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Products')) {
      Registry::set('Products', new ProductsApp());
    }

    $this->app = Registry::get('Products');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/StatsDashboard/page_tab_content');
  }


  private function statsProductsOffline()
  {
    $Qproducts = $this->app->db->prepare('select count(products_id) as count
                                           from :table_products
                                           where products_status = 0
                                           limit 1
                                         ');
    $Qproducts->execute();

    $products_total_off_line = $Qproducts->valueInt('count');

    return $products_total_off_line;
  }


  private function statsProductsTotal()
  {
    $Qproducts = $this->app->db->prepare('select count(products_id) as count
                                           from :table_products
                                           limit 1
                                          ');
    $Qproducts->execute();

    $products_total = $Qproducts->valueInt('count');

    return $products_total;
  }


  public function display()
  {

    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    if ($this->statsProductsTotal() != 0) {
      $content = '
        <div class="row">
          <div class="col-md-11 mainTable">
            <div class="form-group row">
              <label for="' . $this->app->getDef('box_entry_products') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Products') . '">' . $this->app->getDef('box_entry_products') . '</a></label>
              <div class="col-md-3">
                ' . $this->statsProductsTotal() . '
              </div>
            </div>
          </div>
        </div>
        ';
    }


    if ($this->statsProductsOffline() != 0) {
      $content = '
        <div class="row">
          <div class="col-md-11 mainTable">
            <div class="form-group row">
              <label for="' . $this->app->getDef('box_entry_products_off_line') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Products') . '">' . $this->app->getDef('box_entry_products_off_line') . '</a></label>
              <div class="col-md-3">
                ' . $this->statsProductsOffline() . '
              </div>
            </div>
          </div>
        </div>
       ';
    }

    if ($this->statsProductsOffline() != 0 || $this->statsProductsTotal() != 0) {
      $output = <<<EOD
  <!-- ######################## -->
  <!--  Start Products      -->
  <!-- ######################## -->
             {$content}
  <!-- ######################## -->
  <!--  Start Products      -->
  <!-- ######################## -->
EOD;
      return $output;
    }
  }
}
