<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Marketing\Featured\Featured;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Featured = new Featured();
    Registry::set('Featured', $CLICSHOPPING_Featured);

    $this->app = $CLICSHOPPING_Featured;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
