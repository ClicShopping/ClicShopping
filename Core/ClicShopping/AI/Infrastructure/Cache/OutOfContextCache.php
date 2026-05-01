<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\Infrastructure\Cache;

use ClicShopping\OM\CLICSHOPPING;

/**
 * OutOfContextCache Class
 *
 * Caches out-of-context detection results to minimize redundant LLM calls.
 * This cache stores detection results (is_out_of_context, context_relevance, etc.)
 * to avoid repeated LLM calls for the same or similar queries.
 *
 * Cache Structure:
 * - Directory: Work/Cache/Rag/OutOfContext/
 * - Format: JSON files with md5 hash filenames
 * - TTL: 30 days (configurable)

 */
class OutOfContextCache
{
  /**
   * @var string Directory where cache files are stored.
   */
  private string $cacheDir;

  /**
   * @var int Lifetime of cache files in seconds (default: 30 days).
   */
  private int $lifetime;

  /**
   * @var bool Whether caching is enabled (from configuration).
   */
  private bool $cacheEnabled;

  /**
   * @var bool Enable debug logging.
   */
  private bool $debug;

  /**
   * OutOfContextCache constructor.
   *
   * @param int $lifetime Cache lifetime in seconds (default: 30 days = 2592000s)
   * @param bool $debug Enable debug logging
   */
  public function __construct(int $lifetime = 2592000, bool $debug = false)
  {
    $this->cacheEnabled = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';
    $this->cacheDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag/OutOfContext/';
    $this->lifetime = $lifetime;
    $this->debug = $debug || (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True');

    // Check cache configuration
    $this->checkOutOfContextCache();

    // Create cache directory if it doesn't exist
    if (!is_dir($this->cacheDir)) {
      if (!mkdir($concurrentDirectory = $this->cacheDir, 0755, true) && !is_dir($concurrentDirectory)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
      }

      if ($this->debug) {
        error_log("[OutOfContextCache] Created cache directory: {$this->cacheDir}");
      }
    }
  }

  /**
   * Check cache configuration and clear cache if disabled.
   *
   * @return bool True if cache is enabled, false otherwise.
   */
  public function checkOutOfContextCache(): bool
  {
    if ($this->cacheEnabled === false) {
      if ($this->debug) {
        error_log("[OutOfContextCache] Cache disabled, clearing cache");
      }
      $this->clearCache();
      return false;
    }

    if ($this->debug) {
      error_log("[OutOfContextCache] Cache enabled");
    }
    return true;
  }

  /**
   * Clears the entire out-of-context cache.
   *
   * @return int Number of files deleted
   */
  public function clearCache(): int
  {
    if (!is_dir($this->cacheDir)) {
      if ($this->debug) {
        error_log(sprintf(
          "[OutOfContextCache] Cache CLEAR - directory does not exist: %s",
          $this->cacheDir
        ));
      }
      return 0;
    }

    $count = 0;
    $failed = 0;
    $files = glob($this->cacheDir . '*.json');

    foreach ($files as $file) {
      if (is_file($file)) {
        if (unlink($file)) {
          $count++;
        } else {
          $failed++;
        }
      }
    }

    if ($this->debug) {
      error_log(sprintf(
        "[OutOfContextCache] Cache CLEARED - directory: %s, files_deleted: %d, files_failed: %d, success: %s",
        $this->cacheDir,
        $count,
        $failed,
        $failed === 0 ? 'true' : 'false'
      ));
    }

    return $count;
  }

  /**
   * Get cached out-of-context detection result
   *
   * @param string $query User query to check
   * @return array|null Cached result or null if not found/expired
   */
  public function getCachedDetection(string $query): ?array
  {
    if (!$this->cacheEnabled) {
      if ($this->debug) {
        error_log(sprintf(
          "[OutOfContextCache] Cache DISABLED - query: \"%s\"",
          substr($query, 0, 50)
        ));
      }
      return null;
    }

    $cacheKey = md5($query);
    $cacheFile = $this->cacheDir . $cacheKey . '.json';

    if (file_exists($cacheFile)) {
      $data = json_decode(file_get_contents($cacheFile), true);
      $age = time() - $data['timestamp'];

      if ($age <= $this->lifetime) {
        // Cache HIT
        if ($this->debug) {
          error_log(sprintf(
            "[OutOfContextCache] Cache HIT - query: \"%s\", is_out_of_context: %s, relevance: %.2f, age: %ds/%ds, key: %s",
            substr($query, 0, 50),
            $data['result']['is_out_of_context'] ? 'true' : 'false',
            $data['result']['context_relevance'] ?? 0.0,
            $age,
            $this->lifetime,
            $cacheKey
          ));
        }

        return $data['result'];
      } else {
        // Cache EXPIRED
        unlink($cacheFile);
        if ($this->debug) {
          error_log(sprintf(
            "[OutOfContextCache] Cache EXPIRED - query: \"%s\", age: %ds > TTL: %ds, key: %s, file deleted",
            substr($query, 0, 50),
            $age,
            $this->lifetime,
            $cacheKey
          ));
        }
      }
    } else {
      // Cache MISS
      if ($this->debug) {
        error_log(sprintf(
          "[OutOfContextCache] Cache MISS - query: \"%s\", key: %s, file: %s",
          substr($query, 0, 50),
          $cacheKey,
          $cacheFile
        ));
      }
    }

    return null;
  }

  /**
   * Store out-of-context detection result in cache
   *
   * @param string $query User query
   * @param array $result Detection result from HallucinationDetector
   * @return bool True if stored successfully
   */
  public function cacheDetection(string $query, array $result): bool
  {
    if (!$this->cacheEnabled) {
      if ($this->debug) {
        error_log(sprintf(
          "[OutOfContextCache] Cache DISABLED - not storing detection for query: \"%s\"",
          substr($query, 0, 50)
        ));
      }
      return false;
    }

    $cacheKey = md5($query);
    $cacheFile = $this->cacheDir . $cacheKey . '.json';

    $data = [
      'query' => $query,
      'result' => $result,
      'timestamp' => time(),
      'cache_version' => '1.0.0',
    ];

    $success = file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT));

