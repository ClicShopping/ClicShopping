<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Apps\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\Apps\Apps;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Apps = new Apps();
    Registry::set('Apps', $CLICSHOPPING_Apps);

    $this->app = Registry::get('Apps');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
