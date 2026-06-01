<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Orders\ReturnOrders\ReturnOrders;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_ReturnOrders = new ReturnOrders();
    Registry::set('ReturnOrders', $CLICSHOPPING_ReturnOrders);

    $this->app = Registry::get('ReturnOrders');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
