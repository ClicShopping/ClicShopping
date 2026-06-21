<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Specials;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Insert implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Groups application.
   *
   * Checks if the 'Groups' instance exists within the Registry.
   * If it does not exist, a new instance of GroupsApp is created and registered.
   * Retrieves the 'Groups' instance from the Registry and assigns it to the app property.
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
   * Executes the method logic to handle the insertion of customer group data into the specials table.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Insert'])) {
      if (isset($_POST['customers_group'])) {
        $customers_group_id = HTML::sanitize($_POST['customers_group']);

        $Qspecials = $this->app->db->prepare('select specials_id
                                                 from :table_specials
                                                 order by specials_id desc
                                                 limit 1
                                                ');
        $Qspecials->execute();

        $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

        $this->app->db->save('specials', $sql_data_array, ['specials_id' => (int)$Qspecials->valueInt('specials_id')]);
      }
    }
  }
}