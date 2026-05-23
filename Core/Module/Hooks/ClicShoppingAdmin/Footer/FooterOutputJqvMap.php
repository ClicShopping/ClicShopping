<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

class FooterOutputJqvMap
{
  /**
   * Generates and returns a script block for initializing the Jqvmap visualization
   * if the session indicates an administrative user and there are no query string parameters.
   *
   * @return string|bool Returns the generated script block as a string if conditions are met,
   *                     or false if the conditions are not satisfied.
   */
  public function display(): string|bool
  {
    $params = $_SERVER['QUERY_STRING'];

    if (!empty($params)) {
      return false;
    }

    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<! -- Start Jqvmap -->' . "\n";
      $output .= '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/jqvmap/1.5.1/jquery.vmap.min.js"></script>' . "\n";
      $output .= '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/jqvmap/1.5.1/maps/jquery.vmap.world.js"></script>' . "\n";
      $output .= '<!-- End Jqvmap  -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}