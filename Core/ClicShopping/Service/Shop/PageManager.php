<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Communication\PageManager\Classes\Shop\PageManagerShop as PageManagerShopClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Service class for managing the PageManager functionality within the shop.
 * This service initializes and activates the PageManagerShop class, enabling
 * the handling of page manager-related operations.
 */
class PageManager implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the PageManagerShop class if the required file exists.
   *
   * @return bool Returns true if the PageManagerShop class is successfully initialized and methods are called; false otherwise.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Communication/PageManager/Classes/Shop/PageManagerShop.php')) {
      Registry::set('PageManagerShop', new PageManagerShopClass());

      $CLICSHOPPING_PageManagerShop = Registry::get('PageManagerShop');

      $CLICSHOPPING_PageManagerShop->activatePageManager();
      $CLICSHOPPING_PageManagerShop->expirePageManager();

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the current process or operation.
   *
   * @return bool Returns true if the stop operation was successful.
   */
  public static function stop(): bool
  {
    return true;
  }
}
