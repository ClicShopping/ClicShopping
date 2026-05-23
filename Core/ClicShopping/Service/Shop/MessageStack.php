<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\MessageStack as MessageStackClass;
use ClicShopping\OM\Registry;

/**
 * Class MessageStack
 *
 * Implements the ServiceInterface for initializing and stopping the MessageStack service
 * in the ClicShopping application. This service is responsible for managing and rendering
 * message stacks that store system or user messages.
 *
 * Methods:
 * - start(): Initializes the MessageStack service, registers it with the application registry,
 *   and sets up any required hooks for pre-page processing.
 * - stop(): Handles any necessary cleanup or termination processes for the service.
 */
class MessageStack implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Starts the initialization process by setting up the message stack
   * and configuring the service to execute specific actions before page content loads.
   *
   * @return bool Returns true if the message stack file exists and was successfully initialized; false otherwise.
   */
  public static function start(): bool
  {
// initialize the message stack for output messages
    $CLICSHOPPING_Service = Registry::get('Service');

    if (is_file(CLICSHOPPING::BASE_DIR . 'OM/MessageStack.php')) {
      Registry::set('MessageStack', new MessageStackClass());

      $CLICSHOPPING_Service->addCallBeforePageContent('Address', 'initialize');

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the current process or operation.
   *
   * @return bool Returns true if the process is successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}
