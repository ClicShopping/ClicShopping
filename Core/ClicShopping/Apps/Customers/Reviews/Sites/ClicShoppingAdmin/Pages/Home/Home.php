<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Customers\Reviews\Reviews;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Reviews = new Reviews();
    Registry::set('Reviews', $CLICSHOPPING_Reviews);

    $this->app = Registry::get('Reviews');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
