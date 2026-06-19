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

class Delete implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Groups application by checking its existence in the Registry.
   * If not found, it sets a new instance of GroupsApp in the Registry.
   * Assigns the Groups application from the Registry to the app property.
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
   * Executes the method to handle deletion of product group associations when a product ID is provided in the POST request.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_POST['product_id'])) {
      $product_id = HTML::sanitize($_POST['product_id']);

      $this->app->db->delete('products_groups', ['products_id' => (int)$product_id]);
    }
  }
}