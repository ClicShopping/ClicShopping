<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Orders\Orders\Orders;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new Orders());
    }

    $this->app = Registry::get('Orders');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
