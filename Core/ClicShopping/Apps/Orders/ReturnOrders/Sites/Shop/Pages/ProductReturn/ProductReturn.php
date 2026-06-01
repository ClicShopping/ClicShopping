<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\Shop\Pages\ProductReturn;

use ClicShopping\Apps\Orders\ReturnOrders\ReturnOrders as ReturnOrdersApp;
use ClicShopping\OM\Registry;

class ProductReturn extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    if (!Registry::exists('ReturnOrders')) {
      Registry::set('ReturnOrders', new ReturnOrdersApp());
    }

    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');

    $CLICSHOPPING_ReturnOrders->loadDefinitions('Sites/Shop/main');
  }
}
