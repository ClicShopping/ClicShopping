<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\Antispam\Antispam;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Antispam = new Antispam();
    Registry::set('Antispam', $CLICSHOPPING_Antispam);

    $this->app = $CLICSHOPPING_Antispam;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
