<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\Shop\Pages\ReviewsInfo;

use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;
use ClicShopping\OM\Registry;

class ReviewsInfo extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {

    if (!Registry::exists('ReviewsApp')) {
      Registry::set('ReviewsApp', new ReviewsApp());
    }

    $CLICSHOPPING_Reviews = Registry::get('ReviewsApp');

    $CLICSHOPPING_Reviews->loadDefinitions('Sites/Shop/main');
  }
}
