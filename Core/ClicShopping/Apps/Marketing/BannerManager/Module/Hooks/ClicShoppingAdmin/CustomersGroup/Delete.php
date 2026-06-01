<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Module\Hooks\ClicShoppingAdmin\CustomersGroup;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\BannerManager\BannerManager as BannerManagerApp;

class Delete implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the BannerManagerApp instance and sets it in the registry.
   * Ensures that a single instance of the application exists.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('BannerManager')) {
      Registry::set('BannerManager', new BannerManagerApp());
    }

    $this->app = Registry::get('BannerManager');
  }

  /**
   * Deletes banners associated with a specific customer group ID if they exist.
   *
   * @param int $group_id The ID of the customer group whose associated banners need to be deleted.
   * @return void This method does not return any value.
   */
  private function delete(int $group_id): void
  {
    $QbannerCustomersId = $this->app->db->prepare('select count(customers_group_id) as count
                                                     from :table_banners
                                                     where customers_group_id = :customers_group_id
                                                   ');
    $QbannerCustomersId->bindInt(':customers_group_id', (int)$group_id);
    $QbannerCustomersId->execute();

    $banner_customers_id = $QbannerCustomersId->fetch();

    if ($banner_customers_id['count'] > 0) {
      $Qdelete = $this->app->db->prepare('delete from :table_banners
                                                        where customers_group_id = :customers_group_id
                                                       ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();
    }
  }

  /**
   * Executes the necessary operations based on the 'Delete' parameter in the GET request.
   * If the 'Delete' parameter is set, it retrieves and sanitizes the 'cID' value and calls the delete method.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Delete'])) {
      $id = HTML::sanitize($_GET['cID']);
      $this->delete($id);
    }
  }
}