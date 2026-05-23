<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\MessageStack as MessageStackClassAdmin;
use ClicShopping\OM\Registry;

/**
 * Class Core
 *
 * This class represents the core service for the ClicShoppingAdmin namespace.
 * It implements the ServiceInterface and provides functionality to start and stop the service.
 *
 * The `start` method initializes the MessageStack instance and registers it in the Registry
 * if the required file exists, while the `stop` method is designed to terminate the service.
 */
class Core implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Starts the process by checking for the existence of the required file and initializing the MessageStackClassAdmin.
   *
   * @return bool Returns true if the file exists and the MessageStackClassAdmin is successfully initialized; otherwise, false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'OM/MessageStack.php')) {
      Registry::set('MessageStack', new MessageStackClassAdmin());

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the current process or operation.
   *
   * @return bool Returns true when the process is successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}
