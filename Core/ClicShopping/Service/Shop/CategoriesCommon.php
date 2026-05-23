<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Catalog\Categories\Classes\Common\CategoryCommon as CategoryCommonClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Service class responsible for managing the initialization and shutdown of
 * the CategoriesCommon functionality within the ClicShopping system. It registers
 * the CategoryCommon class into the Registry for use across the application.
 */
class CategoriesCommon implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Starts the process by checking the existence of a specific file.
   * If the file exists, it initializes the CategoryCommonClass and
   * registers it under the 'CategoryCommon' key in the Registry.
   *
   * @return bool Returns true if the file exists and the process is initialized successfully, otherwise false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Catalog/Categories/Classes/Common/CategoryCommon.php')) {
      Registry::set('CategoryCommon', new CategoryCommonClass());

      return true;
    } else {
      return false;
    }
  }

  /**
   * Terminates a process or operation.
   *
   * @return bool Returns true indicating the successful termination of the process or operation.
   */
  public static function stop(): bool
  {
    return true;
  }
}
