<?php
/**
 * CriticMetricsCollector
 * 
 * Collects and tracks performance metrics for Critic agents in the actor-critic separation system.
 * Monitors evaluation counts, agreement with consensus, feedback quality, and evaluation times.
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
 * CriticMetricsCollector Class
 * 
 * Tracks critic performance metrics including:
 * - Total evaluations performed
 * - Average evaluation time
 * - Agreement with consensus
 * - Feedback quality
 * - Current load
 * - Domain-specific performance
 */
class CriticMetricsCollector
{
  private $db;
  private string $prefix;
  private bool $debug;
  
  // Metrics buffer
  private array $metricsBuffer = [];
  private int $bufferSize = 10;
  
  // Real-time metrics cache
  private array $criticMetrics = [];
  
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    
    $this->loadMetricsCache();
  }
  
  /**
   * Loads metrics cache
   */
  private function loadMetricsCache(): void
  {
    // Implementation for loading cached metrics if needed
  }
  
  /**
   * Records a critic evaluation
   *
   * @param string $criticId Critic identifier
   * @param string $outputType Type of output evaluated
   * @param float $evaluationTime Evaluation time in seconds
   * @param float $overallScore Overall score given (0.0-1.0)
   * @param float $consensusScore Consensus score (0.0-1.0)
   * @param array $metadata Additional metadata
   * @return bool True if recorded successfully
   */
  public function recordEvaluation(
    string $criticId,
    string $outputType,
    float $evaluationTime,
    float $overallScore,
    float $consensusScore,
    array $metadata = []
  ): bool {
    try {
      // Calculate agreement (how close to consensus)
      $agreement = 1.0 - abs($overallScore - $consensusScore);

      // Insert into database
      DoctrineOrm::execute("
        INSERT INTO {$this->prefix}rag_agent_critic_evaluations
        (critic_id, output_type, evaluation_time_ms, overall_score, consensus_score, 
         agreement_score, metadata, evaluated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
      ", [
        $criticId,
        $outputType,
        (int)($evaluationTime * 1000), // Convert to milliseconds
        $overallScore,
        $consensusScore,
        $agreement,
        json_encode($metadata)
      ]);

      // Update real-time metrics
      $this->updateCriticMetrics($criticId, $outputType, $evaluationTime, $agreement);

      // Add to buffer
      $this->metricsBuffer[] = [
        'critic_id' => $criticId,
        'output_type' => $outputType,
        'evaluation_time' => $evaluationTime,
        'overall_score' => $overallScore,
        'consensus_score' => $consensusScore,
        'agreement' => $agreement,
        'timestamp' => time()
      ];

      $this->checkBuffer();

      if ($this->debug) {
        error_log(sprintf(
          "[CriticMetrics] Recorded evaluation: critic=%s, type=%s, time=%.2fs, score=%.2f, consensus=%.2f, agreement=%.2f",
          $criticId, $outputType, $evaluationTime, $overallScore, $consensusScore, $agreement
        ));
      }

      return true;

    } catch (\Exception $e) {
      error_log("CriticMetricsCollector: Error recording evaluation: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Updates real-time critic metrics
   *
   * @param string $criticId Critic identifier
   * @param string $outputType Output type
   * @param float $evaluationTime Evaluation time
   * @param float $agreement Agreement score
   */
  private function updateCriticMetrics(
    string $criticId,
    string $outputType,
    float $evaluationTime,
    float $agreement
  ): void {
    if (!isset($this->criticMetrics[$criticId])) {
      $this->criticMetrics[$criticId] = [
        'total_evaluations' => 0,
        'total_evaluation_time' => 0.0,
        'total_agreement' => 0.0,
        'by_output_type' => []
      ];
    }

    $metrics = &$this->criticMetrics[$criticId];
    $metrics['total_evaluations']++;
    $metrics['total_evaluation_time'] += $evaluationTime;
    $metrics['total_agreement'] += $agreement;

    // Track by output type
    if (!isset($metrics['by_output_type'][$outputType])) {
      $metrics['by_output_type'][$outputType] = [
        'count' => 0,
        'total_time' => 0.0,
        'total_agreement' => 0.0
      ];
    }

    $typeMetrics = &$metrics['by_output_type'][$outputType];
    $typeMetrics['count']++;
    $typeMetrics['total_time'] += $evaluationTime;
    $typeMetrics['total_agreement'] += $agreement;
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
      error_log("[CriticMetrics] Flushing " . count($this->metricsBuffer) . " metrics");
    }

    $this->metricsBuffer = [];
    $this->saveMetricsCache();
  }
  
  /**
   * Saves metrics cache
   */
  private function saveMetricsCache(): void
  {
    // Implementation for saving cached metrics if needed
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
      'min_agreement' => 0.7,         // 70%
      'max_avg_time_ms' => 3000,      // 3 seconds
      'max_utilization' => 90.0,      // 90%
      'min_consistency' => 0.8        // 80%
    ];

    $thresholds = array_merge($defaultThresholds, $thresholds);
    $alerts = [];

    $allMetrics = $this->getAllCriticsMetrics(7); // Last 7 days

    foreach ($allMetrics as $criticId => $metrics) {
      // Check agreement with consensus
      if ($metrics['avg_agreement'] < $thresholds['min_agreement']) {
        $alerts[] = [
          'critic_id' => $criticId,
          'type' => 'low_agreement',
          'severity' => 'error',
          'message' => "Critic {$criticId} agreement with consensus below threshold",
          'current_value' => $metrics['avg_agreement'],
          'threshold' => $thresholds['min_agreement'],
          'details' => $metrics
        ];
      }

      // Check evaluation time
      if ($metrics['avg_evaluation_time_ms'] > $thresholds['max_avg_time_ms']) {
        $alerts[] = [
          'critic_id' => $criticId,
          'type' => 'slow_evaluation',
          'severity' => 'warning',
          'message' => "Critic {$criticId} evaluation time exceeds threshold",
          'current_value' => $metrics['avg_evaluation_time_ms'],
          'threshold' => $thresholds['max_avg_time_ms'],
          'details' => $metrics
        ];
      }

      // Check consistency
      if ($metrics['agreement_consistency'] < $thresholds['min_consistency']) {
        $alerts[] = [
          'critic_id' => $criticId,
          'type' => 'inconsistent_evaluations',
          'severity' => 'warning',
          'message' => "Critic {$criticId} evaluation consistency below threshold",
          'current_value' => $metrics['agreement_consistency'],
          'threshold' => $thresholds['min_consistency'],
          'details' => $metrics
        ];
      }

      // Check utilization
      $utilization = $this->getCriticUtilization($criticId, 24);
      if ($utilization > $thresholds['max_utilization']) {
        $alerts[] = [
          'critic_id' => $criticId,
          'type' => 'high_utilization',
          'severity' => 'warning',
          'message' => "Critic {$criticId} utilization exceeds threshold",
          'current_value' => $utilization,
          'threshold' => $thresholds['max_utilization'],
          'details' => $metrics
        ];
      }
    }

    return $alerts;
  }
  
  /**
   * Gets all critics metrics summary
   *
   * @param int $days Number of days to analyze
   * @return array All critics metrics
   */
  public function getAllCriticsMetrics(int $days = 30): array
  {
    try {
      $sql = "
        SELECT DISTINCT critic_id
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE evaluated_at > DATE_SUB(NOW(), INTERVAL ? DAY)
      ";

      $critics = DoctrineOrm::select($sql, [$days]);

      $allMetrics = [];
      foreach ($critics as $critic) {
        $criticId = $critic['critic_id'];
        $allMetrics[$criticId] = $this->getCriticMetrics($criticId, $days);
      }

      return $allMetrics;

    } catch (\Exception $e) {
      error_log("CriticMetricsCollector: Error getting all critics metrics: " . $e->getMessage());
      return [];
    }
  }
  
  /**
   * Gets critic performance metrics
   *
   * @param string $criticId Critic identifier
   * @param int $days Number of days to analyze (default: 30)
   * @return array Performance metrics
   */
  public function getCriticMetrics(string $criticId, int $days = 30): array
  {
    try {
      $sql = "
        SELECT 
          COUNT(*) as total_evaluations,
          AVG(evaluation_time_ms) as avg_evaluation_time_ms,
          AVG(agreement_score) as avg_agreement,
          MIN(evaluation_time_ms) as min_evaluation_time_ms,
          MAX(evaluation_time_ms) as max_evaluation_time_ms,
          MIN(agreement_score) as min_agreement,
          MAX(agreement_score) as max_agreement,
          STDDEV(agreement_score) as stddev_agreement
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE critic_id = ?
          AND evaluated_at > DATE_SUB(NOW(), INTERVAL ? DAY)
      ";

      $result = DoctrineOrm::selectOne($sql, [$criticId, $days]);

      if (!$result || $result['total_evaluations'] == 0) {
        return $this->getEmptyMetrics();
      }

      return [
        'critic_id' => $criticId,
        'period_days' => $days,
        'total_evaluations' => (int)$result['total_evaluations'],
        'avg_evaluation_time_ms' => round($result['avg_evaluation_time_ms'], 2),
        'min_evaluation_time_ms' => round($result['min_evaluation_time_ms'], 2),
        'max_evaluation_time_ms' => round($result['max_evaluation_time_ms'], 2),
        'avg_agreement' => round($result['avg_agreement'], 4),
        'min_agreement' => round($result['min_agreement'], 4),
        'max_agreement' => round($result['max_agreement'], 4),
        'agreement_consistency' => round(1.0 - ($result['stddev_agreement'] ?? 0), 4),
        'performance_score' => $this->calculatePerformanceScore($result['avg_agreement']),
        'by_output_type' => $this->getMetricsByOutputType($criticId, $days)
      ];

    } catch (\Exception $e) {
      error_log("CriticMetricsCollector: Error getting metrics: " . $e->getMessage());
      return $this->getEmptyMetrics();
    }
  }
  
  /**
   * Gets empty metrics structure
   *
   * @return array Empty metrics
   */
  private function getEmptyMetrics(): array
  {
    return [
      'total_evaluations' => 0,
      'avg_evaluation_time_ms' => 0.0,
      'avg_agreement' => 0.0,
      'agreement_consistency' => 0.0,
      'performance_score' => 0.0,
      'by_output_type' => []
    ];
  }
  
  /**
   * Calculates overall performance score
   *
   * @param float $avgAgreement Average agreement with consensus (0.0-1.0)
   * @return float Performance score (0.0-1.0)
   */
  private function calculatePerformanceScore(float $avgAgreement): float
  {
    // Performance is primarily based on agreement with consensus
    return round($avgAgreement, 4);
  }
  
  /**
   * Gets metrics broken down by output type
   *
   * @param string $criticId Critic identifier
   * @param int $days Number of days
   * @return array Metrics by output type
   */
  private function getMetricsByOutputType(string $criticId, int $days): array
  {
    try {
      $sql = "
        SELECT 
          output_type,
          COUNT(*) as count,
          AVG(evaluation_time_ms) as avg_time_ms,
          AVG(agreement_score) as avg_agreement
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE critic_id = ?
          AND evaluated_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY output_type
        ORDER BY count DESC
      ";

      $results = DoctrineOrm::select($sql, [$criticId, $days]);

      $byType = [];
      foreach ($results as $row) {
        $byType[$row['output_type']] = [
          'count' => (int)$row['count'],
          'avg_evaluation_time_ms' => round($row['avg_time_ms'], 2),
          'avg_agreement' => round($row['avg_agreement'], 4)
        ];
      }

      return $byType;

    } catch (\Exception $e) {
      error_log("CriticMetricsCollector: Error getting metrics by output type: " . $e->getMessage());
      return [];
    }
  }
  
  /**
   * Gets critic utilization (percentage of time evaluating)
   *
   * @param string $criticId Critic identifier
   * @param int $hours Number of hours to analyze
   * @return float Utilization percentage (0.0-100.0)
   */
  public function getCriticUtilization(string $criticId, int $hours = 24): float
  {
    try {
      $sql = "
        SELECT SUM(evaluation_time_ms) as total_time_ms
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE critic_id = ?
          AND evaluated_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
      ";

      $result = DoctrineOrm::selectOne($sql, [$criticId, $hours]);

      if (!$result || !$result['total_time_ms']) {
        return 0.0;
      }

      $totalTimeMs = $result['total_time_ms'];
      $periodMs = $hours * 3600 * 1000; // Convert hours to milliseconds

      $utilization = ($totalTimeMs / $periodMs) * 100;

      return round(min(100.0, $utilization), 2);

    } catch (\Exception $e) {
      error_log("CriticMetricsCollector: Error getting utilization: " . $e->getMessage());
      return 0.0;
    }
  }
  
  /**
   * Destructor - flush buffer
   */
  public function __destruct()
  {
    $this->flush();
  }
}
