<?php
/**
 * Performance Monitor Class
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\AI\Infrastructure\Monitoring;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Cache;

/**
 * Class PerformanceMonitor
 * 
 * Provides comprehensive performance monitoring for AI operations
 * 
 * Features:
 * - Operation duration tracking
 * - Cache hit/miss rate monitoring
 * - Slow operation detection (>100ms)
 * - Performance metrics aggregation
 * - Dashboard data generation
 * 
 * Usage:
 * ```php
 * $monitor = new PerformanceMonitor();
 * $monitor->startOperation('intent_analysis');
 * // ... perform operation ...
 * $monitor->endOperation('intent_analysis');
 * $monitor->logCacheHit('intent_cache');
 * $monitor->logCacheMiss('intent_cache');
 * ```
 */
class PerformanceMonitor
{
  private SecurityLogger $logger;
  private bool $debug;
  
  // Operation tracking
  private array $operations = [];
  private array $operationStats = [];
  
  // Cache tracking
  private array $cacheStats = [];
  
  // Slow operation threshold (milliseconds)
  private float $slowOperationThreshold = 100.0;
  
  // Metrics aggregation
  private array $metrics = [];
  
  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param float $slowOperationThreshold Threshold for slow operations in milliseconds
   */
  public function __construct(bool $debug = false, float $slowOperationThreshold = 100.0)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->slowOperationThreshold = $slowOperationThreshold;
    
