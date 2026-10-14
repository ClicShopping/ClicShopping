<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Report\StatsProductsNotification\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Report\StatsProductsNotification\StatsProductsNotification;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_StatsProductsNotification = new StatsProductsNotification();
    Registry::set('StatsProductsNotification', $CLICSHOPPING_StatsProductsNotification);

    $this->app = Registry::get('StatsProductsNotification');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
