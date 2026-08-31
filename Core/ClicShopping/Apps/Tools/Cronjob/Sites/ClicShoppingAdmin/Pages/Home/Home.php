<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\Cronjob\Cronjob;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Cronjob = new Cronjob();
    Registry::set('Cronjob', $CLICSHOPPING_Cronjob);

    $this->app = $CLICSHOPPING_Cronjob;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
