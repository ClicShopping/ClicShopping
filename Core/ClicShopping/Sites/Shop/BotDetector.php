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

    /**
     * BotDetector constructor.
     * Initializes cache dependency to optimize spider list loading
     */
    public function __construct()
    {
      if (!Registry::exists('Cache')) {
        // Provide an identifier to the constructor to avoid ArgumentCountError
        Registry::set('Cache', new Cache('BotDetector'));
      }
      $this->cache = Registry::get('Cache');
    }

    /**
     * Check if the current visitor is a bot/crawler
     * @return bool
     */
    public function isBot(): bool
    {
      $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

      // If no User Agent is provided, we assume it's a suspicious bot or malformed request
      if (empty($user_agent)) {
        return true;
      }

      // Retrieve the spider list from cache or flat file
      $spiders = $this->getSpidersList();

      foreach ($spiders as $spider) {
        if (str_contains($user_agent, $spider)) {
          return true;
        }
      }

      return false;
    }

    /**
     * Retrieve the spiders list with cache management
     * @return array
     */
    private function getSpidersList(): array
    {
      $cache_key = 'bot_detector_spiders_list';

      // Try to fetch data from cache first
      if ($this->cache->exists($cache_key)) {
        return $this->cache->get($cache_key);
      }

      // Fallback: read the spiders.txt configuration file
      $spiders_file = CLICSHOPPING::BASE_DIR . 'Sites/' . CLICSHOPPING::getSite() . '/Assets/spiders.txt';
      $spiders_array = [];

      if (file_exists($spiders_file)) {
        $content = file($spiders_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($content as $line) {
          $line = trim($line);
          // Ignore empty lines and comments starting with '#'
          if (!empty($line) && !str_starts_with($line, '#')) {
            $spiders_array[] = strtolower($line);
          }
        }

        // Save processed list to cache for future requests
        $this->cache->save($cache_key, $spiders_array);
      }

      return $spiders_array;
    }
  }