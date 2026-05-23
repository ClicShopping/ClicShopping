<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Marketing\SEO\SEO;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_SEO = new SEO();
    Registry::set('SEO', $CLICSHOPPING_SEO);

    $this->app = Registry::get('SEO');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
