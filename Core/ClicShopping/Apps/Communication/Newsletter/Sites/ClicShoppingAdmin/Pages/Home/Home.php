<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Communication\Newsletter\Newsletter;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_Newsletter = new Newsletter();
    Registry::set('Newsletter', $CLICSHOPPING_Newsletter);

    $this->app = Registry::get('Newsletter');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
