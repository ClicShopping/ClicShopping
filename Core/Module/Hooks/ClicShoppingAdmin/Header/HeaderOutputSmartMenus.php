<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

use ClicShopping\OM\CLICSHOPPING;

class HeaderOutputSmartMenus
{
  /**
   * Generates and returns the HTML output for SmartMenus integration if the admin session is set
   * and vertical menu configuration is disabled.
   *
   * @return string|bool Returns the HTML output as a string when the conditions are met, or false otherwise.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin']) && VERTICAL_MENU_CONFIGURATION == 'false') {
      $output = '<!-- Start SmatMenus -->' . "\n";
      $output .= '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery.smartmenus/1.2.1/css/sm-core-css.css" media="screen, print">' . "\n";
      $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/smartmenus.min.css') . '" media="screen, print">' . "\n";
      $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/smartmenus_customize.css') . '" media="screen, print">' . "\n";
      $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/smartmenus_customize_responsive.css') . '" media="screen, print">' . "\n";
      $output .= '<!-- Start SmatMenus -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}