<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\COD\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Payment\COD\COD;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_COD = new COD();
    Registry::set('COD', $CLICSHOPPING_COD);

    $this->app = $CLICSHOPPING_COD;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
