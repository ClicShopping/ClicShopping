<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Communication\Newsletter\Newsletter as NewslettersApp;

class StatsCustomersNewsletterB2bBySex implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Newsletters application.
   * It checks and sets the application in the registry if not already present.
   * Additionally, it loads the necessary definitions for the specified module and hook.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Newsletters')) {
      Registry::set('Newsletters', new NewslettersApp());
    }

    $this->app = Registry::get('Newsletters');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/StatsCustomersNewsletterB2bBySex');
  }

  /**
   * Calculates the percentage of male customers subscribed to the newsletter.
   *
   * This method executes a database query to determine the percentage of customers
   * identified as male ('m') who have subscribed to the newsletter. The result
   * is rounded to two decimal places.
   *
   * @return float|null The percentage of male customers subscribed to the newsletter, or null if the query result is empty.
   */
  private function statsNewsletterCustomersMen(): ?float
  {
    $QstatAnalyseCustomersMan = $this->app->db->prepare('select ROUND(((COUNT(customers_id)/(SELECT COUNT(customers_id) FROM :table_customers))*100),2) AS avgage
                                                           from :table_customers
                                                           where customers_gender = :customers_gender
                                                           and customers_newsletter = 1
                                                           and customers_group_id > 0
                                                         ');
    $QstatAnalyseCustomersMan->bindValue(':customers_gender', 'm');

    $QstatAnalyseCustomersMan->execute();

    if (!\is_null($QstatAnalyseCustomersMan->valueDecimal('avgage'))) {
      $statAnalyseCustomersMan = $QstatAnalyseCustomersMan->valueDecimal('avgage');
      return $statAnalyseCustomersMan;
    }
  }

  /**
   * Analyzes the percentage of female customers who are subscribed to the newsletter
   * and belong to a specific customer group.
   *
   * @return float|null Returns the calculated percentage as a float if available, otherwise null.
   */
  private function statsNewsletterCustomersWomen(): ?float
  {
    $QstatAnalyseCustomersWomen = $this->app->db->prepare('select ROUND(((COUNT(customers_id)/(SELECT COUNT(customers_id) FROM :table_customers))*100),2) AS avgage
                                                              from :table_customers
                                                              where customers_gender = :customers_gender
                                                              and customers_newsletter = 1
                                                              and customers_group_id > 0
                                                             ');
    $QstatAnalyseCustomersWomen->bindValue(':customers_gender', 'f');

    $QstatAnalyseCustomersWomen->execute();

    if (!\is_null($QstatAnalyseCustomersWomen->valueDecimal('avgage'))) {
      $statAnalyseCustomersWomen = $QstatAnalyseCustomersWomen->valueDecimal('avgage');
      return $statAnalyseCustomersWomen;
    }
  }

  /**
   * Renders and returns the HTML output for the newsletter statistics card.
   *
   * @return string The HTML content of the newsletter statistics card, including
   *                statistical data about male and female newsletter customers.
   *                Returns `false` if the newsletter module is disabled.
   */
  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_NEWSLETTER_NL_STATUS') || CLICSHOPPING_APP_NEWSLETTER_NL_STATUS == 'False') {
      return false;
    }

    $output = '
  <div class="col-md-2 m-1">
    <div class="card cardStatsSuccess">
      <h4 class="card-title StatsTitle">' . $this->app->getDef('text_average_newsletter') . '</h4>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class=""bi bi-person-fill"></i>
          </span>
          <span class="float-end">
            <div class="StatsValue">' . $this->statsNewsletterCustomersMen() . '% ' . $this->app->getDef('text_male') . '</div>
            <div class="StatsValue">' . $this->statsNewsletterCustomersWomen() . '% ' . $this->app->getDef('text_female') . '</div>
          </span>
        </div>
      </div>
    </div>
  </div>
      ';

    return $output;
  }
}