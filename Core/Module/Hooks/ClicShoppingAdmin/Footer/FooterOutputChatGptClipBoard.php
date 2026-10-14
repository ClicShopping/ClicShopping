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

class FooterOutputChatGptClipBoard
{
  /**
   * Generates and returns HTML output for the admin clipboard functionality if the admin session is active and specific conditions are met.
   *
   * @return string The generated HTML output or an empty string if conditions are not met.
   */
  public function display(): string
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      $output = '<!-- Start Clipboard -->' . "\n";

      if (!defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS') || CLICSHOPPING_APP_CHATGPT_CH_STATUS == 'True') {
        $output .= '<script src="' . CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/ChatGpt/chat_modal.js') . '"></script>';
        $output .= '<!-- End Clipboard -->' . "\n";
      }
    }

    return $output;
  }
}