<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\PageManager;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Insert implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Groups application instance.
   *
   * Checks if an instance of 'Groups' exists in the Registry. If not, creates a new instance of GroupsApp and sets it in the Registry.
   * Retrieves the instance from the Registry and assigns it to the $app property.
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
   * Executes the logic for inserting customer group data into the pages manager table.
   *
   * This method checks if the required `Insert` and `customers_group` parameters are present.
   * It sanitizes the input, retrieves the last page ID from the database,
   * and inserts the specified customer group data into the `pages_manager` table.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Insert'], $_POST['customers_group'])) {
      $customers_group_id = HTML::sanitize($_POST['customers_group']);

      $Qpages = $this->app->db->prepare('select pages_id
                                           from :table_pages_manager
                                           order by pages_id desc
                                           limit 1
                                          ');
      $Qpages->execute();

      $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

      $this->app->db->save('pages_manager', $sql_data_array, ['pages_id' => (int)$Qpages->valueInt('pages_id')]);
    }
  }
}