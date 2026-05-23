<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Customers\Customers as CustomersApp;

class StatsCustomersNewsletterBySex implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method initializes the Customers application instance in the Registry and
   * loads the necessary definitions for the stats functionality.
   *
   * @return void
   */
  public function __construct()
  {

    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $this->app = Registry::get('Customers');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/StatsCustomersNewsletterBySex');
  }

  /**
   * Calculates the percentage of male customers subscribed to the newsletter.
   *
   * This method queries the database to compute the percentage of customers
   * who are male and have subscribed to the newsletter. The result is calculated
   * by dividing the count of newsletter-subscribed male customers by the total
   * count of customers, multiplied by 100, and rounded to two decimal places.
   *
   * @return float|null The percentage of male newsletter subscribers as a decimal
   * value if available, or null if the data cannot be retrieved.
   */
  private function statsNewsletterCustomersMen()
  {
    $QstatAnalyseCustomersMan = $this->app->db->prepare('select ROUND(((COUNT(*)/(SELECT COUNT(*) FROM :table_customers))*100),2) AS avgage
                                                          from :table_customers
                                                          where customers_gender = :customers_gender
                                                          and customers_newsletter = 1
                                                         ');
    $QstatAnalyseCustomersMan->bindValue(':customers_gender', 'm');

    $QstatAnalyseCustomersMan->execute();

    if (!\is_null($QstatAnalyseCustomersMan->valueDecimal('avgage'))) {
      $statAnalyseCustomersMan = $QstatAnalyseCustomersMan->valueDecimal('avgage');

      return $statAnalyseCustomersMan;
    }
  }


  /**
   * Retrieves the percentage of female customers who are subscribed to the newsletter.
   *
   * This method calculates the proportion of female customers
   * subscribed to newsletters with respect to the total number
   * of customers. The result is returned as a rounded percentage
   * value with two decimal precision.
   *
   * @return float|null The percentage of female customers subscribed to the newsletter, or null if no data is available.
   *
   * @throws \Exception If a database query error occurs.
   */
  private function statsNewsletterCustomersWomen()
  {
    $QstatAnalyseCustomersWomen = $this->app->db->prepare('select ROUND(((COUNT(*)/(SELECT COUNT(*) FROM :table_customers))*100),2) AS avgage
                                                            from :table_customers
                                                            where customers_gender = :customers_gender
                                                            and customers_newsletter = 1
                                                           ');
    $QstatAnalyseCustomersWomen->bindValue(':customers_gender', 'f');

    $QstatAnalyseCustomersWomen->execute();

    if (!\is_null($QstatAnalyseCustomersWomen->valueDecimal('avgage'))) {
      $statAnalyseCustomersWomen = $QstatAnalyseCustomersWomen->valueDecimal('avgage');
    }

    return $statAnalyseCustomersWomen;
  }


  /**
   * Executes the logic to generate a statistical representation of newsletter subscribers
   * segmented by gender. This method checks the status of the Newsletter application
   * and constructs a formatted HTML output if the application is active.
   *
   * @return string|false Returns the formatted HTML string if the Newsletter application is active, or false otherwise.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_NEWSLETTER_NL_STATUS') || CLICSHOPPING_APP_NEWSLETTER_NL_STATUS == 'False') {
      return false;
    }

    $output = '
        <div class="col-sm-5 col-md-3">
            <span class="col-md-4 float-start">
              <i class="bi bi-person-fill text-warning"></i>
            </span>
            <span class="col-md-8 float-end">
              <div class="col-sm-12 StatsTitle">' . $this->app->getDef('text_average_newsletter') . '</div>
              <div class="col-sm-12 StatsValue">' . $this->statsNewsletterCustomersMen() . '% ' . $this->app->getDef('text_male') . '</div>
              <div class="col-sm-12 StatsValue">' . $this->statsNewsletterCustomersWomen() . '% ' . $this->app->getDef('text_female') . '</div>
            </span>
          </div>
      ';

    return $output;
  }
}