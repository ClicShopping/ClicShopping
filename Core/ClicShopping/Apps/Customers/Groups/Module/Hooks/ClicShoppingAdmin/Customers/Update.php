<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Customers;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Groups application by checking the registry and setting it if not already present.
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
   * Executes the update process for customer groups and associated data.
   *
   * This method checks for parameters in the GET request related to customers and updates
   * the `customers_group_id` for a specific customer based on the POST data. It also ensures
   * that outdated customer group references in other tables are removed.
   * The process involves sanitizing input data, validating group availability,
   * and performing database operations.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update'], $_GET['Customers'])) {
      $CLICSHOPPING_Customers = Registry::get('Customers');

      if (isset($_POST['customers_group_id'])) {
        if (isset($_POST['customers_id'])) {
          $customers_group_id = HTML::sanitize($_POST['customers_group_id']);
          if (empty($customers_group_id)) $customers_group_id = 0;

          $customers_id = HTML::sanitize($_POST['customers_id']);

          $QmultipleGroups = $CLICSHOPPING_Customers->db->prepare('select distinct customers_group_id
                                                                     from :table_products_groups
                                                                   ');

          $QmultipleGroups->execute();

          while ($QmultipleGroups->fetch()) {
            $QmultipleCustomers = $CLICSHOPPING_Customers->db->prepare('select distinct customers_group_id
                                                                          from :table_customers_groups
                                                                          where customers_group_id = :customers_group_id
                                                                        ');
            $QmultipleCustomers->bindInt(':customers_group_id', $QmultipleGroups->valueInt('customers_group_id'));
            $QmultipleCustomers->execute();

            if (!($QmultipleCustomers->fetch())) {
              $Qdelete = $CLICSHOPPING_Customers->db->prepare('delete
                                                                from :table_products_groups
                                                                where customers_group_id = :customers_group_id
                                                               ');
              $Qdelete->bindInt(':customers_group_id', $QmultipleGroups->valueInt('customers_group_id'));

              $Qdelete->execute();
            }
          } // end while

          $sql_data_array = ['customers_group_id' => $customers_group_id];

          $this->app->db->save('customers', $sql_data_array, ['customers_id' => (int)$customers_id]);
        }
      }
    }
  }
}