<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Module\Hooks\ClicShoppingAdmin\DashboardShortCut;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

class DashboardShortCutOrders implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new OrdersApp());
    }

    $this->app = Registry::get('Orders');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/DashboardShortCut/dashboard_shortcut_orders');
  }

  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_ORDERS_OD_STATUS') || CLICSHOPPING_APP_ORDERS_OD_STATUS == 'False') {
      return false;
    }

    $output = HTML::link(CLICSHOPPING::link(null, 'A&Orders\Orders&Orders'), null, 'class="btn btn-success btn-sm" role="button"><span class="bi bi-bag-check-fill" title="' . $this->app->getDef('heading_short_orders') . '"') . ' ';

    return $output;
  }
}