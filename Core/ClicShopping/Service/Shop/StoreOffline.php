<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\OM\CLICSHOPPING;

/**
 * Service class to handle the "Store Offline" mode in the application.
 * This service checks if the store is in offline mode and redirects users
 * who are not on the allowed IP address list to an offline page.
 */
class StoreOffline implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Starts the application and checks if it should run in offline mode.
   * If the store is offline, verifies whether the current IP is allowed access.
   * Redirects to an offline page if access is restricted.
   *
   * @return bool Returns true when the application is successfully started.
   */
  public static function start(): bool
  {
    if (STORE_OFFLINE == 'true') {
      $allowed_ip = false;
      $ips = explode(',', STORE_OFFLINE_ALLOW);

      foreach ($ips as $ip) {
        if (trim($ip) === $_SERVER['REMOTE_ADDR']) {
          $allowed_ip = true;
          break;
        }
      }

      if ($allowed_ip === false) {
        CLICSHOPPING::redirect('offline.php');
      }
    }

    return true;
  }

  /**
   * Stops the currently running process or action.
   *
   * @return bool Returns true to indicate the process has been successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}
