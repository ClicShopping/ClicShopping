<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\PageManager;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;
use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class CustomerGroup implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method initializes the Groups application and sets it in the registry
   * if it does not already exist.
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
   * Retrieves the customer group ID associated with a specific page.
   *
   * This method checks if a page ID (bID) is provided in the GET parameters,
   * queries the database for the corresponding customer group ID, and returns it.
   *
   * @return int|null The customer group ID if found, or null if no ID is provided in the GET parameters.
   */
  private function getCustomerGroupId()
  {
    if (isset($_GET['bID'])) {
      $Qpages = $this->app->db->prepare('select customers_group_id
                                           from :table_pages_manager
                                           where pages_id = :pages_id
                                          ');
      $Qpages->bindInt('pages_id', $_GET['bID']);
      $Qpages->execute();

      return $Qpages->valueInt('customers_group_id');
    }
  }


  /**
   * Displays the customer group selection interface for the admin panel,
   * based on the status of the Customers Groups module and B2B/B2C mode.
   *
   * @return mixed Returns false if the Customers Groups module is disabled
   *               or a formatted HTML string containing the selection interface
   *               if the module and B2B/B2C mode are enabled.
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
