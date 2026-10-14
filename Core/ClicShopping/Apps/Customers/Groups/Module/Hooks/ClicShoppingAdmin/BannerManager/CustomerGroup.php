<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\BannerManager;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;

class CustomerGroup implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Groups application.
   *
   * Checks if the 'Groups' object exists in the registry. If not, it creates
   * and registers a new instance of GroupsApp. Then it retrieves the 'Groups'
   * object from the registry and assigns it to the $app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Groups')) {
      Registry::set('Groups', new GroupsApp());
    }

    $this->app = Registry::get('Groups');
  }

  /**
   * Retrieves the customer group ID associated with a specific banner ID, if provided.
   * Queries the database to fetch the `customers_group_id` from the banners table
   * based on the banner ID passed via the `bID` parameter in the GET request.
   *
   * @return int|null Returns the customer group ID if found, or null if the `bID` parameter is not set or no matching record is found.
   */
  private function getCustomerGroupId()
  {
    if (isset($_GET['bID'])) {
      $Qbanners = $this->app->db->prepare('select customers_group_id
                                             from :table_banners
                                             where banners_id = :banners_id
                                            ');
      $Qbanners->bindInt('banners_id', $_GET['bID']);
      $Qbanners->execute();

      return $Qbanners->valueInt('customers_group_id');
    }
  }

  /**
   * Displays customer group UI content if the module is enabled and B2B mode is active.
   *
   * @return string|bool Returns the generated HTML content for the customer group UI when conditions are met.
   *                     Returns false if the module status is disabled or conditions are not satisfied.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS') || CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS == 'False') {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/CustomerGroup/customer_group');

    if (CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS == 'True' && !empty(CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS)) {
      if (MODE_B2B_B2C == 'True') {

        $content = '<div class="row">';
        $content .= '<div class="col-md-5">';
        $content .= '<div class="form-group row">';
        $content .= '<label for="' . $this->app->getDef('text_customer_group') . '" class="col-5 col-form-label">' . $this->app->getDef('text_customer_group') . '</label>';
        $content .= '<div class="col-md-5">';
        $content .= HTML::selectMenu('customers_group_id', GroupsB2BAdmin::getAllGroups(), $this->getCustomerGroupId());
        $content .= '</div>';
        $content .= '</div>';
        $content .= '</div>';
        $content .= '</div>';

        $title = $this->app->getDef('text_group');

        $output = <<<EOD
<!-- ######################## -->
<!--  Start CustomersGroup      -->
<!-- ######################## -->
        <div class="mt-1"></div>
        <div class="mainTitle">
          <span class="col-md-10">{$title}</span>
        </div>
        <div class="adminformTitle">
           {$content}
        </div>
<!-- ######################## -->
<!--  Start CustomersGroup      -->
<!-- ######################## -->
EOD;
        return $output;
      }
    }
  }
}
