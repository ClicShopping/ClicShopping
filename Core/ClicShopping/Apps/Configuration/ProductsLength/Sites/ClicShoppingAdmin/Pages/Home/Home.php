<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsLength\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\ProductsLength\ProductsLength;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_ProductsLength = new ProductsLength();
    Registry::set('ProductsLength', $CLICSHOPPING_ProductsLength);

    $this->app = $CLICSHOPPING_ProductsLength;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
