<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxGeoZones\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class TaxGeoZones extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_TaxGeoZones = Registry::get('TaxGeoZones');

    $this->page->setFile('tax_geo_zones.php');
    $this->page->data['action'] = 'TaxGeoZones';

    $CLICSHOPPING_TaxGeoZones->loadDefinitions('Sites/ClicShoppingAdmin/TaxGeoZones');
  }
}