<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

class HeaderOutputChart
{
  /**
   * Generates and returns HTML output for embedding charts if the current session belongs to an admin user.
   *
   * @return string|bool Returns the generated HTML string if the session is admin; otherwise, returns false.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      $output = '<!-- Start Chart -->' . "\n";
      $output .= '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>' . "\n";
      $output .= '<!-- End Chart -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}