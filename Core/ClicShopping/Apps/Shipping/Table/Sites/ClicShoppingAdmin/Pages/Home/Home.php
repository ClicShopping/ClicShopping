<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Table\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Shipping\Table\Table;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Table = new Table();
    Registry::set('Table', $CLICSHOPPING_Table);

    $this->app = $CLICSHOPPING_Table;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
