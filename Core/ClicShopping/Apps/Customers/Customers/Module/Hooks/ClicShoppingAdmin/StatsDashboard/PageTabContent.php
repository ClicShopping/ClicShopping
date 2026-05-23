<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Module\Hooks\ClicShoppingAdmin\StatsDashboard;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Customers\Customers as CustomersApp;

class PageTabContent implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method initializes the CustomersApp class within the Registry if not already set and loads necessary definitions.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $this->app = Registry::get('Customers');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/StatsDashboard/page_tab_content');
  }

  /**
   * Counts the total number of customers, optionally filtered by group.
   *
   * @param string|null $groups The customer group to filter by. Use 'B2C' for individual customers (group ID = 0)
   *                            or any other value for non-individual customers (group ID > 0). If null, count all customers.
   */
  private function statsCountCustomers(string|null $groups = null)
  {
    $condition = '';

    if (!\is_null($groups)) {
      if ($groups == 'B2C') {
        $condition = 'where customers_group_id = 0';
      } else {
        $condition = 'where customers_group_id > 0';
      }
    }

    $Qcustomer = $this->app->db->prepare('select count(customers_id) as count
                                             from :table_customers
                                             ' . $condition . '
                                             limit 1
                                          ');
    $Qcustomer->execute();

    $customers_total = $Qcustomer->valueInt('count');

    return $customers_total;
  }

  /**
   * Calculates and retrieves statistical data about male customers, including the percentage
   * representation of male customers and their average age.
   *
   * @return mixed The percentage of male customers and their average age, concatenated as a string if available,
   *               or 0 if the data is unavailable.
   */
  private function statsAverageCustomersMen()
  {
    $QstatAnalyseCustomersMan = $this->app->db->prepare('select ROUND(((COUNT(customers_id)/(SELECT COUNT(customers_id) FROM :table_customers))*100),2) AS numberByGenderPerCent,
                                                                  ROUND(AVG(TIMESTAMPDIFF(YEAR,(customers_dob), now())),0) AS avgage
                                                          from :table_customers
                                                          where customers_gender = :customers_gender
                                                         ');
    $QstatAnalyseCustomersMan->bindValue(':customers_gender', 'm');

    $QstatAnalyseCustomersMan->execute();

    if (!\is_null($QstatAnalyseCustomersMan->valueDecimal('numberByGenderPerCent'))) {
      $numberByGenderPerCent = $QstatAnalyseCustomersMan->valueDecimal('numberByGenderPerCent');
    } else {
      $numberByGenderPerCent = '-- ';
    }

    if (!\is_null($QstatAnalyseCustomersMan->valueDecimal('avgage'))) {
      $avgage = $QstatAnalyseCustomersMan->valueDecimal('avgage');
      $stat_analyse_customers_man = $numberByGenderPerCent . '% <br />' . $avgage . ' ' . $this->app->getDef('text_year');
    } else {
      $stat_analyse_customers_man = 0;
    }

    return $stat_analyse_customers_man;
  }

  /**
   * Calculates and retrieves statistical data for female customers, including
   * the percentage of female customers and their average age.
   *
   * @return mixed A formatted string containing the percentage of female customers
   *               and their average age, or 0 if the data is unavailable.
   */
  private function statsAverageCustomersWomen()
  {
    $QstatAnalyseCustomersWoman = $this->app->db->prepare('SELECT ROUND(((COUNT(customers_gender)/(SELECT COUNT(customers_id) FROM :table_customers))*100),2) AS numberByGenderPerCent,
                                                                  ROUND(AVG(TIMESTAMPDIFF(YEAR,(customers_dob), now())),0) AS avgage
                                                             from :table_customers
                                                             where customers_gender = :customers_gender
                                                            ');

    $QstatAnalyseCustomersWoman->bindValue(':customers_gender', 'f');
    $QstatAnalyseCustomersWoman->execute();
    $stat_analyse_customers_woman = $QstatAnalyseCustomersWoman->fetch();

    if ($stat_analyse_customers_woman['numberByGenderPerCent'] != 'null') {
      $numberByGenderPerCent = $stat_analyse_customers_woman['numberByGenderPerCent'];
    } else {
      $numberByGenderPerCent = '-- ';
    }

    if (!\is_null($stat_analyse_customers_woman['avgage'])) {
      $avgage = $stat_analyse_customers_woman['avgage'];
      $stat_analyse_customers_woman = $numberByGenderPerCent . '% <br />' . $avgage . ' ' . $this->app->getDef('text_year');
    } else {
      $stat_analyse_customers_woman = 0;
    }

    return $stat_analyse_customers_woman;
  }


  /**
   * Displays customer statistics and information in an HTML format.
   *
   * @return string|false The formatted HTML content with customer statistics or false if the application status is disabled.
   */
  public function display()
  {

    if (!\defined('CLICSHOPPING_APP_CUSTOMERS_CS_STATUS') || CLICSHOPPING_APP_CUSTOMERS_CS_STATUS == 'False') {
      return false;
    }

    if ($this->statsCountCustomers() != 0) {
      $content = '
        <div class="row">
          <div class="col-md-11 mainTable">
            <div class="form-group row">
              <label for="' . $this->app->getDef('box_entry_customers') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Customers') . '">' . $this->app->getDef('box_entry_customers') . '</a></label>
              <div class="col-md-3">
                ' . $this->statsCountCustomers() . '
              </div>
            </div>
          </div>
        </div>
        ';

      if ($this->statsCountCustomers('B2C') != 0) {
        $content .= '
          <div class="row">
            <div class="col-md-11 mainTable">
              <div class="form-group row">
                <label for="' . $this->app->getDef('text_entry_customers_b2c') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Customers') . '">' . $this->app->getDef('text_entry_customers_b2c') . '</a></label>
                <div class="col-md-3">
                  ' . $this->statsCountCustomers('B2C') . '
                </div>
              </div>
            </div>
          </div>
         ';
      }

      if ($this->statsCountCustomers('B2B') != 0) {
        $content .= '
           <div class="row">
            <div class="col-md-11 mainTable">
              <div class="form-group row">
                <label for="' . $this->app->getDef('text_entry_customers_b2b') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Customers') . '">' . $this->app->getDef('text_entry_customers_b2b') . '</a></label>
                <div class="col-md-3">
                  ' . $this->statsCountCustomers('B2B') . '
                </div>
              </div>
            </div>
          </div>
          ';
      }

      if ($this->statsAverageCustomersMen() != 0) {
        $content .= '
          <div class="row">
            <div class="col-md-11 mainTable">
              <div class="form-group row">
                <label for="' . $this->app->getDef('text_stats_man') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Customers') . '">' . $this->app->getDef('text_stats_man') . '</a></label>
                <div class="col-md-3">
                  ' . $this->statsAverageCustomersMen() . '
                </div>
              </div>
            </div>
          </div>
         ';
      }

      if ($this->statsAverageCustomersWomen() != 0) {
        $content .= '
          <div class="row">
            <div class="col-md-11 mainTable">
              <div class="form-group row">
                <label for="' . $this->app->getDef('text_stats_woman') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Customers') . '">' . $this->app->getDef('text_stats_woman') . '</a></label>
                <div class="col-md-3">
                  ' . $this->statsAverageCustomersWomen() . '
                </div>
              </div>
            </div>
          </div>
          ';
      }


      $output = <<<EOD
  <!-- ######################## -->
  <!--  Start Customer      -->
  <!-- ######################## -->
             {$content}
  <!-- ######################## -->
  <!--  Start Customer      -->
  <!-- ######################## -->
EOD;
      return $output;
    }
  }
}