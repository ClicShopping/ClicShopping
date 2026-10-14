<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxGeoZones\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\TaxGeoZones\TaxGeoZones;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_TaxGeoZones = new TaxGeoZones();
    Registry::set('TaxGeoZones', $CLICSHOPPING_TaxGeoZones);

    $this->app = $CLICSHOPPING_TaxGeoZones;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
