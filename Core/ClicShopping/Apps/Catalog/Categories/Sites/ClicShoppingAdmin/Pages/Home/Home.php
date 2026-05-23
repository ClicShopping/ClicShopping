<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Catalog\Categories\Categories;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Categories = new Categories();
    Registry::set('Categories', $CLICSHOPPING_Categories);

    $this->app = Registry::get('Categories');
    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
