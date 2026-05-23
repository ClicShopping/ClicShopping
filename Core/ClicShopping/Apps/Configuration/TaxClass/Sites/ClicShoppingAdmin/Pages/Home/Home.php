<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxClass\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\TaxClass\TaxClass;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_TaxClass = new TaxClass();
    Registry::set('TaxClass', $CLICSHOPPING_TaxClass);

    $this->app = $CLICSHOPPING_TaxClass;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
