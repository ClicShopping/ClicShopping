<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class RemoveProduct implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Groups application instance by checking if it exists in the registry.
   * If not found, it sets a new instance of GroupsApp in the registry and retrieves it.
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
   * Removes groups associated with a specific product ID from the database.
   *
   * @param int $id The ID of the product whose groups are to be removed.
   * @return void
   */
  private function removeGroups($id)
  {

    if (isset($_POST['remove_id']) && !empty($_POST['remove_id'])) {
      $this->app->db->delete('products_groups', ['products_id' => (int)$id]);
    }
  }

  /**
   * Executes the removal of groups associated with a product.
   *
   * This method checks if the product ID is provided in the parameters, sanitizes it,
   * and then calls the method to remove the associated groups.
   *
   * @param array $parameters An associative array containing 'products_id'.
   * @return mixed Returns true if the operation was successful, false otherwise.
   */
  public function execute(array $parameters): mixed
  {
    $products_id = $parameters['products_id'] ?? null;

    if (empty($products_id)) {
      return false;
    }

    $products_id = HTML::sanitize($products_id);

    $this->removeGroups($products_id);

    return true;
  }
}