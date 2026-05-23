<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_MCP = new MCP();
    Registry::set('MCP', $CLICSHOPPING_MCP);

    $this->app = Registry::get('MCP');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
