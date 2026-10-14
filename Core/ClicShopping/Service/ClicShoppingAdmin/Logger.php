<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\LoggerAdmin;

/**
 * Service class for handling the initialization and management of the LoggerAdmin service
 * within the ClicShoppingAdmin site.
 *
 * This class implements the ClicShopping\OM\ServiceInterface and provides the methods
 * for starting and stopping the LoggerAdmin service.
 */
class Logger implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the LoggerAdmin instance and registers it in the Registry.
   *
   * @return bool Returns true upon successful initialization.
   */
  public static function start(): bool
  {
    Registry::set('LoggerAdmin', new LoggerAdmin());

    return true;
  }

  /**
   * Stops the current operation or process.
   *
   * @return bool Returns true on successful operation termination.
   */
  public static function stop(): bool
  {
    return true;
  }
}
