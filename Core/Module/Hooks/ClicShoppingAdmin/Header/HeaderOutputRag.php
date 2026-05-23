<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

use ClicShopping\OM\CLICSHOPPING;

class HeaderOutputRag
{
  /**
   * Generates and returns HTML output for embedding charts if the current session belongs to an admin user.
   *
   * @return string|bool Returns the generated HTML string if the session is admin; otherwise, returns false.
   */
  public function display(): string|bool
  {
    $output = '';
    $status =  \defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') ?? 'False';

    if (isset($_SESSION['admin']) && $status == 'True') {
      $css_url = CLICSHOPPING::link('css/RAG/rag_dashboard.css');

      $output .= '<!-- Start Chart -->' . "\n";
      $output .= ' <link rel="stylesheet" href="'. $css_url . '" media="screen, print">' . "\n";
      $output .= '<!-- End Chart -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}