<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\OrderTotal\TotalShipping\TotalShipping;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_TotalShipping = new TotalShipping();
    Registry::set('TotalShipping', $CLICSHOPPING_TotalShipping);

    $this->app = $CLICSHOPPING_TotalShipping;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
