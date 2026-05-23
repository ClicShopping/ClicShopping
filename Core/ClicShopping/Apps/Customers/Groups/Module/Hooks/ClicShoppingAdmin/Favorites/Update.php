<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Favorites;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method that initializes the Groups application.
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
   * Executes the update functionality for customer groups and favorite products.
   * Updates the 'products_favorites' database table with sanitized data if required conditions are met.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update'])) {
      if (isset($_POST['customers_group']) && isset($_POST['products_favorites_id'])) {
        $customers_group_id = HTML::sanitize($_POST['customers_group']);

        $products_favorites_id = HTML::sanitize($_POST['products_favorites_id']);

        $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

        $this->app->db->save('products_favorites', $sql_data_array, ['products_favorites_id' => (int)$products_favorites_id]);
      }
    }
  }
}