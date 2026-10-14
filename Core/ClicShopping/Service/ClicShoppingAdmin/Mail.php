<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\OM\Mail as MailClass;
use ClicShopping\OM\Registry;

/**
 * The Mail service class for ClicShoppingAdmin initializes and manages the Mail service.
 * It interacts with the Registry to set up the Mail instance.
 *
 * @package ClicShopping\Service\ClicShoppingAdmin
 */
class Mail implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes and registers the Mail service within the Registry.
   *
   * @return bool Returns true upon successful registration of the Mail service.
   */
  public static function start(): bool
  {
    Registry::set('Mail', new MailClass());

    return true;
  }

  /**
   * Stops the current process or operation.
   *
   * @return bool Returns true if the operation is successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}
