<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Specials;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;
use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class CustomerGroup implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Groups application by checking if it exists in the Registry.
   * If not, it creates and registers it. Assigns the registered Groups application
   * instance to the $app property.
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
   * Retrieves the customer group ID associated with a specific special ID.
   *
   * This method checks whether the `sID` parameter is present in the GET request.
   * If present, a database query is executed to fetch the `customers_group_id`
   * from the `specials` table using the given special ID.
   *
   * @return int|null The customer group ID if found, or null otherwise.
   */
  private function getCustomerGroupId()
  {
    if (isset($_GET['sID'])) {
      $Qspecials = $this->app->db->prepare('select customers_group_id
                                           from :table_specials
                                           where specials_id = :specials_id
                                          ');
      $Qspecials->bindInt('specials_id', $_GET['sID']);
      $Qspecials->execute();

      return $Qspecials->valueInt('customers_group_id');
    }
  }

  /**
   * Displays the customer group selection form if the necessary conditions are met.
   *
   * @return string|false Returns the generated customer group selection HTML output when the application status and mode are enabled; otherwise, returns false.
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
        $content .= HTML::selectMenu('customers_group', GroupsB2BAdmin::getAllGroups(), $this->getCustomerGroupId());
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
