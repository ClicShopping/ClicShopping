<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Archive\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Catalog\Archive\Archive;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Archive = new Archive();
    Registry::set('Archive', $CLICSHOPPING_Archive);

    $this->app = Registry::get('Archive');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
