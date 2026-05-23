<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Session;

use ClicShopping\OM\CLICSHOPPING;
use function file;
use function is_array;
use function strlen;

class StartBefore
{
  /**
   * Executes a series of checks to identify whether the user agent belongs to a web crawler or spider.
   * Blocks the start of specific processes if a spider is detected, based on matching user agents against a pre-defined list.
   *
   * @param array $parameters An associative array containing control parameters, which will be modified to determine if a process can start.
   * @return void
   */
  public function execute($parameters)
  {
    if (SESSION_BLOCK_SPIDERS == 'True') {
      $user_agent = '';

      if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $user_agent = mb_strtolower($_SERVER['HTTP_USER_AGENT']);
      }

      if (!empty($user_agent)) {
        $file_array = file(CLICSHOPPING::BASE_DIR . 'Sites/' . CLICSHOPPING::getSite() . '/Assets/spiders.txt');

        if (is_array($file_array)) {
          foreach ($file_array as $spider) {
            if ((substr($spider, strlen($spider) - 1, 1) == ' ') || (substr($spider, strlen($spider) - 1, 1) == "\n")) {
              $spider = substr($spider, 0, strlen($spider) - 1);
            }

            if (!empty($spider)) {
              if (strpos($user_agent, $spider) !== false) {
                $parameters['can_start'] = false;
                break;
              }
            }
          }
        }
      }
    }
  }
}
