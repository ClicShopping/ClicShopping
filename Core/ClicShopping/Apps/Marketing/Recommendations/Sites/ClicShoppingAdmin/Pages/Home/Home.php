<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Marketing\Recommendations\Recommendations;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Recommendations = new Recommendations();
    Registry::set('Recommendations', $CLICSHOPPING_Recommendations);

    $this->app = $CLICSHOPPING_Recommendations;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
