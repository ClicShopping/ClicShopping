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

         // MAIS que ce n'est PAS un moteur de recherche vital (Google, Bing...)
         if (!$botDetector->isSearchEngine() && !$botDetector->isWebMcpAgent()) {
           $parameters['can_start'] = false;
         }

        // On bloque la session pour les IA (pour économiser le serveur)
        if ($botDetector->isAiBot()) {
          $parameters['can_start'] = false;
        }
      }
    }
  }
