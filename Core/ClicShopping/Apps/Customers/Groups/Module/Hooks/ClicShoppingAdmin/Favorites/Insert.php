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

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Groups application and registers it within the Registry
   * if it does not already exist. Retrieves the registered Groups application
   * instance and assigns it to the $app property.
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
   * Executes the logic for inserting a new customer group entry into the 'products_favorites' database table.
   *
   * This method checks if the 'Insert' parameter exists in the GET request and validates the 'customers_group' value
   * sent through the POST request. It retrieves the latest 'products_favorites_id' from the database and inserts
   * a new record with the sanitized 'customers_group_id'.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Insert'])) {
      if (isset($_POST['customers_group'])) {
        $customers_group_id = HTML::sanitize($_POST['customers_group']);

        $Qfavorites = $this->app->db->prepare('select products_favorites_id
                                                 from :table_products_favorites
                                                 order by products_favorites_id desc
                                                 limit 1
                                                ');
        $Qfavorites->execute();

        $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

        $this->app->db->save('products_favorites', $sql_data_array, ['products_favorites_id' => (int)$Qfavorites->valueInt('products_favorites_id')]);
      }
    }
  }
}