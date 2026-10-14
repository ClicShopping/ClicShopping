<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ModulesHooks\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\ModulesHooks\ModulesHooks;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_ModulesHooks = new ModulesHooks();
    Registry::set('ModulesHooks', $CLICSHOPPING_ModulesHooks);

    $this->app = Registry::get('ModulesHooks');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
