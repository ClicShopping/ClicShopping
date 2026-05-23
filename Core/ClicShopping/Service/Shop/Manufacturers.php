<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Catalog\Manufacturers\Classes\Shop\Manufacturers as ManufacturersShopClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Service class responsible for managing the integration of the Manufacturers module into the shop environment.
 *
 * This class implements the ServiceInterface and provides methods to start and stop the service.
 * The Manufacturers class ensures that the Manufacturers module is properly registered in the Registry
 * for usage within the shop system, checking the existence and loading the required class file.
 */
class Manufacturers implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the Manufacturers module by checking the existence of the required file
   * and registering the ManufacturersShopClass in the application registry.
   *
   * @return bool Returns true if the required file exists and the module is successfully initialized, otherwise false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Catalog/Manufacturers/Classes/Shop/Manufacturers.php')) {
      Registry::set('Manufacturers', new ManufacturersShopClass());
      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the execution or process.
   *
   * @return bool Returns true if the stop operation was successful.
   */
  public static function stop(): bool
  {
    return true;
  }
}
