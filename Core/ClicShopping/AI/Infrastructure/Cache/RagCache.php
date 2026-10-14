<?php
/**
 * RagCache - Redis/Memcached Cache Adapter for RAG System
 * 
 * Adapts existing Redis/Memcached session classes for RAG cache usage.
 * Provides fast in-memory caching with graceful fallback to database.
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache;


use ClicShopping\OM\Session\Redis as RedisSession;
use ClicShopping\OM\Session\Memcached as MemcachedSession;

/**
 * RagCache Class
 * 
 * Provides high-performance caching using Redis or Memcached backends.
 * Reuses existing session handler classes with RAG-specific adaptations.
 * 
 * Features:
 * - Sub-millisecond cache access (vs 10-50ms database)
 * - Automatic backend selection (Redis > Memcached > Database)
 * - Graceful fallback on connection failures
 * - TTL support for cache expiration
 * - Statistics and monitoring
 * 
 * Configuration:
 * - CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER: Enable/disable cache
 * - CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER: Enable debug logging
 * - USE_MEMCACHED: Enable Memcached backend (True/False)
 * - USE_REDIS: Enable Redis backend (True/False)
 * - MEMCACHED_CACHE_LIFETIME: Default TTL in seconds (default: 3600)
 */

class RagCache
{
  private const CACHE_PREFIX = 'rag:';
  private const DEFAULT_TTL = 3600; // 1 hour
  
  private ?RedisSession $redisHandler = null;
  private ?MemcachedSession $memcachedHandler = null;
  private ?\Redis $redisConn = null;
  private ?\Memcached $memcachedConn = null;
  
  private string $backend = 'none'; // 'redis', 'memcached', or 'none'
  private bool $enabled = true;
  private bool $debug = false;
  private int $defaultTTL;
  
  // Statistics
  private int $hits = 0;
  private int $misses = 0;
  private int $sets = 0;
  private int $deletes = 0;
  private int $errors = 0;

  /**
   * Constructor
   * 
   * Initializes cache backend based on configuration.
   * Priority: Redis > Memcached > None (fallback to database)
   * 
   * @param bool $forceEnable Force enable cache regardless of config
   */
  public function __construct(bool $forceEnable = false)
  {
    // Check if cache is enabled
    $this->enabled = $forceEnable || 
      (!defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') || 
       CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True');
    
    // Check debug mode
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    
    // Get default TTL
    $this->defaultTTL = defined('MEMCACHED_CACHE_LIFETIME') ? (int)MEMCACHED_CACHE_LIFETIME : self::DEFAULT_TTL;
    
    if (!$this->enabled) {
      if ($this->debug) {
        error_log("RagCache: Cache disabled (CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER = False)");
      }
      return;
    }
    
    // Try to initialize backend
    $this->initializeBackend();
  }

  /**
   * Initialize cache backend
   * Priority: Redis > Memcached > None
   */
  private function initializeBackend(): void
  {
    // Try Redis first
    if (defined('USE_REDIS') && USE_REDIS === 'True') {
      if ($this->initializeRedis()) {
        $this->backend = 'redis';
        if ($this->debug) {
          error_log("RagCache: Using Redis backend");
        }
        return;
      }
    }
    
    // Try Memcached second
    if (defined('USE_MEMCACHED') && USE_MEMCACHED === 'True') {
      if ($this->initializeMemcached()) {
        $this->backend = 'memcached';
        if ($this->debug) {
          error_log("RagCache: Using Memcached backend");
        }
        return;
      }
    }
    
    // No backend available
    $this->backend = 'none';
    if ($this->debug) {
      error_log("RagCache: No cache backend available, will fallback to database");
    }
  }

  /**
   * Initialize Redis backend
   * 
   * @return bool True if Redis is available and connected
   */
  private function initializeRedis(): bool
  {
    try {
      // Check if Redis extension is loaded
      if (!extension_loaded('redis') || !class_exists('\Redis')) {
        if ($this->debug) {
          error_log("RagCache: Redis extension not available");
        }
        return false;
      }
      
      // Create Redis connection
      $this->redisConn = new \Redis();
      
      // Connect to Redis server
      if (!$this->redisConn->connect('localhost', 6379, 1)) {
        if ($this->debug) {
          error_log("RagCache: Failed to connect to Redis server");
        }
        $this->redisConn = null;
        return false;
      }
      
      // Test connection
      $ping = $this->redisConn->ping();
      $isPingOk = $ping === true || strtoupper((string)$ping) === '+PONG' || strtoupper((string)$ping) === 'PONG';

      if (!$isPingOk) {
        if ($this->debug) {
          error_log("RagCache: Redis ping failed");
        }
        $this->redisConn->close();
        $this->redisConn = null;

        return false;
      }
      
      return true;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("RagCache: Redis initialization error: " . $e->getMessage());
      }
      $this->redisConn = null;
      return false;
    }
  }

