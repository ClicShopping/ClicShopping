<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\TopDashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class DashboardMembersB2B implements \ClicShopping\OM\Modules\HooksInterface
{
  /**
   * @var bool|null
   */
  public mixed $app;

  /**
   * Initializes the Groups module by checking its existence in the Registry.
   * If not already set, a new instance of GroupsApp is registered.
   * Also loads the required definitions for the dashboard top members B2B module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Groups')) {
      Registry::set('Groups', new GroupsApp());
    }

    $this->app = Registry::get('Groups');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/TopDashboard/dashboard_top_members_B2B');
  }

  /**
   * Generates the HTML output to display the number of B2B members (customers with member level 0)
   * along with a link to view more details.
   *
   * @return string Returns the HTML content of the output displaying the number of B2B members or an empty string if no members exist.
   */
  public function Display(): string
  {
    $Qmembes = $this->app->db->prepare('select count(customers_id) as count 
                                          from :table_customers
                                          where member_level = 0
                                          ');
    $Qmembes->execute();

    $number_members = $Qmembes->valueInt('count');
    $output = '';

    if ($number_members > 0) {
      $text = $this->app->getDef('text_number_members_b2b');
      $text_view = $this->app->getDef('text_view');

      $output = '
<div class="col-md-2 col-12 m-1">
  <div class="card bg-secondary">
      <div class="card-body">
        <div class="row">
          <h6 class="card-title text-white"><i class="bi bi-person-fill"></i> ' . $text . '</h6>
        </div>
        <div class="col-md-12">
          <span class="text-white"><strong>' . $number_members . '</strong></span>
          <span><small class="text-white">' . HTML::link(CLICSHOPPING::link(null, 'A&Customers\Members&Members'), $text_view, 'class="text-white"') . '</small></span>
      </div>
    </div>
  </div>
</div>
';
    }

    return $output;
  }
}