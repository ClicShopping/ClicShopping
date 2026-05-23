<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Cache\Classes\Shop;

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
     * @var bool Enable/disable cache file compression
     * @todo : the compression does not workcurrently, it needs to be fixed
     */
    public const USE_CATALOG_COMPRESS_CACHE = false;

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
     * @var string Log file name
     */
    private const LOG_FILE_NAME = 'cache_operations.json';

    /**
     * @var string Last cleanup file name
     */
    private const LAST_CLEANUP_FILE = 'cache_status.json';
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
        $this->lifetime = $lifetime;
        $this->defaultTemplateName = mb_strtolower(SITE_THEMA);

        if (!is_dir($this->cacheDir)) {
            if (!mkdir($concurrentDirectory = $this->cacheDir, 0755, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

      if ($this->isResetEnabled() === true) {
        $this->resetAllCache();
      }


      // Clean expired cache files with a 1% probability
        if (random_int(1, 100) === 1) {
            $this->cleanExpiredCache();
        }
    }

    /**
     * Retrieves cached content for a given key, or generates and caches it if not present or expired.
     *
     * @param string   $key       Unique cache key.
     * @param callable $generator Function to generate content if cache is missing or expired.
     * @return mixed   Cached or newly generated content.
     */
    public function getCachedContent(string $key, callable $generator)
    {
        if ($this->hasCache($key)) {
            return $this->getCache($key);
        }

        $content = $generator();
        $this->setCache($key, $content);
        return $content;
    }

    /**
     * Check if caching is enabled and if a cache file exists for the given key
     *
     * @param string $key Cache key
     * @return bool True if cache exists and is valid
     */
    public function hasCache(string $key): bool
    {
        if (!$this->isCacheEnabled()) {
            return false;
        }

        $cacheFile = $this->getCacheFile($key);

        if (!file_exists($cacheFile)) {
            return false;
        }

        return (time() - filemtime($cacheFile)) < $this->lifetime;
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

      if ($this->hasCache($key)) {
          $content = file_get_contents($this->getCacheFile($key));
          if ($content === false) {
              $this->log("Failed to read cache file for key: $key");
              return false;
          }

          $decompressed = $this->decompressContent($content);
          if ($decompressed === false) {
              $this->log("Failed to decompress cache content for key: $key");
              return false;
          }

          return $decompressed;
      }

      return false;
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

        $compressed = $this->compressContent($content);
        $result = file_put_contents($this->getCacheFile($key), $compressed);

        if ($result === false) {
            $this->log("Failed to write cache file for key: $key");
            return false;
        }

        $this->log("Successfully cached content for key: $key");
        return true;
    }

    /**
     * Delete a cache file
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function deleteCache(string $key): bool
    {
        if (!$this->isCacheEnabled() || !$this->isResetEnabled()) {
            return false;
        }

        $cacheFile = $this->getCacheFile($key);

        if (file_exists($cacheFile)) {
            try {
                $result = unlink($cacheFile);
                if ($result) {
                    $this->log("Successfully deleted cache file for key: $key");
                } else {
                    $this->log("Failed to delete cache file for key: $key");
                }
                return $result;
            } catch (\Exception $e) {
                $this->log("Error deleting cache file for key: $key - " . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Reset cache for a specific module or group
     *
     * @param string $prefix Prefix to identify the cache group to reset
     * @return bool Success status
     */
    public function resetCacheBlock(string $prefix): bool
    {
        if (!$this->isCacheEnabled() || !$this->isResetEnabled()) {
            return false;
        }

        $template_name = $this->defaultTemplateName;
        $pattern = $this->cacheDir . '/template_' . $template_name . '_' . md5($prefix) . '*.cache';
        $files = glob($pattern);

        if ($files === false) {
            $this->log("Failed to list cache files for prefix: $prefix");
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $this->log("Failed to delete cache file: $file");
                $success = false;
            } else {
                $this->log("Successfully deleted cache file: $file");
            }
        }

        return $success;
    }

    /**
     * Reset all template cache
     *
     * @return bool Success status
     */
    public function resetAllCache(bool|null $force = false): bool
    {

      if ($force === false) {
        if ($this->isResetEnabled() === false) {
          return false;
        }
      }

      $pattern = $this->cacheDir . '/template_*_*.cache';
      $files = glob($pattern);
      $success = true;

      if ($files === false) {
          return false;
      }

      foreach ($files as $file) {
          if (!unlink($file)) {
              $success = false;
          }
      }

      // Reset status and log files
      $statusFile = $this->cacheDir . '/' . self::LAST_CLEANUP_FILE;
      $logFile = $this->cacheDir . '/' . self::LOG_FILE_NAME;

      if (file_exists($statusFile)) {
          unlink($statusFile);
      }

      if (file_exists($logFile)) {
          $initialLog = [
              [
                  'timestamp' => date('Y-m-d H:i:s'),
                  'message' => 'Cache reset - Log file initialized'
              ]
          ];

          file_put_contents($logFile, json_encode($initialLog, JSON_PRETTY_PRINT));
      }

      return $success;
    }

    /**
     * Check if cache is enabled
     *
     * @return bool True if cache is enabled
     */
    public function isCacheEnabled(): bool
    {
      $result = false;

      if (defined('USE_CATALOG_CACHE') && USE_CATALOG_CACHE == 'True') {
        $result =  true;
      }

      return $result;
    }

    /**
     * Check if cache reset operations are enabled
     *
     * @return bool True if cache reset is enabled
     */
    public function isResetEnabled(): bool
    {
      $result = false;

      if (defined('USE_CATALOG_RESET_CACHE') && USE_CATALOG_RESET_CACHE == 'True') {
        $result =  true;
      }

      return $result;
    }

    /**
     * Write to log file if logging is enabled
     *
     * @param string $message Message to log
     * @return void
     */
    private function log(string $message): void
    {
        if (defined('USE_CATALOG_LOG_CACHE') && USE_CATALOG_LOG_CACHE == 'True') {
          $logFile = $this->cacheDir . '/' . self::LOG_FILE_NAME;
          $logs = [];

          if (file_exists($logFile)) {
              $content = file_get_contents($logFile);
              if ($content) {
                  $logs = json_decode($content, true) ?? [];
              }

              // Check file size and clean if necessary
              if (filesize($logFile) > self::MAX_LOG_SIZE) {
                  $logs = array_slice($logs, -self::MAX_LOG_ENTRIES); // Keep only last 500 entries
                  $this->log("Log file size exceeded " . (self::MAX_LOG_SIZE / 1024 / 1024) . "MB - Cleaned up to last " . self::MAX_LOG_ENTRIES . " entries");
              }
          }

          $logs[] = [
              'timestamp' => date('Y-m-d H:i:s'),
              'message' => $message,
              'template' => $this->defaultTemplateName
          ];

          file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Compress content before caching
     *
     * @param string $content Content to compress
     * @return string Compressed content
     */
    private function compressContent(string $content): string
    {
        if (!static::USE_CATALOG_COMPRESS_CACHE) {
            return $content;
        }

        return gzcompress($content, 9);
    }

    /**
     * Decompress cached content
     *
     * @param string $content Compressed content
     * @return string|false Decompressed content or false on error
     */
    private function decompressContent(string $content)
    {
        if (!static::USE_CATALOG_COMPRESS_CACHE) {
            return $content;
        }

        return gzuncompress($content);
    }

    /**
     * Clean expired cache files if enough time has passed since last cleanup
     *
     * @return bool Success status
     */
    public function cleanExpiredCache(): bool
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

        $pattern = $this->cacheDir . '/template_*_*.cache';
        $files = glob($pattern);
        $success = true;

        if ($files === false) {
            $this->log('Failed to list cache files during cleanup');
            return false;
        }

        foreach ($files as $file) {
            if (filemtime($file) < ($now - $this->lifetime)) {
                if (!unlink($file)) {
                    $this->log("Failed to delete expired cache file: $file");
                    $success = false;
                } else {
                    $this->log("Deleted expired cache file: $file");
                }
            }
        }

        // Update status file
        if ($success) {
            $template_name = $this->defaultTemplateName;
            $status['templates'][$template_name] = [
                'last_cleanup' => $now,
                'files_count' => count($files)
            ];
            file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
        }

        return $success;
    }

    /**
     * Returns the full path to the cache file for the given key.
     *
     * @param string $key Cache key.
     * @return string Full path to the cache file.
     */
    private function getCacheFile(string $key): string
    {
        $template_name = $this->defaultTemplateName;
        return $this->cacheDir . '/template_' . $template_name . '_' . md5($key) . '.cache';
    }
}
