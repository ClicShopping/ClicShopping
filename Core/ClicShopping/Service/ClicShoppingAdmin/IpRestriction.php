<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\Apps\Tools\SecurityCheck\Classes\IpRestriction as Reject;
use ClicShopping\OM\CLICSHOPPING;

/**
 * This class implements the IpRestriction service for the ClicShoppingAdmin namespace.
 * It enforces IP-based restrictions on the admin area by checking and managing IP rules.
 */
class IpRestriction implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initiates the process of checking and handling IP restrictions for admin access.
   *
   * @return bool Returns true after processing the IP restriction checks and redirection if necessary.
   */
  public static function start(): bool
  {
    $ip_restriction = Reject::checkAllIpAdminRestriction();
    Reject::saveIpRestriction();

    if ($ip_restriction === true) {
      CLICSHOPPING::redirect('../offline.php');
    }

    return true;
  }

  /**
   * Stops an operation or process and ensures a successful termination.
   *
   * @return bool Returns true to indicate the stop operation was successful.
   */
  public static function stop(): bool
  {
    return true;
  }
}

