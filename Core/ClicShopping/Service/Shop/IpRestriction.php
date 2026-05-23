<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Tools\SecurityCheck\Classes\IpRestriction as Reject;
use ClicShopping\OM\CLICSHOPPING;

/**
 * This class provides the service for handling IP restrictions in the shop.
 * It implements the ServiceInterface and ensures that unauthorized IPs are redirected to an offline page.
 */
class IpRestriction implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Starts the process of checking IP restrictions and handling the redirection if necessary.
   *
   * This method performs a check for all shop-specific IP restrictions, saves the current restriction state,
   * and redirects to an offline page if the IP restriction condition is met.
   *
   * @return bool Returns true after completing the process.
   */
  public static function start(): bool
  {
    $ip_restriction = Reject::checkAllIpShopRestriction();
    Reject::saveIpRestriction();

    if ($ip_restriction === true) {
      CLICSHOPPING::redirect('offline.php');
    }

    return true;
  }

  /**
   * Stops the current process or action.
   *
   * @return bool Returns true indicating the process was successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}

