<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\BannerManager;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the GroupsApp instance.
   *
   * If the 'Groups' registry entry does not exist, it creates a new instance of
   * GroupsApp and assigns it to the registry. The instance is then retrieved
   * from the registry and assigned to the `$app` property.
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
   * Performs the execution of the update operation for banners based on the provided
   * `banners_id` and `customers_group_id` from the input data ($_POST).
   *
   * Initiates the database save operation only when the required parameters are provided.
   * The method sanitizes the input values before processing to prevent any potential
   * security vulnerabilities.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update'])) {
      if (isset($_POST['banners_id'])) {
        if (isset($_POST['customers_group_id'])) {
          $customers_group_id = HTML::sanitize($_POST['customers_group_id']);

          if (isset($_POST['banners_id'])) {
            $banners_id = HTML::sanitize($_POST['banners_id']);
            $sql_data_array = ['customers_group_id' => (int)$customers_group_id];

            $this->app->db->save('banners', $sql_data_array, ['banners_id' => (int)$banners_id]);
          }
        }
      }
    }
  }
}