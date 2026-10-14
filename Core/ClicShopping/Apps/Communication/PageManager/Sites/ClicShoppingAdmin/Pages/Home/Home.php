<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Communication\PageManager\PageManager;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_PageManager = new PageManager();
    Registry::set('PageManager', $CLICSHOPPING_PageManager);

    $this->app = Registry::get('PageManager');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
