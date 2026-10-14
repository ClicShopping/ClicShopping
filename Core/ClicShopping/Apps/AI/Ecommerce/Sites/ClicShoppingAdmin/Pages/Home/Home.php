<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Ecommerce = new Ecommerce();
    Registry::set('Ecommerce', $CLICSHOPPING_Ecommerce);

    $this->app = $CLICSHOPPING_Ecommerce;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
