<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Zones\Sites\ClicShoppingAdmin\Pages\Home\Actions\Zones;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Zones');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $zone_country_id = HTML::sanitize($_POST['zone_country_id']);
    $zone_code = HTML::sanitize($_POST['zone_code']);
    $zone_name = HTML::sanitize($_POST['zone_name']);

    $this->app->db->save('zones', [
        'zone_country_id' => (int)$zone_country_id,
        'zone_code' => $zone_code,
        'zone_name' => $zone_name,
        'zone_status' => 0
      ]
    );

    $this->app->redirect('Zones&page=' . $page);
  }
}