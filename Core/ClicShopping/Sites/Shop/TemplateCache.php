<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;

/**
 * Class TemplateCache
 *
 * Handles template caching by storing and retrieving cached template content.
 * Uses file-based caching with configurable cache directory and lifetime.
 *
 * @package ClicShopping\Sites\Shop
 */
class TemplateCache
{
  /**
   * @var string Directory where cache files are stored.
   */
  private $cacheDir;

  /**
   * @var int Lifetime of cache files in seconds.
   */
  private $lifetime;

  /**
   * @var string OM\Cache namespace holding the rendered blocks. Storage, expiry and purge are
   * delegated to OM\Cache: one file-cache mechanism for the whole repository.
   */
  private const CACHE_NAMESPACE = 'Templates';

  /**
   * @var int Minimum time between cache cleanups (24 hours by default)
   */
  private const CLEANUP_INTERVAL = 86400; // 24 hours in seconds

  /**
   * @var int Maximum size for log file in bytes (2MB)
   */
  private const MAX_LOG_SIZE = 2097152;

  /**
   * @var int Maximum number of log entries to keep
   */
  private const MAX_LOG_ENTRIES = 500;

  /**
   * @var string Path to the log file for caching operations
   * This file is used to log cache operations and status.
   */
  private string $logStaticTemplate;

  /**
   * @var string Last cleanup file name (relative path)
   */
  private const LAST_CLEANUP_FILE = 'cache_cleanup_status.json';

  /**
   * @var string Default template name used for cache file naming
   */
  private string $defaultTemplateName;

  /**
   * TemplateCache constructor.
   *
   * @param string $cacheDir  Relative path to the cache directory.
   * @param int    $lifetime  Cache lifetime in seconds.
   */
  public function __construct(string $cacheDir = 'Work/Cache/Templates', int $lifetime = 3600)
  {
    $this->cacheDir = CLICSHOPPING::BASE_DIR . $cacheDir;
    $this->logStaticTemplate = CLICSHOPPING::BASE_DIR . 'Work/LogStaticTemplate/cache_status.json';

    $this->lifetime = $lifetime;
    $this->defaultTemplateName = mb_strtolower(SITE_THEMA);

    // Créer le répertoire de cache
    if (!is_dir($this->cacheDir)) {
      if (!mkdir($concurrentDirectory = $this->cacheDir, 0755, true) && !is_dir($concurrentDirectory)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
      }
    }

    // Créer le répertoire de log
    $logDir = dirname($this->logStaticTemplate);
    if (!is_dir($logDir)) {
      if (!mkdir($logDirConcurrent = $logDir, 0755, true) && !is_dir($logDirConcurrent)) {
        throw new \RuntimeException(sprintf('Log directory "%s" was not created', $logDirConcurrent));
      }
    }

    // Clean expired cache files with a 1% probability
    if (random_int(1, 100) === 1) {
      $this->cleanExpiredCache();
    }

    $this->checkStaticCache();
  }

  /**
   * @return false|void
   */
  private function checkStaticCache()
  {
    if (defined('USE_CATALOG_CACHE') && USE_CATALOG_CACHE == 'False') {
      $this->resetAllCache();
      $this->clearLogs();

      return false;
    }
  }

  /**
   * Check if caching is enabled and if a cache file exists for the given key
   *
   * @param string $key Cache key
   * @return bool True if cache exists and is valid
   */
  private function hasCache(string $key): bool
  {
    if (!$this->isCacheEnabled()) {
      return false;
    }

    return $this->entry($key)->exists((string) $this->lifetimeMinutes());
  }

  /**
   * Get cached content if it exists
   *
   * @param string $key Cache key
   * @return string|false Cached content or false if no valid cache exists
   */
  public function getCache(string $key)
  {
    if (!$this->isCacheEnabled()) {
      return false;
    }

    if (!$this->hasCache($key)) {
      return false;
    }

    $content = $this->entry($key)->get();

    if (!is_string($content)) {
      $this->log("Failed to read cache entry for key: $key");
      return false;
    }

    return $content;
  }

  /**
   * Save content to cache
   *
   * @param string $key Cache key
   * @param string $content Content to cache
   * @return bool Success status
   */
  public function setCache(string $key, string $content): bool
  {
    if (!$this->isCacheEnabled()) {
      return false;
    }

    if ($this->entry($key)->save($content) === false) {
      $this->log("Failed to write cache entry for key: $key");
      return false;
    }

    $this->log("Successfully cached content for key: $key");
    return true;
  }

  /**
   * Reset all template cache
   *
   * @return bool Success status
   */
  private function resetAllCache(): bool
  {
    Cache::clearNamespace(self::CACHE_NAMESPACE);

    // Reset status and log files
    $statusFile = $this->cacheDir . '/' . self::LAST_CLEANUP_FILE;
    $logFile = $this->logStaticTemplate;

    if (file_exists($statusFile)) {
      unlink($statusFile);
    }

    if (file_exists($logFile)) {
      $initialLog = [
        [
          'timestamp' => date('Y-m-d H:i:s'),
          'message' => 'Cache reset - Log file initialized',
          'template' => $this->defaultTemplateName,
          'type' => 'system'
        ]
      ];

      file_put_contents($logFile, json_encode($initialLog, JSON_PRETTY_PRINT));
    }

    return true;
  }

