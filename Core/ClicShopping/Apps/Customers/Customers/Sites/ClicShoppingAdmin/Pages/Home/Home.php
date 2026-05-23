<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Customers\Customers\Customers;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Customers = new Customers();
    Registry::set('Customers', $CLICSHOPPING_Customers);

    $this->app = Registry::get('Customers');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
