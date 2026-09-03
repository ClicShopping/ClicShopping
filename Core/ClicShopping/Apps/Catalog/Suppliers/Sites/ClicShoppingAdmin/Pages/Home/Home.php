<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Catalog\Suppliers\Suppliers;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Suppliers = new Suppliers();
    Registry::set('Suppliers', $CLICSHOPPING_Suppliers);

    $this->app = Registry::get('Suppliers');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
