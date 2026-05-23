<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\Apps\Catalog\Categories\Classes\Common\CategoryCommon as CategoryCommonClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Service class responsible for managing the initialization and lifecycle of
 * the CategoryCommon class within the ClicShopping application.
 */
class CategoriesCommon implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the CategoryCommon class if the required file exists.
   *
   * @return bool Returns true if the file is found and the class is registered successfully; otherwise, false.
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
   * Stops the execution or process invoked.
   *
   * @return bool Returns true upon successful stop operation.
   */
  public static function stop(): bool
  {
    return true;
  }
}
