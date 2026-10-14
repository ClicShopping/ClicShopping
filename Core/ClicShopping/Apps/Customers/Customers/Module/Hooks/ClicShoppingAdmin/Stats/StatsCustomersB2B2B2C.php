<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Customers\Customers as CustomersApp;

class StatsCustomersB2B2B2C implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method initializing the Customers registry and loading the required definitions.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $this->app = Registry::get('Customers');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/StatsCustomersB2B2B2C');
  }

  /**
   * Retrieves the total number of B2C (Business-to-Consumer) customers from the database.
   *
   * @return int The total count of B2C customers identified by their group ID being 0.
   */
  private function statsCustomersB2C(): int
  {
    $QstatCustomersB2C = $this->app->db->prepare('select count(customers_id) as count
                                                    from :table_customers
                                                    where customers_group_id = 0
                                                   ');
    $QstatCustomersB2C->execute();

    $statCustomersB2C = $QstatCustomersB2C->valueDecimal('count');

    return $statCustomersB2C;
  }

  /**
   * Retrieves the total count of B2B customers from the database.
   *
   * @return int Returns the count of customers belonging to a group with an ID greater than zero.
   */
  private function statCustomersB2B(): int
  {
    $QstatCustomersB2B = $this->app->db->prepare('select count(customers_id) as count
                                                    from :table_customers
                                                    where customers_group_id > 0
                                                   ');
    $QstatCustomersB2B->execute();

    $statCustomersB2B = $QstatCustomersB2B->valueDecimal('count');

    return $statCustomersB2B;
  }

  /**
   * Generates and returns a formatted string representation of customer statistics.
   *
   * This method checks specific customer statistics for B2C and B2B.
   * If both are 0, it returns an empty string.
   * Otherwise, it generates HTML content displaying these statistics.
   *
   * @return string A formatted string containing the customer statistics, or an empty string if no statistics are available.
   */
  public function display(): string
  {
    if ($this->statsCustomersB2C() == 0 && $this->statCustomersB2B() == 0) {
      $output = '';
    } else {
      $output = '
<div class="col-md-2 col-12">
    <div class="card bg-success">
     <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('text_customers') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-person-fill text-white"></i>
          </span>
          <span class="float-end">
            <div class="col-sm-12 text-white">' . $this->statsCustomersB2C() . ' - ' . $this->app->getDef('text_b2c') . '</div>
            <div class="col-sm-12 text-white">' . $this->statCustomersB2B() . ' - ' . $this->app->getDef('text_b2b') . '</div>
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