<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

class HeaderOutputJquery
{
  /**
   * Generates and returns a string containing HTML script tags for including the jQuery library.
   *
   * @return string The HTML string with the jQuery library script included.
   */
  public function display(): string
  {
    $output = '<!-- Start Jquery -->' . "\n";
    $output .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>' . "\n";
    $output .= '<!-- End Jquery -->' . "\n";

    return $output;
  }
}