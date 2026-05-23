<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin;
/**
 * Class CacheAdmin
 *
 * Provides administrative functions for managing cache systems in ClicShopping.
 *
 * Features:
 * - Memcached session management (check, get, reset)
 * - OPcache management (check, reset)
 *
 * Usage:
 *   - Use getMemcached() to obtain a Memcached instance.
 *   - Use resetMemcached() to flush all Memcached data.
 *   - Use resetOpCache() to reset PHP OPcache.
 *
 * @package ClicShopping\Apps\Configuration\Cache\Class\CacheAdmin
 */
class CacheAdmin
{
  /**
   * The session name for Memcached.
   *  Persistent session identifier for Memcached
   *  Kept separate from general cache identifier (clicshopping_session_memcached)
   * @var string
   */
  private static string $memcachedSession = 'clicshopping_session_memcached';

  private static string $sqlCachePrefix = 'sql_cache_';
  private static int $defaultTTL = 3600; // 1 hour default TTL

  /**
   * CacheAdmin constructor.
   */
  public function __construct()
  {
  }

  /**
   * Checks if the Memcached class is available.
   *
   * @return bool True if Memcached is available, false otherwise.
   */
  private static function checkMemcached(): bool
  {
    if (!extension_loaded('memcached')) {
      return false;
    }

    return class_exists('Memcached');
  }

  /**
   * Returns a Memcached instance if available.
   *
   * @return \Memcached|false Memcached instance or false if not available.
   */
  public static function getMemcached(): \Memcached|false
  {
    if (self::checkMemcached() === true) {
      try {
        $memcache = new \Memcached(self::$memcachedSession);

        if (count($memcache->getServerList()) === 0) {
          $memcache->addServer('localhost', 11211);

          $memcache->setOptions([
            \Memcached::OPT_COMPRESSION => true,
            \Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
            \Memcached::OPT_BINARY_PROTOCOL => true,
            \Memcached::OPT_TCP_NODELAY => true,
            \Memcached::OPT_CONNECT_TIMEOUT => 1000,
            \Memcached::OPT_RETRY_TIMEOUT => 2,
            \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT
          ]);
        }

        // Test connection
        $stats = $memcache->getStats();
        if (empty($stats) || $memcache->getResultCode() !== \Memcached::RES_SUCCESS) {
          return false;
        }

        return $memcache;
      } catch (\Exception $e) {
        return false;
      }
    }
    
    return false;
  }

  /**
   * Reset (flush) the Memcached server.
   *
   * This method checks if Memcached is available, then flushes the cache for the
   * 'clicshopping_session' instance. Returns true on success, false otherwise.
   *
   * @return bool Returns true if Memcached was flushed, false otherwise.
   */
  public static function resetMemcached(): bool
  {
    if (self::checkMemcached() === true) {
      try {
        $memcache = new \Memcached(self::$memcachedSession);
        return $memcache->flush();
      } catch (\Exception $e) {
        return false;
      }
    }
    
    return false;
  }

  /**
   * Cache SQL query results
   *
   * @param string $query The SQL query to cache
   * @param array $result The query result to cache
   * @param int|null $ttl Time to live in seconds
   * @return bool Success or failure
   */
  public static function cacheSQLQuery(string $query, array $result, ?int $ttl = null): bool
  {
    $memcache = self::getMemcached();
    if ($memcache === false) {
      return false;
    }

    $cacheKey = self::$sqlCachePrefix . md5($query);
    return $memcache->set($cacheKey, $result, $ttl ?? self::$defaultTTL);
  }

  /**
   * Get cached SQL query results
   *
   * @param string $query The SQL query to look up
   * @return array|false The cached result or false if not found
   */
  public static function getCachedSQLQuery(string $query): array|false
  {
    $memcache = self::getMemcached();
    if ($memcache === false) {
      return false;
    }

    $cacheKey = self::$sqlCachePrefix . md5($query);
    $result = $memcache->get($cacheKey);

    if ($memcache->getResultCode() === \Memcached::RES_SUCCESS) {
      return $result;
    }

    return false;
  }

  /**
   * Invalidate cached SQL query
   *
   * @param string $query The SQL query to invalidate
   * @return bool Success or failure
   */
  public static function invalidateSQLCache(string $query): bool
  {
    $memcache = self::getMemcached();
    if ($memcache === false) {
      return false;
    }

    $cacheKey = self::$sqlCachePrefix . md5($query);
    return $memcache->delete($cacheKey);
  }

  /***********************************
   * OpCache
   */

  /**
   * Checks if OPcache reset function is available.
   *
   * @return bool True if opcache_reset exists, false otherwise.
   */
  public static function checkOpCache(): bool
  {
    return function_exists('opcache_reset');
  }

  /**
   * Reset (flush) the OpCache.
   *
   * This method checks if OpCache is available, then resets the OpCache.
   * Returns true on success, false otherwise.
   *
   * @return bool Returns true if OpCache was reset, false otherwise.
   */
  public static function resetOpCache(): bool
  {
    if (self::checkOpCache() === true) {
      return opcache_reset();
    }
    return false;
  }
}
