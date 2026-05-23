<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\Apps\Customers\Customers\Classes\Shop\CustomerShop as CustomerShopClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * This service is part of the ClicShopping administration, specifically for managing the Customers functionality
 * in the Shop application context by interfacing with the CustomerShop class.
 */
class CustomersShop implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the CustomerShopClass if the required file exists.
   *
   * @return bool Returns true if the file exists and the class is successfully initialized; otherwise, false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Customers/Customers/Classes/Shop/CustomerShop.php')) {
      Registry::set('Customer', new CustomerShopClass());
      return true;
    } else {
      return false;
    }
  }

  /**
   * Terminates the process or operation.
   *
   * @return bool Returns true indicating the process was successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}
