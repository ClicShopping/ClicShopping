<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Marketing\BannerManager\Classes\Shop\Banner as BannerClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Class Banner
 *
 * This service handles the initialization and management of the Banner functionality.
 * It integrates with the BannerManager app and ensures banners are activated and expired appropriately.
 * Implements the ServiceInterface for use within the ClicShopping framework.
 */
class Banner implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the BannerManager application by setting up the necessary dependencies
   * and triggering the activation and expiration of banners.
   *
   * @return bool Returns true if the BannerManager class was successfully initialized;
   *              otherwise, returns false if the required file is not found.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Marketing/BannerManager/Classes/Shop/Banner.php')) {
      Registry::set('Banner', new BannerClass());

      $CLICSHOPPING_Banner = Registry::get('Banner');

      $CLICSHOPPING_Banner->activateBanners();
      $CLICSHOPPING_Banner->expireBanners();

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the execution process.
   *
   * @return bool Returns true if the process is successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}
