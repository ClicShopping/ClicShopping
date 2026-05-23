<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Specials;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Groups application.
   *
   * Checks if the 'Groups' key exists in the Registry. If it does not exist,
   * a new instance of GroupsApp is created and added to the Registry.
   * Finally, assigns the 'Groups' app instance from the Registry to the class.
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
   * Executes the method logic for handling updates to the specials data.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update'])) {
      if (isset($_POST['customers_group'])) {
        $customers_group_id = HTML::sanitize($_POST['customers_group']);

        $specials_id = HTML::sanitize($_POST['specials_id']);

        $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

        $this->app->db->save('specials', $sql_data_array, ['specials_id' => (int)$specials_id]);
      }
    }
  }
}