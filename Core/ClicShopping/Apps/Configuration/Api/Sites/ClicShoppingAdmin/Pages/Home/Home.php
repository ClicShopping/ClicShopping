<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\Api\Api;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Api = new Api();
    Registry::set('Api', $CLICSHOPPING_Api);

    $this->app = $CLICSHOPPING_Api;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
