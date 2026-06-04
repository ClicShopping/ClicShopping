<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

use ClicShopping\OM\CLICSHOPPING;

class FooterOutputClipBoardCopyText
{
  /**
   * Generates a formatted string containing a script for tooltips if the user session is identified as admin.
   *
   * @return string|bool Returns the formatted script string if conditions are met; otherwise, returns false.
   */
  public function display(): string|bool
  {
    $params = $_SERVER['QUERY_STRING'] ?? '';

    if (empty($params)) {
      return false;
    }

    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!--Copy text start-->' . "\n";
      $output .= '<script defer src="' . CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/tooltip_copy_text.js') . '"></script>' . "\n";
      $output .= '<!--Copy text end -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}