  /**
   * Check if cache is enabled
   *
   * @return bool True if cache is enabled
   */
  public function isCacheEnabled(): bool
  {
    return defined('USE_CATALOG_CACHE') && USE_CATALOG_CACHE == 'True';
  }

  /**
   * Write to log file if logging is enabled
   *
   * @param string $message Message to log
   * @return void
   */
  public function log(string $message): void
  {
    // Vérifier si le logging est activé
    if (!defined('USE_CATALOG_LOG_CACHE') || USE_CATALOG_LOG_CACHE !== 'True') {
      return;
    }

    try {
      $logFile = $this->logStaticTemplate;
      $logs = [];

      // Lire les logs existants
      if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if ($content !== false && !empty($content)) {
          $decodedLogs = json_decode($content, true);
          if (json_last_error() === JSON_ERROR_NONE && is_array($decodedLogs)) {
            $logs = $decodedLogs;
          }
        }

        // Nettoyer le fichier si trop volumineux
        if (filesize($logFile) > self::MAX_LOG_SIZE) {
          $logs = array_slice($logs, -self::MAX_LOG_ENTRIES);
          // Ajouter directement l'entrée de nettoyage sans récursion
          $logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => "Log file size exceeded " . (self::MAX_LOG_SIZE / 1024 / 1024) . "MB - Cleaned up to last " . self::MAX_LOG_ENTRIES . " entries",
            'template' => $this->defaultTemplateName,
            'type' => 'system'
          ];
        }
      }

      // Ajouter la nouvelle entrée
      $logs[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'template' => $this->defaultTemplateName,
        'memory_usage' => memory_get_usage(true),
        'pid' => getmypid()
      ];

      // Écrire dans le fichier
      $jsonData = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      if ($jsonData === false) {
        error_log("TemplateCache: Failed to encode log data to JSON");
        return;
      }

      $result = file_put_contents($logFile, $jsonData, LOCK_EX);
      if ($result === false) {
        error_log("TemplateCache: Failed to write to log file: " . $logFile);
      }

    } catch (\Exception $e) {
      error_log("TemplateCache logging error: " . $e->getMessage());
    }
  }

  /**
   * Clear all log entries
   *
   * @return bool Success status
   */
  private function clearLogs(): bool
  {
    $initialLog = [
      [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Log file cleared and reinitialized',
        'template' => $this->defaultTemplateName,
        'type' => 'system'
      ]
    ];

    $result = file_put_contents($this->logStaticTemplate, json_encode($initialLog, JSON_PRETTY_PRINT), LOCK_EX);

    return $result !== false;
  }

  /**
   * Clean expired cache files if enough time has passed since last cleanup
   *
   * @return bool Success status
   */
  private function cleanExpiredCache(): bool
  {
    if (!$this->isCacheEnabled()) {
      return false;
    }

    $statusFile = $this->cacheDir . '/' . self::LAST_CLEANUP_FILE;
    $now = time();
    $status = [
      'last_cleanup' => $now,
      'templates' => []
    ];

    // Check when was the last cleanup
    if (file_exists($statusFile)) {
      $content = file_get_contents($statusFile);
      if ($content) {
        $savedStatus = json_decode($content, true);
        if ($savedStatus && isset($savedStatus['last_cleanup'])) {
          if (($now - $savedStatus['last_cleanup']) < self::CLEANUP_INTERVAL) {
            return true; // Too soon to cleanup again
          }
          $status['templates'] = $savedStatus['templates'] ?? [];
        }
      }
    }

    $purged = Cache::purgeExpired($this->lifetimeMinutes(), self::CACHE_NAMESPACE);
    $this->log("Cleanup removed {$purged} expired cache entries");

    $status['templates'][$this->defaultTemplateName] = [
      'last_cleanup' => $now,
      'purged_count' => $purged
    ];

    file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));

    return true;
  }

  /**
   * Returns the full path to the cache file for the given key.
   *
   * @param string $key Cache key.
   * @return string Full path to the cache file.
   */
  private function cacheKey(string $key): string
  {
    // The block name stays IN CLEAR: Cache::clear() purges by prefix, and md5 would destroy the
    // prefix relation — which is exactly why resetCacheBlock() could never match a file before.
    return 'template_' . preg_replace('/[^a-zA-Z0-9\-_]/', '-', $this->defaultTemplateName . '_' . $key);
  }

  /**
   * The OM\Cache entry backing a template key.
   *
   * @param string $key Cache key
   * @return Cache
   */
  private function entry(string $key): Cache
  {
    return new Cache($this->cacheKey($key), self::CACHE_NAMESPACE, true);
  }

  /**
   * Lifetime in minutes — OM\Cache expresses expiry in minutes, this class in seconds.
   *
   * @return int
   */
  private function lifetimeMinutes(): int
  {
    return max(1, (int)($this->lifetime / 60));
  }
}