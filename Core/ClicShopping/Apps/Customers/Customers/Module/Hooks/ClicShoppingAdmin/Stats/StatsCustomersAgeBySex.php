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

class StatsCustomersAgeBySex implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the constructor method, setting up the necessary registry entry for the Customers application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $this->app = Registry::get('Customers');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/StatsCustomersAgeBySex');
  }

  /**
   * Calculates and returns the average age of male customers.
   *
   * Queries the database to determine the average age of customers
   * whose gender is identified as male.
   *
   * @return int The average age of male customers, rounded to the nearest integer. If no data is available, returns 0.
   */
  private function statsAgeCustomersMen(): int
  {
    $statAnalyseCustomersMan = '    ';

    $QstatAnalyseCustomersMan = $this->app->db->prepare('select ROUND(AVG(TIMESTAMPDIFF(YEAR,(customers_dob), now())),0) AS avgage
                                                          from :table_customers
                                                          where customers_gender = :customers_gender
                                                         ');
    $QstatAnalyseCustomersMan->bindValue(':customers_gender', 'm');

    $QstatAnalyseCustomersMan->execute();

    if (!\is_null($QstatAnalyseCustomersMan->valueDecimal('avgage'))) {
      $statAnalyseCustomersMan = $QstatAnalyseCustomersMan->valueDecimal('avgage');
    }

    return $statAnalyseCustomersMan;
  }

  /**
   * Calculates and retrieves the average age of female customers from the database.
   *
   * @return int The average age of female customers, rounded to the nearest integer. Returns 0 if no data is available.
   */
  private function statsAgeCustomersWomen(): int
  {
    $statAnalyseCustomersWomen = '    ';

    $QstatAnalyseCustomersWomen = $this->app->db->prepare('select ROUND(AVG(TIMESTAMPDIFF(YEAR,(customers_dob), now())),0) AS avgage
                                                              from :table_customers
                                                              where customers_gender = :customers_gender
                                                             ');
    $QstatAnalyseCustomersWomen->bindValue(':customers_gender', 'f');

    $QstatAnalyseCustomersWomen->execute();

    if (!\is_null($QstatAnalyseCustomersWomen->valueDecimal('avgage'))) {
      $statAnalyseCustomersWomen = $QstatAnalyseCustomersWomen->valueDecimal('avgage');
    }

    return $statAnalyseCustomersWomen;
  }

  /**
   * Generates and returns the HTML display content based on customer statistics.
   *
   * @return string The HTML content to display customer statistics or an empty string if no statistics are available.
   */
  public function display(): string
  {
    if ($this->statsAgeCustomersMen() == 0 && $this->statsAgeCustomersWomen() == 0) {
      $output = '';
    } else {
      $output = '
<div class="col-md-2 col-12">
  <div class="card bg-info">
    <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('text_average_age') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-calendar text-white"></i>
          </span>
          <span class="float-end">
            <div class="col-sm-12 text-white">' . $this->statsAgeCustomersMen() . ' - ' . $this->app->getDef('text_male') . '</div>
            <div class="col-sm-12 text-white">' . $this->statsAgeCustomersWomen() . ' - ' . $this->app->getDef('text_female') . '</div>
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