<?php
/**
 * ActorMetricsCollector
 * 
 * Collects and tracks performance metrics for Actor agents in the actor-critic separation system.
 * Monitors execution success rates, response times, quality scores, and load metrics.
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * ActorMetricsCollector Class
 * 
 * Tracks actor performance metrics including:
 * - Total actions executed
 * - Success rate
 * - Average execution time
 * - Average quality score
 * - Current load
 * - Domain-specific performance
 */
class ActorMetricsCollector
{
  private $db;
  private string $prefix;
  private bool $debug;
  
  // Metrics buffer
  private array $metricsBuffer = [];
  private int $bufferSize = 10;
  
  // Real-time metrics cache
  private array $actorMetrics = [];
  
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    
    $this->loadMetricsCache();
  }
  
  /**
   * Records an actor execution
   * 
   * @param string $actorId Actor identifier
   * @param string $actionType Type of action executed
   * @param float $executionTime Execution time in seconds
   * @param string $status Execution status (success, failed, partial)
   * @param float $qualityScore Quality score (0.0-1.0)
   * @param array $metadata Additional metadata
   * @return bool True if recorded successfully
   */
  public function recordExecution(
    string $actorId,
    string $actionType,
    float $executionTime,
    string $status,
    float $qualityScore,
    array $metadata = []
  ): bool {
    try {
      // Insert into database
      DoctrineOrm::execute("
        INSERT INTO {$this->prefix}rag_agent_actor_executions
        (actor_id, action_type, execution_time_ms, status, quality_score, metadata, executed_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
      ", [
        $actorId,
        $actionType,
        (int)($executionTime * 1000), // Convert to milliseconds
        $status,
        $qualityScore,
        json_encode($metadata)
      ]);
      
      // Update real-time metrics
      $this->updateActorMetrics($actorId, $actionType, $executionTime, $status, $qualityScore);
      
      // Add to buffer
      $this->metricsBuffer[] = [
        'actor_id' => $actorId,
        'action_type' => $actionType,
        'execution_time' => $executionTime,
        'status' => $status,
        'quality_score' => $qualityScore,
        'timestamp' => time()
      ];
      
      $this->checkBuffer();
      
      if ($this->debug) {
        error_log(sprintf(
          "[ActorMetrics] Recorded execution: actor=%s, action=%s, time=%.2fs, status=%s, quality=%.2f",
          $actorId, $actionType, $executionTime, $status, $qualityScore
        ));
      }
      
      return true;
      
    } catch (\Exception $e) {
      error_log("ActorMetricsCollector: Error recording execution: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Updates real-time actor metrics
   * 
   * @param string $actorId Actor identifier
   * @param string $actionType Action type
   * @param float $executionTime Execution time
   * @param string $status Status
   * @param float $qualityScore Quality score
   */
  private function updateActorMetrics(
    string $actorId,
    string $actionType,
    float $executionTime,
    string $status,
    float $qualityScore
  ): void {
    if (!isset($this->actorMetrics[$actorId])) {
      $this->actorMetrics[$actorId] = [
        'total_executions' => 0,
        'successful_executions' => 0,
        'failed_executions' => 0,
        'total_execution_time' => 0.0,
        'total_quality_score' => 0.0,
        'by_action_type' => []
      ];
    }
    
    $metrics = &$this->actorMetrics[$actorId];
    $metrics['total_executions']++;
    $metrics['total_execution_time'] += $executionTime;
    $metrics['total_quality_score'] += $qualityScore;
    
    if ($status === 'success') {
      $metrics['successful_executions']++;
    } else {
      $metrics['failed_executions']++;
    }
    
    // Track by action type
    if (!isset($metrics['by_action_type'][$actionType])) {
      $metrics['by_action_type'][$actionType] = [
        'count' => 0,
        'success_count' => 0,
        'total_time' => 0.0,
        'total_quality' => 0.0
      ];
    }
    
    $typeMetrics = &$metrics['by_action_type'][$actionType];
    $typeMetrics['count']++;
    $typeMetrics['total_time'] += $executionTime;
    $typeMetrics['total_quality'] += $qualityScore;
    
    if ($status === 'success') {
      $typeMetrics['success_count']++;
    }
  }
  
  /**
   * Gets actor performance metrics
   * 
   * @param string $actorId Actor identifier
   * @param int $days Number of days to analyze (default: 30)
   * @return array Performance metrics
   */
  public function getActorMetrics(string $actorId, int $days = 30): array
  {
    try {
      $sql = "
        SELECT 
          COUNT(*) as total_executions,
          SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_executions,
          SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_executions,
          AVG(execution_time_ms) as avg_execution_time_ms,
          AVG(quality_score) as avg_quality_score,
          MIN(execution_time_ms) as min_execution_time_ms,
          MAX(execution_time_ms) as max_execution_time_ms,
          MIN(quality_score) as min_quality_score,
          MAX(quality_score) as max_quality_score
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE actor_id = ?
          AND executed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
      ";
      
      $result = DoctrineOrm::query($sql, [$actorId, $days])->fetch();
      
      if (!$result || $result['total_executions'] == 0) {
        return $this->getEmptyMetrics();
      }
      
      $successRate = $result['successful_executions'] / $result['total_executions'];
      
      return [
        'actor_id' => $actorId,
        'period_days' => $days,
        'total_executions' => (int)$result['total_executions'],
        'successful_executions' => (int)$result['successful_executions'],
        'failed_executions' => (int)$result['failed_executions'],
        'success_rate' => round($successRate * 100, 2),
        'avg_execution_time_ms' => round($result['avg_execution_time_ms'], 2),
        'min_execution_time_ms' => round($result['min_execution_time_ms'], 2),
        'max_execution_time_ms' => round($result['max_execution_time_ms'], 2),
        'avg_quality_score' => round($result['avg_quality_score'], 4),
        'min_quality_score' => round($result['min_quality_score'], 4),
        'max_quality_score' => round($result['max_quality_score'], 4),
        'performance_score' => $this->calculatePerformanceScore($successRate, $result['avg_quality_score']),
        'by_action_type' => $this->getMetricsByActionType($actorId, $days)
      ];
      
    } catch (\Exception $e) {
      error_log("ActorMetricsCollector: Error getting metrics: " . $e->getMessage());
      return $this->getEmptyMetrics();
    }
  }
  
  /**
   * Gets metrics broken down by action type
   * 
   * @param string $actorId Actor identifier
   * @param int $days Number of days
   * @return array Metrics by action type
   */
  private function getMetricsByActionType(string $actorId, int $days): array
  {
    try {
      $sql = "
        SELECT 
          action_type,
          COUNT(*) as count,
          SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
          AVG(execution_time_ms) as avg_time_ms,
          AVG(quality_score) as avg_quality
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE actor_id = ?
          AND executed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY action_type
        ORDER BY count DESC
      ";
      
      $results = DoctrineOrm::query($sql, [$actorId, $days])->fetchAll();
      
      $byType = [];
      foreach ($results as $row) {
        $successRate = $row['count'] > 0 ? $row['success_count'] / $row['count'] : 0;
        
        $byType[$row['action_type']] = [
          'count' => (int)$row['count'],
          'success_count' => (int)$row['success_count'],
          'success_rate' => round($successRate * 100, 2),
          'avg_execution_time_ms' => round($row['avg_time_ms'], 2),
          'avg_quality_score' => round($row['avg_quality'], 4)
        ];
      }
      
      return $byType;
      
    } catch (\Exception $e) {
      error_log("ActorMetricsCollector: Error getting metrics by action type: " . $e->getMessage());
      return [];
    }
  }
  
  /**
   * Calculates overall performance score
   * 
   * @param float $successRate Success rate (0.0-1.0)
   * @param float $qualityScore Average quality score (0.0-1.0)
   * @return float Performance score (0.0-1.0)
   */
  private function calculatePerformanceScore(float $successRate, float $qualityScore): float
  {
    // Weighted combination: success rate (60%) + quality (40%)
    return round(($successRate * 0.6) + ($qualityScore * 0.4), 4);
  }
  
  /**
   * Gets all actors metrics summary
   * 
   * @param int $days Number of days to analyze
   * @return array All actors metrics
   */
  public function getAllActorsMetrics(int $days = 30): array
  {
    try {
      $sql = "
        SELECT DISTINCT actor_id
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE executed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
      ";
      
      $actors = DoctrineOrm::query($sql, [$days])->fetchAll();
      
      $allMetrics = [];
      foreach ($actors as $actor) {
        $actorId = $actor['actor_id'];
        $allMetrics[$actorId] = $this->getActorMetrics($actorId, $days);
      }
      
      return $allMetrics;
      
    } catch (\Exception $e) {
      error_log("ActorMetricsCollector: Error getting all actors metrics: " . $e->getMessage());
      return [];
    }
  }
  
  /**
   * Gets actor utilization (percentage of time executing)
   * 
   * @param string $actorId Actor identifier
   * @param int $hours Number of hours to analyze
   * @return float Utilization percentage (0.0-100.0)
   */
  public function getActorUtilization(string $actorId, int $hours = 24): float
  {
    try {
      $sql = "
        SELECT SUM(execution_time_ms) as total_time_ms
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE actor_id = ?
          AND executed_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
      ";
      
      $result = DoctrineOrm::query($sql, [$actorId, $hours])->fetch();
      
      if (!$result || !$result['total_time_ms']) {
        return 0.0;
      }
      
      $totalTimeMs = $result['total_time_ms'];
      $periodMs = $hours * 3600 * 1000; // Convert hours to milliseconds
      
      $utilization = ($totalTimeMs / $periodMs) * 100;
      
      return round(min(100.0, $utilization), 2);
      
    } catch (\Exception $e) {
      error_log("ActorMetricsCollector: Error getting utilization: " . $e->getMessage());
      return 0.0;
    }
  }
  
  /**
   * Gets performance degradation alerts
   * 
   * @param array $thresholds Alert thresholds
   * @return array Alerts
   */
  public function getPerformanceAlerts(array $thresholds = []): array
  {
    $defaultThresholds = [
      'min_success_rate' => 0.8,      // 80%
      'max_avg_time_ms' => 5000,      // 5 seconds
      'min_quality_score' => 0.7,     // 70%
      'max_utilization' => 90.0       // 90%
    ];
    
    $thresholds = array_merge($defaultThresholds, $thresholds);
    $alerts = [];
    
    $allMetrics = $this->getAllActorsMetrics(7); // Last 7 days
    
    foreach ($allMetrics as $actorId => $metrics) {
      // Check success rate
      if ($metrics['success_rate'] < ($thresholds['min_success_rate'] * 100)) {
        $alerts[] = [
          'actor_id' => $actorId,
          'type' => 'low_success_rate',
          'severity' => 'warning',
          'message' => "Actor {$actorId} success rate below threshold",
          'current_value' => $metrics['success_rate'],
          'threshold' => $thresholds['min_success_rate'] * 100,
          'details' => $metrics
        ];
      }
      
      // Check execution time
      if ($metrics['avg_execution_time_ms'] > $thresholds['max_avg_time_ms']) {
        $alerts[] = [
          'actor_id' => $actorId,
          'type' => 'slow_execution',
          'severity' => 'warning',
          'message' => "Actor {$actorId} execution time exceeds threshold",
          'current_value' => $metrics['avg_execution_time_ms'],
          'threshold' => $thresholds['max_avg_time_ms'],
          'details' => $metrics
        ];
      }
      
      // Check quality score
      if ($metrics['avg_quality_score'] < $thresholds['min_quality_score']) {
        $alerts[] = [
          'actor_id' => $actorId,
          'type' => 'low_quality',
          'severity' => 'error',
          'message' => "Actor {$actorId} quality score below threshold",
          'current_value' => $metrics['avg_quality_score'],
          'threshold' => $thresholds['min_quality_score'],
          'details' => $metrics
        ];
      }
      
      // Check utilization
      $utilization = $this->getActorUtilization($actorId, 24);
      if ($utilization > $thresholds['max_utilization']) {
        $alerts[] = [
          'actor_id' => $actorId,
          'type' => 'high_utilization',
          'severity' => 'warning',
          'message' => "Actor {$actorId} utilization exceeds threshold",
          'current_value' => $utilization,
          'threshold' => $thresholds['max_utilization'],
          'details' => $metrics
        ];
      }
    }
    
    return $alerts;
  }
  
  /**
   * Gets empty metrics structure
   * 
   * @return array Empty metrics
   */
  private function getEmptyMetrics(): array
  {
    return [
      'total_executions' => 0,
      'successful_executions' => 0,
      'failed_executions' => 0,
      'success_rate' => 0.0,
      'avg_execution_time_ms' => 0.0,
      'avg_quality_score' => 0.0,
      'performance_score' => 0.0,
      'by_action_type' => []
    ];
  }
  
  /**
   * Checks if buffer should be flushed
   */
  private function checkBuffer(): void
  {
    if (count($this->metricsBuffer) >= $this->bufferSize) {
      $this->flush();
    }
  }
  
  /**
   * Flushes metrics buffer
   */
  public function flush(): void
  {
    if (empty($this->metricsBuffer)) {
      return;
    }
    
    if ($this->debug) {
      error_log("[ActorMetrics] Flushing " . count($this->metricsBuffer) . " metrics");
    }
    
    $this->metricsBuffer = [];
    $this->saveMetricsCache();
  }
  
  /**
   * Loads metrics cache
   */
  private function loadMetricsCache(): void
  {
    // Implementation for loading cached metrics if needed
  }
  
  /**
   * Saves metrics cache
   */
  private function saveMetricsCache(): void
  {
    // Implementation for saving cached metrics if needed
  }
  
  /**
   * Destructor - flush buffer
   */
  public function __destruct()
  {
    $this->flush();
  }
}
