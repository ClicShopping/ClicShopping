<?php
/**
 * Cache State Detector for RAG Query Cache
 * 
 * Detects cache state (cold, warm, expired) to enable adaptive timeout management.
 * Uses QueryCache to check cache existence and validity.
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache;


use ClicShopping\AI\Infrastructure\Cache\QueryCache;

/**
 * CacheStateDetector Class
 * 
 * Analyzes cache entries to determine cache state for adaptive timeout management.
 * 
 * Cache States:
 * - 'cold': No cache entry exists or cache is expired
 * - 'warm': Valid cache entry exists and is not expired
 * - 'expired': Cache entry exists but has expired
 * 
 * Features:
 * - Fast cache state detection (<1ms with RagCache)
 * - Cache age calculation for monitoring
 * - Cache validity checking
 * - Comprehensive metadata reporting
 * 
 * Usage:
 * ```php
 * $detector = new CacheStateDetector($queryCache);
 * $state = $detector->detectCacheState($userQuery, $context);
 * 
 * if ($state['state'] === 'cold') {
 *   // Apply extended timeout
 * } else {
 *   // Apply standard timeout
 * }
 * ```
 */

class CacheStateDetector
{
  private QueryCache $queryCache;
  private bool $debug = false;

  /**
   * Constructor
   * 
   * @param QueryCache $queryCache QueryCache instance for cache access
   */
  public function __construct(QueryCache $queryCache)
  {
    $this->queryCache = $queryCache;
    
    // Check debug mode
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Detect cache state for a given query
   * 
   * Determines if cache is cold (no cache/expired) or warm (valid cache exists).
   * 
   * @param string $userQuery User's natural language query
   * @param array $context Query context (interpretation, entity_id, entity_type)
   * @return array Cache state information
   *   - state: 'cold', 'warm', or 'expired'
   *   - exists: bool - whether cache entry exists
   *   - valid: bool - whether cache entry is valid (not expired)
   *   - age_seconds: int|null - age of cache entry in seconds
   *   - timestamp: int|null - cache entry creation timestamp
   *   - backend: string|null - cache backend used
   */
  public function detectCacheState(string $userQuery, array $context = []): array
  {
    $startTime = microtime(true);
    
    try {
      // Try to get cache entry
      $cached = $this->queryCache->get($userQuery, $context);
      
      if ($cached === null) {
        // No cache entry exists
        $state = [
          'state' => 'cold',
          'exists' => false,
          'valid' => false,
          'age_seconds' => null,
          'timestamp' => null,
          'backend' => null,
          'detection_time_ms' => (microtime(true) - $startTime) * 1000
        ];
        
        if ($this->debug) {
          error_log("CacheStateDetector: COLD cache detected (no entry) - " . substr($userQuery, 0, 50));
        }
        
        return $state;
      }
      
      // Cache entry exists - check validity
      $timestamp = $cached['timestamp'] ?? null;
      $ageSeconds = $timestamp ? (time() - $timestamp) : null;
      $backend = $cached['backend'] ?? null;
      
      // Check if cache is valid (not expired)
      $isValid = $this->isCacheValid($cached);
      
      if ($isValid) {
        // Valid cache entry
        $state = [
          'state' => 'warm',
          'exists' => true,
          'valid' => true,
          'age_seconds' => $ageSeconds,
          'timestamp' => $timestamp,
          'backend' => $backend,
          'detection_time_ms' => (microtime(true) - $startTime) * 1000
        ];
        
        if ($this->debug) {
          error_log("CacheStateDetector: WARM cache detected (age: {$ageSeconds}s, backend: {$backend}) - " . substr($userQuery, 0, 50));
        }
        
        return $state;
      } else {
        // Expired cache entry
        $state = [
          'state' => 'expired',
          'exists' => true,
          'valid' => false,
          'age_seconds' => $ageSeconds,
          'timestamp' => $timestamp,
          'backend' => $backend,
          'detection_time_ms' => (microtime(true) - $startTime) * 1000
        ];
        
        if ($this->debug) {
          error_log("CacheStateDetector: EXPIRED cache detected (age: {$ageSeconds}s) - " . substr($userQuery, 0, 50));
        }
        
        return $state;
      }
      
    } catch (\Exception $e) {
      error_log("CacheStateDetector: Error detecting cache state: " . $e->getMessage());
      
      // On error, assume cold cache (safe default)
      return [
        'state' => 'cold',
        'exists' => false,
        'valid' => false,
        'age_seconds' => null,
        'timestamp' => null,
        'backend' => null,
        'error' => $e->getMessage(),
        'detection_time_ms' => (microtime(true) - $startTime) * 1000
      ];
    }
  }

  /**
   * Check if cache entry is valid (not expired)
   * 
   * A cache entry is considered valid if:
   * - It has a timestamp
   * - The timestamp is not in the future
   * - The age is within the TTL (if available)
   * 
   * @param array $cached Cache entry from QueryCache
   * @return bool True if cache is valid, false if expired
   */
  public function isCacheValid(array $cached): bool
  {
    // Check if timestamp exists
    if (!isset($cached['timestamp']) || $cached['timestamp'] === null) {
      // No timestamp - assume invalid
      return false;
    }
    
    $timestamp = $cached['timestamp'];
    $currentTime = time();
    
    // Check if timestamp is in the future (invalid)
    if ($timestamp > $currentTime) {
      if ($this->debug) {
        error_log("CacheStateDetector: Invalid cache - timestamp in future");
      }
      return false;
    }
    
    // Calculate age
    $ageSeconds = $currentTime - $timestamp;
    
    // Get TTL from configuration (default: 30 days = 2592000 seconds)
    $ttl = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL') ? 
           CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL : 
           2592000;
    
    // Check if cache has expired
    if ($ageSeconds > $ttl) {
      if ($this->debug) {
        error_log("CacheStateDetector: Cache expired (age: {$ageSeconds}s, TTL: {$ttl}s)");
      }
      return false;
    }
    
    // Cache is valid
    return true;
  }

  /**
   * Calculate cache age in seconds
   * 
   * @param array $cached Cache entry from QueryCache
   * @return int|null Age in seconds, or null if no timestamp
   */
  public function calculateCacheAge(array $cached): ?int
  {
    if (!isset($cached['timestamp']) || $cached['timestamp'] === null) {
      return null;
    }
    
    $timestamp = $cached['timestamp'];
    $currentTime = time();
    
    // Ensure timestamp is not in the future
    if ($timestamp > $currentTime) {
      return null;
    }
    
    return $currentTime - $timestamp;
  }

  /**
   * Get cache state statistics
   * 
   * Provides summary statistics about cache state detection.
   * 
   * @return array Statistics
   *   - total_detections: int - total number of detections performed
   *   - cold_count: int - number of cold cache detections
   *   - warm_count: int - number of warm cache detections
   *   - expired_count: int - number of expired cache detections
   *   - avg_detection_time_ms: float - average detection time
   */
  public function getStats(): array
  {
    // Get QueryCache stats
    $cacheStats = $this->queryCache->getStats();
    
    return [
      'enabled' => $cacheStats['enabled'] ?? false,
      'backend' => $cacheStats['backend'] ?? 'unknown',
      'cache_hit_rate' => $cacheStats['hit_rate'] ?? 0,
      'total_entries' => $cacheStats['total_entries'] ?? 0,
      'active_entries' => $cacheStats['active_entries'] ?? 0,
      'expired_entries' => $cacheStats['expired_entries'] ?? 0
    ];
  }

  /**
   * Check if cache state is cold
   * 
   * Convenience method to check if cache state is cold.
   * 
   * @param array $cacheState Cache state from detectCacheState()
   * @return bool True if cache is cold
   */
  public function isCold(array $cacheState): bool
  {
    return $cacheState['state'] === 'cold' || $cacheState['state'] === 'expired';
  }

  /**
   * Check if cache state is warm
   * 
   * Convenience method to check if cache state is warm.
   * 
   * @param array $cacheState Cache state from detectCacheState()
   * @return bool True if cache is warm
   */
  public function isWarm(array $cacheState): bool
  {
    return $cacheState['state'] === 'warm';
  }

  /**
   * Get recommended timeout based on cache state
   * 
   * Provides timeout recommendation based on cache state.
   * This is a helper method - actual timeout management should use AdaptiveTimeoutManager.
   * 
   * @param array $cacheState Cache state from detectCacheState()
   * @return int Recommended timeout in seconds
   */
  public function getRecommendedTimeout(array $cacheState): int
  {
    // Cold cache: extended timeout (120 seconds)
    // Warm cache: standard timeout (30 seconds)
    
    if ($this->isCold($cacheState)) {
      return 120; // Extended timeout for cold cache
    } else {
      return 30; // Standard timeout for warm cache
    }
  }
}
