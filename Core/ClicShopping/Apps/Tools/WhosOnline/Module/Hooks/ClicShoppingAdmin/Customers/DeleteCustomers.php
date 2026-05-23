<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline\Module\Hooks\ClicShoppingAdmin\Customers;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\WhosOnline\WhosOnline as WhosOnlineApp;

class DeleteCustomers implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructs the class and ensures the 'WhosOnline' application is registered within the Registry.
   * If 'WhosOnline' is not already registered, it initializes and sets it in the Registry.
   * Retrieves the 'WhosOnline' application instance and assigns it to the class property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('WhosOnline')) {
      Registry::set('WhosOnline', new WhosOnlineApp());
    }

    $this->app = Registry::get('WhosOnline');
  }

  /**
   * Deletes a customer record from the 'whos_online' database table using the specified customer ID.
   *
   * @param int $id The ID of the customer to be deleted.
   * @return void
   */
  private function deleteCustomer(int $id): void
  {
    $this->app->db->delete('whos_online', ['customer_id' => $id]);
  }

  /**
   * Executes the delete operation for customers.
   *
   * If the 'DeleteAll' parameter is present in the GET request, the method checks for selected customer IDs in the POST request.
   * - If multiple IDs are provided in 'selected', all corresponding customers are deleted.
   * - If a single ID is provided, it is sanitized and the corresponding customer is deleted.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['DeleteAll'])) {
      if (isset($_POST['selected'])) {
        foreach ($_POST['selected'] as $id) {
          $this->deleteCustomer($id);
        }
      } else {
        $id = HTML::sanitize($_POST['id']);
        $this->deleteCustomer($id);
      }
    }
  }
}