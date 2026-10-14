<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_ChatGpt = new ChatGpt();
    Registry::set('ChatGpt', $CLICSHOPPING_ChatGpt);

    $this->app = $CLICSHOPPING_ChatGpt;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
