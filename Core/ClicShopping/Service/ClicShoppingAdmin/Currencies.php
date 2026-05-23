<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\Apps\Configuration\Currency\Classes\Shop\Currencies as CurrenciesClass;
use ClicShopping\OM\Registry;

/**
 * The Currencies service is responsible for initializing and managing the application-specific
 * currency functionalities within the ClicShoppingAdmin environment.
 *
 * This service integrates the Currencies class into the global Registry, allowing the application
 * to interact with currency-related operations seamlessly.
 */
class Currencies implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes and sets up the Currencies registry.
   *
   * @return bool Returns true upon successful initialization.
   */
  public static function start(): bool
  {
    Registry::set('Currencies', new CurrenciesClass());

    return true;
  }

  /**
   *
   * @return bool Returns true on successful execution.
   */
  public static function stop(): bool
  {
    return true;
  }
}
