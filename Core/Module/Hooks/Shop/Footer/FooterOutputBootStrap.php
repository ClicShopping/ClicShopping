<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Footer;

class FooterOutputBootStrap
{
  /**
   * Generates and returns a string containing the script for including the Bootstrap JavaScript bundle.
   *
   * @return string The HTML script tag for the Bootstrap JavaScript bundle.
   */
  public function display(): string
  {
    $output = '<!--Bootstrap Script start-->' . "\n";
    $output .= '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>';
    $output .= '<!--End Bootstrap Script-->' . "\n";

    return $output;
  }
}