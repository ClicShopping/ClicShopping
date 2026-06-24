<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Sites\Shop;

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\Cache;

  class BotDetector
  {
    protected $cache;

    public function __construct()
    {
      if (!Registry::exists('Cache')) {
        Registry::set('Cache', new Cache('BotDetector'));
      }
      $this->cache = Registry::get('Cache');
    }

    /**
     * Vérifie si le visiteur est un robot (général)
     */
    public function isBot(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      if (empty($user_agent)) {
        return true;
      }

      $spiders = $this->getSpidersList();

      foreach ($spiders as $spider) {
        if (str_contains($user_agent, $spider)) {
          return true;
        }
      }

      return false;
    }

    /**
     * RECOMMANDÉ POUR LE SEO : Détecte UNIQUEMENT les vrais moteurs de recherche légitimes
     */
    public function isSearchEngine(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      if (empty($user_agent)) {
        return false;
      }

      // Liste stricte des moteurs majeurs pour le SEO de votre boutique
      $search_engines = ['googlebot', 'bingbot', 'yandex', 'baiduspider', 'duckduckgo'];

      foreach ($search_engines as $engine) {
        if (str_contains($user_agent, $engine)) {
          return true;
        }
      }

      return false;
    }

    /**
     * Détecte UNIQUEMENT les robots d'Intelligence Artificielle et LLM
     */
    public function isAiBot(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      if (empty($user_agent)) {
        return false;
      }

      // Liste basée sur la fin de votre fichier spiders.txt
      $ai_bots = ['gptbot', 'chatgpt-user', 'oai-searchbot', 'claudebot', 'claude-web', 'anthropic-ai', 'perplexitybot', 'google-extended'];

      foreach ($ai_bots as $bot) {
        if (str_contains($user_agent, $bot)) {
          return true;
        }
      }

      return false;
    }

    /**
     * Récupère la liste globale depuis le fichier ou le cache
     */
    private function getSpidersList(): array
    {
      $cache_key = 'bot_detector_spiders_list';

      if ($this->cache->exists($cache_key)) {
        return $this->cache->get($cache_key);
      }

      $spiders_file = CLICSHOPPING::BASE_DIR . 'Sites/' . CLICSHOPPING::getSite() . '/Assets/spiders.txt';
      $spiders_array = [];

      if (file_exists($spiders_file)) {
        $content = file($spiders_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($content as $line) {
          $line = trim($line);
          if (!empty($line) && !str_starts_with($line, '#')) {
            $spiders_array[] = strtolower($line);
          }
        }

        $this->cache->save($cache_key, $spiders_array);
      }

      return $spiders_array;
    }

    /**
     * Détecte si le robot est un agent spécifiquement connu pour exécuter ou chercher du WebMCP
     */
    public function isWebMcpAgent(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      if (empty($user_agent)) {
        return false;
      }

      // Signatures des agents de navigation IA de nouvelle génération
      $webmcp_agents = ['oai-searchbot', 'chatgpt-user', 'google-extended', 'claudebot', 'perplexitybot'];

      foreach ($webmcp_agents as $agent) {
        if (str_contains($user_agent, $agent)) {
          return true;
        }
      }

      return false;
    }
  }