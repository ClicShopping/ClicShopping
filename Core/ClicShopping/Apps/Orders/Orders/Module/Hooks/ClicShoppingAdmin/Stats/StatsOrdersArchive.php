<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Orders\Orders\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

class StatsOrdersArchive implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new OrdersApp());
    }

    $this->app = Registry::get('Orders');
  }

  private function statsOrderArchive()
  {
    $QstatArchive = $this->app->db->prepare('select count(orders_archive) as archive
                                              from :table_orders
                                              where orders_archive = 1
                                             ');
    $QstatArchive->execute();

    $stat_archive = $QstatArchive->valueInt('archive');

    return $stat_archive;
  }

  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_ORDERS_OD_STATUS')) {
      return false;
    }

    $output = '';
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/stats_orders_archive');

    if ($this->statsOrderArchive() > 0) {
      $output = '
<div class="col-md-2 col-12">
    <div class="card bg-info">
     <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('text_orders_archive') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-archive-fill text-white"></i>
          </span>
          <span class="float-end">
            <div class="text-white">' . $this->statsOrderArchive() . ' - ' . HTML::link($this->app->link('Orders&aID=1'), $this->app->getDef('text_orders_archive')) . '</div>
            <div class="mt-1"></div>   
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