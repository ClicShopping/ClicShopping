<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalTax\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\OrderTotal\TotalTax\TotalTax;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_TotalTax = new TotalTax();
    Registry::set('TotalTax', $CLICSHOPPING_TotalTax);

    $this->app = $CLICSHOPPING_TotalTax;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
