<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

class FooterOutputMustache
{
  /**
   * @return string|bool
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!-- Mustache Script start-->' . "\n";
      $output .= '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/mustache.js/4.2.0/mustache.min.js"></script>' . "\n";

      $output .= '<!--Mustache end -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}