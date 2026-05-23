<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\PageManager;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method that initializes the Groups application object.
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
   * Executes the update operation for a specified customer group associated with a page.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update'], $_POST['customers_group'])) {
      $customers_group_id = HTML::sanitize($_POST['customers_group']);

      $pages_id = HTML::sanitize($_POST['pages_id']);

      $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

      $this->app->db->save('pages_manager', $sql_data_array, ['pages_id' => (int)$pages_id]);
    }
  }
}