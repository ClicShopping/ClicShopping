<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop\SubProduct;

/**
 * Focused Catalog/Products debug flag (error_log). ON by default during the
 * Products god-class decomposition so the auto-uploaded files trace on the test
 * server without any manual step; override it (e.g. in config) to silence. Same
 * CLICSHOPPING_APP_CATALOG_PRODUCTS_DEBUG constant as the admin-side trait.
 */
if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_DEBUG')) {
  \define('CLICSHOPPING_APP_CATALOG_PRODUCTS_DEBUG', true);
}

/**
 * ProductsDebugTrait
 *
 * Shared, gated diagnostic logger for the front-office SubProduct collaborators.
 */
trait ProductsDebugTrait
{
  /**
   * Logs a diagnostic line (prefixed with the short class name) when
   * CLICSHOPPING_APP_CATALOG_PRODUCTS_DEBUG is on.
   *
   * @param string $stage Short stage label
   * @param mixed $context Optional context, json-encoded
   */
  private function debugLog(string $stage, mixed $context = null): void
  {
    if (!CLICSHOPPING_APP_CATALOG_PRODUCTS_DEBUG) {
      return;
    }

    $shortClass = basename(str_replace('\\', '/', static::class));
    $line = '[Products][' . $shortClass . '] ' . $stage;

    if ($context !== null) {
      $line .= ' ' . (string)json_encode($context);
    }

    error_log($line);
  }
}
