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

  /**
   * Class BotDetector
   *
   * Handles the detection of various types of web crawlers, search engines,
   * and AI bots by analyzing the HTTP User-Agent header.
   */
  class BotDetector
  {
    /**
     * @var Cache The cache instance used to store the parsed spiders list.
     */
    protected $cache;

    /**
     * BotDetector constructor.
     *
     * Initializes and registers the dedicated Cache instance for the bot detector.
     */
    public function __construct()
    {
      if (!Registry::exists('Cache')) {
        Registry::set('Cache', new Cache('BotDetector'));
      }
      $this->cache = Registry::get('Cache');
    }

    /**
     * Checks if the visitor is a generic web robot or automated scraper.
     *
     * @return bool True if the User-Agent is empty or matches the global spiders list, false otherwise.
     */
    public function isBot(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      // An empty User-Agent is highly suspicious and treated as a bot
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
     * Detects ONLY legitimate search engines.
     *
     * RECOMMENDED FOR SEO: Ensures major search engine crawlers are not blocked,
     * protecting the store's search visibility.
     *
     * @return bool True if the visitor matches a primary search engine crawler, false otherwise.
     */
    public function isSearchEngine(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      if (empty($user_agent)) {
        return false;
      }

      // Strict list of major search engines critical for shop SEO
      $search_engines = ['googlebot', 'bingbot', 'yandex', 'baiduspider', 'duckduckgo'];

      foreach ($search_engines as $engine) {
        if (str_contains($user_agent, $engine)) {
          return true;
        }
      }

      return false;
    }

    /**
     * Detects ONLY Artificial Intelligence and LLM data collection bots.
     *
     * @return bool True if the crawler belongs to an AI training or research model, false otherwise.
     */
    public function isAiBot(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      if (empty($user_agent)) {
        return false;
      }

      // Based on the AI crawler signatures appended to the spiders.txt file
      $ai_bots = ['gptbot', 'chatgpt-user', 'oai-searchbot', 'claudebot', 'claude-web', 'anthropic-ai', 'perplexitybot', 'google-extended'];

      foreach ($ai_bots as $bot) {
        if (str_contains($user_agent, $bot)) {
          return true;
        }
      }

      return false;
    }

    /**
     * Retrieves the global list of spiders from the text configuration file.
     *
     * Utilizes the caching framework to prevent redundant file system reads.
     *
     * @return array A normalized (lowercased) array of detectable spider signatures.
     */
    private function getSpidersList(): array
    {
      $cache_key = 'bot_detector_spiders_list';

      // Return the cached list immediately if available
      if ($this->cache->exists($cache_key)) {
        return $this->cache->get($cache_key);
      }

      $spiders_file = CLICSHOPPING::BASE_DIR . 'Sites/' . CLICSHOPPING::getSite() . '/Assets/spiders.txt';
      $spiders_array = [];

      // Process and sanitize the spiders.txt file
      if (file_exists($spiders_file)) {
        $content = file($spiders_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($content as $line) {
          $line = trim($line);
          // Omit empty lines and inline comments starting with '#'
          if (!empty($line) && !str_starts_with($line, '#')) {
            $spiders_array[] = strtolower($line);
          }
        }

        // Cache the clean dataset for future requests
        $this->cache->save($cache_key, $spiders_array);
      }

      return $spiders_array;
    }

    /**
     * Detects if the visitor is an AI agent specifically acting as a WebMCP browser.
     *
     * WebMCP agents represent next-generation dynamic AI browsing clients.
     *
     * @return bool True if the agent matches known WebMCP signatures, false otherwise.
     */
    public function isWebMcpAgent(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      if (empty($user_agent)) {
        return false;
      }

      // Signatures for next-gen interactive AI browsing agents
      $webmcp_agents = ['oai-searchbot', 'chatgpt-user', 'google-extended', 'claudebot', 'perplexitybot'];

      foreach ($webmcp_agents as $agent) {
        if (str_contains($user_agent, $agent)) {
          return true;
        }
      }

      return false;
    }
  }