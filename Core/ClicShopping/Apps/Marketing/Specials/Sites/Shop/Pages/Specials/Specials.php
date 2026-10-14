<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Sites\Shop\Pages\Specials;

use ClicShopping\Apps\Marketing\Specials\Specials as SpecialsApp;
use ClicShopping\OM\Registry;

class Specials extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    if (!Registry::exists('Specials')) {
      Registry::set('Specials', new SpecialsApp());
    }

    $CLICSHOPPING_Specials = Registry::get('Specials');

    $CLICSHOPPING_Specials->loadDefinitions('Sites/Shop/main');
  }
}