  /**
   * Initialize Memcached backend
   * 
   * @return bool True if Memcached is available and connected
   */
  private function initializeMemcached(): bool
  {
    try {
      // Check if Memcached extension is loaded
      if (!extension_loaded('memcached') || !class_exists('Memcached')) {
        if ($this->debug) {
          error_log("RagCache: Memcached extension not available");
        }
        return false;
      }
      
      // Create Memcached connection with persistent ID
      $this->memcachedConn = new \Memcached('clicshopping_rag_cache');
      
      // Configure Memcached options
      $this->memcachedConn->setOptions([
        \Memcached::OPT_COMPRESSION => true,
        \Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
        \Memcached::OPT_BINARY_PROTOCOL => true,
        \Memcached::OPT_TCP_NODELAY => true,
        \Memcached::OPT_CONNECT_TIMEOUT => 1000,
        \Memcached::OPT_RETRY_TIMEOUT => 2,
        \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT
      ]);
      
      // Add server if not already added (persistent connection)
      if (count($this->memcachedConn->getServerList()) === 0) {
        if (!$this->memcachedConn->addServer('localhost', 11211)) {
          if ($this->debug) {
            error_log("RagCache: Failed to add Memcached server");
          }
          $this->memcachedConn = null;
          return false;
        }
      }
      
      // Test connection
      $stats = $this->memcachedConn->getStats();
      if (empty($stats) || $this->memcachedConn->getResultCode() !== \Memcached::RES_SUCCESS) {
        if ($this->debug) {
          error_log("RagCache: Memcached connection test failed");
        }
        $this->memcachedConn = null;
        return false;
      }
      
      return true;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("RagCache: Memcached initialization error: " . $e->getMessage());
      }
      $this->memcachedConn = null;
      return false;
    }
  }

  /**
   * Get value from cache
   * 
   * @param string $key Cache key
   * @return mixed|null Cached value or null if not found
   */
  public function get(string $key): mixed
  {
    if (!$this->enabled || $this->backend === 'none') {
      return null;
    }
    
    $fullKey = self::CACHE_PREFIX . $key;
    
    try {
      $value = null;
      
      switch ($this->backend) {
        case 'redis':
          $value = $this->getFromRedis($fullKey);
          break;
          
        case 'memcached':
          $value = $this->getFromMemcached($fullKey);
          break;
      }
      
      if ($value !== null && $value !== false) {
        $this->hits++;
        if ($this->debug) {
          error_log("RagCache: HIT - {$key}");
        }
        return $this->unserialize($value);
      }
      
      $this->misses++;
      if ($this->debug) {
        error_log("RagCache: MISS - {$key}");
      }
      return null;
      
    } catch (\Exception $e) {
      $this->errors++;
      if ($this->debug) {
        error_log("RagCache: GET error for {$key}: " . $e->getMessage());
      }
      return null;
    }
  }

  /**
   * Get value from Redis
   * 
   * @param string $fullKey Full cache key with prefix
   * @return string|false Value or false if not found
   */
  private function getFromRedis(string $fullKey): string|false
  {
    if ($this->redisConn === null) {
      return false;
    }
    
    return $this->redisConn->get($fullKey);
  }

  /**
   * Get value from Memcached
   * 
   * @param string $fullKey Full cache key with prefix
   * @return string|false Value or false if not found
   */
  private function getFromMemcached(string $fullKey): string|false
  {
    if ($this->memcachedConn === null) {
      return false;
    }
    
    $value = $this->memcachedConn->get($fullKey);
    
    if ($this->memcachedConn->getResultCode() === \Memcached::RES_SUCCESS) {
      return $value;
    }
    
    return false;
  }

  /**
   * Set value in cache
   * 
   * @param string $key Cache key
   * @param mixed $value Value to cache
   * @param int|null $ttl Time to live in seconds (null = default)
   * @return bool True on success, false on failure
   */
  public function set(string $key, mixed $value, ?int $ttl = null): bool
  {
    if (!$this->enabled || $this->backend === 'none') {
      return false;
    }
    
    $fullKey = self::CACHE_PREFIX . $key;
    $ttl = $ttl ?? $this->defaultTTL;
    
    try {
      $serialized = $this->serialize($value);
      $success = false;
      
      switch ($this->backend) {
        case 'redis':
          $success = $this->setToRedis($fullKey, $serialized, $ttl);
          break;
          
        case 'memcached':
          $success = $this->setToMemcached($fullKey, $serialized, $ttl);
          break;
      }
      
      if ($success) {
        $this->sets++;
        if ($this->debug) {
          error_log("RagCache: SET - {$key} (TTL: {$ttl}s)");
        }
      } else {
        $this->errors++;
        if ($this->debug) {
          error_log("RagCache: SET FAILED - {$key}");
        }
      }
      
      return $success;
      
    } catch (\Exception $e) {
      $this->errors++;
      if ($this->debug) {
        error_log("RagCache: SET error for {$key}: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Set value in Redis
   * 
   * @param string $fullKey Full cache key with prefix
   * @param string $value Serialized value
   * @param int $ttl Time to live in seconds
   * @return bool True on success
   */
  private function setToRedis(string $fullKey, string $value, int $ttl): bool
  {
    if ($this->redisConn === null) {
      return false;
    }
    
    return $this->redisConn->setex($fullKey, $ttl, $value);
  }

  /**
   * Set value in Memcached
   * 
   * @param string $fullKey Full cache key with prefix
   * @param string $value Serialized value
   * @param int $ttl Time to live in seconds
   * @return bool True on success
   */
  private function setToMemcached(string $fullKey, string $value, int $ttl): bool
  {
    if ($this->memcachedConn === null) {
      return false;
    }
    
    return $this->memcachedConn->set($fullKey, $value, $ttl);
  }

  /**
   * Delete value from cache
   * 
   * @param string $key Cache key
   * @return bool True on success, false on failure
   */
  public function delete(string $key): bool
  {
    if (!$this->enabled || $this->backend === 'none') {
      return false;
    }
    
    $fullKey = self::CACHE_PREFIX . $key;
    
    try {
      $success = false;
      
      switch ($this->backend) {
        case 'redis':
          $success = $this->deleteFromRedis($fullKey);
          break;
          
        case 'memcached':
          $success = $this->deleteFromMemcached($fullKey);
          break;
      }
      
      if ($success) {
        $this->deletes++;
        if ($this->debug) {
          error_log("RagCache: DELETE - {$key}");
        }
      }
      
      return $success;
      
    } catch (\Exception $e) {
      $this->errors++;
      if ($this->debug) {
        error_log("RagCache: DELETE error for {$key}: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Delete value from Redis
   * 
   * @param string $fullKey Full cache key with prefix
   * @return bool True on success
   */
  private function deleteFromRedis(string $fullKey): bool
  {
    if ($this->redisConn === null) {
      return false;
    }
    
    return (bool)$this->redisConn->del($fullKey);
  }

  /**
   * Delete value from Memcached
   * 
   * @param string $fullKey Full cache key with prefix
   * @return bool True on success
   */
  private function deleteFromMemcached(string $fullKey): bool
  {
    if ($this->memcachedConn === null) {
      return false;
    }
    
    return $this->memcachedConn->delete($fullKey);
  }

  /**
   * Check if key exists in cache
   * 
   * @param string $key Cache key
   * @return bool True if exists, false otherwise
   */
  public function exists(string $key): bool
  {
    if (!$this->enabled || $this->backend === 'none') {
      return false;
    }
    
    $fullKey = self::CACHE_PREFIX . $key;
    
    try {
      switch ($this->backend) {
        case 'redis':
          return $this->existsInRedis($fullKey);
          
        case 'memcached':
          return $this->existsInMemcached($fullKey);
          
        default:
          return false;
      }
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("RagCache: EXISTS error for {$key}: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Check if key exists in Redis
   * 
   * @param string $fullKey Full cache key with prefix
   * @return bool True if exists
   */
  private function existsInRedis(string $fullKey): bool
  {
    if ($this->redisConn === null) {
      return false;
    }
    
    return (bool)$this->redisConn->exists($fullKey);
  }

  /**
   * Check if key exists in Memcached
   * 
   * @param string $fullKey Full cache key with prefix
   * @return bool True if exists
   */
  private function existsInMemcached(string $fullKey): bool
  {
    if ($this->memcachedConn === null) {
      return false;
    }
    
    $this->memcachedConn->get($fullKey);
    return $this->memcachedConn->getResultCode() === \Memcached::RES_SUCCESS;
  }

  /**
   * Get cache statistics
   * 
   * @return array Statistics array
   */
  public function getStats(): array
  {
    return [
      'enabled' => $this->enabled,
      'backend' => $this->backend,
      'hits' => $this->hits,
      'misses' => $this->misses,
      'sets' => $this->sets,
      'deletes' => $this->deletes,
      'errors' => $this->errors,
      'hit_rate' => ($this->hits + $this->misses) > 0 ? 
                    round(($this->hits / ($this->hits + $this->misses)) * 100, 2) : 
                    0,
      'default_ttl' => $this->defaultTTL
    ];
  }

  /**
   * Serialize value for storage
   * 
   * @param mixed $value Value to serialize
   * @return string Serialized value
   */
  private function serialize(mixed $value): string
  {
    return serialize($value);
  }

  /**
   * Unserialize value from storage
   * 
   * @param string $value Serialized value
   * @return mixed Unserialized value
   */
  private function unserialize(string $value): mixed
  {
    // Security: prevent object injection via cached payloads
    $unserialized = @unserialize($value, ['allowed_classes' => false]);
    if ($unserialized !== false || $value === 'b:0;') {
      return $unserialized;
    }

    // Fallback for future JSON-based cache payloads
    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      return $decoded;
    }

    return null;
  }

  /**
   * Flush all cache entries with RAG prefix
   * 
   * @return bool True on success
   */
  public function flush(): bool
  {
    if (!$this->enabled || $this->backend === 'none') {
      return false;
    }
    
    try {
      switch ($this->backend) {
        case 'redis':
          return $this->flushRedis();
          
        case 'memcached':
          return $this->flushMemcached();
          
        default:
          return false;
      }
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("RagCache: FLUSH error: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Flush Redis cache (only RAG keys)
   * 
   * @return bool True on success
   */
  private function flushRedis(): bool
  {
    if ($this->redisConn === null) {
      return false;
    }
    
    // Get all keys with RAG prefix
    $keys = $this->redisConn->keys(self::CACHE_PREFIX . '*');
    
    if (!empty($keys)) {
      $this->redisConn->del($keys);
    }
    
    if ($this->debug) {
      error_log("RagCache: FLUSH - Deleted " . count($keys) . " Redis keys");
    }
    
    return true;
  }

  /**
   * Flush Memcached cache (all keys - Memcached doesn't support prefix deletion)
   * 
   * @return bool True on success
   */
  private function flushMemcached(): bool
  {
    if ($this->memcachedConn === null) {
      return false;
    }
    
    // Note: Memcached flush() removes ALL keys, not just RAG keys
    // This is a limitation of Memcached
    $success = $this->memcachedConn->flush();
    
    if ($this->debug) {
      error_log("RagCache: FLUSH - Flushed all Memcached keys");
    }
    
    return $success;
  }

  /**
   * Close connections
   */
  public function __destruct()
  {
    if ($this->redisConn !== null) {
      try {
        $this->redisConn->close();
      } catch (\Exception $e) {
        // Ignore errors on close
      }
    }
    
    if ($this->memcachedConn !== null) {
      try {
        $this->memcachedConn->quit();
      } catch (\Exception $e) {
        // Ignore errors on close
      }
    }
  }
}
