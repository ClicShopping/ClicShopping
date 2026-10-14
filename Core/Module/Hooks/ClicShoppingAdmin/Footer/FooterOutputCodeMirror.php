<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

class FooterOutputCodeMirror
{
  /**
   * Generates and returns a script for CodeMirror editor setup if the user is an admin.
   *
   * @return string|bool Returns the generated output as a string if the user is an admin, or false otherwise.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!--CodeMirror Script start-->' . "\n";
      $output .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/codemirror.min.js"></script>' . "\n";
      $output .= '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/mode/css/css.min.js"></script>' . "\n";
      $output .= '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/6.65.7/addon/selection/active-line.min.js"></script>' . "\n";

      $output .= '<script defer>';
      $output .= 'if (document.getElementById("code") != null) {';
      $output .= 'var editor = CodeMirror.fromTextArea(document.getElementById("code"), {';
      $output .= 'styleActiveLine: true,';
      $output .= 'lineNumbers: true,';
      $output .= 'lineWrapping: true,';
      $output .= 'viewportMargin: Infinity,';
      $output .= 'smartInden: true,';
      $output .= 'spellcheck: true,';
      $output .= 'autofocus: true,';
      $output .= 'extraKeys: {"Ctrl-Space": "autocomplete"}';
      $output .= '})';
      $output .= '};';
      $output .= '</script>';

      $output .= '<!--End CodeMirror Script-->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}