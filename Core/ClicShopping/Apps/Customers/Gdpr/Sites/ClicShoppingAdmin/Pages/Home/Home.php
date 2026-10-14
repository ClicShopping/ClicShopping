<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Customers\Gdpr\Gdpr;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Gdpr = new Gdpr();
    Registry::set('Gdpr', $CLICSHOPPING_Gdpr);

    $this->app = Registry::get('Gdpr');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
