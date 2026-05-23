<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\SecDirPermissions\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\SecDirPermissions\SecDirPermissions;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_SecDirPermissions = new SecDirPermissions();
    Registry::set('SecDirPermissions', $CLICSHOPPING_SecDirPermissions);

    $this->app = Registry::get('SecDirPermissions');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
