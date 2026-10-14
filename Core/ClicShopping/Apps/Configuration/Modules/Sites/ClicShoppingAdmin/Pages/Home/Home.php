<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Modules\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\Modules\Modules;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Modules = new Modules();
    Registry::set('Modules', $CLICSHOPPING_Modules);

    $this->app = $CLICSHOPPING_Modules;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
