<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\Newsletter\Newsletter as NewslettersApp;

class StatsCustomersNewsletterBySex implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Newsletters application and loads the necessary definitions
   * associated with the customer newsletter statistics by sex.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Newsletters')) {
      Registry::set('Newsletters', new NewslettersApp());
    }

    $this->app = Registry::get('Newsletters');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/StatsCustomersNewsletterBySex');
  }

  /**
   * Calculates the percentage of male customers who are subscribed to the newsletter.
   *
   * @return float|null Returns the percentage as a float, or null if no data is available.
   */
  private function statsNewsletterCustomersMen(): ?float
  {
    $QstatAnalyseCustomersMan = $this->app->db->prepare('select ROUND(((COUNT(customers_id)/(SELECT COUNT(customers_id) FROM :table_customers))*100),2) AS avgage
                                                           from :table_customers
                                                           where customers_gender = :customers_gender
                                                           and customers_newsletter = 1
                                                         ');
    $QstatAnalyseCustomersMan->bindValue(':customers_gender', 'm');

    $QstatAnalyseCustomersMan->execute();

    if (!\is_null($QstatAnalyseCustomersMan->valueDecimal('avgage'))) {
      $statAnalyseCustomersMan = $QstatAnalyseCustomersMan->valueDecimal('avgage');
    }

    return $statAnalyseCustomersMan;
  }

  /**
   * Calculates the percentage of female customers who have subscribed to the newsletter.
   *
   * @return float|null The percentage of female customers subscribed to the newsletter,
   * or null if the calculation cannot be performed.
   */
  private function statsNewsletterCustomersWomen(): ?float
  {
    $QstatAnalyseCustomersWomen = $this->app->db->prepare('select ROUND(((COUNT(customers_id)/(SELECT COUNT(customers_id) FROM :table_customers))*100),2) AS avgage
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
   * Renders and returns the display output for the newsletter statistics,
   * including percentages of male and female subscribers.
   *
   * @return string The HTML representation of the newsletter statistics card.
   */
  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_NEWSLETTER_NL_STATUS') || CLICSHOPPING_APP_NEWSLETTER_NL_STATUS == 'False') {
      return false;
    }

    $output = '
  <div class="col-md-2 m-1">
    <div class="card cardStatsPrimary">
      <h4 class="card-title StatsTitle">' . $this->app->getDef('text_average_newsletter') . '</h4>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-person-fill"></i>
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