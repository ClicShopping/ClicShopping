<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Customers\Groups\Groups;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Groups = new Groups();
    Registry::set('Groups', $CLICSHOPPING_Groups);

    $this->app = Registry::get('Groups');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
