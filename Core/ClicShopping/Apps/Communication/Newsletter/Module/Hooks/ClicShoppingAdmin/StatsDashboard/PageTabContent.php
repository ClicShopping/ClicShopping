<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\Hooks\ClicShoppingAdmin\StatsDashboard;

use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Communication\Newsletter\Newsletter as NewsletterApp;

class PageTabContent implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Newsletter module.
   *
   * This method checks if the 'Newsletter' instance exists in the Registry.
   * If not, it creates and registers a new instance of the NewsletterApp.
   * It also sets the application instance and loads the necessary definitions for the module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Newsletter')) {
      Registry::set('Newsletter', new NewsletterApp());
    }

    $this->app = Registry::get('Newsletter');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/StatsDashboard/page_tab_content');
  }

  /**
   * Counts the number of customers who are subscribed to the newsletter.
   *
   * This method performs a database query to count all customers who have
   * opted in to receive newsletters. The count is based on the value of the
   * `customers_newsletter` field in the customers table.
   *
   * @return int The total number of customers subscribed to the newsletter.
   */
  private function statsCountCustomersNewsletter()
  {
    $QcustomersNewsletter = $this->app->db->prepare('select count(customers_id) as count
                                                       from :table_customers
                                                       where customers_newsletter = 1
                                                       limit 1
                                                      ');
    $QcustomersNewsletter->execute();

    $customers_total_newsletter = $QcustomersNewsletter->valueInt('count');

    return $customers_total_newsletter;
  }

// Nbr de clients total

  /**
   * Counts the number of B2C customers who are subscribed to the newsletter.
   *
   * This method queries the database to calculate the total number of customers
   * with a group ID of 0 (indicating B2C customers) and who have opted in for
   * the newsletter subscription.
   *
   * @return int The total count of B2C customers subscribed to the newsletter.
   */
  private function statCountCustomersB2C()
  {
    $Qcustomer = $this->app->db->prepare('select count(customers_id) as count
                                            from :table_customers
                                            where customers_group_id = 0
                                            and customers_newsletter = 1
                                            limit 1
                                           ');
    $Qcustomer->execute();

    $customers_total = $Qcustomer->valueInt('count');

    return $customers_total;
  }

// Average newlstter subcribers vs total customers

  /**
   * Calculates the average percentage of B2C customers who are subscribed to the newsletter.
   *
   * @return string The calculated average as a percentage with two decimal precision, followed by a percent sign. Returns nothing if one of the counts is zero.
   */
  private function statAverageCustomersNewsletterB2C()
  {
    if ($this->statCountCustomersB2C() > 0 && $this->statsCountCustomersNewsletter() > 0) {
      $Average = round(($this->statCountCustomersB2C() / $this->statsCountCustomersNewsletter()) * 100, 2) . ' %';
      return $Average;
    }
  }

// Nbr de clients total

  /**
   * Retrieves the total number of B2B customers who are subscribed to the newsletter.
   *
   * The method executes a database query to count all customers whose `customers_group_id`
   * is greater than 0 and `customers_newsletter` is set to 1. The result is limited to a
   * single record, capturing the total count of matching customers.
   *
   * @return int The total number of B2B customers subscribed to the newsletter.
   */
  private function statCountCustomersB2B()
  {
    $Qcustomer = $this->app->db->prepare('select count(customers_id) as count
                                             from :table_customers
                                             where customers_group_id > 0
                                             and customers_newsletter = 1
                                             limit 1
                                            ');
    $Qcustomer->execute();

    $customers_total = $Qcustomer->valueInt('count');

    return $customers_total;
  }

// Average newlstter subcribers vs total customers

  /**
   * Calculates the average percentage of B2B customers subscribed to the newsletter.
   *
   * @return string The average percentage of B2B customers subscribed to the newsletter, formatted with '%' and rounded to two decimal places. Returns nothing if the required counts are zero.
   */
  public function statAverageCustomersNewsletterB2B()
  {
    if ($this->statCountCustomersB2B() > 0 && $this->statsCountCustomersNewsletter() > 0) {
      $Average = round(($this->statCountCustomersB2B() / $this->statsCountCustomersNewsletter()) * 100, 2) . ' %';

      return $Average;
    }
  }

  /**
   * Generates and returns the HTML content for displaying newsletter-related statistics,
   * including counts and averages for various customer categories (e.g., B2C, B2B),
   * based on application settings and configurations.
   *
   * @return false|string Returns a string containing the generated HTML output if the application
   *                      is properly configured and statistical data is available; otherwise, returns false.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_CUSTOMERS_CS_STATUS') || CLICSHOPPING_APP_CUSTOMERS_CS_STATUS == 'False') {
      return false;
    }

    if ($this->statsCountCustomersNewsletter() != 0) {
      $content = '
         <div class="row">
          <div class="col-md-11">
            <div class="form-group row">
              <label for="' . $this->app->getDef('box_text_newsletter') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Newsletter') . '">' . $this->app->getDef('box_text_newsletter') . '</a></label>
              <div class="col-md-3">
                ' . $this->statsCountCustomersNewsletter() . '
              </div>
            </div>
          </div>
        </div>
        ';

      $content .= '
         <div class="row">
          <div class="col-md-11">
            <div class="form-group row">
              <label for="' . $this->app->getDef('box_entry_newsletter_b2c') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Newsletter') . '">' . $this->app->getDef('box_entry_newsletter_b2c') . '</a></label>
              <div class="col-md-3">
                ' . $this->statAverageCustomersNewsletterB2C() . '
              </div>
            </div>
          </div>
        </div>
        ';

      if (MODE_B2B_B2C == 'True' || MODE_B2B == 'true') {
        $content .= '
           <div class="row">
            <div class="col-md-11">
              <div class="form-group row">
                <label for="' . $this->app->getDef('box_entry_newsletter_b2b') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Newsletter') . '">' . $this->app->getDef('box_entry_newsletter_b2b') . '</a></label>
                <div class="col-md-3">
                  ' . $this->statAverageCustomersNewsletterB2B() . '
                </div>
              </div>
            </div>
          </div>
          ';
      }

//        $output .= '<div class="col-md-11 mainTable"></div>';

      $output = <<<EOD
  <!-- ######################## -->
  <!--  Start NewsletterAnonymous      -->
  <!-- ######################## -->
             {$content}
              <div class="col-md-11 mainTable"></div>
  <!-- ######################## -->
  <!--  Start NewsletterAnonymous      -->
  <!-- ######################## -->
EOD;
      return $output;
    }
  }
}