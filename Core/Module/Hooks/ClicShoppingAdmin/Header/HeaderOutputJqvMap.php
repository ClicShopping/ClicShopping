<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

use ClicShopping\OM\CLICSHOPPING;

class HeaderOutputJqvMap
{
  /**
   * Generates and returns HTML output for including jqvmap assets if the session indicates an admin user.
   * Returns false if query parameters are present in the request or the user is not an admin.
   *
   * @return string|bool The HTML output with stylesheet links for jqvmap if conditions are met, otherwise false.
   */
  public function display(): string|bool
  {
    $params = $_SERVER['QUERY_STRING'];

    if (!empty($params)) {
      return false;
    }

    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!-- Start Jqvmap -->' . "\n";
      $output .= '<link href="https://cdnjs.cloudflare.com/ajax/libs/jqvmap/1.5.1/jqvmap.min.css" rel="stylesheet" media="screen" rel="preload"/>' . "\n";
      $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/jqvmap.css') . '" rel="stylesheet" rel="preload"/>' . "\n";
      $output .= '<!-- End Jqvmap  -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}