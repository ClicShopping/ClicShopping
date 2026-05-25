<?php
/**
 * Rate Limiting Class
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\AI\Security;

use ClicShopping\OM\CLICSHOPPING;

/**
 * Class RateLimit
 * Implements rate limiting functionality for API requests
 * This class manages the rate of incoming requests to prevent abuse
 */
class RateLimit
{
  private $namespace;
  private $maxRequests;
  private $timeWindow;
  private $storage;
  private $storageFile;

  /**
   * RateLimit constructor.
   * Initializes rate limiting parameters and storage
   *
   * @param string $namespace Unique namespace for the rate limit
   * @param int $maxRequests Maximum number of requests allowed in the time window
   * @param int $timeWindow Time window in seconds for rate limiting
   */
  public function __construct(string $namespace, int $maxRequests, int $timeWindow)
  {
    $this->namespace = $namespace;
    $this->maxRequests = $maxRequests;
    $this->timeWindow = $timeWindow;

    $cacheDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/RateLimit/';

    if (!is_dir($cacheDir)) {
      @mkdir($cacheDir, 0775, true);
    }

    // Sanitize the namespace for safe use as a filename:
    // strip anything outside [a-zA-Z0-9_-] to prevent path traversal and to
    // avoid the "Undefined variable $safeNs" warning logged on every request.
    $safeNs = preg_replace('/[^A-Za-z0-9_-]/', '_', $namespace);
    if ($safeNs === '' || $safeNs === null) {
      $safeNs = 'default';
    }

    $this->storageFile = $cacheDir . $safeNs . '.cache';

    if (!function_exists('apcu_fetch')) {
      $this->loadStorage();
    }
  }

  /**
   * Checks if the rate limit has been exceeded for a given identifier
   * Returns true if within limit, false if exceeded
   *
   * @param string $identifier Unique identifier for the request (e.g., IP address)
   * @return bool True if within limit, false if exceeded
   */
  public function checkLimit(string $identifier): bool
  {
    $key = $this->namespace . ':' . $identifier;

    if (function_exists('apcu_fetch')) {
      return $this->checkLimitWithApcu($key);
    }

    return $this->checkLimitWithFile($key);
  }

  /**
   * Checks the rate limit using APCu cache
   * Returns true if within limit, false if exceeded
   *
   * @param string $key Unique key for the request
   * @return bool True if within limit, false if exceeded
   */
  private function checkLimitWithApcu(string $key): bool
  {
    $currentCount = apcu_fetch($key) ?: 0;

    if ($currentCount >= $this->maxRequests) {
      return false;
    }

    apcu_store($key, $currentCount + 1, $this->timeWindow);
    return true;
  }

  /**
   * Checks the rate limit using file-based storage
   * Returns true if within limit, false if exceeded
   *
   * @param string $key Unique key for the request
   * @return bool True if within limit, false if exceeded
   */
  private function checkLimitWithFile(string $key): bool
  {
    $now = time();

    $this->cleanupStorage($now);

    if (!isset($this->storage[$key])) {
      $this->storage[$key] = [
        'count' => 0,
        'timestamps' => [],
        'first_request' => $now
      ];
    }

    $this->storage[$key]['timestamps'] = array_filter(
      $this->storage[$key]['timestamps'],
      fn($timestamp) => $timestamp >= ($now - $this->timeWindow)
    );

    $count = count($this->storage[$key]['timestamps']);

    if ($count >= $this->maxRequests) {
      $this->logRateLimitExceeded($key, $count);
      return false;
    }

    $this->storage[$key]['timestamps'][] = $now;
    $this->storage[$key]['count']++;

    $this->saveStorage();

    return true;
  }

  /**
   * Loads the rate limit storage from file
   * Initializes the storage array if file does not exist
   */
  private function loadStorage(): void
  {
    if (file_exists($this->storageFile)) {
      $content = file_get_contents($this->storageFile);
      $this->storage = json_decode($content, true) ?: [];
    } else {
      $this->storage = [];
    }
  }

  /**
   * Saves the rate limit storage to file
   * Creates the directory if it does not exist
   */
  private function saveStorage(): void
  {
    $dir = dirname($this->storageFile);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    file_put_contents(
      $this->storageFile,
      json_encode($this->storage),
      LOCK_EX
    );
  }

  /**
   * Cleans up the storage by removing old entries
   * Removes entries older than twice the time window
   *
   * @param int $now Current timestamp
   */
  private function cleanupStorage(int $now): void
  {
    foreach ($this->storage as $key => $data) {
      if (isset($data['first_request']) && $data['first_request'] < ($now - ($this->timeWindow * 2))) {
        unset($this->storage[$key]);
      }
    }
  }

  /**
   * Logs a warning when the rate limit is exceeded
   * Creates a log entry with timestamp and details
   *
   * @param string $key Unique key for the request
   * @param int $count Number of requests made
   */
  private function logRateLimitExceeded(string $key, int $count): void
  {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [warning] Rate limit exceeded: {$key} made {$count} requests in {$this->namespace} (limit: {$this->maxRequests}/{$this->timeWindow}s)" . PHP_EOL;

    $logDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag';
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, true);
    }

    file_put_contents(
      $logDir . '/rag_rate_limits.cache',
      $logEntry,
      FILE_APPEND
    );
  }
}