<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Sites\Shop\Pages\Recommendations;

use ClicShopping\Apps\Marketing\Recommendations\Recommendations as RecommendationsApp;
use ClicShopping\OM\Registry;

class Recommendations extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    if (!Registry::exists('Recommendations')) {
      Registry::set('Recommendations', new RecommendationsApp());
    }

    $CLICSHOPPING_ProductsRecommendation = Registry::get('Recommendations');

    $CLICSHOPPING_ProductsRecommendation->loadDefinitions('Sites/Shop/main');
  }
}
