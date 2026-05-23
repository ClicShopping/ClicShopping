<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\Total\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\OrderTotal\Total\Total;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Total = new Total();
    Registry::set('Total', $CLICSHOPPING_Total);

    $this->app = $CLICSHOPPING_Total;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
