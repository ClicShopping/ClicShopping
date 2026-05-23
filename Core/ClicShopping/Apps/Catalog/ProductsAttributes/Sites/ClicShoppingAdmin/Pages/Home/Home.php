<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Catalog\ProductsAttributes\ProductsAttributes;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_ProductsAttributes = new ProductsAttributes();
    Registry::set('ProductsAttributes', $CLICSHOPPING_ProductsAttributes);

    $this->app = Registry::get('ProductsAttributes');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
