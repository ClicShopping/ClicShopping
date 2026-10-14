<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Featured;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Insert implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Groups application by checking and setting its registry entry.
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
   * Executes the process for inserting a customer's group into the products featured database.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Insert'])) {
      if (isset($_POST['customers_group'])) {
        $customers_group_id = HTML::sanitize($_POST['customers_group']);

        $Qfeatured = $this->app->db->prepare('select products_featured_id
                                               from :table_products_featured
                                               order by products_featured_id desc
                                               limit 1
                                              ');
        $Qfeatured->execute();

        $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

        $this->app->db->save('products_featured', $sql_data_array, ['products_featured_id' => (int)$Qfeatured->valueInt('products_featured_id')]);
      }
    }
  }
}