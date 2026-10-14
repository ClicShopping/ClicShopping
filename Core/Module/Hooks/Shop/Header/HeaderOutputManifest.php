<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Header;

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;

class HeaderOutputManifest
{
  /**
   * Generates and returns a string containing HTML meta tags and link elements
   * required for manifest settings and Apple-specific application features.
   *
   * @return string The generated HTML content for manifest and Apple app meta tags.
   */
  public function display()
  {
    $output = '<link rel="manifest" href="' . HTTP::getShopUrlDomain() . 'manifest.php">' . "\n";
    $output .= '<link rel="apple-touch-icon" sizes="192x192" href="' . HTTP::getShopUrlDomain() . 'images/logo_clicshopping.webp">' . "\n";

    $output .= '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    $output .= '<meta name="apple-mobile-web-app-title" content="' . HTML::outputProtected(STORE_NAME) . '">' . "\n";
    $output .= '<meta name="theme-color" content="#317EFB"/>';

    return $output;
  }
}