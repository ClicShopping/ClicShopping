<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\Administrators\Administrators;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Administrators = new Administrators();
    Registry::set('Administrators', $CLICSHOPPING_Administrators);

    $this->app = $CLICSHOPPING_Administrators;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
