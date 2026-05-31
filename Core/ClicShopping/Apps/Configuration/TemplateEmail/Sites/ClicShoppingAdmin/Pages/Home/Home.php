<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\TemplateEmail\TemplateEmail;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_TemplateEmail = new TemplateEmail();
    Registry::set('TemplateEmail', $CLICSHOPPING_TemplateEmail);

    $this->app = $CLICSHOPPING_TemplateEmail;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
