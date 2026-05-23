<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Products;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

/**
 * Products page controller
 * Handles product display and validation before rendering
 */
class Products extends \ClicShopping\OM\Domains\PagesAbstract
{
  /**
   * Initialize the page and validate product exists before rendering
   * This prevents "headers already sent" errors by checking product validity
   * before the template header is included
   */
  protected function init(): void
  {
    // Only validate for product_info page (not for other product pages like ProductsNew)
    if (isset($_GET['Products']) && isset($_GET['Description'])) {
      $this->validateProductExists();
    }
  }
  
  /**
   * Validate that the requested product exists and is accessible
   * Redirects to 404 if product is not found or invalid
   */
  private function validateProductExists(): void
  {
    if (!Registry::exists('ProductsCommon')) {
      return; // ProductsCommon not initialized yet, skip validation
    }
    
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    
    // Check if product exists and is valid
    if ($CLICSHOPPING_ProductsCommon->getProductsCount() < 1 || 
        \is_null($CLICSHOPPING_ProductsCommon->getID()) || 
        $CLICSHOPPING_ProductsCommon->getID() === false) {
      
      http_response_code(404);
      HTTP::redirect(CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'error_documents/404.php');
    }
  }
}
