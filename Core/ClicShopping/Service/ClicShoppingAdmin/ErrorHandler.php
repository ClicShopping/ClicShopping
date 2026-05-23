<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\OM\ErrorHandler as ErrorHandlerClass;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\Registry;

/**
 * @namespace ClicShopping\Service\ClicShoppingAdmin
 *
 * The ErrorHandler class implements the ServiceInterface to provide start and stop functionalities for the error handler service.
 */
class ErrorHandler implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initiates the process by checking if the log directory is writable, and adds a message to the message stack if it is not.
   *
   * @return bool Returns true after execution, indicating the process has completed.
   */
  public static function start(): bool
  {
    if (!FileSystem::isWritable(ErrorHandlerClass::getDirectory())) {
      Registry::get('MessageStack')->add('The log directory is not writable. Please allow the web server to write to: ' . FileSystem::displayPath(ErrorHandlerClass::getDirectory()));
    }

    return true;
  }

  /**
   * Stops the current process or operation.
   *
   * @return bool Returns true on successful termination.
   */
  public static function stop(): bool
  {
    return true;
  }
}
