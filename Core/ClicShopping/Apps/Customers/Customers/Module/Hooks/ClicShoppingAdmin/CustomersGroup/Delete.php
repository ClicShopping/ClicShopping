<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Module\Hooks\ClicShoppingAdmin\CustomersGroup;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Customers\Customers as CustomersApp;

class Delete implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the constructor by checking the existence of the 'Customers' registry.
   * If the 'Customers' registry does not exist, it creates a new instance of CustomersApp and sets it.
   * Assigns the 'Customers' registry instance to the app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $this->app = Registry::get('Customers');
  }

  /**
   * Deletes the group with the specified group ID and updates all customers
   * associated with that group to use a default group ID.
   *
   * @param int $group_id The ID of the group to be deleted.
   * @return void
   */
  private function delete(int $group_id): void
  {
    // update all customers
    $QcustomersId = $this->app->db->prepare('select customers_id
                                               from :table_customers
                                               where customers_group_id = :customers_group_id
                                             ');
    $QcustomersId->bindInt(':customers_group_id', (int)$group_id);
    $QcustomersId->execute();

    while ($QcustomersId->fetch()) {
      $Qupdate = $this->app->db->prepare('update :table_customers
                                            set customers_group_id = :customers_group_id
                                            where customers_id = :customers_id
                                            ');
      $Qupdate->bindValue(':customers_group_id', 1);
      $Qupdate->bindInt(':customers_id', $QcustomersId->valueInt('customers_id'));
      $Qupdate->execute();
    }
  }

  /**
   * Executes the current request. Checks if the 'Delete' parameter is set in the GET request,
   * sanitizes the 'cID' parameter, and calls the delete method with the sanitized ID.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Delete'])) {
      $id = HTML::sanitize($_GET['cID']);
      $this->delete($id);
    }
  }
}