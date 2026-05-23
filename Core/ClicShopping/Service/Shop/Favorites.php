<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Marketing\Favorites\Classes\Shop\FavoritesClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Service class for managing the Favorites functionality in the shop.
 *
 * This class implements the ServiceInterface and provides methods to
 * initialize and terminate the Favorites service. It ensures the availability
 * of the Favorites module and invokes necessary functions related to scheduled
 * and expired favorites handling.
 */
class Favorites implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initiates the FavoritesClass if the required file exists.
   *
   * @return bool Returns true if the FavoritesClass is successfully initialized and executed, otherwise false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Marketing/Favorites/Classes/Shop/FavoritesClass.php')) {
      Registry::set('FavoritesClass', new FavoritesClass());

      $CLICSHOPPING_Favorites = Registry::get('FavoritesClass');

      $CLICSHOPPING_Favorites->scheduledFavorites();
      $CLICSHOPPING_Favorites->expireFavorites();

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the current operation.
   *
   * @return bool Returns true upon successful completion.
   */
  public static function stop(): bool
  {
    return true;
  }
}
