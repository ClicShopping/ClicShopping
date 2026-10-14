<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\Upgrade\Upgrade;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Upgrade = new Upgrade();
    Registry::set('Upgrade', $CLICSHOPPING_Upgrade);

    $this->app = Registry::get('Upgrade');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