    // Initialize cache stats
    $this->initializeCacheStats();
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "PerformanceMonitor initialized (slow threshold: {$slowOperationThreshold}ms)",
        'info'
      );
    }
  }
  
  /**
   * Start tracking an operation
   *
   * @param string $operationName Name of the operation
   * @param array $context Additional context data
   * @return void
   */
  public function startOperation(string $operationName, array $context = []): void
  {
    $this->operations[$operationName] = [
      'start_time' => microtime(true),
      'context' => $context,
    ];
    
    if ($this->debug) {
      $this->logger->logStructured(
        'debug',
        'PerformanceMonitor',
        'operation_started',
        [
          'operation' => $operationName,
          'context' => $context,
        ]
      );
    }
  }
  
  /**
   * End tracking an operation and log duration
   *
   * @param string $operationName Name of the operation
   * @param array $additionalContext Additional context to log
   * @return float Duration in milliseconds
   */
  public function endOperation(string $operationName, array $additionalContext = []): float
  {
    if (!isset($this->operations[$operationName])) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Attempted to end operation '{$operationName}' that was never started",
          'warning'
        );
      }
      return 0.0;
    }
    
    $startTime = $this->operations[$operationName]['start_time'];
    $context = $this->operations[$operationName]['context'];
    $endTime = microtime(true);
    $durationMs = ($endTime - $startTime) * 1000;
    
    // Update operation stats
    $this->updateOperationStats($operationName, $durationMs);
    
    // Merge contexts
    $fullContext = array_merge($context, $additionalContext, [
      'operation' => $operationName,
      'duration_ms' => round($durationMs, 2),
    ]);
    
    // Check if slow operation
    $isSlow = $durationMs > $this->slowOperationThreshold;
    
    // Log operation completion
    $this->logger->logStructured(
      $isSlow ? 'warning' : 'info',
      'PerformanceMonitor',
      $isSlow ? 'slow_operation_detected' : 'operation_completed',
      $fullContext
    );
    
    // Clean up
    unset($this->operations[$operationName]);
    
    return $durationMs;
  }
  
  /**
   * Log a cache hit
   *
   * @param string $cacheKey Cache key or cache type
   * @param array $context Additional context
   * @return void
   */
  public function logCacheHit(string $cacheKey, array $context = []): void
  {
    // Update cache stats
    if (!isset($this->cacheStats[$cacheKey])) {
      $this->cacheStats[$cacheKey] = ['hits' => 0, 'misses' => 0];
    }
    $this->cacheStats[$cacheKey]['hits']++;
    
    // Log cache hit
    $this->logger->logStructured(
      'info',
      'PerformanceMonitor',
      'cache_hit',
      array_merge($context, [
        'cache_key' => $cacheKey,
        'hit_rate' => $this->getCacheHitRate($cacheKey),
      ])
    );
  }
  
  /**
   * Log a cache miss
   *
   * @param string $cacheKey Cache key or cache type
   * @param array $context Additional context
   * @return void
   */
  public function logCacheMiss(string $cacheKey, array $context = []): void
  {
    // Update cache stats
    if (!isset($this->cacheStats[$cacheKey])) {
      $this->cacheStats[$cacheKey] = ['hits' => 0, 'misses' => 0];
    }
    $this->cacheStats[$cacheKey]['misses']++;
    
    // Log cache miss
    $this->logger->logStructured(
      'info',
      'PerformanceMonitor',
      'cache_miss',
      array_merge($context, [
        'cache_key' => $cacheKey,
        'hit_rate' => $this->getCacheHitRate($cacheKey),
      ])
    );
  }
  
  /**
   * Get cache hit rate for a specific cache key
   *
   * @param string $cacheKey Cache key or cache type
   * @return float Hit rate (0-1)
   */
  public function getCacheHitRate(string $cacheKey): float
  {
    if (!isset($this->cacheStats[$cacheKey])) {
      return 0.0;
    }
    
    $stats = $this->cacheStats[$cacheKey];
    $total = $stats['hits'] + $stats['misses'];
    
    if ($total === 0) {
      return 0.0;
    }
    
    return round($stats['hits'] / $total, 4);
  }
  
  /**
   * Get all cache statistics
   *
   * @return array Cache statistics for all keys
   */
  public function getAllCacheStats(): array
  {
    $result = [];
    
    foreach ($this->cacheStats as $key => $stats) {
      $total = $stats['hits'] + $stats['misses'];
      $result[$key] = [
        'hits' => $stats['hits'],
        'misses' => $stats['misses'],
        'total' => $total,
        'hit_rate' => $total > 0 ? round($stats['hits'] / $total, 4) : 0.0,
      ];
    }
    
    return $result;
  }
  
  /**
   * Update operation statistics
   *
   * @param string $operationName Operation name
   * @param float $durationMs Duration in milliseconds
   * @return void
   */
  private function updateOperationStats(string $operationName, float $durationMs): void
  {
    if (!isset($this->operationStats[$operationName])) {
      $this->operationStats[$operationName] = [
        'count' => 0,
        'total_ms' => 0.0,
        'min_ms' => PHP_FLOAT_MAX,
        'max_ms' => 0.0,
        'durations' => [], // Store last 100 durations for percentile calculation
      ];
    }
    
    $stats = &$this->operationStats[$operationName];
    $stats['count']++;
    $stats['total_ms'] += $durationMs;
    $stats['min_ms'] = min($stats['min_ms'], $durationMs);
    $stats['max_ms'] = max($stats['max_ms'], $durationMs);
    
    // Store duration for percentile calculation (keep last 100)
    $stats['durations'][] = $durationMs;
    if (count($stats['durations']) > 100) {
      array_shift($stats['durations']);
    }
  }
  
  /**
   * Get operation statistics
   *
   * @param string $operationName Operation name (optional, returns all if null)
   * @return array Operation statistics
   */
  public function getOperationStats(?string $operationName = null): array
  {
    if ($operationName !== null) {
      if (!isset($this->operationStats[$operationName])) {
        return [];
      }
      
      return $this->calculateOperationMetrics($operationName, $this->operationStats[$operationName]);
    }
    
    // Return all operation stats
    $result = [];
    foreach ($this->operationStats as $name => $stats) {
      $result[$name] = $this->calculateOperationMetrics($name, $stats);
    }
    
    return $result;
  }
  
  /**
   * Calculate operation metrics from raw stats
   *
   * @param string $operationName Operation name
   * @param array $stats Raw statistics
   * @return array Calculated metrics
   */
  private function calculateOperationMetrics(string $operationName, array $stats): array
  {
    $count = $stats['count'];
    $avgMs = $count > 0 ? $stats['total_ms'] / $count : 0.0;
    
    // Calculate percentiles
    $p50 = $this->calculatePercentile($stats['durations'], 0.5);
    $p95 = $this->calculatePercentile($stats['durations'], 0.95);
    $p99 = $this->calculatePercentile($stats['durations'], 0.99);
    
    return [
      'operation' => $operationName,
      'count' => $count,
      'total_ms' => round($stats['total_ms'], 2),
      'avg_ms' => round($avgMs, 2),
      'min_ms' => round($stats['min_ms'], 2),
      'max_ms' => round($stats['max_ms'], 2),
      'p50_ms' => round($p50, 2),
      'p95_ms' => round($p95, 2),
      'p99_ms' => round($p99, 2),
    ];
  }
  
  /**
   * Calculate percentile from array of values
   *
   * @param array $values Array of numeric values
   * @param float $percentile Percentile (0-1)
   * @return float Percentile value
   */
  private function calculatePercentile(array $values, float $percentile): float
  {
    if (empty($values)) {
      return 0.0;
    }
    
    sort($values);
    $count = count($values);
    $index = (int)ceil($count * $percentile) - 1;
    $index = max(0, min($index, $count - 1));
    
    return $values[$index];
  }
  
  /**
   * Generate performance dashboard data
   *
   * @return array Dashboard data with metrics and statistics
   */
  public function generateDashboardData(): array
  {
    return [
      'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
      'operations' => $this->getOperationStats(),
      'cache' => $this->getAllCacheStats(),
      'slow_operations' => $this->getSlowOperations(),
      'summary' => $this->generateSummary(),
    ];
  }
  
  /**
   * Get list of slow operations (above threshold)
   *
   * @return array List of slow operations with details
   */
  private function getSlowOperations(): array
  {
    $slowOps = [];
    
    foreach ($this->operationStats as $name => $stats) {
      $avgMs = $stats['count'] > 0 ? $stats['total_ms'] / $stats['count'] : 0.0;
      
      if ($avgMs > $this->slowOperationThreshold) {
        $slowOps[] = [
          'operation' => $name,
          'avg_ms' => round($avgMs, 2),
          'max_ms' => round($stats['max_ms'], 2),
          'count' => $stats['count'],
          'threshold_ms' => $this->slowOperationThreshold,
        ];
      }
    }
    
    // Sort by average duration (slowest first)
    usort($slowOps, function($a, $b) {
      return $b['avg_ms'] <=> $a['avg_ms'];
    });
    
    return $slowOps;
  }
  
  /**
   * Generate performance summary
   *
   * @return array Performance summary
   */
  private function generateSummary(): array
  {
    $totalOperations = 0;
    $totalDuration = 0.0;
    $slowOperationCount = 0;
    
    foreach ($this->operationStats as $stats) {
      $totalOperations += $stats['count'];
      $totalDuration += $stats['total_ms'];
      
      $avgMs = $stats['count'] > 0 ? $stats['total_ms'] / $stats['count'] : 0.0;
      if ($avgMs > $this->slowOperationThreshold) {
        $slowOperationCount++;
      }
    }
    
    $totalCacheHits = 0;
    $totalCacheMisses = 0;
    
    foreach ($this->cacheStats as $stats) {
      $totalCacheHits += $stats['hits'];
      $totalCacheMisses += $stats['misses'];
    }
    
    $totalCacheRequests = $totalCacheHits + $totalCacheMisses;
    $overallCacheHitRate = $totalCacheRequests > 0 
      ? round($totalCacheHits / $totalCacheRequests, 4) 
      : 0.0;
    
    return [
      'total_operations' => $totalOperations,
      'total_duration_ms' => round($totalDuration, 2),
      'avg_operation_duration_ms' => $totalOperations > 0 
        ? round($totalDuration / $totalOperations, 2) 
        : 0.0,
      'slow_operation_count' => $slowOperationCount,
      'slow_operation_threshold_ms' => $this->slowOperationThreshold,
      'cache_hit_rate' => $overallCacheHitRate,
      'cache_hits' => $totalCacheHits,
      'cache_misses' => $totalCacheMisses,
      'cache_requests' => $totalCacheRequests,
    ];
  }
  
  /**
   * Initialize cache statistics from persistent storage
   *
   * @return void
   */
  private function initializeCacheStats(): void
  {
    try {
      // Try to load cache stats from OM\Cache
      $cache = new Cache('performance_cache_stats', 'monitoring');
      
      if ($cache->exists(3600)) { // 1 hour TTL
        $cached = $cache->get();
        if (is_array($cached)) {
          $this->cacheStats = $cached;
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Loaded cache stats from persistent storage",
              'info'
            );
          }
        }
      }
    } catch (\Exception $e) {
      // Graceful degradation - start with empty stats
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Failed to load cache stats: " . $e->getMessage(),
          'warning'
        );
      }
    }
  }
  
  /**
   * Save cache statistics to persistent storage
   *
   * @return bool True if saved successfully
   */
  public function saveCacheStats(): bool
  {
    try {
      $cache = new Cache('performance_cache_stats', 'monitoring');
      $cache->save($this->cacheStats);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Saved cache stats to persistent storage",
          'info'
        );
      }
      
      return true;
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Failed to save cache stats: " . $e->getMessage(),
          'error'
        );
      }
      
      return false;
    }
  }
  
  /**
   * Reset all statistics
   *
   * @return void
   */
  public function reset(): void
  {
    $this->operations = [];
    $this->operationStats = [];
    $this->cacheStats = [];
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Performance monitor statistics reset",
        'info'
      );
    }
  }
  
  /**
   * Log performance metrics summary
   *
   * @return void
   */
  public function logSummary(): void
  {
    $summary = $this->generateSummary();
    
    $this->logger->logStructured(
      'info',
      'PerformanceMonitor',
      'performance_summary',
      $summary
    );
  }
}
