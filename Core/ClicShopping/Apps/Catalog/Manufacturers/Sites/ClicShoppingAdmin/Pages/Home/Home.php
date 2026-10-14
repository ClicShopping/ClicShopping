<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Catalog\Manufacturers\Manufacturers;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Manufacturers = new Manufacturers();
    Registry::set('Manufacturers', $CLICSHOPPING_Manufacturers);

    $this->app = Registry::get('Manufacturers');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
