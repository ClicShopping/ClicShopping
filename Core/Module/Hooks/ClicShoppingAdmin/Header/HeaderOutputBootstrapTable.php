<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

class HeaderOutputBootstrapTable
{
  /**
   * Generates and returns a string containing HTML and CSS links for initializing a Bootstrap table if the user is an admin.
   *
   * @return string|bool Returns a string with the HTML and CSS links if the user is an admin, otherwise returns false.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      $output = '<!-- Start BootStrap Table -->' . "\n";
      $output .= '<link rel="stylesheet" href="https://unpkg.com/bootstrap-table@1.27.3/dist/bootstrap-table.min.css">' . "\n";
      $output .= '<!-- End BootStrap Table -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}