<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\DataBaseTables\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\DataBaseTables\DataBaseTables;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_DataBaseTables = new DataBaseTables();
    Registry::set('DataBaseTables', $CLICSHOPPING_DataBaseTables);

    $this->app = Registry::get('DataBaseTables');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
