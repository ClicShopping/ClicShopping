<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Footer;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class FooterOutputClicShopping
{
  /**
   * Generates and returns the HTML script tag for including the default ClicShopping footer JavaScript file.
   *
   * @return string The HTML string containing the script tag to include the footer JavaScript file.
   */
  public function display(): string
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    $output = '<!--ClicShopping Script start-->' . "\n";
    $output .= '<script defer src="' . CLICSHOPPING::link($CLICSHOPPING_Template->getTemplateDefaultJavaScript('clicshopping/footer.js')) . '"></script>' . "\n";
    $output .= '<!--End ClicShopping Script-->' . "\n";

    return $output;
  }
}