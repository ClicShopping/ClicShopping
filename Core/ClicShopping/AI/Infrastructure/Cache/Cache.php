<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Cache\Helper\SQLTableParser;

use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;
use ClicShopping\OM\Cache as OMCache;
use ClicShopping\OM\FileSystem;

use function count;
use function define;
use function defined;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_replace;
use function rename;
use function strip_tags;
use function dirname;
use function time;
use function mb_strtolower;
use function iconv;
use function md5;
use function trim;
use function uasort;
use function array_slice;

/**
 * Class Cache
 *
 * Handles caching functionality for prompts in the ChatGPT application
 * This class manages a cache system to store and retrieve prompts efficiently
 *
 */

class Cache
{
  private array $promptCache = [];
  private bool $enablePromptCache = false;
  private bool $debug = false;
  private bool $cache = false;
  private bool $useMemcached = false;
  private bool $useRedis = false;
  private ?\Memcached $memcached = null;
  private ?\Redis $redis = null;
  private SecurityLogger $securityLogger;
  private const MEMCACHED_PREFIX = 'rag_cache_';
  private const REDIS_PREFIX = 'rag_cache_';
  
  // Cache type prefixes for specialized caching
  public const CACHE_TYPE_EMBEDDING = 'emb_';
  public const CACHE_TYPE_SEMANTIC = 'sem_';
  public const CACHE_TYPE_SQL = 'sql_';
  public const CACHE_TYPE_WEB = 'web_';
  public const CACHE_TYPE_CONVERSATION = 'conv_';
  public const CACHE_TYPE_INTENT = 'intent_';
  public const CACHE_TYPE_TRANSLATION = 'trans_';
  public const CACHE_TYPE_CLASSIFICATION = 'class_';

  protected static array $memoryCache = [];
  
  // Statistics tracking by cache type
  private array $cacheStats = [
    'embedding' => ['hits' => 0, 'misses' => 0],
    'semantic' => ['hits' => 0, 'misses' => 0],
    'sql' => ['hits' => 0, 'misses' => 0],
    'web' => ['hits' => 0, 'misses' => 0],
    'conversation' => ['hits' => 0, 'misses' => 0],
    'intent' => ['hits' => 0, 'misses' => 0],
    'translation' => ['hits' => 0, 'misses' => 0],
    'classification' => ['hits' => 0, 'misses' => 0]
  ];

  /**
   * Cache constructor.
   *
   * Initializes the prompt cache system for the ChatGPT application.
   * Sets up debug and cache flags based on configuration constants.
   * Optionally enables prompt cache and initializes Memcached if configured.
   *
   * @param bool $enablePromptCache Whether to enable the prompt cache on construction (default: true)
   */
  public function __construct($enablePromptCache = true)
  {
    $this->promptCache = [];
    $this->securityLogger = new SecurityLogger();

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->cache = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';

    // First set the enable state
    $this->enablePromptCache = $this->cache === true ? true : false;

    if ($this->cache === true) {
      // Then call setPromptCacheEnabled with the parameter
      if ($enablePromptCache) {
        $this->setPromptCacheEnabled(true);
      }

      if (defined('USE_MEMCACHED') && USE_MEMCACHED == 'True') {
        $this->useMemcached = true;
        $this->initMemcached();
      } elseif (defined('USE_REDIS') && USE_REDIS === 'True') {
        $this->useRedis = true;
        $this->initRedis();
      }
    }
  }

