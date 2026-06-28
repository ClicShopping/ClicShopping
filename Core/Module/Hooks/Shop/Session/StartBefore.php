<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\OM\Module\Hooks\Shop\Session;

  use ClicShopping\Sites\Shop\BotDetector;

  class StartBefore
  {
    /**
     * Executes a series of checks to identify whether the user agent belongs to a web crawler or spider.
     * Blocks the start of specific processes if a spider is detected.
     *
     * @param array $parameters An associative array containing control parameters.
     * @return void
     */
    public function execute($parameters)
    {
       if (defined('SESSION_BLOCK_SPIDERS') && SESSION_BLOCK_SPIDERS == 'True') {
        $botDetector = new BotDetector();

        if ($botDetector->isBot() && !$botDetector->isSearchEngine()) {

          if ($botDetector->isAiBot() || $botDetector->isWebMcpAgent()) {
            // AI / WebMCP agents: deny the session only (content stays readable
            // for llms.txt / WebMCP) — gentle, spares the session store.
            $parameters['can_start'] = false;
          } else {
            // Bad bot / scanner: hard 403 BEFORE any Memcached read/write, to
            // stop fake-URL cache-miss pollution at the earliest point.
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Access Denied (Bad bot activity detected)');
          }
        }
      }
    }
  }
