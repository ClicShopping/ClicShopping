<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatus\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\OrdersStatus\OrdersStatus;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_OrdersStatus = new OrdersStatus();
    Registry::set('OrdersStatus', $CLICSHOPPING_OrdersStatus);

    $this->app = $CLICSHOPPING_OrdersStatus;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
