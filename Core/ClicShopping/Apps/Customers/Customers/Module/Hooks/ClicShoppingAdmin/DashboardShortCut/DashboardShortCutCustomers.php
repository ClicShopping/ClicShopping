<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Module\Hooks\ClicShoppingAdmin\DashboardShortCut;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Customers\Customers as CustomersApp;

class DashboardShortCutCustomers implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Customers application module.
   *
   * This method checks if the 'Customers' registry entry exists. If it does not exist,
   * it creates a new instance of CustomersApp and sets it in the registry. Once initialized,
   * it retrieves the application from the registry and loads the required definitions.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $this->app = Registry::get('Customers');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/DashboardShortCut/dashboard_shortcut_customers');
  }

  /**
   * Generates a string containing an HTML link for the Customers module shortcut button in the application's dashboard.
   *
   * @return string Returns the generated HTML link as a string. If the application status is disabled, it will return false.
   */
  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_CUSTOMERS_CS_STATUS') || CLICSHOPPING_APP_CUSTOMERS_CS_STATUS == 'False') {
      return false;
    }

    $output = HTML::link(CLICSHOPPING::link(null, 'A&Customers\Customers&Customers'), null, 'class="btn btn-warning btn-sm" role="button"><span class="bi bi-person-fill" title="' . $this->app->getDef('heading_short_customers') . '"') . ' ';

    return $output;
  }
}