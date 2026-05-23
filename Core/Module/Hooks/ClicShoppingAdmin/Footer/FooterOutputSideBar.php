<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

class FooterOutputSideBar
{
  /**
   * Generates and returns the sidebar vertical menu script if the admin session is set
   * and the vertical menu configuration is enabled.
   *
   * @return string|bool Returns the script as a string if conditions are met, or false otherwise.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin']) && VERTICAL_MENU_CONFIGURATION == 'true') {
      $output .= '<!--Sidebar Vertical Menu Script start-->' . "\n";
      $output .= '<script defer>' . "\n";
      $output .= '$(function() {  $(\'#sidebarCollapse\').on(\'click\', function() { $(\'#sidebar, #content\').toggleClass(\'active\');  }); });' . "\n";
      $output .= '$(function() {  $(\'#sidebarCollapse1\').on(\'click\', function() { $(\'#sidebar, #content\').toggleClass(\'active\');  }); });' . "\n";
      $output .= '</script>' . "\n";
      $output .= '<!--End Sidebar Vertical Menu -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}