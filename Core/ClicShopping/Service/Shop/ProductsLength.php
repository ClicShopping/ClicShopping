<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Configuration\ProductsLength\Classes\Shop\ProductsLength as ProductsLengthShop;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Service class for managing the ProductsLength functionality in the shop.
 * This service initializes the ProductsLength class if the required file exists.
 */
class ProductsLength implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the ProductsLength module by checking the existence of the required file
   * and registering it within the application.
   *
   * @return bool Returns true if the initialization was successful, false otherwise.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Configuration/ProductsLength/Classes/Shop/ProductsLength.php')) {
      Registry::set('ProductsLength', new ProductsLengthShop());

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the execution or process.
   *
   * @return bool Returns true to indicate the stop was successful.
   */
  public static function stop(): bool
  {
    return true;
  }
}
