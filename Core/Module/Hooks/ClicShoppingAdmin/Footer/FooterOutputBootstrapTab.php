<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

use ClicShopping\OM\CLICSHOPPING;

class FooterOutputBootstrapTab
{
  /**
   * Generates and returns a script block for a Bootstrap tab if conditions are met.
   *
   * @return string|bool A string containing the script block if the query string is not empty
   *                     and the user session is identified as admin; false otherwise.
   */
  public function display(): string|bool
  {
    $params = $_SERVER['QUERY_STRING'] ?? '';

    if (empty($params)) {
      return false;
    }

    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!-- Bootstrap tab Script start-->' . "\n";
      $output .= '<script src="' . CLICSHOPPING::link('Shop/ext/javascript/bootstrap/tab/bootstrap_tab.js') . '"></script>' . "\n";
      $output .= '<!--Bootstrap tab end -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}