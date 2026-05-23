<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

use ClicShopping\OM\CLICSHOPPING;
use function defined;

/**
 * Handles the generation of HTML and JavaScript output for embedding a tool that
 * enables text copying functionality in the ClicShopping administration footer,
 * conditioned on certain application and user session states.
 */
class FooterOutputBootstrapCopyText
{
  /**
   * Generates and returns a structured HTML output with additional JavaScript inclusion
   * if specific conditions for admin session and application status are met.
   *
   * @return string|bool Returns the generated HTML output as a string if conditions are met,
   *                     or false if query parameters are empty or the user is not an admin.
   */
  public function display(): string|bool
  {
    $params = $_SERVER['QUERY_STRING'];

    if (empty($params)) {
      return false;
    }

    $output = '';

    if (isset($_SESSION['admin'])) {
      if (!defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS') || CLICSHOPPING_APP_CHATGPT_CH_STATUS == 'True') {
        $output .= '<!--Copy text start-->' . "\n";
        $output .= '<script defer src="' . CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/tooltip_copy_text.js') . '"></script>' . "\n";
        $output .= '<!--Copy text end -->' . "\n";
      }
    } else {
      return false;
    }

    return $output;
  }
}