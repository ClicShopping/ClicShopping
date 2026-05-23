<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Customers\Reviews\Classes\Shop\ReviewsClass as NewReviews;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Service class responsible for managing the initialization and termination of the Reviews module in the shop.
 * Implements the ClicShopping service interface.
 */
class Reviews implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Starts the process to initialize the Reviews class if the specified file exists.
   *
   * @return bool Returns true if the Reviews class file exists and is successfully initialized, otherwise false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Customers/Reviews/Classes/Shop/ReviewsClass.php')) {
      Registry::set('Reviews', new NewReviews());

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the execution or process.
   *
   * @return bool Returns true when the method is successfully executed.
   */
  public static function stop(): bool
  {
    return true;
  }
}
