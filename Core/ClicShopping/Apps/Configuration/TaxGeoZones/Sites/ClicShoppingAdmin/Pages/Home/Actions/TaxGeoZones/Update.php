<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxGeoZones\Sites\ClicShoppingAdmin\Pages\Home\Actions\TaxGeoZones;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('TaxGeoZones');
  }

  public function execute()
  {
    $page = (isset($_GET['zpage']) && is_numeric($_GET['zpage'])) ? $_GET['zpage'] : 1;
    $zID = HTML::sanitize($_GET['zID']);
    $geo_zone_name = HTML::sanitize($_POST['geo_zone_name']);
    $geo_zone_description = HTML::sanitize($_POST['geo_zone_description']);

    $this->app->db->save('geo_zones', [
      'geo_zone_name' => $geo_zone_name,
      'geo_zone_description' => $geo_zone_description,
      'last_modified' => 'now()'
    ], [
        'geo_zone_id' => (int)$zID
      ]
    );

    $this->app->redirect('TaxGeoZones&zpage=' . $page . '&zID=' . $zID);
  }
}