  /**
   * Initializes the Memcached connection for caching.
   *
   * Checks if the Memcached extension is available, creates a Memcached instance,
   * adds a default server if none are configured, and tests the connection.
   * Logs events for debugging and disables Memcached usage on failure.
   *
   * @return void
   */
  private function initMemcached(): void
  {
    if (class_exists('Memcached')) {
      try {
        // Using CacheAdmin's Memcached instance to ensure consistency across the application
        $this->memcached = CacheAdmin::getMemcached();

        // Test connection
        $stats = $this->memcached->getStats();
        if (!is_array($stats) || count($stats) === 0) {
          throw new \Exception('Could not connect to Memcached server');
        }

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent("Memcached initialized", 'info');
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent("Memcached initialization failed: " . $e->getMessage(), 'error');
        }
        $this->useMemcached = false;
        $this->memcached = null;
      }
    } else {
      $this->useMemcached = false;
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("Memcached extension not available", 'warning');
      }
    }
  }

  /**
   * Initializes the Redis connection for caching.
   *
   * @return void
   */
  private function initRedis(): void
  {
    if (class_exists('Redis')) {
      try {
        $this->redis = new \Redis();
        // Assuming Redis is on localhost with default port
        if (!$this->redis->connect('localhost', 6379, 1)) {
          throw new \Exception('Could not connect to Redis server');
        }

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent("Redis initialized", 'info');
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent("Redis initialization failed: " . $e->getMessage(), 'error');
        }
        $this->useRedis = false;
        $this->redis = null;
      }
    } else {
      $this->useRedis = false;
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("Redis extension not available", 'warning');
      }
    }
  }

  /**
   * Gets statistics about the prompt cache.
   *
   * Returns information about the cache status, number of entries, source, and statistics by type.
   * If Memcached is used, returns Memcached stats; otherwise, returns file cache stats.
   *
   * @return array An array containing cache statistics with keys:
   * - 'enabled': boolean indicating if cache is enabled
   * - 'entries': integer count of cached items
   * - 'source' (optional): 'memcached', 'redis', or 'file'
   * - 'size_bytes' (optional): size of file cache in bytes
   * - 'cache_file' (optional): file path of the cache file
   * - 'stats_by_type': array of hit/miss statistics by cache type
   */
  public function getPromptCacheStats(): array
  {
    $baseStats = [];
    
    if ($this->useRedis && $this->redis) {
      $entries = count($this->redis->keys(self::REDIS_PREFIX . '*'));
      $baseStats = [
        'enabled' => true,
        'entries' => $entries,
        'source' => 'redis'
      ];
    } elseif ($this->useMemcached && $this->memcached) {
      $stats = $this->memcached->getStats();
      $entries = array_sum(array_column($stats, 'curr_items'));
      $baseStats = [
        'enabled' => true,
        'entries' => $entries,
        'source' => 'memcached'
      ];
    } else {
      $baseStats = [
        'enabled' => $this->enablePromptCache,
        'entries' => count($this->promptCache),
        'size_bytes' => strlen(json_encode($this->promptCache)),
        'cache_file' => $this->getPromptCacheFilePath(),
        'source' => 'file'
      ];
    }
    
    // Add statistics by type
    $baseStats['stats_by_type'] = $this->cacheStats;
    
    return $baseStats;
  }

  /**
   * Sets whether the prompt cache is enabled or disabled.
   *
   * Loads the prompt cache from file if enabling and the cache is empty.
   * Logs the change if debugging is enabled.
   *
   * @param bool $enable True to enable the cache, false to disable
   * @return void
   */
  public function setPromptCacheEnabled(bool $enable): void
  {
    $this->enablePromptCache = $enable;

    if ($enable && empty($this->promptCache)) {
      $this->loadPromptCache();
    }

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("Prompt cache " . ($enable ? "enabled" : "disabled"), 'info');
    }
  }

  /**
   * Loads the prompt cache data from the cache file into memory.
   *
   * If the cache file exists, attempts to load and decode the JSON data.
   * Prunes expired entries based on their TTL.
   * Initializes an empty cache if loading fails or file does not exist.
   * Does nothing if caching is disabled.
   *
   * @return void
   */
  private function loadPromptCache(): void
  {
    if (!$this->enablePromptCache) {
      return;
    }

    $cacheFile = $this->getPromptCacheFilePath();

    if (file_exists($cacheFile)) {
      $json = @file_get_contents($cacheFile);
      $this->promptCache = json_decode($json, true) ?: [];
      // prune expired
      $now = time();

      foreach ($this->promptCache as $k => $entry) {
        if ($now - $entry['last_used'] > $entry['ttl']) {
          unset($this->promptCache[$k]);
        }
      }

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("Prompt cache loaded with " . count($this->promptCache) . " live entries", 'info');
      }
    } else {
      $this->promptCache = [];
    }
  }

  /**
   * Ensures that a directory exists, creating it if necessary.
   *
   * @param string $dir The directory path to check/create
   * @throws \RuntimeException if the directory cannot be created
   * @return void
   */
  private static function ensureDirectoryExists(string $dir): void
  {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
      throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
    }
  }

  /**
   * Returns the file path for the log file.
   *
   * The log file is stored in the Work/Cache/Rag directory.
   *
   * @return string The file path for the log file
   */
  public static function getLogFilePath(): string
  {
    $logDir = CLICSHOPPING::BASE_DIR . 'Work/Log';
    self::ensureDirectoryExists($logDir);
    return $logDir . '/rag_security.log';
  }

  /**
   * Saves the current prompt cache data to the cache file.
   *
   * Creates the cache directory if it doesn't exist.
   * Encodes the cache data as JSON before saving.
   * Uses a temporary file and renames it for atomicity.
   * Logs the save event if debugging is enabled.
   * Does nothing if caching is disabled.
   *
   * @return void
   */
  public function savePromptCache(): void
  {
    if (!$this->enablePromptCache) {
      return;
    }

    $cacheFile = $this->getPromptCacheFilePath();

    try {
      $cacheDir = dirname($cacheFile);
      self::ensureDirectoryExists($cacheDir);

      $tmpFile = $cacheFile . '.tmp';
      file_put_contents($tmpFile, json_encode($this->promptCache));
      rename($tmpFile, $cacheFile);

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("Prompt cache saved with " . count($this->promptCache) . " entries", 'info');
      }
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("Error saving prompt cache: " . $e->getMessage(), 'error');
      }
    }
  }

  /**
   * Returns the file path for the prompt cache.
   *
   * The cache file is stored in the Work/Cache/Rag directory.
   *
   * @return string The file path for the prompt cache
   */
  private function getPromptCacheFilePath(): string
  {
    return CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag/rag_cache.cache';
  }

  /**
   * Generates a unique cache key for a given prompt.
   *
   * Normalizes the prompt by trimming whitespace, converting to lowercase,
   * removing punctuation, and stripping accents before hashing.
   *
   * @param string $prompt The prompt text to generate a key for
   * @return string MD5 hash of the normalized prompt
   */
  public function generateCacheKey(string $prompt): string
  {
    // strip tags, collapse whitespace, remove punctuation, lowercase, strip accents
    $clean = strip_tags($prompt);
    $clean = mb_strtolower($clean, 'UTF-8');
    $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $clean);
    $clean = preg_replace('/\s+/', ' ', $clean);
    $clean = mb_strtolower($clean, 'UTF-8');

    // remove accents
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
    $clean = $converted !== false ? $converted : $clean;

    return md5(trim($clean));
  }

  /**
   * Checks if a given prompt is already cached.
   *
   * Checks both Memcached and file cache depending on configuration.
   * Does nothing if caching is disabled.
   *
   * @param string $prompt The prompt text to check
   * @return bool True if the prompt is cached, false otherwise
   */
  public function isPromptInCache(string $prompt): bool
  {
    $cacheKey = $this->generateCacheKey($prompt);

    if ($this->useRedis && $this->redis) {
      return $this->redis->exists(self::REDIS_PREFIX . $cacheKey);
    } elseif ($this->useMemcached && $this->memcached) {
      return $this->memcached->get(self::MEMCACHED_PREFIX . $cacheKey) !== false;
    }

    if (!$this->enablePromptCache) {
      return false;
    }

    return isset($this->promptCache[$cacheKey]);
  }

  /**
   * Caches a response for a given prompt.
   *
   * Stores the prompt, response, and timestamps in the cache.
   * Limits the cache size to 1000 entries.
   * Stores in both Memcached (if enabled) and file cache as backup.
   * Does nothing if caching is disabled.
   *
   * @param string $prompt The prompt to cache
   * @param string $response The response to cache
   * @param int $ttl Time-to-live for the cache entry in seconds (default: 3600)
   * @return void
   */
  public function cacheResponse(string $prompt, string $response, int $ttl = 3600): void
  {
    if (!$this->enablePromptCache) {
      return;
    }

    $cacheKey = $this->generateCacheKey($prompt);
    $data = [
      'prompt' => $prompt,
      'response' => $response,
      'created' => time(),
      'last_used' => time(),
      'ttl' => $ttl
    ];

    if ($this->useRedis && $this->redis) {
      if (!$this->redis->setex(self::REDIS_PREFIX . $cacheKey, $ttl, json_encode($data))) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Redis setex failed",
            'warning'
          );
        }
      }
    } elseif ($this->useMemcached && $this->memcached) {
      if (!$this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $ttl)) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Memcached set failed: " . $this->memcached->getResultMessage(),
            'warning'
          );
        }
      }
    }

    // Always maintain file cache as backup
    $this->promptCache[$cacheKey] = $data;

    if (count($this->promptCache) > 1000) {
      uasort($this->promptCache, fn($a, $b) => $b['last_used'] - $a['last_used']);
      $this->promptCache = array_slice($this->promptCache, 0, 1000, true);
    }

    $this->savePromptCache();
  }

  /**
   * Retrieves a cached response for the given prompt if it exists.
   *
   * Updates the last_used timestamp if a cache entry is found.
   * Checks both Memcached and file cache depending on configuration.
   *
   * @param string $prompt The prompt to get the response for
   * @return string|null The cached response if found, null otherwise
   */
  public function getCachedResponse(string $prompt): ?string
  {
    if (!$this->enablePromptCache) {
      return null;
    }

    $cacheKey = $this->generateCacheKey($prompt);

    if ($this->useRedis && $this->redis) {
      $data = $this->redis->get(self::REDIS_PREFIX . $cacheKey);
      if ($data !== false) {
        $data = json_decode($data, true);
        if ($data !== null) {
          $data['last_used'] = time();
          $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $data['ttl'], json_encode($data));
          return $data['response'];
        }
      }
    } elseif ($this->useMemcached && $this->memcached) {
      $data = $this->memcached->get(self::MEMCACHED_PREFIX . $cacheKey);
      if ($data !== false) {
        $data['last_used'] = time();
        $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $data['ttl']);
        return $data['response'];
      }
    }

    if (isset($this->promptCache[$cacheKey])) {
      $data = $this->promptCache[$cacheKey];
      if (time() - $data['created'] <= $data['ttl']) {
        $this->promptCache[$cacheKey]['last_used'] = time();
        return $data['response'];
      }
      unset($this->promptCache[$cacheKey]);
    }

    return null;
  }

  /**
   * Caches an embedding for a given content and model.
   *
   * Stores the embedding with a cache key based on content and model.
   * Uses the CACHE_TYPE_EMBEDDING prefix for organization.
   *
   * @param string $content The content that was embedded
   * @param string $model The embedding model used
   * @param mixed $embedding The embedding vector/data to cache
   * @param int $ttl Time-to-live in seconds (default: 86400 = 24 hours)
   * @return void
   */
  public function cacheEmbedding(string $content, string $model, $embedding, int $ttl = 86400): void
  {
    if (!$this->enablePromptCache) {
      return;
    }

    $cacheKey = self::CACHE_TYPE_EMBEDDING . md5($content . $model);
    $data = [
      'content' => $content,
      'model' => $model,
      'embedding' => $embedding,
      'created' => time(),
      'last_used' => time(),
      'ttl' => $ttl
    ];

    if ($this->useRedis && $this->redis) {
      $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $ttl, json_encode($data));
    } elseif ($this->useMemcached && $this->memcached) {
      $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $ttl);
    }

    // Always maintain file cache as backup
    $this->promptCache[$cacheKey] = $data;
    $this->savePromptCache();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("Embedding cached for model: {$model}", 'info');
    }
  }

  /**
   * Retrieves a cached embedding for the given content and model.
   *
   * @param string $content The content to get the embedding for
   * @param string $model The embedding model used
   * @return mixed|null The cached embedding if found, null otherwise
   */
  public function getCachedEmbedding(string $content, string $model)
  {
    if (!$this->enablePromptCache) {
      return null;
    }

    $cacheKey = self::CACHE_TYPE_EMBEDDING . md5($content . $model);

    if ($this->useRedis && $this->redis) {
      $data = $this->redis->get(self::REDIS_PREFIX . $cacheKey);
      if ($data !== false) {
        $data = json_decode($data, true);
        if ($data !== null) {
          $this->cacheStats['embedding']['hits']++;
          $data['last_used'] = time();
          $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $data['ttl'], json_encode($data));
          return $data['embedding'];
        }
      }
    } elseif ($this->useMemcached && $this->memcached) {
      $data = $this->memcached->get(self::MEMCACHED_PREFIX . $cacheKey);
      if ($data !== false) {
        $this->cacheStats['embedding']['hits']++;
        $data['last_used'] = time();
        $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $data['ttl']);
        return $data['embedding'];
      }
    }

    if (isset($this->promptCache[$cacheKey])) {
      $data = $this->promptCache[$cacheKey];
      if (time() - $data['created'] <= $data['ttl']) {
        $this->cacheStats['embedding']['hits']++;
        $this->promptCache[$cacheKey]['last_used'] = time();
        return $data['embedding'];
      }
      unset($this->promptCache[$cacheKey]);
    }

    $this->cacheStats['embedding']['misses']++;
    return null;
  }

  /**
   * Caches a semantic search result.
   *
   * Stores the search results with a cache key based on query, entity type, and language.
   * Uses the CACHE_TYPE_SEMANTIC prefix for organization.
   *
   * @param string $query The search query
   * @param string $entityType The entity type being searched
   * @param int $languageId The language ID
   * @param array $results The search results to cache
   * @param int $ttl Time-to-live in seconds (default: 1800 = 30 minutes)
   * @return void
   */
  public function cacheSemanticSearch(string $query, string $entityType, int $languageId, array $results, int $ttl = 1800): void
  {
    if (!$this->enablePromptCache) {
      return;
    }

    $cacheKey = self::CACHE_TYPE_SEMANTIC . md5($query . $entityType . $languageId);
    $data = [
      'query' => $query,
      'entity_type' => $entityType,
      'language_id' => $languageId,
      'results' => $results,
      'created' => time(),
      'last_used' => time(),
      'ttl' => $ttl
    ];

    if ($this->useRedis && $this->redis) {
      $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $ttl, json_encode($data));
    } elseif ($this->useMemcached && $this->memcached) {
      $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $ttl);
    }

    // Always maintain file cache as backup
    $this->promptCache[$cacheKey] = $data;
    $this->savePromptCache();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("Semantic search cached for entity: {$entityType}", 'info');
    }
  }

  /**
   * Retrieves a cached semantic search result.
   *
   * @param string $query The search query
   * @param string $entityType The entity type being searched
   * @param int $languageId The language ID
   * @return array|null The cached search results if found, null otherwise
   */
  public function getCachedSemanticSearch(string $query, string $entityType, int $languageId): ?array
  {
    if (!$this->enablePromptCache) {
      return null;
    }

    $cacheKey = self::CACHE_TYPE_SEMANTIC . md5($query . $entityType . $languageId);

    if ($this->useRedis && $this->redis) {
      $data = $this->redis->get(self::REDIS_PREFIX . $cacheKey);
      if ($data !== false) {
        $data = json_decode($data, true);
        if ($data !== null) {
          $this->cacheStats['semantic']['hits']++;
          $data['last_used'] = time();
          $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $data['ttl'], json_encode($data));
          return $data['results'];
        }
      }
    } elseif ($this->useMemcached && $this->memcached) {
      $data = $this->memcached->get(self::MEMCACHED_PREFIX . $cacheKey);
      if ($data !== false) {
        $this->cacheStats['semantic']['hits']++;
        $data['last_used'] = time();
        $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $data['ttl']);
        return $data['results'];
      }
    }

    if (isset($this->promptCache[$cacheKey])) {
      $data = $this->promptCache[$cacheKey];
      if (time() - $data['created'] <= $data['ttl']) {
        $this->cacheStats['semantic']['hits']++;
        $this->promptCache[$cacheKey]['last_used'] = time();
        return $data['results'];
      }
      unset($this->promptCache[$cacheKey]);
    }

    $this->cacheStats['semantic']['misses']++;
    return null;
  }

  /**
   * Caches a SQL query generated from natural language with table tracking.
   *
   * Stores the SQL query with a cache key based on natural query and context.
   * Uses the CACHE_TYPE_SQL prefix for organization.
   * Automatically extracts and stores table names for intelligent invalidation.
   *
   * @param string $naturalQuery The natural language query
   * @param string $sqlQuery The generated SQL query
   * @param array $results The query results
   * @param array $context Additional context used for generation
   * @param int $ttl Time-to-live in seconds (default: 3600 = 1 hour)
   * @return void
   */
  public function cacheSQLQuery(string $naturalQuery, string $sqlQuery, array $results, array $context, int $ttl = 3600): void
  {
    if (!$this->enablePromptCache) {
      return;
    }

    // Extract tables from SQL query for intelligent invalidation
    $tablesUsed = \ClicShopping\AI\Infrastructure\Cache\Helper\SQLTableParser::extractTables($sqlQuery);

    $cacheKey = self::CACHE_TYPE_SQL . md5($naturalQuery . json_encode($context));
    $data = [
      'natural_query' => $naturalQuery,
      'sql_query' => $sqlQuery,
      'results' => $results,
      'context' => $context,
      'tables_used' => $tablesUsed, // Store table names for invalidation
      'created' => time(),
      'last_used' => time(),
      'ttl' => $ttl
    ];

    if ($this->useRedis && $this->redis) {
      $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $ttl, json_encode($data));
    } elseif ($this->useMemcached && $this->memcached) {
      $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $ttl);
    }

    // Always maintain file cache as backup
    $this->promptCache[$cacheKey] = $data;
    $this->savePromptCache();

    if ($this->debug) {
      $tablesList = implode(', ', $tablesUsed);
      $this->securityLogger->logSecurityEvent("SQL query cached: {$naturalQuery} (tables: {$tablesList})", 'info');
    }
  }

  /**
   * Retrieves a cached SQL query.
   *
   * @param string $naturalQuery The natural language query
   * @param array $context Additional context used for generation
   * @return array|null Array with 'sql_query' and 'results' if found, null otherwise
   */
  public function getCachedSQLQuery(string $naturalQuery, array $context): ?array
  {
    if (!$this->enablePromptCache) {
      return null;
    }

    $cacheKey = self::CACHE_TYPE_SQL . md5($naturalQuery . json_encode($context));

    if ($this->useRedis && $this->redis) {
      $data = $this->redis->get(self::REDIS_PREFIX . $cacheKey);
      if ($data !== false) {
        $data = json_decode($data, true);
        if ($data !== null) {
          $this->cacheStats['sql']['hits']++;
          $data['last_used'] = time();
          $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $data['ttl'], json_encode($data));
          return [
            'sql_query' => $data['sql_query'],
            'results' => $data['results']
          ];
        }
      }
    } elseif ($this->useMemcached && $this->memcached) {
      $data = $this->memcached->get(self::MEMCACHED_PREFIX . $cacheKey);
      if ($data !== false) {
        $this->cacheStats['sql']['hits']++;
        $data['last_used'] = time();
        $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $data['ttl']);
        return [
          'sql_query' => $data['sql_query'],
          'results' => $data['results']
        ];
      }
    }

    if (isset($this->promptCache[$cacheKey])) {
      $data = $this->promptCache[$cacheKey];
      if (time() - $data['created'] <= $data['ttl']) {
        $this->cacheStats['sql']['hits']++;
        $this->promptCache[$cacheKey]['last_used'] = time();
        return [
          'sql_query' => $data['sql_query'],
          'results' => $data['results']
        ];
      }
      unset($this->promptCache[$cacheKey]);
    }

    $this->cacheStats['sql']['misses']++;
    return null;
  }

  /**
   * Caches a web search result.
   *
   * Stores the search results with a cache key based on the query.
   * Uses the CACHE_TYPE_WEB prefix for organization.
   *
   * @param string $query The search query
   * @param array $results The search results to cache
   * @param int $ttl Time-to-live in seconds (default: 7200 = 2 hours)
   * @return void
   */
  public function cacheWebSearch(string $query, array $results, int $ttl = 7200): void
  {
    if (!$this->enablePromptCache) {
      return;
    }

    $cacheKey = self::CACHE_TYPE_WEB . md5($query);
    $data = [
      'query' => $query,
      'results' => $results,
      'created' => time(),
      'last_used' => time(),
      'ttl' => $ttl
    ];

    if ($this->useRedis && $this->redis) {
      $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $ttl, json_encode($data));
    } elseif ($this->useMemcached && $this->memcached) {
      $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $ttl);
    }

    // Always maintain file cache as backup
    $this->promptCache[$cacheKey] = $data;
    $this->savePromptCache();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("Web search cached: {$query}", 'info');
    }
  }

  /**
   * Retrieves a cached web search result.
   *
   * @param string $query The search query
   * @return array|null The cached search results if found, null otherwise
   */
  public function getCachedWebSearch(string $query): ?array
  {
    if (!$this->enablePromptCache) {
      return null;
    }

    $cacheKey = self::CACHE_TYPE_WEB . md5($query);

    if ($this->useRedis && $this->redis) {
      $data = $this->redis->get(self::REDIS_PREFIX . $cacheKey);
      if ($data !== false) {
        $data = json_decode($data, true);
        if ($data !== null) {
          $this->cacheStats['web']['hits']++;
          $data['last_used'] = time();
          $this->redis->setex(self::REDIS_PREFIX . $cacheKey, $data['ttl'], json_encode($data));
          return $data['results'];
        }
      }
    } elseif ($this->useMemcached && $this->memcached) {
      $data = $this->memcached->get(self::MEMCACHED_PREFIX . $cacheKey);
      if ($data !== false) {
        $this->cacheStats['web']['hits']++;
        $data['last_used'] = time();
        $this->memcached->set(self::MEMCACHED_PREFIX . $cacheKey, $data, $data['ttl']);
        return $data['results'];
      }
    }

    if (isset($this->promptCache[$cacheKey])) {
      $data = $this->promptCache[$cacheKey];
      if (time() - $data['created'] <= $data['ttl']) {
        $this->cacheStats['web']['hits']++;
        $this->promptCache[$cacheKey]['last_used'] = time();
        return $data['results'];
      }
      unset($this->promptCache[$cacheKey]);
    }

    $this->cacheStats['web']['misses']++;
    return null;
  }

  /**
   * Clear semantic cache for a specific entity type
   *
   * @param string|null $entityType The entity type to clear (null = clear all semantic cache)
   * @param int|null $languageId The language ID to clear (null = all languages)
   * @return int Number of files cleared
   */
  public static function clearSemanticCache(?string $entityType = null, ?int $languageId = null): int
  {
    if ($entityType === null && $languageId === null) {
      // Clear all semantic cache
      $cache = new Cache();

      return $cache->clearCacheByType('semantic');
    }

    if (!FileSystem::isWritable(OMCache::getPath())) {
      return 0;
    }

    $namespacePath = 'Rag/Semantic/';
    $fullPath = OMCache::getPath() . $namespacePath;

    if (!is_dir($fullPath)) {
      return 0;
    }

    $cleared = 0;
    $files = glob($fullPath . '*.cache', GLOB_NOSORT);

    // We need to check each cache file to see if it matches the entity type or language
    // Since the cache key is md5(query + entityType + languageId), we can't directly
    // match by filename. Instead, we'll clear all semantic cache when entity data changes.
    // This is a conservative approach that ensures consistency.

    foreach ($files as $file) {
      if (unlink($file)) {
        $cleared++;

        // Also remove from memory cache
        $filename = basename($file, '.cache');
        $fullKey = 'Rag/Semantic_' . $filename;

        OMCache::memoryCache($fullKey);
        unset(static::$memoryCache[$fullKey]);
      }
    }

    if ($cleared > 0) {
      $context = [];
      if ($entityType !== null) {
        $context[] = "EntityType: {$entityType}";
      }
      if ($languageId !== null) {
        $context[] = "LanguageId: {$languageId}";
      }
      $contextStr = !empty($context) ? ' (' . implode(', ', $context) . ')' : '';
      error_log("Semantic cache invalidated: {$cleared} files cleared{$contextStr}");
    }

    return $cleared;
  }

  /**
   * Clears cache entries by type.
   *
   * Removes all cache entries matching the specified type prefix.
   * Supports clearing from Memcached, Redis, and file cache.
   *
   * @param string $type The cache type to clear (e.g., 'embedding', 'semantic', 'sql', 'web', 'conversation')
   * @return int The number of entries cleared
   */
  public function clearCacheByType(string $type): int
  {
    $cleared = 0;
    
    // Map type names to prefixes
    $prefixMap = [
      'embedding' => self::CACHE_TYPE_EMBEDDING,
      'semantic' => self::CACHE_TYPE_SEMANTIC,
      'sql' => self::CACHE_TYPE_SQL,
      'web' => self::CACHE_TYPE_WEB,
      'conversation' => self::CACHE_TYPE_CONVERSATION,
      'intent' => self::CACHE_TYPE_INTENT,
      'translation' => self::CACHE_TYPE_TRANSLATION,
      'classification' => self::CACHE_TYPE_CLASSIFICATION
    ];
    
    $prefix = $prefixMap[$type] ?? null;
    if ($prefix === null) {
      return 0;
    }

    // Clear from Redis
    if ($this->useRedis && $this->redis) {
      $keys = $this->redis->keys(self::REDIS_PREFIX . $prefix . '*');
      foreach ($keys as $key) {
        $this->redis->del($key);
        $cleared++;
      }
    }

    // Clear from Memcached (note: Memcached doesn't support key pattern matching easily)
    // We'll rely on TTL expiration for Memcached

    // Clear from file cache
    foreach ($this->promptCache as $key => $data) {
      if (strpos($key, $prefix) === 0) {
        unset($this->promptCache[$key]);
        $cleared++;
      }
    }

    $this->savePromptCache();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("Cleared {$cleared} cache entries of type: {$type}", 'info');
    }

    return $cleared;
  }

  /**
   * Gets cache statistics by type.
   *
   * Returns hit/miss statistics for each cache type.
   *
   * @return array Array of statistics by cache type
   */
  public function getCacheStatsByType(): array
  {
    return $this->cacheStats;
  }

  /**
   * Invalidates all SQL query cache entries that reference a specific table.
   *
   * This method is used for intelligent cache invalidation - when a table is updated,
   * all cached SQL queries that reference that table are invalidated.
   *
   * @param string $tableName The name of the table that was updated
   * @return int The number of cache entries invalidated
   */
  public function invalidateCacheByTable(string $tableName): int
  {
    $invalidated = 0;
    $cleanTableName = SQLTableParser::cleanTableName($tableName);

    // Invalidate from Redis
    if ($this->useRedis && $this->redis) {
      $keys = $this->redis->keys(self::REDIS_PREFIX . self::CACHE_TYPE_SQL . '*');
      foreach ($keys as $key) {
        $data = $this->redis->get($key);
        if ($data !== false) {
          $data = json_decode($data, true);
          if (isset($data['tables_used']) && in_array($cleanTableName, $data['tables_used'], true)) {
            $this->redis->del($key);
            $invalidated++;
          }
        }
      }
    }

    // Invalidate from Memcached
    // Note: Memcached doesn't support key pattern matching, so we can't efficiently
    // iterate through all keys. We'll rely on TTL expiration for Memcached.
    // For production use, consider using Redis for better invalidation support.

    // Invalidate from file cache
    foreach ($this->promptCache as $key => $data) {
      if (strpos($key, self::CACHE_TYPE_SQL) === 0) {
        if (isset($data['tables_used']) && in_array($cleanTableName, $data['tables_used'], true)) {
          unset($this->promptCache[$key]);
          $invalidated++;
        }
      }
    }

    $this->savePromptCache();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("Invalidated {$invalidated} SQL cache entries for table: {$tableName}", 'info');
    }

    return $invalidated;
  }

  /**
   * Invalidates all SQL query cache entries that reference any of the specified tables.
   *
   * This is a batch version of invalidateCacheByTable for efficiency when multiple
   * tables are updated in a single operation.
   *
   * @param array $tableNames Array of table names that were updated
   * @return int The number of cache entries invalidated
   */
  public function invalidateCacheByTables(array $tableNames): int
  {
    $invalidated = 0;
    $cleanTableNames = array_map(
      SQLTableParser::cleanTableName(...),
      $tableNames
    );

    // Invalidate from Redis
    if ($this->useRedis && $this->redis) {
      $keys = $this->redis->keys(self::REDIS_PREFIX . self::CACHE_TYPE_SQL . '*');
      foreach ($keys as $key) {
        $data = $this->redis->get($key);
        if ($data !== false) {
          $data = json_decode($data, true);
          if (isset($data['tables_used'])) {
            $intersection = array_intersect($cleanTableNames, $data['tables_used']);
            if (!empty($intersection)) {
              $this->redis->del($key);
              $invalidated++;
            }
          }
        }
      }
    }

    // Invalidate from file cache
    foreach ($this->promptCache as $key => $data) {
      if (strpos($key, self::CACHE_TYPE_SQL) === 0) {
        if (isset($data['tables_used'])) {
          $intersection = array_intersect($cleanTableNames, $data['tables_used']);
          if (!empty($intersection)) {
            unset($this->promptCache[$key]);
            $invalidated++;
          }
        }
      }
    }

    $this->savePromptCache();

    if ($this->debug) {
      $tablesList = implode(', ', $tableNames);
      $this->securityLogger->logSecurityEvent("Invalidated {$invalidated} SQL cache entries for tables: {$tablesList}", 'info');
    }

    return $invalidated;
  }

  /**
   * Resets cache statistics.
   *
   * Clears all hit/miss counters for all cache types.
   *
   * @return void
   */
  public function resetCacheStats(): void
  {
    foreach ($this->cacheStats as $type => $stats) {
      $this->cacheStats[$type] = ['hits' => 0, 'misses' => 0];
    }
  }
}