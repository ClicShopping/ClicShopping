<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Zones\Sites\ClicShoppingAdmin\Pages\Home\Actions\Zones;

use ClicShopping\Apps\Configuration\Zones\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\Registry;

class AllFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Zones');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {

        $Qzones = $this->app->db->prepare('select zone_status
                                            from :table_zones
                                            where zone_id = :zone_id
                                           ');

        $Qzones->bindInt(':zone_id', $id);
        $Qzones->execute();


        if ($Qzones->valueInt('zone_status') == 1) {
          Status::getZonesStatus($id, 0);
        } else {
          Status::getZonesStatus($id, 1);
        }
      }
    }

    $this->app->redirect('Zones&page=' . $page);
  }
}