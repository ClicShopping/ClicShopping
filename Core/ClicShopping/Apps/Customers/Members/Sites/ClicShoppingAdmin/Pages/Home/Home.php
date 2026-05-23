<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Members\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Customers\Members\Members;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Members = new Members();
    Registry::set('Members', $CLICSHOPPING_Members);

    $this->app = Registry::get('Members');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
