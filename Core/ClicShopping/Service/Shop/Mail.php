<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Mail as MailClass;
use ClicShopping\OM\Registry;

/**
 * This class implements the ServiceInterface and provides functionality
 * for initializing and managing the Mail service in the ClicShopping application.
 */
class Mail implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the Mail class if the Mail.php file exists in the specified directory.
   *
   * @return bool Returns true if the Mail class is successfully initialized, false otherwise.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'OM/Mail.php')) {
      Registry::set('Mail', new MailClass());

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the current process or operation.
   *
   * @return bool Returns true on successful stop.
   */
  public static function stop(): bool
  {
    return true;
  }
}
