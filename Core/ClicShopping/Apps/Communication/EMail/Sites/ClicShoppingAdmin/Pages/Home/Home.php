<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\EMail\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Communication\EMail\EMail;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_EMail = new EMail();
    Registry::set('EMail', $CLICSHOPPING_EMail);

    $this->app = Registry::get('EMail');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
