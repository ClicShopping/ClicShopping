<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
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
   * Redis
   */

  /**
   * Returns a connected Redis instance, or false when unreachable.
   *
   * @return \Redis|false
   */
  public static function getRedis(): \Redis|false
  {
    if (!extension_loaded('redis') || !class_exists('\Redis')) {
      return false;
    }

    try {
      $redis = new \Redis();

      if (!$redis->connect('localhost', 6379, 1)) {
        return false;
      }

      return $redis;
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Reports the Redis server state: memory, eviction policy, keyspace and hit ratio.
   *
   * @return array|false False when the server is unreachable.
   */
  public static function getRedisInfo(): array|false
  {
    $redis = self::getRedis();

    if ($redis === false) {
      return false;
    }

    try {
      $info = $redis->info();
      $maxmemory = $redis->config('GET', 'maxmemory');
      $policy = $redis->config('GET', 'maxmemory-policy');
      $keys = $redis->dbSize();
      $redis->close();
    } catch (\Exception $e) {
      return false;
    }

    if (!is_array($info)) {
      return false;
    }

    $hits = (int)($info['keyspace_hits'] ?? 0);
    $misses = (int)($info['keyspace_misses'] ?? 0);

    return [
      'version' => (string)($info['redis_version'] ?? ''),
      'used_memory' => (int)($info['used_memory'] ?? 0),
      'maxmemory' => (int)(is_array($maxmemory) ? ($maxmemory['maxmemory'] ?? 0) : 0),
      'maxmemory_policy' => (string)(is_array($policy) ? ($policy['maxmemory-policy'] ?? '') : ''),
      'keys' => (int)$keys,
      'clients' => (int)($info['connected_clients'] ?? 0),
      'uptime' => (int)($info['uptime_in_seconds'] ?? 0),
      'hits' => $hits,
      'misses' => $misses,
      'hit_ratio' => $hits + $misses > 0 ? $hits / ($hits + $misses) : 0
    ];
  }

  /**
   * Empties Redis database 0. Deliberate administrator action: it drops everything
   * that database holds, sessions included when store_sessions is Redis.
   *
   * @return bool True when the flush was issued.
   */
  public static function resetRedis(): bool
  {
    $redis = self::getRedis();

    if ($redis === false) {
      return false;
    }

    try {
      $result = $redis->flushDB();
      $redis->close();

      return $result !== false;
    } catch (\Exception $e) {
      return false;
    }
  }

  /***********************************
   * APCu
   */

  private static bool $apcuFallbackLogged = false;

  /**
   * Tells whether the APCu tier may be used, and logs the fallback once per process.
   * APCu is process-local: it is never a substitute for Memcached or Redis.
   *
   * @param string|null $switch Value of USE_APCU when the constant is not defined yet (bootstrap).
   * @return bool True when the switch is on and the extension is usable.
   */
  public static function isApcuAvailable(?string $switch = null): bool
  {
    $enabled = $switch !== null ? $switch == 'True' : (defined('USE_APCU') && USE_APCU == 'True');

    if (!$enabled) {
      return false;
    }

    if (!extension_loaded('apcu') || !apcu_enabled()) {
      if (self::$apcuFallbackLogged === false) {
        self::$apcuFallbackLogged = true;
        error_log('ClicShopping: USE_APCU is on but APCu is unavailable (SAPI ' . PHP_SAPI . ', apc.enable_cli=' . ini_get('apc.enable_cli') . ') - falling back to the lower tier');
      }

      return false;
    }

    return true;
  }

  /**
   * Namespaces an APCu key per installation tree: one SAPI pool is shared by every
   * tree on the host, so an unprefixed key makes two installs read each other.
   *
   * @param string $key Raw cache key.
   * @return string Prefixed key.
   */
  public static function apcuKey(string $key): string
  {
    return 'clic_' . substr(md5(CLICSHOPPING::BASE_DIR), 0, 8) . '_' . $key;
  }

  /**
   * Clears every APCu entry belonging to this installation tree.
   *
   * @return bool True when the extension was reachable.
   */
  public static function resetApcu(): bool
  {
    if (!extension_loaded('apcu') || !apcu_enabled()) {
      return false;
    }

    $prefix = self::apcuKey('');

    foreach (new \APCUIterator('/^' . preg_quote($prefix, '/') . '/') as $entry) {
      apcu_delete($entry['key']);
    }

    return true;
  }

  /**
   * Reports the APCu state of both SAPIs. The CLI column needs a child process:
   * apc.enable_cli is a SAPI setting the web process cannot read.
   *
   * @return array{web: array, cli: array|null} cli is null when it cannot be probed.
   */
  public static function getApcuSapiState(): array
  {
    $web = [
      'loaded' => extension_loaded('apcu'),
      'enabled' => ini_get('apc.enabled'),
      'enable_cli' => ini_get('apc.enable_cli'),
      'usable' => extension_loaded('apcu') && apcu_enabled()
    ];

    $cli = null;

    // Probe the CLI SAPI in a child process (apc.enable_cli is per-SAPI). proc_open with an
    // array command runs without a shell, so no argument can be interpreted as one.
    if (function_exists('proc_open')) {
      $code = 'echo (int)extension_loaded("apcu"), ",", (int)ini_get("apc.enabled"), ",", (int)ini_get("apc.enable_cli"), ",", (int)(function_exists("apcu_enabled") && apcu_enabled());';
      $out = null;
      $process = @proc_open(['php', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

      if (is_resource($process)) {
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
      }

      if (is_string($out) && substr_count($out, ',') === 3) {
        [$loaded, $enabled, $enable_cli, $usable] = explode(',', trim($out));
        $cli = [
          'loaded' => (bool)$loaded,
          'enabled' => $enabled,
          'enable_cli' => $enable_cli,
          'usable' => (bool)$usable
        ];
      }
    }

    return ['web' => $web, 'cli' => $cli];
  }

  /**
   * Returns the APCu memory occupancy of this SAPI, or false when unreachable.
   *
   * @return array|false
   */
  public static function getApcuInfo(): array|false
  {
    if (!extension_loaded('apcu') || !apcu_enabled()) {
      return false;
    }

    $sma = apcu_sma_info(true);
    $cache = apcu_cache_info(true);

    if (!is_array($sma) || !is_array($cache)) {
      return false;
    }

    $used = ($sma['num_seg'] * $sma['seg_size']) - $sma['avail_mem'];
    $hits = (int)($cache['num_hits'] ?? 0);
    $misses = (int)($cache['num_misses'] ?? 0);

    return [
      'total' => $sma['num_seg'] * $sma['seg_size'],
      'used' => $used,
      'available' => $sma['avail_mem'],
      'entries' => (int)($cache['num_entries'] ?? 0),
      'hits' => $hits,
      'misses' => $misses,
      'hit_ratio' => $hits + $misses > 0 ? $hits / ($hits + $misses) : 0
    ];
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
