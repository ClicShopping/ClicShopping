<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\CfgModulesAdmin as CfgModulesAdminClass;

/**
 * The CfgModulesAdmin service manages the initialization and termination process
 * of the CfgModulesAdmin class within the ClicShoppingAdmin namespace.
 * This service ensures the appropriate setup and cleanup of resources in the application.
 */
class CfgModulesAdmin implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the CfgModulesAdmin module by setting it in the Registry.
   *
   * @return bool Returns true on successful initialization.
   */
  public static function start(): bool
  {
    Registry::set('CfgModulesAdmin', new CfgModulesAdminClass());

    return true;
  }

  /**
   * Stops the current operation or process.
   *
   * @return bool Returns true on successful stop execution.
   */
  public static function stop(): bool
  {
    return true;
  }
}
