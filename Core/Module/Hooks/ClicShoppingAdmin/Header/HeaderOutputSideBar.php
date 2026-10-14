<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

use ClicShopping\OM\CLICSHOPPING;

class HeaderOutputSideBar
{
  /**
   * Generates and returns the sidebar vertical menu script for admin users.
   *
   * @return string|bool Returns the generated HTML code as a string if the user is an admin,
   *                     or false if the user is not an admin.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!--Sidebar Vertical Menu Script start-->' . "\n";
      $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/headerOutputSideBar.css') . '" media="screen, print">' . "\n";
      $output .= '<!--End Sidebar Vertical Menu -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}