<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

use function defined;

class FooterOutputGpt
{
  /**
   * Renders and returns the necessary JavaScript and HTML for a modal chat interface
   * if the admin session is active and the ChatGPT application status is enabled.
   *
   * @return string The generated HTML and JavaScript for the modal chat interface.
   */
  public function display(): string
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      if (!defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS') || CLICSHOPPING_APP_CHATGPT_CH_STATUS == 'True') {

        // Note: Le JavaScript d'envoi des messages est maintenant dans
        // ext/javascript/clicshopping/ClicShoppingAdmin/ChatGpt/chat_send.js
        // et est chargé par Gpt::gptModalMenu()
      }
    }

    return $output;
  }
}