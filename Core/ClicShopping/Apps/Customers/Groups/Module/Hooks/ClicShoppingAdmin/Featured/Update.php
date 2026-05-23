<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Featured;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes an instance of the class and sets up the required application registry.
   *
   * Checks if the 'Groups' registry key exists. If it does not exist, it creates a new instance
   * of the GroupsApp and stores it in the registry. Finally, assigns the 'Groups' registry
   * instance to the app property of the class.
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
   * Executes the update operation for a featured product with customer group details.
   *
   * This method checks for the existence of the 'Update' GET parameter as well as
   * the 'customers_group' and 'products_featured_id' POST parameters. If all are set,
   * it sanitizes the inputs, constructs the required query data array, and then updates
   * the 'products_featured' table in the database with the provided information.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update'])) {
      if (isset($_POST['customers_group']) && isset($_POST['products_featured_id'])) {
        $customers_group_id = HTML::sanitize($_POST['customers_group']);

        $products_featured_id = HTML::sanitize($_POST['products_featured_id']);

        $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

        $this->app->db->save('products_featured', $sql_data_array, ['products_featured_id' => (int)$products_featured_id]);
      }
    }
  }
}