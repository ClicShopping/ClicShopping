<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\Shop\Pages\RSS;

use ClicShopping\Apps\Communication\PageManager\PageManager as PageManagerApp;
use ClicShopping\OM\Registry;

class RSS extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {

    if (!Registry::exists('PageManager')) {
      Registry::set('PageManager', new PageManagerApp());
    }

    $CLICSHOPPING_PageManager = Registry::get('PageManager');
    $this->app = $CLICSHOPPING_PageManager;

    $CLICSHOPPING_PageManager->loadDefinitions('Sites/Shop/main');
  }
}
