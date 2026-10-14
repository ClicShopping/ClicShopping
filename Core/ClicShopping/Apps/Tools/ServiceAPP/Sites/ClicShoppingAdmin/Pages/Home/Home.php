<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ServiceAPP\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\ServiceAPP\ServiceAPP;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_ServiceAPP = new ServiceAPP();
    Registry::set('ServiceAPP', $CLICSHOPPING_ServiceAPP);

    $this->app = Registry::get('ServiceAPP');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
