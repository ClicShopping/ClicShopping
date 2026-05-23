<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ActionsRecorder\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\ActionsRecorder\ActionsRecorder;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_ActionsRecorder = new ActionsRecorder();
    Registry::set('ActionsRecorder', $CLICSHOPPING_ActionsRecorder);

    $this->app = $CLICSHOPPING_ActionsRecorder;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
