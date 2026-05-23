<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\CkEditor5;

/**
 * Service class for handling the WYSIWYG editor integration within the ClicShoppingAdmin application.
 * This class implements the ServiceInterface and provides methods to start and stop the WYSIWYG service.
 */
class Wysiwyg implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the WYSIWYG editor if the default editor is set to 'CkEditor5'.
   *
   * @return bool Returns true after successful initialization.
   */
  public static function start(): bool
  {
    if (defined('DEFAULT_WYSIWYG') && DEFAULT_WYSIWYG == 'CkEditor5') {
      Registry::set('Wysiwyg', new CkEditor5());
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
