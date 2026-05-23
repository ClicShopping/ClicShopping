<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Header;

use ClicShopping\OM\Registry;

class HeaderOutputAMetaHeader
{
  /**
   * Generates and returns the output string containing app header tags and header tag blocks.
   *
   * @return string Concatenated string consisting of app header tags and header tag blocks.
   */
  public function display(): string
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    $output = $CLICSHOPPING_Template->getAppsHeaderTags() . "\n";
    $output .= $CLICSHOPPING_Template->getBlocks('header_tags') . "\n";

    return $output;
  }
}