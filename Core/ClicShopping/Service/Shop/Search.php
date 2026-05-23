<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Search as SearchClass;

/**
 * Service class responsible for initializing and managing the Search functionality
 * in the Shop namespace of ClicShopping.
 */
class Search implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initiates the Search functionality by verifying the existence of a required file
   * and registering the SearchClass in the Registry.
   *
   * @return bool Returns true if the required file exists and the class is registered successfully, otherwise false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Sites/Shop/Search.php')) {
      Registry::set('Search', new SearchClass());

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the execution and returns the stop status.
   *
   * @return bool Returns true to indicate that the stop process was successful.
   */
  public static function stop(): bool
  {
    return true;
  }
}
