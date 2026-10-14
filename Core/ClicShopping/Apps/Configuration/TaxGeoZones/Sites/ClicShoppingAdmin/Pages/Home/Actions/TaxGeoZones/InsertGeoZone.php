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

class InsertGeoZone extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('TaxGeoZones');
  }

  public function execute()
  {
    $page = (isset($_GET['spage']) && is_numeric($_GET['spage'])) ? $_GET['spage'] : 1;
    $zpage = (isset($_GET['zpage']) && is_numeric($_GET['zpage'])) ? $_GET['zpage'] : 1;

    $zID = HTML::sanitize($_GET['zID']);
    $zone_country_id = HTML::sanitize($_POST['country']);
    $zone_id = HTML::sanitize($_POST['state']);

    $this->app->db->save('zones_to_geo_zones', [
        'zone_country_id' => (int)$zone_country_id,
        'zone_id' => (int)$zone_id,
        'geo_zone_id' => (int)$zID,
        'date_added' => 'now()'
      ]
    );

    $new_subzone_id = $this->app->db->lastInsertId();

    $this->app->redirect('ListGeo&zpage=' . $zpage . '&zID=' . $zID . '&spage=' . $page . '&sID=' . $new_subzone_id);
  }
}
