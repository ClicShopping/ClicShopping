<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditDesign\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\EditDesign\EditDesign;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_EditDesign = new EditDesign();
    Registry::set('EditDesign', $CLICSHOPPING_EditDesign);

    $this->app = Registry::get('EditDesign');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
