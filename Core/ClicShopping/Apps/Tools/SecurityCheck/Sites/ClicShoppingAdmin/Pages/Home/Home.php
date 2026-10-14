<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\SecurityCheck\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\SecurityCheck\SecurityCheck;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_SecurityCheck = new SecurityCheck();
    Registry::set('SecurityCheck', $CLICSHOPPING_SecurityCheck);

    $this->app = Registry::get('SecurityCheck');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }

}
