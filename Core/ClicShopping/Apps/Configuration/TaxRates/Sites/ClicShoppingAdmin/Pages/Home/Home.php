<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxRates\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\TaxRates\TaxRates;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_TaxRates = new TaxRates();
    Registry::set('TaxRates', $CLICSHOPPING_TaxRates);

    $this->app = $CLICSHOPPING_TaxRates;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
