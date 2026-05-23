<?php
/**
 * Hybrid Query Cache System for Multi-Temporal Queries
 * 
 * Caches each sub-query result separately for multi-temporal queries.
 * Supports partial cache retrieval (some sub-queries cached, others not).
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * @date 2026-01-08
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Cache;


use ClicShopping\OM\Cache as OMCache;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * HybridQueryCache Class
 * 
 * Manages caching for multi-temporal hybrid queries.
 * Each sub-query is cached separately with its temporal period.
 * 
 * Cache Key Structure:
 * - Main query: hybrid_{query_hash}_{temporal_periods_hash}
 * - Sub-query: hybrid_sub_{query_hash}_{temporal_period}
 * 
 * Cache Directory: Work/Cache/Rag/Hybrid/
 */

class HybridQueryCache
{
  private const CACHE_NAMESPACE = 'Rag/Hybrid';
  private const CACHE_TTL = 60; // 60 minutes default TTL
  
  private SecurityLogger $logger;
  private bool $debug;
  private bool $enabled;
  private int $cacheTTL;
  
  // Statistics
  private int $cacheHits = 0;
  private int $cacheMisses = 0;
  private int $partialHits = 0;
  
  /**
   * Constructor
   * 
   * @param int $ttl Cache TTL in minutes (default: 60)
   * @param bool $debug Enable debug logging
   */
  public function __construct(int $ttl = self::CACHE_TTL, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->cacheTTL = $ttl;
    
    // Check if caching is enabled globally
    $this->enabled = !defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') 
      || CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';
    
    // Ensure cache directory exists
    $this->ensureCacheDirectory();
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "HybridQueryCache initialized - enabled: " . ($this->enabled ? 'yes' : 'no') . ", TTL: {$this->cacheTTL}min",
        'info'
      );
    }
  }
  
  /**
   * Ensure the cache directory exists
   */
  private function ensureCacheDirectory(): void
  {
    $cacheDir = OMCache::getPath() . self::CACHE_NAMESPACE . '/';
    
    if (!is_dir($cacheDir)) {
      mkdir($cacheDir, 0775, true);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Created hybrid cache directory: {$cacheDir}",
          'info'
        );
      }
    }
  }
  
  /**
   * Generate cache key for a multi-temporal query
   * 
   * @param string $query Original query
   * @param array $temporalPeriods List of temporal periods
   * @param array $context Additional context (time_range, base_metric)
   * @return string Cache key
   */
  public function generateCacheKey(string $query, array $temporalPeriods, array $context = []): string
  {
    $normalizedQuery = $this->normalizeQuery($query);
    $periodsHash = md5(implode('_', $temporalPeriods));
    $contextHash = md5(json_encode($context));
    
    return 'hybrid_' . md5($normalizedQuery) . '_' . substr($periodsHash, 0, 8) . '_' . substr($contextHash, 0, 8);
  }
  
  /**
   * Generate cache key for a single sub-query
   * 
   * @param string $query Sub-query text
   * @param string $temporalPeriod Temporal period (month, quarter, semester, etc.)
   * @param array $context Additional context
   * @return string Cache key
   */
  public function generateSubQueryCacheKey(string $query, string $temporalPeriod, array $context = []): string
  {
    $normalizedQuery = $this->normalizeQuery($query);
    $contextHash = md5(json_encode($context));
    
    return 'hybrid_sub_' . md5($normalizedQuery) . '_' . $temporalPeriod . '_' . substr($contextHash, 0, 8);
  }
  
  /**
   * Normalize query for consistent cache keys
   * 
   * @param string $query Query to normalize
   * @return string Normalized query
   */
  private function normalizeQuery(string $query): string
  {
    // Lowercase, trim, remove extra whitespace
    $normalized = strtolower(trim($query));
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    
    return $normalized;
  }

  
  /**
   * Get cached result for a sub-query
   * 
   * @param string $query Sub-query text
   * @param string $temporalPeriod Temporal period
   * @param array $context Additional context
   * @return array|null Cached result or null if not found
   */
  public function getSubQueryResult(string $query, string $temporalPeriod, array $context = []): ?array
  {
    if (!$this->enabled) {
      return null;
    }
    
    $cacheKey = $this->generateSubQueryCacheKey($query, $temporalPeriod, $context);
    
    try {
      $cache = new OMCache($cacheKey, self::CACHE_NAMESPACE);
      
      if ($cache->exists($this->cacheTTL)) {
        $result = $cache->get();
        
        if ($result !== null && is_array($result)) {
          $this->cacheHits++;
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "HybridQueryCache HIT - sub-query: {$temporalPeriod}, key: {$cacheKey}",
              'info'
            );
          }
          
          // Add cache metadata
          $result['from_cache'] = true;
          $result['cache_key'] = $cacheKey;
          $result['cache_timestamp'] = $cache->getTime();
          
          return $result;
        }
      }
      
      $this->cacheMisses++;
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "HybridQueryCache MISS - sub-query: {$temporalPeriod}, key: {$cacheKey}",
          'info'
        );
      }
      
      return null;
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "HybridQueryCache error getting sub-query: " . $e->getMessage(),
        'error'
      );
      return null;
    }
  }
  
  /**
   * Cache a sub-query result
   * 
   * @param string $query Sub-query text
   * @param string $temporalPeriod Temporal period
   * @param array $result Result to cache
   * @param array $context Additional context
   * @return bool Success
   */
  public function cacheSubQueryResult(string $query, string $temporalPeriod, array $result, array $context = []): bool
  {
    if (!$this->enabled) {
      return false;
    }
    
    $cacheKey = $this->generateSubQueryCacheKey($query, $temporalPeriod, $context);
    
    try {
      $cache = new OMCache($cacheKey, self::CACHE_NAMESPACE);
      
      // Add metadata before caching
      $result['cached_at'] = time();
      $result['temporal_period'] = $temporalPeriod;
      
      $success = $cache->save($result, [
        'temporal_period' => $temporalPeriod,
        'query' => substr($query, 0, 200),
        'ttl' => $this->cacheTTL
      ]);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "HybridQueryCache SET - sub-query: {$temporalPeriod}, key: {$cacheKey}, success: " . ($success ? 'yes' : 'no'),
          'info'
        );
      }
      
      return $success;
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "HybridQueryCache error caching sub-query: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }
  
  /**
   * Get cached results for multiple sub-queries (partial retrieval)
   * 
   * Returns cached results for sub-queries that are in cache,
   * and null for those that need to be executed.
   * 
   * @param array $subQueries Array of sub-queries with structure:
   *   [['query' => string, 'temporal_period' => string, ...], ...]
   * @param array $context Additional context
   * @return array Array with structure:
   *   [
   *     'cached' => [index => result, ...],
   *     'uncached' => [index => subQuery, ...],
   *     'all_cached' => bool,
   *     'partial_hit' => bool
   *   ]
   */
  public function getMultipleSubQueryResults(array $subQueries, array $context = []): array
  {
    $cached = [];
    $uncached = [];
    
    foreach ($subQueries as $index => $subQuery) {
      $query = $subQuery['query'] ?? '';
      $temporalPeriod = $subQuery['temporal_period'] ?? 'unknown';
      
      $result = $this->getSubQueryResult($query, $temporalPeriod, $context);
      
      if ($result !== null) {
        $cached[$index] = $result;
      } else {
        $uncached[$index] = $subQuery;
      }
    }
    
    $allCached = empty($uncached);
    $partialHit = !empty($cached) && !empty($uncached);
    
    if ($partialHit) {
      $this->partialHits++;
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "HybridQueryCache PARTIAL HIT - cached: " . count($cached) . ", uncached: " . count($uncached),
          'info'
        );
      }
    }
    
    return [
      'cached' => $cached,
      'uncached' => $uncached,
      'all_cached' => $allCached,
      'partial_hit' => $partialHit
    ];
  }
  
  /**
   * Cache multiple sub-query results
   * 
   * @param array $results Array of results with structure:
   *   [index => ['query' => string, 'temporal_period' => string, 'result' => array], ...]
   * @param array $context Additional context
   * @return array Array of success status for each result
   */
  public function cacheMultipleSubQueryResults(array $results, array $context = []): array
  {
    $statuses = [];
    
    foreach ($results as $index => $item) {
      $query = $item['query'] ?? '';
      $temporalPeriod = $item['temporal_period'] ?? 'unknown';
      $result = $item['result'] ?? [];
      
      $statuses[$index] = $this->cacheSubQueryResult($query, $temporalPeriod, $result, $context);
    }
    
    return $statuses;
  }

  
  /**
   * Invalidate cache for a specific sub-query
   * 
   * @param string $query Sub-query text
   * @param string $temporalPeriod Temporal period
   * @param array $context Additional context
   * @return bool Success
   */
  public function invalidateSubQuery(string $query, string $temporalPeriod, array $context = []): bool
  {
    $cacheKey = $this->generateSubQueryCacheKey($query, $temporalPeriod, $context);
    
    try {
      $success = OMCache::clear($cacheKey, self::CACHE_NAMESPACE);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "HybridQueryCache INVALIDATE - sub-query: {$temporalPeriod}, key: {$cacheKey}",
          'info'
        );
      }
      
      return $success;
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "HybridQueryCache error invalidating sub-query: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }
  
  /**
   * Invalidate all cache entries for a multi-temporal query
   * 
   * @param string $query Original query
   * @param array $temporalPeriods List of temporal periods
   * @param array $context Additional context
   * @return bool Success
   */
  public function invalidateMultiTemporalQuery(string $query, array $temporalPeriods, array $context = []): bool
  {
    $success = true;
    
    foreach ($temporalPeriods as $period) {
      if (!$this->invalidateSubQuery($query, $period, $context)) {
        $success = false;
      }
    }
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "HybridQueryCache INVALIDATE ALL - periods: " . implode(',', $temporalPeriods) . ", success: " . ($success ? 'yes' : 'no'),
        'info'
      );
    }
    
    return $success;
  }
  
  /**
   * Clear all hybrid query cache
   * 
   * @return int Number of files deleted
   */
  public function clearAll(): int
  {
    $cacheDir = OMCache::getPath() . self::CACHE_NAMESPACE . '/';
    $deleted = 0;
    
    if (is_dir($cacheDir)) {
      $files = glob($cacheDir . '*.cache');
      
      foreach ($files as $file) {
        if (@unlink($file)) {
          $deleted++;
        }
      }
    }
    
    // Also clear memory cache
    OMCache::clearMemoryCache();
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "HybridQueryCache CLEAR ALL - deleted: {$deleted} files",
        'info'
      );
    }
    
    return $deleted;
  }
  
  /**
   * Get cache statistics
   * 
   * @return array Statistics
   */
  public function getStatistics(): array
  {
    $cacheDir = OMCache::getPath() . self::CACHE_NAMESPACE . '/';
    $fileCount = 0;
    $totalSize = 0;
    $oldestFile = null;
    $newestFile = null;
    
    if (is_dir($cacheDir)) {
      $files = glob($cacheDir . '*.cache');
      $fileCount = count($files);
      
      foreach ($files as $file) {
        $totalSize += filesize($file);
        $mtime = filemtime($file);
        
        if ($oldestFile === null || $mtime < $oldestFile) {
          $oldestFile = $mtime;
        }
        if ($newestFile === null || $mtime > $newestFile) {
          $newestFile = $mtime;
        }
      }
    }
    
    $hitRate = ($this->cacheHits + $this->cacheMisses) > 0
      ? round(($this->cacheHits / ($this->cacheHits + $this->cacheMisses)) * 100, 2)
      : 0;
    
    return [
      'enabled' => $this->enabled,
      'ttl_minutes' => $this->cacheTTL,
      'file_count' => $fileCount,
      'total_size' => $totalSize,
      'total_size_formatted' => $this->formatBytes($totalSize),
      'oldest_entry' => $oldestFile ? date('Y-m-d H:i:s', $oldestFile) : null,
      'newest_entry' => $newestFile ? date('Y-m-d H:i:s', $newestFile) : null,
      'session_hits' => $this->cacheHits,
      'session_misses' => $this->cacheMisses,
      'session_partial_hits' => $this->partialHits,
      'session_hit_rate' => $hitRate,
      'cache_directory' => $cacheDir
    ];
  }
  
  /**
   * Format bytes to human readable format
   * 
   * @param int $bytes Number of bytes
   * @param int $precision Decimal precision
   * @return string Formatted string
   */
  private function formatBytes(int $bytes, int $precision = 2): string
  {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitCount = count($units);
    
    for ($i = 0; $bytes > 1024 && $i < $unitCount - 1; $i++) {
      $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
  }
  
  /**
   * Check if caching is enabled
   * 
   * @return bool
   */
  public function isEnabled(): bool
  {
    return $this->enabled;
  }
  
  /**
   * Set cache TTL
   * 
   * @param int $ttl TTL in minutes
   */
  public function setTTL(int $ttl): void
  {
    $this->cacheTTL = $ttl;
  }
  
  /**
   * Get cache TTL
   * 
   * @return int TTL in minutes
   */
  public function getTTL(): int
  {
    return $this->cacheTTL;
  }
}
