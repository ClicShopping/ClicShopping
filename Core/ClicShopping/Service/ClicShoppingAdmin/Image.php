<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\Image as ImageClass;
use ClicShopping\OM\Registry;

/**
 * Service class for handling the initialization and termination of the Image component
 * in the ClicShoppingAdmin context.
 *
 * This service class integrates the Image functionality by registering it into the
 * application registry.
 */
class Image implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes and registers the ImageClass instance into the Registry.
   *
   * @return bool Returns true upon successful initialization.
   */
  public static function start(): bool
  {
    Registry::set('Image', new ImageClass());

    return true;
  }

  /**
   * Stops the execution or operation of a given process or functionality.
   *
   * @return bool Returns true if the operation was successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}
