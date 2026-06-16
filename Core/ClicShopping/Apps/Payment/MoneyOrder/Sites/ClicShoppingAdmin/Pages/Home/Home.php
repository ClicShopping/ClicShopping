<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\MoneyOrder\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Payment\MoneyOrder\MoneyOrder;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_MoneyOrder = new MoneyOrder();
    Registry::set('MoneyOrder', $CLICSHOPPING_MoneyOrder);

    $this->app = $CLICSHOPPING_MoneyOrder;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
