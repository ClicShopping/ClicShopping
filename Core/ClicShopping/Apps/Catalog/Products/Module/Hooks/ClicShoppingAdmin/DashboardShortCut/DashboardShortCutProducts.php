<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\Hooks\ClicShoppingAdmin\DashboardShortCut;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;

class DashboardShortCutProducts implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Products module.
   *
   * Ensures that the 'Products' module is registered in the Registry. If it does not exist, it creates a new instance of ProductsApp
   * and sets it in the Registry. Then, retrieves the 'Products' module from the Registry and loads its language definitions for the
   * dashboard shortcut.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Products')) {
      Registry::set('Products', new ProductsApp());
    }

    $this->app = Registry::get('Products');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/DashboardShortCut/dashboard_shortcut_products');
  }

  /**
   * Displays a button linking to the product catalog if the product catalog module is enabled.
   *
   * @return string A string containing HTML for the button or an empty string if the module is disabled.
   */
  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    $output = HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Products'), null, 'class="btn btn-info btn-sm" role="button"><span class="bi-shop" title="' . $this->app->getDef('heading_short_products') . '"') . ' ';

    return $output;
  }
}