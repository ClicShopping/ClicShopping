<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\DefineLanguage\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\DefineLanguage\DefineLanguage;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_DefineLanguage = new DefineLanguage();
    Registry::set('DefineLanguage', $CLICSHOPPING_DefineLanguage);

    $this->app = $CLICSHOPPING_DefineLanguage;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