    if ($success !== false) {
      if ($this->debug) {
        error_log(sprintf(
          "[OutOfContextCache] Cache STORED - query: \"%s\", is_out_of_context: %s, relevance: %.2f, key: %s, file: %s, size: %d bytes",
          substr($query, 0, 50),
          $result['is_out_of_context'] ? 'true' : 'false',
          $result['context_relevance'] ?? 0.0,
          $cacheKey,
          basename($cacheFile),
          $success
        ));
      }
    } else {
      error_log(sprintf(
        "[OutOfContextCache] Cache STORAGE FAILED - query: \"%s\", key: %s, file: %s, error: failed to write file",
        substr($query, 0, 50),
        $cacheKey,
        $cacheFile
      ));
    }

    return $success !== false;
  }

  /**
   * Get cache statistics
   *
   * @return array Cache statistics (file count, total size, oldest/newest)
   */
  public function getStatistics(): array
  {
    if (!is_dir($this->cacheDir)) {
      return [
        'enabled' => $this->cacheEnabled,
        'file_count' => 0,
        'total_size_bytes' => 0,
        'total_size_kb' => 0,
        'oldest_file_age_seconds' => 0,
        'newest_file_age_seconds' => 0,
      ];
    }

    $files = glob($this->cacheDir . '*.json');
    $totalSize = 0;
    $oldestAge = 0;
    $newestAge = PHP_INT_MAX;

    foreach ($files as $file) {
      if (is_file($file)) {
        $totalSize += filesize($file);
        $age = time() - filemtime($file);
        $oldestAge = max($oldestAge, $age);
        $newestAge = min($newestAge, $age);
      }
    }

    return [
      'enabled' => $this->cacheEnabled,
      'file_count' => count($files),
      'total_size_bytes' => $totalSize,
      'total_size_kb' => round($totalSize / 1024, 2),
      'oldest_file_age_seconds' => $oldestAge,
      'newest_file_age_seconds' => $newestAge === PHP_INT_MAX ? 0 : $newestAge,
      'cache_directory' => $this->cacheDir,
      'ttl_seconds' => $this->lifetime,
    ];
  }

  /**
   * Clean expired cache files
   *
   * @return int Number of expired files deleted
   */
  public function cleanExpired(): int
  {
    if (!is_dir($this->cacheDir)) {
      return 0;
    }

    $count = 0;
    $files = glob($this->cacheDir . '*.json');

    foreach ($files as $file) {
      if (is_file($file)) {
        $age = time() - filemtime($file);
        if ($age > $this->lifetime) {
          if (unlink($file)) {
            $count++;
          }
        }
      }
    }

    if ($this->debug && $count > 0) {
      error_log("[OutOfContextCache] Cleaned {$count} expired cache files");
    }

    return $count;
  }
}
