<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\BannerManager;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Insert implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Groups application by checking its existence in the Registry.
   * If not already registered, it registers a new instance of GroupsApp.
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
   * Executes the logic to handle the insertion of customer group data into the banners table.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Insert'])) {
      if (isset($_POST['customers_groups'])) {
        $customers_group_id = HTML::sanitize($_POST['customers_groups']);

        $Qbanners = $this->app->db->prepare('select banners_id
                                               from :table_banners
                                               order by banners_id desc
                                               limit 1
                                              ');
        $Qbanners->execute();

        $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

        $this->app->db->save('banners', $sql_data_array, ['banners_id' => (int)$Qbanners->valueInt('banners_id')]);
      }
    }
  }
}