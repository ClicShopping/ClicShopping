<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Module\Hooks\ClicShoppingAdmin\CustomersGroup;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\PageManager\PageManager as PageManagerApp;

class Delete implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the PageManagerApp instance and assigns it to the app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('PageManager')) {
      Registry::set('PageManager', new PageManagerApp());
    }

    $this->app = Registry::get('PageManager');
  }

  /**
   * Deletes all records associated with a specific customer group ID
   * from the pages manager database table if any are found.
   *
   * @param int $group_id The ID of the customer group whose records need to be deleted.
   * @return void
   */
  private function delete(int $group_id): void
  {
    $QpageManagerCustomersId = $this->app->db->prepare("select count(customers_group_id) as count
                                                           from :table_pages_manager
                                                           where customers_group_id = :customers_group_id
                                                         ");
    $QpageManagerCustomersId->bindInt(':customers_group_id', (int)$group_id);
    $QpageManagerCustomersId->execute();

    if ($QpageManagerCustomersId->valueInt('count') > 0) {
      // delete all page manager
      $Qdelete = $this->app->db->prepare('delete
                                                        from :table_pages_manager
                                                        where customers_group_id = :customers_group_id
                                                      ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();
    }
  }

  /**
   * Executes the main functionality of the method.
   * Checks if a 'Delete' parameter is set in the GET request.
   * If 'Delete' is set, sanitizes the provided 'cID' value and performs a delete operation.
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