<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Marketing\Specials\Classes\Shop\SpecialsClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * The Specials class provides methods to manage the lifecycle of the Specials service in the ClicShopping framework.
 * This service initializes the SpecialsClass functionality and ensures that scheduled and expired specials are processed.
 *
 * Implements the ServiceInterface to adhere to the structure of ClicShopping service components.
 */
class Specials implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the SpecialsClass functionality if the required file exists.
   *
   * @return bool Returns true if the SpecialsClass is successfully initialized, otherwise false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Marketing/Specials/Classes/Shop/SpecialsClass.php')) {
      Registry::set('SpecialsClass', new SpecialsClass());

      $CLICSHOPPING_Specials = Registry::get('SpecialsClass');

      $CLICSHOPPING_Specials->scheduledSpecials();
      $CLICSHOPPING_Specials->expireSpecials();

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the execution or performs the necessary termination operations.
   *
   * @return bool Returns true to indicate that the stop operation was successful.
   */
  public static function stop(): bool
  {
    return true;
  }
}
