<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Module\Hooks\ClicShoppingAdmin\TopDashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Orders\ReturnOrders\ReturnOrders as ReturnOrdersApp;

class DashboardReturnOrders implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the ReturnOrders module by checking its presence in the registry,
   * setting it if not already registered, and loading necessary definitions.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ReturnOrders')) {
      Registry::set('ReturnOrders', new ReturnOrdersApp());
    }

    $this->app = Registry::get('ReturnOrders');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/TopDashboard/dashboard_top_return_order');
  }

  /**
   * Generates and returns a formatted HTML string displaying the count of unopened return orders.
   *
   * Queries the database to retrieve the count of unopened return orders and, if count is greater than zero,
   * formats an HTML output with a warning card containing the count and a link for viewing the return orders.
   *
   * @return string The generated HTML content, or an empty string if there are no unopened return orders.
   */
  public function Display(): string
  {
    $Qreturn = $this->app->db->prepare('select count(return_id) as count 
                                          from :table_return_orders
                                          where opened = 0
                                         ');
    $Qreturn->execute();

    $number_return = $Qreturn->valueInt('count');
    $output = '';

    if ($number_return > 0) {
      $text = $this->app->getDef('text_newsletter');
      $text_view = $this->app->getDef('text_view');

      $output = '
<div class="col-md-2 col-12 m-1">
    <div class="card bg-warning">
      <div class="card-body">
        <div class="row">
          <h6 class="card-title text-white"><i class="bi bi-bell-fill"></i> ' . $text . '</h6>
        </div>
        <div class="col-md-12">
          <span class="text-white"><strong>' . $number_return . '</strong></span>
          <span><small class="text-white">' . HTML::link(CLICSHOPPING::link(null, 'A&Orders\ReturnOrders&ReturnOrders'), $text_view, 'class="text-white"') . '</small></span>
        </div>
      </div>
    </div>
</div>
';
    }

    return $output;
  }
}