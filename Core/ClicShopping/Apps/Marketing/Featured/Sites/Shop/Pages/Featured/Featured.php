<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Sites\Shop\Pages\Featured;

use ClicShopping\Apps\Marketing\Featured\Featured as FeaturedApp;
use ClicShopping\OM\Registry;

class Featured extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {

    if (!Registry::exists('Featured')) {
      Registry::set('Featured', new FeaturedApp());
    }

    $CLICSHOPPING_Featured = Registry::get('Featured');

    $CLICSHOPPING_Featured->loadDefinitions('Sites/Shop/main');
  }
}
