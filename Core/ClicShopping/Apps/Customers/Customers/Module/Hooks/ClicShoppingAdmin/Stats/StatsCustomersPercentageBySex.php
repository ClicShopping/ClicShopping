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

class StatsCustomersPercentageBySex implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the CustomersApp module.
   *
   * This method checks if the 'Customers' registry entry exists. If it does not,
   * it sets a new instance of the CustomersApp class to the 'Customers' registry.
   * It also retrieves the 'Customers' registry instance and loads the required
   * language definitions related to the statistics of customer percentage by sex.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $this->app = Registry::get('Customers');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/StatsCustomersPercentageBySex');
  }

  /**
   * Calculates and returns the percentage of male customers in the database.
   *
   * @return float The percentage of male customers as a decimal value rounded to two decimal places.
   */
  private function statsAverageCustomersMen(): float
  {
    $numberByPerCentMen = '   ';

    $QstatAnalyseCustomersMan = $this->app->db->prepare('select ROUND(((COUNT(customers_id)/(SELECT COUNT(customers_id) FROM :table_customers))*100),2) AS numberByGenderPerCent
                                                            from :table_customers
                                                            where customers_gender = :customers_gender
                                                           ');
    $QstatAnalyseCustomersMan->bindValue(':customers_gender', 'm');

    $QstatAnalyseCustomersMan->execute();

    if (!\is_null($QstatAnalyseCustomersMan->valueDecimal('numberByGenderPerCent'))) {
      $numberByPerCentMen = $QstatAnalyseCustomersMan->valueDecimal('numberByGenderPerCent');
    }

    return $numberByPerCentMen;
  }

  /**
   * Calculates and returns the percentage of female customers from the total number of customers.
   *
   * This method retrieves the gender distribution of customers from the database
   * and computes the percentage of customers with a female gender ('f').
   *
   * @return float The percentage of female customers rounded to two decimal places.
   */
  private function statsAverageCustomersWomen(): float
  {
    $numberByPerCentWomen = '   ';

    $QstatAnalyseCustomersWomen = $this->app->db->prepare('select ROUND(((COUNT(customers_id)/(SELECT COUNT(customers_id) FROM :table_customers))*100),2) AS numberByGenderPerCent
                                                              from :table_customers
                                                              where customers_gender = :customers_gender
                                                             ');
    $QstatAnalyseCustomersWomen->bindValue(':customers_gender', 'f');

    $QstatAnalyseCustomersWomen->execute();

    if (!\is_null($QstatAnalyseCustomersWomen->valueDecimal('numberByGenderPerCent'))) {
      $numberByPerCentWomen = $QstatAnalyseCustomersWomen->valueDecimal('numberByGenderPerCent');
    }

    return $numberByPerCentWomen;
  }

  /**
   * Generates and returns a formatted HTML string displaying customer statistics categorized by gender
   * if statistical data is available. If no statistics are available, returns an empty string.
   *
   * @return string The formatted HTML string with customer gender statistics, or an empty string if data is unavailable.
   */
  public function display(): string
  {
    if ($this->statsAverageCustomersMen() == 0 && $this->statsAverageCustomersWomen() == 0) {
      $output = '';
    } else {
      $output = '
<div class="col-md-2 col-12">
    <div class="card bg-primary">
     <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('text_customers') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-person-fill text-white"></i>
          </span>
          <span class="float-end">
            <div class="col-sm-12 text-white">' . $this->statsAverageCustomersMen() . '% ' . $this->app->getDef('text_male') . '</div>
            <div class="col-sm-12 text-white">' . $this->statsAverageCustomersWomen() . '% ' . $this->app->getDef('text_female') . '</div>
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