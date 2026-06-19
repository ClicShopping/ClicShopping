<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Module\Hooks\ClicShoppingAdmin\CustomersGroup;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Marketing\Specials\Specials as SpecialsApp;

class Delete implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Specials application.
   *
   * Ensures that the 'Specials' registry entry exists by creating a new instance of SpecialsApp if not already set.
   * Retrieves the 'Specials' application instance from the registry and assigns it to the app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Specials')) {
      Registry::set('Specials', new SpecialsApp());
    }

    $this->app = Registry::get('Specials');
  }

  /**
   * Deletes records associated with the specified customer group ID from the specials table.
   *
   * @param int $group_id The ID of the customer group whose associated records will be deleted.
   * @return void
   */
  private function delete(int $group_id): void
  {
    $QspecialsProductsCustomersId = $this->app->db->prepare('select count(customers_group_id) as count
                                                               from :table_specials
                                                               where customers_group_id = :customers_group_id
                                                              ');
    $QspecialsProductsCustomersId->bindInt(':customers_group_id', (int)$group_id);
    $QspecialsProductsCustomersId->execute();

    if ($QspecialsProductsCustomersId->valueInt('count') > 0) {

      $Qdelete = $this->app->db->prepare('delete
                                            from :table_specials
                                            where customers_group_id = :customers_group_id
                                          ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();
    }
  }

  /**
   * Executes the necessary actions based on the presence of the 'Delete' parameter in the GET request.
   *
   * If the 'Delete' parameter is set, it retrieves and sanitizes the value of 'cID' from the GET request,
   * then calls the delete method with the sanitized value.
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