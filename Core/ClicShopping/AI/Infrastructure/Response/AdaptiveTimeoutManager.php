<?php
/**
 * Adaptive Timeout Manager for RAG Query Execution
 * 
 * Manages timeout thresholds based on cache state to prevent cold cache timeouts.
 * Applies extended timeouts for cold cache scenarios and standard timeouts for warm cache.
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Response;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * AdaptiveTimeoutManager Class
 * 
 * Provides adaptive timeout management based on cache state.
 * 
 * Timeout Strategy:
 * - Cold cache: 120 seconds (extended timeout for initial processing)
 * - Warm cache: 30 seconds (standard timeout for cached results)
 * - Expired cache: 120 seconds (treated as cold cache)
 * 
 * Features:
 * - Cache state-based timeout selection
 * - Timeout event logging for monitoring
 * - Configurable timeout thresholds
 * - Performance metrics tracking
 * 
 * Usage:
 * ```php
 * $timeoutManager = new AdaptiveTimeoutManager(120, 30);
 * $timeout = $timeoutManager->getTimeout($cacheState);
 * 
 * // Execute query with adaptive timeout
 * set_time_limit($timeout);
 * 
 * // Log timeout event if timeout occurs
 * $timeoutManager->logTimeoutEvent($query, $cacheState, $executionTime, true);
 * ```
 */
class AdaptiveTimeoutManager
{
  private int $coldCacheTimeout;
  private int $warmCacheTimeout;
  private bool $debug = false;
  private ?SecurityLogger $logger = null;

  /**
   * Constructor
   * 
   * @param int $coldCacheTimeout Timeout for cold cache scenarios (default: 120 seconds)
   * @param int $warmCacheTimeout Timeout for warm cache scenarios (default: 30 seconds)
   * @param bool $debug Enable debug logging
   */
  public function __construct(
    int $coldCacheTimeout = 120,
    int $warmCacheTimeout = 30,
    bool $debug = false
  ) {
    $this->coldCacheTimeout = $coldCacheTimeout;
    $this->warmCacheTimeout = $warmCacheTimeout;
    $this->debug = $debug;
    
    // Initialize logger
    try {
      $this->logger = new SecurityLogger('info');
    } catch (\Exception $e) {
      error_log("AdaptiveTimeoutManager: Failed to initialize SecurityLogger: " . $e->getMessage());
    }
    
    if ($this->debug) {
      error_log("AdaptiveTimeoutManager: Initialized with cold={$coldCacheTimeout}s, warm={$warmCacheTimeout}s");
    }
  }

  /**
   * Get appropriate timeout based on cache state
   * 
   * Selects timeout threshold based on cache state:
   * - Cold cache: Extended timeout (default 120s)
   * - Warm cache: Standard timeout (default 30s)
   * - Expired cache: Extended timeout (treated as cold)
   * 
   * @param array $cacheState Cache state from CacheStateDetector
   *   - state: 'cold', 'warm', or 'expired'
   *   - exists: bool
   *   - valid: bool
   *   - age_seconds: int|null
   * @return int Timeout in seconds
   */
  public function getTimeout(array $cacheState): int
  {
    $state = $cacheState['state'] ?? 'cold';
    
    // Determine timeout based on cache state
    if ($state === 'warm') {
      // Warm cache: use standard timeout
      $timeout = $this->warmCacheTimeout;
      
      if ($this->debug) {
        error_log("AdaptiveTimeoutManager: Selected WARM cache timeout: {$timeout}s");
      }
    } else {
      // Cold or expired cache: use extended timeout
      $timeout = $this->coldCacheTimeout;
      
      if ($this->debug) {
        $stateLabel = strtoupper($state);
        error_log("AdaptiveTimeoutManager: Selected {$stateLabel} cache timeout: {$timeout}s");
      }
    }
    
    return $timeout;
  }

  /**
   * Log timeout event with cache state information
   * 
   * Records timeout events for monitoring and analysis.
   * Includes cache state, execution time, and timeout threshold.
   * 
   * @param string $query User query that timed out
   * @param array $cacheState Cache state from CacheStateDetector
   * @param float $executionTime Actual execution time in seconds
   * @param bool $timedOut Whether the query actually timed out
   * @return void
   */
  public function logTimeoutEvent(
    string $query,
    array $cacheState,
    float $executionTime,
    bool $timedOut
  ): void {
    $state = $cacheState['state'] ?? 'unknown';
    $timeout = $this->getTimeout($cacheState);
    
    // Prepare log context
    $context = [
      'query' => substr($query, 0, 100), // Truncate for logging
      'cache_state' => $state,
      'cache_exists' => $cacheState['exists'] ?? false,
      'cache_valid' => $cacheState['valid'] ?? false,
      'cache_age_seconds' => $cacheState['age_seconds'] ?? null,
      'execution_time' => round($executionTime, 2),
      'timeout_threshold' => $timeout,
      'timed_out' => $timedOut,
      'exceeded_threshold' => $executionTime > $timeout
    ];
    
    // Determine log level
    if ($timedOut) {
      $level = 'warning';
      $message = "Query timeout occurred (state: {$state}, time: {$executionTime}s, threshold: {$timeout}s)";
    } else {
      $level = 'info';
      $message = "Query completed (state: {$state}, time: {$executionTime}s, threshold: {$timeout}s)";
    }
    
    // Log to SecurityLogger
    if ($this->logger !== null) {
      try {
        $this->logger->logSecurityEvent($message, $level, $context);
      } catch (\Exception $e) {
        error_log("AdaptiveTimeoutManager: Failed to log timeout event: " . $e->getMessage());
      }
    }
    
    // Also log to error_log for immediate visibility
    if ($timedOut || $this->debug) {
      error_log("AdaptiveTimeoutManager: {$message}");
    }
  }

