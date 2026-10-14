<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Weight\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\Weight\Weight;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Weight = new Weight();
    Registry::set('Weight', $CLICSHOPPING_Weight);

    $this->app = $CLICSHOPPING_Weight;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
