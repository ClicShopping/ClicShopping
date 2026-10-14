<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

use ClicShopping\OM\CLICSHOPPING;

class HeaderOutputCodeMirror
{
  /**
   * Generates and returns HTML output for including CodeMirror-related resources
   * if the user is authenticated as an admin in the session.
   *
   * @return string|bool The generated HTML output for CodeMirror resources or false if the user is not an admin.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!-- Start Mirror -->' . "\n";
      $output .= '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.css"/>' . "\n";
      $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/codemirror.css') . '">' . "\n";
      $output .= '<!-- End Code Mirror -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}