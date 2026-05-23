<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\OM\Registry;

/**
 * ColdCacheMetricsCollector
 *
 * Collects and aggregates metrics related to cold cache performance, timeout events,
 * parallel execution, and cache warming for the RAG system.
 *
 * This class provides comprehensive metrics for monitoring:
 * - Cache state distribution (cold/warm/expired)
 * - Cold vs warm cache performance comparison
 * - Timeout events by cache state
 * - Parallel execution performance (analytics and hybrid queries)
 * - Cache warming statistics
 * - Hybrid query performance metrics
 *
 * @package ClicShopping\AI\Infrastructure\Metrics
 */
class ColdCacheMetricsCollector
{
  private \ClicShopping\OM\Db $db;
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug mode for detailed logging
   */
  public function __construct(bool $debug = false)
  {
    $this->db = Registry::get('Db');
    $this->debug = $debug;
  }

  /**
   * Get cache state distribution metrics
   *
   * Analyzes the distribution of queries across different cache states:
   * - Cold: No cache entry exists
   * - Warm: Valid cache entry exists
   * - Expired: Cache entry exists but is expired
   *
   * @param int $days Number of days to analyze (default: 7)
   * @return array Cache state distribution with counts and percentages
   */
  public function getCacheStateDistribution(int $days = 7): array
  {
    try {
      $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

      // Get cache state distribution from rag_statistics
      $query = "
        SELECT 
          COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.cache_state')),
            IF(cache_hit = 1, 'warm', 'cold')
          ) as cache_state,
          COUNT(*) as count
        FROM :table_rag_statistics
        WHERE date_added >= :start_date
        GROUP BY cache_state
      ";

      $result = $this->db->prepare($query);
      $result->bindValue(':start_date', $startDate);
      $result->execute();

      $distribution = [
        'cold' => 0,
        'warm' => 0,
        'expired' => 0,
        'total' => 0
      ];

      while ($row = $result->fetch()) {
        $state = trim($row['cache_state'], '"');
        $count = (int)$row['count'];
        
        if (isset($distribution[$state])) {
          $distribution[$state] = $count;
          $distribution['total'] += $count;
        }
      }

      // Calculate percentages
      if ($distribution['total'] > 0) {
        $distribution['cold_percentage'] = round(($distribution['cold'] / $distribution['total']) * 100, 1);
        $distribution['warm_percentage'] = round(($distribution['warm'] / $distribution['total']) * 100, 1);
        $distribution['expired_percentage'] = round(($distribution['expired'] / $distribution['total']) * 100, 1);
      } else {
        $distribution['cold_percentage'] = 0;
        $distribution['warm_percentage'] = 0;
        $distribution['expired_percentage'] = 0;
      }

      return $distribution;
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("ColdCacheMetricsCollector::getCacheStateDistribution() Error: " . $e->getMessage());
      }
      return [
        'cold' => 0,
        'warm' => 0,
        'expired' => 0,
        'total' => 0,
        'cold_percentage' => 0,
        'warm_percentage' => 0,
        'expired_percentage' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get cold vs warm cache performance comparison
   *
   * Compares execution times between cold and warm cache scenarios
   * to measure the performance impact of caching.
   *
   * @param int $days Number of days to analyze (default: 7)
   * @return array Performance comparison with average times and speedup factor
   */
  public function getColdVsWarmPerformance(int $days = 7): array
  {
    try {
      $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

      // Get average execution time by cache state
      $query = "
        SELECT 
          COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.cache_state')),
            IF(cache_hit = 1, 'warm', 'cold')
          ) as cache_state,
          AVG(response_time_ms) / 1000 as avg_time,
          COUNT(*) as count
        FROM :table_rag_statistics
        WHERE date_added >= :start_date
          AND response_time_ms IS NOT NULL
        GROUP BY cache_state
      ";

      $result = $this->db->prepare($query);
      $result->bindValue(':start_date', $startDate);
      $result->execute();

      $performance = [
        'cold_avg_time' => 0,
        'warm_avg_time' => 0,
        'cold_count' => 0,
        'warm_count' => 0,
        'speedup_factor' => 0,
        'time_saved_per_query' => 0
      ];

      while ($row = $result->fetch()) {
        $state = trim($row['cache_state'], '"');
        $avgTime = (float)$row['avg_time'];
        $count = (int)$row['count'];

        if ($state === 'cold') {
          $performance['cold_avg_time'] = round($avgTime, 2);
          $performance['cold_count'] = $count;
        } elseif ($state === 'warm') {
          $performance['warm_avg_time'] = round($avgTime, 2);
          $performance['warm_count'] = $count;
        }
      }

      // Calculate speedup factor
      if ($performance['warm_avg_time'] > 0 && $performance['cold_avg_time'] > 0) {
        $performance['speedup_factor'] = round($performance['cold_avg_time'] / $performance['warm_avg_time'], 2);
        $performance['time_saved_per_query'] = round($performance['cold_avg_time'] - $performance['warm_avg_time'], 2);
        $performance['percentage_faster'] = round((($performance['cold_avg_time'] - $performance['warm_avg_time']) / $performance['cold_avg_time']) * 100, 1);
      }

      return $performance;
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("ColdCacheMetricsCollector::getColdVsWarmPerformance() Error: " . $e->getMessage());
      }
      return [
        'cold_avg_time' => 0,
        'warm_avg_time' => 0,
        'cold_count' => 0,
        'warm_count' => 0,
        'speedup_factor' => 0,
        'time_saved_per_query' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get timeout events by cache state
   *
   * Analyzes timeout occurrences across different cache states
   * to identify patterns and potential issues.
   *
   * @param int $days Number of days to analyze (default: 7)
   * @return array Timeout events with counts by cache state
   */
  public function getTimeoutEvents(int $days = 7): array
  {
    try {
      $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

      // Get timeout events from rag_statistics
      $query = "
        SELECT 
          COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.cache_state')),
            IF(cache_hit = 1, 'warm', 'cold')
          ) as cache_state,
          JSON_EXTRACT(metadata, '$.timeout_occurred') as timeout_occurred,
          COUNT(*) as count
        FROM :table_rag_statistics
        WHERE date_added >= :start_date
          AND JSON_EXTRACT(metadata, '$.timeout_occurred') = true
        GROUP BY cache_state
      ";

      $result = $this->db->prepare($query);
      $result->bindValue(':start_date', $startDate);
      $result->execute();

      $timeouts = [
        'total_timeouts' => 0,
        'cold_timeouts' => 0,
        'warm_timeouts' => 0,
        'expired_timeouts' => 0,
        'timeout_rate' => 0
      ];

      while ($row = $result->fetch()) {
        $state = trim($row['cache_state'], '"');
        $count = (int)$row['count'];
        
        $timeouts['total_timeouts'] += $count;
        
        if ($state === 'cold') {
          $timeouts['cold_timeouts'] = $count;
        } elseif ($state === 'warm') {
          $timeouts['warm_timeouts'] = $count;
        } elseif ($state === 'expired') {
          $timeouts['expired_timeouts'] = $count;
        }
      }

      // Calculate timeout rate
      $totalQueries = $this->getTotalQueries($days);
      if ($totalQueries > 0) {
        $timeouts['timeout_rate'] = round(($timeouts['total_timeouts'] / $totalQueries) * 100, 2);
      }

      return $timeouts;
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("ColdCacheMetricsCollector::getTimeoutEvents() Error: " . $e->getMessage());
      }
      return [
        'total_timeouts' => 0,
        'cold_timeouts' => 0,
        'warm_timeouts' => 0,
        'expired_timeouts' => 0,
        'timeout_rate' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get parallel execution performance metrics
   *
   * Analyzes the performance impact of parallel execution for both
   * analytics queries (multiple SQL interpretations) and hybrid queries
   * (multiple sub-queries).
   *
   * @param int $days Number of days to analyze (default: 7)
   * @return array Parallel execution metrics with time savings and speedup factors
   */
  public function getParallelExecutionMetrics(int $days = 7): array
  {
    try {
      $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

      // Get parallel execution metrics from rag_statistics
      $query = "
        SELECT 
          classification_type as query_type,
          JSON_EXTRACT(metadata, '$.parallel_execution') as parallel_execution,
          JSON_EXTRACT(metadata, '$.parallel_time') as parallel_time,
          JSON_EXTRACT(metadata, '$.sequential_time') as sequential_time,
          JSON_EXTRACT(metadata, '$.time_saved') as time_saved,
          JSON_EXTRACT(metadata, '$.percentage_faster') as percentage_faster,
          COUNT(*) as count
        FROM :table_rag_statistics
        WHERE date_added >= :start_date
          AND JSON_EXTRACT(metadata, '$.parallel_execution') = true
        GROUP BY query_type
      ";

      $result = $this->db->prepare($query);
      $result->bindValue(':start_date', $startDate);
      $result->execute();

      $metrics = [
        'total_parallel_queries' => 0,
        'total_time_saved' => 0,
        'avg_speedup_factor' => 0,
        'analytics' => [
          'count' => 0,
          'avg_speedup' => 0,
          'time_saved' => 0,
          'percentage_faster' => 0
        ],
        'hybrid' => [
          'count' => 0,
          'avg_speedup' => 0,
          'time_saved' => 0,
          'percentage_faster' => 0
        ]
      ];

      $totalSpeedup = 0;
      $speedupCount = 0;

      while ($row = $result->fetch()) {
        $queryType = $row['query_type'];
        $count = (int)$row['count'];
        $timeSaved = (float)$row['time_saved'];
        $percentageFaster = (float)$row['percentage_faster'];
        $parallelTime = (float)$row['parallel_time'];
        $sequentialTime = (float)$row['sequential_time'];

        $metrics['total_parallel_queries'] += $count;
        $metrics['total_time_saved'] += $timeSaved * $count;

        if ($parallelTime > 0 && $sequentialTime > 0) {
          $speedup = $sequentialTime / $parallelTime;
          $totalSpeedup += $speedup;
          $speedupCount++;
        }

        if ($queryType === 'analytics') {
          $metrics['analytics']['count'] = $count;
          $metrics['analytics']['time_saved'] = round($timeSaved * $count, 2);
          $metrics['analytics']['percentage_faster'] = round($percentageFaster, 1);
          if ($parallelTime > 0 && $sequentialTime > 0) {
            $metrics['analytics']['avg_speedup'] = round($sequentialTime / $parallelTime, 2);
          }
        } elseif ($queryType === 'hybrid') {
          $metrics['hybrid']['count'] = $count;
          $metrics['hybrid']['time_saved'] = round($timeSaved * $count, 2);
          $metrics['hybrid']['percentage_faster'] = round($percentageFaster, 1);
          if ($parallelTime > 0 && $sequentialTime > 0) {
            $metrics['hybrid']['avg_speedup'] = round($sequentialTime / $parallelTime, 2);
          }
        }
      }

      // Calculate average speedup factor
      if ($speedupCount > 0) {
        $metrics['avg_speedup_factor'] = round($totalSpeedup / $speedupCount, 2);
      }

      $metrics['total_time_saved'] = round($metrics['total_time_saved'], 2);

      return $metrics;
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("ColdCacheMetricsCollector::getParallelExecutionMetrics() Error: " . $e->getMessage());
      }
      return [
        'total_parallel_queries' => 0,
        'total_time_saved' => 0,
        'avg_speedup_factor' => 0,
        'analytics' => [
          'count' => 0,
          'avg_speedup' => 0,
          'time_saved' => 0,
          'percentage_faster' => 0
        ],
        'hybrid' => [
          'count' => 0,
          'avg_speedup' => 0,
          'time_saved' => 0,
          'percentage_faster' => 0
        ],
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get hybrid query performance metrics
   *
   * Analyzes hybrid query execution patterns including sub-query counts,
   * execution times, and success rates.
   *
   * @param int $days Number of days to analyze (default: 7)
   * @return array Hybrid query metrics
   */
  public function getHybridQueryMetrics(int $days = 7): array
  {
    try {
      $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

      // Get hybrid query metrics
      $query = "
        SELECT 
          COUNT(*) as total_count,
          AVG(JSON_EXTRACT(metadata, '$.subquery_count')) as avg_subqueries,
          AVG(response_time_ms) / 1000 as avg_execution_time,
          SUM(CASE WHEN error_occurred = 0 THEN 1 ELSE 0 END) as success_count,
          SUM(CASE WHEN response_time_ms < 5000 THEN 1 ELSE 0 END) as under_5s,
          SUM(CASE WHEN response_time_ms >= 5000 AND response_time_ms < 15000 THEN 1 ELSE 0 END) as between_5_15s,
          SUM(CASE WHEN response_time_ms >= 15000 AND response_time_ms < 30000 THEN 1 ELSE 0 END) as between_15_30s,
          SUM(CASE WHEN response_time_ms >= 30000 THEN 1 ELSE 0 END) as over_30s
        FROM :table_rag_statistics
        WHERE date_added >= :start_date
          AND classification_type = 'hybrid'
      ";

      $result = $this->db->prepare($query);
      $result->bindValue(':start_date', $startDate);
      $result->execute();

      $row = $result->fetch();

      $totalCount = (int)$row['total_count'];
      $successCount = (int)$row['success_count'];

      $metrics = [
        'total_count' => $totalCount,
        'avg_subqueries' => round((float)$row['avg_subqueries'], 1),
        'avg_execution_time' => round((float)$row['avg_execution_time'], 2),
        'success_rate' => $totalCount > 0 ? round(($successCount / $totalCount) * 100, 1) : 0,
        'time_distribution' => [
          'under_5s' => (int)$row['under_5s'],
          'between_5_15s' => (int)$row['between_5_15s'],
          'between_15_30s' => (int)$row['between_15_30s'],
          'over_30s' => (int)$row['over_30s']
        ]
      ];

      return $metrics;
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("ColdCacheMetricsCollector::getHybridQueryMetrics() Error: " . $e->getMessage());
      }
      return [
        'total_count' => 0,
        'avg_subqueries' => 0,
        'avg_execution_time' => 0,
        'success_rate' => 0,
        'time_distribution' => [
          'under_5s' => 0,
          'between_5_15s' => 0,
          'between_15_30s' => 0,
          'over_30s' => 0
        ],
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get cache warming statistics
   *
   * Retrieves statistics about proactive cache warming operations
   * including success rates and scheduled runs.
   *
   * @return array Cache warming statistics
   */
  public function getCacheWarmingStats(): array
  {
    // Note: This will return placeholder data until cache warming is implemented
    // Task 15 (cache warming scheduler) is marked as not implemented
    return [
        'total_warmed' => 0,
        'success_rate' => 0,
        'last_run' => null,
        'next_run' => null,
        'queries_identified' => 0,
        'frequency_threshold' => 5,
        'expiring_soon' => 0,
        'expiration_warning_days' => 3,
        'note' => 'Cache warming scheduler not yet implemented (Task 15)'
    ];
  }

  /**
   * Get all cold cache metrics
   *
   * Aggregates all cold cache related metrics into a single response
   * for dashboard display.
   *
   * @param int $days Number of days to analyze (default: 7)
   * @return array Complete cold cache metrics
   */
  public function getAllMetrics(int $days = 7): array
  {
    return [
      'cache_state_distribution' => $this->getCacheStateDistribution($days),
      'cold_vs_warm_performance' => $this->getColdVsWarmPerformance($days),
      'timeout_events' => $this->getTimeoutEvents($days),
      'parallel_execution' => $this->getParallelExecutionMetrics($days),
      'hybrid_query_metrics' => $this->getHybridQueryMetrics($days),
      'cache_warming' => $this->getCacheWarmingStats(),
      'period_days' => $days
    ];
  }

  /**
   * Get total queries for a given period
   *
   * Helper method to calculate total queries for percentage calculations.
   *
   * @param int $days Number of days to analyze
   * @return int Total number of queries
   */
  private function getTotalQueries(int $days): int
  {
    try {
      $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

      $query = "
        SELECT COUNT(*) as total
        FROM :table_rag_statistics
        WHERE date_added >= :start_date
      ";

      $result = $this->db->prepare($query);
      $result->bindValue(':start_date', $startDate);
      $result->execute();

      $row = $result->fetch();
      return (int)$row['total'];
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("ColdCacheMetricsCollector::getTotalQueries() Error: " . $e->getMessage());
      }
      return 0;
    }
  }
}
