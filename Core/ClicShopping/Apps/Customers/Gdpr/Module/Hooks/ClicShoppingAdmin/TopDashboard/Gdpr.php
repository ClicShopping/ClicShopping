<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Module\Hooks\ClicShoppingAdmin\TopDashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Gdpr\Gdpr as GdprApp;

class Gdpr implements HooksInterface
{
  /**
   * @var bool|null
   */
  public mixed $app;

  /**
   * Initializes the Gdpr application module and loads its definitions.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Gdpr')) {
      Registry::set('Gdpr', new GdprApp());
    }

    $this->app = Registry::get('Gdpr');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/TopDashboard/gdpr');
  }

  /**
   * Generates and displays the GDPR statistics widget for the admin dashboard.
   *
   * The method retrieves customer data from the database to calculate the number of customers
   * who meet GDPR-related conditions based on the configuration settings. If applicable,
   * it constructs an HTML block containing the relevant information to display on the admin dashboard.
   *
   * @return string The HTML output of the GDPR statistics widget. Returns an empty string if no GDPR-related data is available or if the feature is disabled.
   */
  public function Display(): string
  {
    if (!\defined('CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_STATUS') || CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_STATUS == 'False') {
      return false;
    }

    $date = date('Y-m-d', strtotime('- ' . CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_DATE . ' days'));

    $QstatGdpr = $this->app->db->prepare('select c.customers_id,
                                            datediff(now(), ci.customers_info_date_of_last_logon) as datediff
                                            from :table_customers c,
                                                 :table_customers_info ci
                                            where c.gdpr = 0
                                            and c.customers_id = ci.customers_info_id
                                           ');

    $QstatGdpr->execute();

    $count = 0;

    while ($QstatGdpr->fetch()) {
      if ($QstatGdpr->value('datediff') > (int)CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_DATE) {
        ++$count;
      }
    }

    $text_gdpr = $this->app->getDef('text_number_gdpr');
    $text_view = $this->app->getDef('text_view');
    $output = '';

    if ($count > 0) {
      $output = '
<div class="col-md-2 col-12 m-1">
    <div class="card bg-warning">
      <div class="card-body">
        <div class="row">
          <div class="col-md-12">
            <div class="row">
            <span class="col-sm-10"><h6 class="card-title text-white"><i class="bi bi-headset"  style="font-size: 1.3rem;"></i> ' . $text_gdpr . '<br></h6></span>         
            </div>
          </div> 
        </div>
        <div class="col-md-12">
          <span class="text-white"><strong>' . $count . '</strong></span>
          <span><small class="text-white">' . HTML::link(CLICSHOPPING::link(null, 'A&Customers\Gdpr&Gdpr'), $text_view, 'class="text-white"') . '</small></span>
        </div>
      </div>
    </div>
</div>
';
    }

    return $output;
  }
}