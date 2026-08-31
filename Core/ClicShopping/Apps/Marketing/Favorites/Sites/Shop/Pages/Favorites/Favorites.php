<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Sites\Shop\Pages\Favorites;

use ClicShopping\Apps\Marketing\Favorites\Favorites as FavoritesApp;
use ClicShopping\OM\Registry;

class Favorites extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    if (!Registry::exists('Favorites')) {
      Registry::set('Favorites', new FavoritesApp());
    }

    $CLICSHOPPING_Favorites = Registry::get('Favorites');

    $CLICSHOPPING_Favorites->loadDefinitions('Sites/Shop/main');
  }
}
