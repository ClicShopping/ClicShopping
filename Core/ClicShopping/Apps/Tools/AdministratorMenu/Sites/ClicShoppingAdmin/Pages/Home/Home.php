<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\AdministratorMenu\AdministratorMenu;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_AdministratorMenu = new AdministratorMenu();
    Registry::set('AdministratorMenu', $CLICSHOPPING_AdministratorMenu);

    $this->app = $CLICSHOPPING_AdministratorMenu;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
