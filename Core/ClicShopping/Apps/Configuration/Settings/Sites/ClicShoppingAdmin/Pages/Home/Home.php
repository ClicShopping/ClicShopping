<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Settings\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\Settings\Settings;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Settings = new Settings();
    Registry::set('Settings', $CLICSHOPPING_Settings);

    $this->app = $CLICSHOPPING_Settings;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
