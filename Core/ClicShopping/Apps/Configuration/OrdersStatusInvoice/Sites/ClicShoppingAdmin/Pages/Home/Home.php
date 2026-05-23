<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatusInvoice\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\OrdersStatusInvoice\OrdersStatusInvoice;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_OrdersStatusInvoice = new OrdersStatusInvoice();
    Registry::set('OrdersStatusInvoice', $CLICSHOPPING_OrdersStatusInvoice);

    $this->app = $CLICSHOPPING_OrdersStatusInvoice;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