  /**
   * Get timeout configuration
   * 
   * Returns current timeout configuration for monitoring and debugging.
   * 
   * @return array Configuration array
   *   - cold_cache_timeout: int - timeout for cold cache (seconds)
   *   - warm_cache_timeout: int - timeout for warm cache (seconds)
   *   - debug: bool - debug mode enabled
   */
  public function getConfiguration(): array
  {
    return [
      'cold_cache_timeout' => $this->coldCacheTimeout,
      'warm_cache_timeout' => $this->warmCacheTimeout,
      'debug' => $this->debug,
      'logger_enabled' => $this->logger !== null
    ];
  }

  /**
   * Set cold cache timeout
   * 
   * Updates the timeout threshold for cold cache scenarios.
   * 
   * @param int $timeout Timeout in seconds
   * @return void
   */
  public function setColdCacheTimeout(int $timeout): void
  {
    $this->coldCacheTimeout = $timeout;
    
    if ($this->debug) {
      error_log("AdaptiveTimeoutManager: Cold cache timeout updated to {$timeout}s");
    }
  }

  /**
   * Set warm cache timeout
   * 
   * Updates the timeout threshold for warm cache scenarios.
   * 
   * @param int $timeout Timeout in seconds
   * @return void
   */
  public function setWarmCacheTimeout(int $timeout): void
  {
    $this->warmCacheTimeout = $timeout;
    
    if ($this->debug) {
      error_log("AdaptiveTimeoutManager: Warm cache timeout updated to {$timeout}s");
    }
  }

  /**
   * Get timeout statistics
   * 
   * Provides statistics about timeout management.
   * 
   * @return array Statistics
   *   - cold_cache_timeout: int - current cold cache timeout
   *   - warm_cache_timeout: int - current warm cache timeout
   *   - timeout_difference: int - difference between cold and warm timeouts
   *   - timeout_ratio: float - ratio of cold to warm timeout
   */
  public function getStats(): array
  {
    $difference = $this->coldCacheTimeout - $this->warmCacheTimeout;
    $ratio = $this->warmCacheTimeout > 0 ? 
             round($this->coldCacheTimeout / $this->warmCacheTimeout, 2) : 
             0;
    
    return [
      'cold_cache_timeout' => $this->coldCacheTimeout,
      'warm_cache_timeout' => $this->warmCacheTimeout,
      'timeout_difference' => $difference,
      'timeout_ratio' => $ratio,
      'debug_enabled' => $this->debug,
      'logger_enabled' => $this->logger !== null
    ];
  }

  /**
   * Check if timeout should be extended for cache state
   * 
   * Convenience method to check if extended timeout should be applied.
   * 
   * @param array $cacheState Cache state from CacheStateDetector
   * @return bool True if extended timeout should be applied
   */
  public function shouldExtendTimeout(array $cacheState): bool
  {
    $state = $cacheState['state'] ?? 'cold';
    return $state === 'cold' || $state === 'expired';
  }

  /**
   * Calculate timeout margin
   * 
   * Calculates the remaining time before timeout based on elapsed time.
   * 
   * @param array $cacheState Cache state from CacheStateDetector
   * @param float $elapsedTime Elapsed execution time in seconds
   * @return float Remaining time before timeout (seconds)
   */
  public function calculateTimeoutMargin(array $cacheState, float $elapsedTime): float
  {
    $timeout = $this->getTimeout($cacheState);
    $margin = $timeout - $elapsedTime;
    
    return max(0, $margin); // Never return negative margin
  }

  /**
   * Check if query is approaching timeout
   * 
   * Determines if query execution is approaching the timeout threshold.
   * Useful for triggering progress updates or early termination.
   * 
   * @param array $cacheState Cache state from CacheStateDetector
   * @param float $elapsedTime Elapsed execution time in seconds
   * @param float $warningThreshold Percentage of timeout to trigger warning (0-1)
   * @return bool True if approaching timeout
   */
  public function isApproachingTimeout(
    array $cacheState, 
    float $elapsedTime, 
    float $warningThreshold = 0.8
  ): bool {
    $timeout = $this->getTimeout($cacheState);
    $threshold = $timeout * $warningThreshold;
    
    return $elapsedTime >= $threshold;
  }
}
