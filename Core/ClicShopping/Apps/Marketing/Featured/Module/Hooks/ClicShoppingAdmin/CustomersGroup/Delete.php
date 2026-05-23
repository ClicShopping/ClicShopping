<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Module\Hooks\ClicShoppingAdmin\CustomersGroup;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\Featured\Featured as FeaturedApp;

class Delete implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Featured application by checking its presence in the registry.
   * If it does not exist, it is created and registered. Assigns the application
   * instance from the registry to the class property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Featured')) {
      Registry::set('Featured', new FeaturedApp());
    }

    $this->app = Registry::get('Featured');
  }

  /**
   * Deletes all records associated with the specified customer group ID from the products featured table.
   *
   * @param int $group_id The ID of the customer group whose associated records should be deleted.
   * @return void
   */
  private function delete(int $group_id): void
  {
    $QProductsFeaturedCustomersId = $this->app->db->prepare('select count(customers_group_id) as count
                                                               from :table_products_featured
                                                               where customers_group_id = :customers_group_id
                                                               ');
    $QProductsFeaturedCustomersId->bindInt(':customers_group_id', (int)$group_id);
    $QProductsFeaturedCustomersId->execute();

    if ($QProductsFeaturedCustomersId->valueInt('count') > 0) {
      $Qdelete = $this->app->db->prepare('delete
                                             from :table_products_featured
                                             where customers_group_id = :customers_group_id
                                             ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();
    }
  }

  /**
   * Executes the main logic for handling the deletion of an item based on the 'Delete' parameter in the request.
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