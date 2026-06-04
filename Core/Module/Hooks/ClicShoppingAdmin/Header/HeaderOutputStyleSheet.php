<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

use ClicShopping\OM\CLICSHOPPING;

class HeaderOutputStyleSheet
{
  /**
   * Generates and returns the HTML output for including the SmartMenus stylesheet links.
   *
   * @return string The generated HTML output containing the necessary stylesheet links for SmartMenus.
   */
  public function display(): string
  {
    $output = '<!-- Start StyleSheet -->' . "\n";
    $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/stylesheet.css') . '" media="screen, print">' . "\n";
    $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/stylesheet_responsive.css') . '" media="screen, print">' . "\n";
    $output .= '<!-- End StyleSheet -->' . "\n";

    return $output;
  }
}