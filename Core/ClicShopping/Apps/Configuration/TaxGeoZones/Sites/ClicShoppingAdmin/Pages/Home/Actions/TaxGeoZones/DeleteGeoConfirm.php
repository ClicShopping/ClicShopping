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

class DeleteGeoConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('TaxGeoZones');
  }

  public function execute()
  {
    $page = (isset($_GET['spage']) && is_numeric($_GET['spage'])) ? $_GET['spage'] : 1;
    $sID = HTML::sanitize($_GET['sID']);
    $zpage = HTML::sanitize($_GET['zpage']);
    $zID = HTML::sanitize($_GET['zID']);


    $this->app->db->delete('zones_to_geo_zones', ['association_id' => (int)$sID]);

    $this->app->redirect('ListGeo&zpage=' . $zpage . '&zID=' . $zID . '&spage=' . $page);
  }
}