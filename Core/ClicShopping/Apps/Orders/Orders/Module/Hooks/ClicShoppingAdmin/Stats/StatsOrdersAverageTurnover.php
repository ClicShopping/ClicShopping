<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

class StatsOrdersAverageTurnover implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new OrdersApp());
    }

    $this->app = Registry::get('Orders');
  }

  /**
   * @return float
   */
  public function statsOrderAverage(): float
  {
    $QstatOrders = $this->app->db->prepare('select avg(value) as value
                                              from :table_orders_total ot,
                                                   :table_orders o
                                              where ot.class = :class
                                              and o.orders_status = 3
                                              and o.orders_id = ot.orders_id
                                              and year(o.date_purchased) >= year(now())
                                             ');
    $QstatOrders->bindValue(':class', 'TO');
    $QstatOrders->execute();

    $result = $QstatOrders->valueDecimal('value');

    return $result;
  }

  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_ORDERS_OD_STATUS')) {
      return false;
    }

    $output = '';
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/stats_orders_average_turn_over');

    if ($this->statsOrderAverage() > 0) {
      $output = '
<div class="col-md-2 col-12">
  <div class="card bg-success">
    <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('title_orders_average_turn_over') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-truck text-white"></i>
          </span>
          <span class="float-end">
            <div class="text-white">' . $this->statsOrderAverage() . '</div>
            <div class="text-white"><br></div>
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