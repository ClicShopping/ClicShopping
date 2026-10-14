<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\Backup\Backup;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Backup = new Backup();
    Registry::set('Backup', $CLICSHOPPING_Backup);

    $this->app = Registry::get('Backup');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
