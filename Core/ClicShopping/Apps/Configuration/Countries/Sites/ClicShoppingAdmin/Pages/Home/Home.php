<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Countries\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\Countries\Countries;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Countries = new Countries();
    Registry::set('Countries', $CLICSHOPPING_Countries);

    $this->app = $CLICSHOPPING_Countries;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
