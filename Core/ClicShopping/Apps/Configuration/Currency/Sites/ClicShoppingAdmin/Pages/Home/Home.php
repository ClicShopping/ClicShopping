<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Currency\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\Currency\Currency;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Currency = new Currency();
    Registry::set('Currency', $CLICSHOPPING_Currency);

    $this->app = $CLICSHOPPING_Currency;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
