<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\AddressAdmin as AddressClass;

/**
 * This class is a service implementation for the ClicShoppingAdmin module in the ClicShopping framework.
 * It provides functionality to start and stop the Address service.
 */
class Address implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the AddressClass within the Registry if it does not already exist,
   * provided the required AddressAdmin.php file is present.
   *
   * @return bool Returns true if the AddressClass was successfully set in the Registry
   *              or if it already existed; returns false if the AddressAdmin.php file
   *              is not found.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Sites/ClicShoppingAdmin/AddressAdmin.php')) {
      if (!Registry::exists('AddressClass')) {
        Registry::set('Address', new AddressClass());
      }
      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the process or operation.
   *
   * @return bool Returns true to indicate the process has been successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}
