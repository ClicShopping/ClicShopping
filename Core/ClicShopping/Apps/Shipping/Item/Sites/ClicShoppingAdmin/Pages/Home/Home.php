<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Item\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Shipping\Item\Item;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Item = new Item();
    Registry::set('Item', $CLICSHOPPING_Item);

    $this->app = $CLICSHOPPING_Item;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
