<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\WhosOnline\Classes\ClicShoppingAdmin\ShoppingCartAdmin;
use ClicShopping\Apps\Tools\WhosOnline\WhosOnline;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_WhosOnline = new WhosOnline();
    Registry::set('WhosOnline', $CLICSHOPPING_WhosOnline);

    $this->app = Registry::get('WhosOnline');

    if (!Registry::exists('ShoppingCartAdmin')) {
      $CLICSHOPPING_ShoppingCartAdmin = new ShoppingCartAdmin();
      Registry::set('ShoppingCartAdmin', $CLICSHOPPING_ShoppingCartAdmin);
    }

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
