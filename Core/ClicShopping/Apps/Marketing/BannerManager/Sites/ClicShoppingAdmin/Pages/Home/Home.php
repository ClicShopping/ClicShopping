<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Marketing\BannerManager\BannerManager;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_BannerManager = new BannerManager();
    Registry::set('BannerManager', $CLICSHOPPING_BannerManager);

    $this->app = $CLICSHOPPING_BannerManager;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
