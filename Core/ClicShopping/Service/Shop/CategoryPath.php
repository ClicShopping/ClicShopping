<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Catalog\Categories\Classes\Shop\Category as CategoryClass;
use ClicShopping\Apps\Catalog\Categories\Classes\Shop\CategoryTree as CategoryTreeClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * This service is used to manage the initialization of CategoryTree and Category classes
 * within the ClicShopping framework. It ensures these components are properly registered
 * and available for use in the application.
 */
class CategoryPath implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the category-related classes if the required file exists.
   *
   * @return bool Returns true if the file is found and categories are successfully initialized; otherwise, false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Catalog/Categories/Classes/Shop/Category.php')) {
      Registry::set('CategoryTree', new CategoryTreeClass());
      Registry::set('Category', new CategoryClass());

      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the requested process or operation.
   *
   * @return bool Returns true indicating the process has been successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}