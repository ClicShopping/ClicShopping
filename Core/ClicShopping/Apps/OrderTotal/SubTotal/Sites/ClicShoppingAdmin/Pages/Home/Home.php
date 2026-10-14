<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\SubTotal\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\OrderTotal\SubTotal\SubTotal;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_SubTotal = new SubTotal();
    Registry::set('SubTotal', $CLICSHOPPING_SubTotal);

    $this->app = $CLICSHOPPING_SubTotal;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
