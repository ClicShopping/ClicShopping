<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

class StatsOrdersCustomersB2BB2C implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new OrdersApp());
    }

    $this->app = Registry::get('Orders');
  }

  private function statsOrdersCustomersB2B()
  {
    $QstatOrders = $this->app->db->prepare('select count(*) as count
                                              from :table_orders
                                              where customers_group_id  > 0
                                             ');
    $QstatOrders->execute();

    $statOrders = $QstatOrders->valueInt('count');

    return $statOrders;
  }

  private function statsOrdersCustomersB2C()
  {
    $QstatOrders = $this->app->db->prepare('select count(*) as count
                                              from :table_orders
                                              where customers_group_id  = 0
                                             ');
    $QstatOrders->execute();

    $statOrders = $QstatOrders->valueInt('count');

    return $statOrders;
  }


  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_ORDERS_OD_STATUS')) {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/stats_orders_customers_b2b_b2c');

    if ($this->statsOrdersCustomersB2C() == 0 && $this->statsOrdersCustomersB2B() == 0) {
      $output = '';
    } else {
      $output = '
<div class="col-md-2 col-12">
    <div class="card bg-primary">
     <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('title_orders_b2b_b2c') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-person-fill text-white"></i>
          </span>
          <span class="float-end">
            <div class="text-white">' . $this->statsOrdersCustomersB2C() . ' - ' . $this->app->getDef('text_orders_b2c') . '</div>
            <div class="text-white">' . $this->statsOrdersCustomersB2B() . ' - ' . $this->app->getDef('text_orders_b2b') . '</div>            
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