<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Footer;

class FooterOutputLozad
{
  /**
   * Generates and returns the necessary HTML and JavaScript for implementing the Lozad.js library.
   *
   * @return string The HTML and JavaScript code for lazy loading using Lozad.js.
   */
  public function display(): string
  {
    $output = '<!--Lazyload Script start-->' . "\n";
    $output .= '<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/lozad/dist/lozad.min.js"></script>' . "\n";
    $output .= '<script defer>';
    $output .= 'const observer = lozad(); observer.observe();';
    $output .= '</script>' . "\n";
    $output .= '<!--End Lazyload Script-->' . "\n";

    return $output;
  }
}