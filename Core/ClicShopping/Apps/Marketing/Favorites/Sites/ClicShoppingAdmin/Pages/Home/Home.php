<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Marketing\Favorites\Favorites;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Favorites = new Favorites();
    Registry::set('Favorites', $CLICSHOPPING_Favorites);

    $this->app = $CLICSHOPPING_Favorites;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
