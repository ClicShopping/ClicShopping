<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\ClicShoppingAdmin;

use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsAdmin as ProductAdminClass;
use ClicShopping\OM\Registry;

/**
 * Service class responsible for initializing and handling the ProductAdmin functionality
 * within the ClicShoppingAdmin namespace. This service integrates the ProductsAdmin
 * class into the application via the Registry class.
 *
 * Implements the ClicShopping\OM\ServiceInterface interface to standardize service behavior.
 */
class ProductAdmin implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the ProductsAdmin system by registering the ProductAdminClass instance.
   *
   * @return bool Returns true upon successful initialization.
   */
  public static function start(): bool
  {
    Registry::set('ProductsAdmin', new ProductAdminClass());

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
