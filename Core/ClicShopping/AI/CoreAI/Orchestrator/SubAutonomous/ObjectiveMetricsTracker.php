<?php
/**
 * ObjectiveMetricsTracker Class
 *
 * Tracks and calculates aggregate metrics for agent objectives including
 * success rates, completion times, and performance impact measurements.
 *
 * Implements Requirement 9.1: Objective Metrics Tracking
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use DateTime;
use Exception;

class ObjectiveMetricsTracker
{
  private $db;
  private ObjectiveRegistry $registry;

  /**
   * Constructor
   *
   * @param ObjectiveRegistry $registry The objective registry instance
   */
  public function __construct(ObjectiveRegistry $registry)
  {
    $this->db = Registry::get('Db');
    $this->registry = $registry;
  }

  /**
   * Calculate success rate for objectives
   *
   * Calculates the percentage of objectives that completed successfully
   * over a given time period.
   *
   * @param DateTime|null $startDate Start of time period (null for all time)
   * @param DateTime|null $endDate End of time period (null for now)
   * @param string|null $agentId Optional agent ID to filter by
   * @return array Success rate metrics:
   *               - total: Total number of objectives
   *               - completed: Number of completed objectives
   *               - failed: Number of failed objectives
   *               - cancelled: Number of cancelled objectives
   *               - success_rate: Percentage of successful completions (0-100)
   */
  public function calculateSuccessRate(
    ?DateTime $startDate = null,
    ?DateTime $endDate = null,
    ?string $agentId = null
  ): array {
    try {
      $conditions = [];
      $params = [];

      // Add time range filters
      if ($startDate) {
        $conditions[] = 'created_at >= :start_date';
        $params[':start_date'] = $startDate->format('Y-m-d H:i:s');
      }

      if ($endDate) {
        $conditions[] = 'created_at <= :end_date';
        $params[':end_date'] = $endDate->format('Y-m-d H:i:s');
      }

      // Add agent filter
      if ($agentId) {
        $conditions[] = 'agent_id = :agent_id';
        $params[':agent_id'] = $agentId;
      }

      // Only count objectives that have reached a terminal state
      $conditions[] = "status IN ('completed', 'failed', 'cancelled')";

      $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

      $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
              FROM :table_rag_agent_objectives 
              {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $row = $stmt->fetch();

      $total = (int)$row['total'];
      $completed = (int)$row['completed'];
      $failed = (int)$row['failed'];
      $cancelled = (int)$row['cancelled'];

      $successRate = $total > 0 ? ($completed / $total) * 100 : 0;

      return [
        'total' => $total,
        'completed' => $completed,
        'failed' => $failed,
        'cancelled' => $cancelled,
        'success_rate' => round($successRate, 2),
        'failure_rate' => round($total > 0 ? ($failed / $total) * 100 : 0, 2),
        'cancellation_rate' => round($total > 0 ? ($cancelled / $total) * 100 : 0, 2)
      ];
    } catch (Exception $e) {
      return [
        'total' => 0,
        'completed' => 0,
        'failed' => 0,
        'cancelled' => 0,
        'success_rate' => 0,
        'failure_rate' => 0,
        'cancellation_rate' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Calculate average completion time
   *
   * Calculates the average time taken to complete objectives,
   * measured from creation to completion.
   *
   * @param DateTime|null $startDate Start of time period (null for all time)
   * @param DateTime|null $endDate End of time period (null for now)
   * @param string|null $agentId Optional agent ID to filter by
   * @return array Completion time metrics:
   *               - count: Number of completed objectives
   *               - average_seconds: Average completion time in seconds
   *               - average_hours: Average completion time in hours
   *               - min_seconds: Fastest completion time
   *               - max_seconds: Slowest completion time
   *               - median_seconds: Median completion time
   */
  public function calculateAverageCompletionTime(
    ?DateTime $startDate = null,
    ?DateTime $endDate = null,
    ?string $agentId = null
  ): array {
    try {
      $conditions = ['status = \'completed\'', 'completed_at IS NOT NULL'];
      $params = [];

      // Add time range filters
      if ($startDate) {
        $conditions[] = 'created_at >= :start_date';
        $params[':start_date'] = $startDate->format('Y-m-d H:i:s');
      }

      if ($endDate) {
        $conditions[] = 'created_at <= :end_date';
        $params[':end_date'] = $endDate->format('Y-m-d H:i:s');
      }

      // Add agent filter
      if ($agentId) {
        $conditions[] = 'agent_id = :agent_id';
        $params[':agent_id'] = $agentId;
      }

      $whereClause = 'WHERE ' . implode(' AND ', $conditions);

      $sql = "SELECT 
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_seconds,
                MIN(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as min_seconds,
                MAX(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as max_seconds
              FROM :table_rag_agent_objectives 
              {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $row = $stmt->fetch();

      $count = (int)$row['count'];
      $avgSeconds = $row['avg_seconds'] ? (float)$row['avg_seconds'] : 0;
      $minSeconds = $row['min_seconds'] ? (int)$row['min_seconds'] : 0;
      $maxSeconds = $row['max_seconds'] ? (int)$row['max_seconds'] : 0;

      // Calculate median separately
      $medianSeconds = $this->calculateMedianCompletionTime($startDate, $endDate, $agentId);

      return [
        'count' => $count,
        'average_seconds' => round($avgSeconds, 2),
        'average_hours' => round($avgSeconds / 3600, 2),
        'average_days' => round($avgSeconds / 86400, 2),
        'min_seconds' => $minSeconds,
        'min_hours' => round($minSeconds / 3600, 2),
        'max_seconds' => $maxSeconds,
        'max_hours' => round($maxSeconds / 3600, 2),
        'median_seconds' => $medianSeconds,
        'median_hours' => round($medianSeconds / 3600, 2)
      ];
    } catch (Exception $e) {
      return [
        'count' => 0,
        'average_seconds' => 0,
        'average_hours' => 0,
        'average_days' => 0,
        'min_seconds' => 0,
        'min_hours' => 0,
        'max_seconds' => 0,
        'max_hours' => 0,
        'median_seconds' => 0,
        'median_hours' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Calculate median completion time
   *
   * @param DateTime|null $startDate Start of time period
   * @param DateTime|null $endDate End of time period
   * @param string|null $agentId Optional agent ID to filter by
   * @return float Median completion time in seconds
   */
  private function calculateMedianCompletionTime(
    ?DateTime $startDate = null,
    ?DateTime $endDate = null,
    ?string $agentId = null
  ): float {
    try {
      $conditions = ['status = \'completed\'', 'completed_at IS NOT NULL'];
      $params = [];

      if ($startDate) {
        $conditions[] = 'created_at >= :start_date';
        $params[':start_date'] = $startDate->format('Y-m-d H:i:s');
      }

      if ($endDate) {
        $conditions[] = 'created_at <= :end_date';
        $params[':end_date'] = $endDate->format('Y-m-d H:i:s');
      }

      if ($agentId) {
        $conditions[] = 'agent_id = :agent_id';
        $params[':agent_id'] = $agentId;
      }

      $whereClause = 'WHERE ' . implode(' AND ', $conditions);

      $sql = "SELECT TIMESTAMPDIFF(SECOND, created_at, completed_at) as duration
              FROM :table_rag_agent_objectives 
              {$whereClause}
              ORDER BY duration";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $durations = [];
      while ($row = $stmt->fetch()) {
        $durations[] = (float)$row['duration'];
      }

      if (empty($durations)) {
        return 0;
      }

      $count = count($durations);
      $middle = floor($count / 2);

      if ($count % 2 === 0) {
        return ($durations[$middle - 1] + $durations[$middle]) / 2;
      } else {
        return $durations[$middle];
      }
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * Measure performance impact
   *
   * Analyzes the performance impact of objectives by examining metrics
   * recorded during objective execution. Aggregates custom metrics
   * stored in the rag_agent_objective_metrics table.
   *
   * @param DateTime|null $startDate Start of time period (null for all time)
   * @param DateTime|null $endDate End of time period (null for now)
   * @param string|null $agentId Optional agent ID to filter by
   * @return array Performance impact metrics grouped by metric name:
   *               - metric_name: Name of the metric
   *               - count: Number of measurements
   *               - average: Average value
   *               - min: Minimum value
   *               - max: Maximum value
   *               - total: Sum of all values
   */
  public function measurePerformanceImpact(
    ?DateTime $startDate = null,
    ?DateTime $endDate = null,
    ?string $agentId = null
  ): array {
    try {
      $conditions = [];
      $params = [];

      // Build conditions for objectives table join
      if ($startDate) {
        $conditions[] = 'o.created_at >= :start_date';
        $params[':start_date'] = $startDate->format('Y-m-d H:i:s');
      }

      if ($endDate) {
        $conditions[] = 'o.created_at <= :end_date';
        $params[':end_date'] = $endDate->format('Y-m-d H:i:s');
      }

      if ($agentId) {
        $conditions[] = 'o.agent_id = :agent_id';
        $params[':agent_id'] = $agentId;
      }

      $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

      $sql = "SELECT 
                m.metric_name,
                COUNT(*) as count,
                AVG(m.metric_value) as average,
                MIN(m.metric_value) as min,
                MAX(m.metric_value) as max,
                SUM(m.metric_value) as total,
                STDDEV(m.metric_value) as stddev
              FROM :table_rag_agent_objective_metrics m
              INNER JOIN :table_rag_agent_objectives o ON m.objective_id = o.objective_id
              {$whereClause}
              GROUP BY m.metric_name
              ORDER BY m.metric_name";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $metrics = [];
      while ($row = $stmt->fetch()) {
        $metrics[$row['metric_name']] = [
          'metric_name' => $row['metric_name'],
          'count' => (int)$row['count'],
          'average' => round((float)$row['average'], 4),
          'min' => round((float)$row['min'], 4),
          'max' => round((float)$row['max'], 4),
          'total' => round((float)$row['total'], 4),
          'stddev' => round((float)($row['stddev'] ?? 0), 4)
        ];
      }

      return $metrics;
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Get objectives by priority distribution
   *
   * Analyzes the distribution of objectives across priority levels.
   *
   * @param DateTime|null $startDate Start of time period
   * @param DateTime|null $endDate End of time period
   * @param string|null $agentId Optional agent ID to filter by
   * @return array Priority distribution with counts and percentages
   */
  public function getPriorityDistribution(
    ?DateTime $startDate = null,
    ?DateTime $endDate = null,
    ?string $agentId = null
  ): array {
    try {
      $conditions = [];
      $params = [];

      if ($startDate) {
        $conditions[] = 'created_at >= :start_date';
        $params[':start_date'] = $startDate->format('Y-m-d H:i:s');
      }

      if ($endDate) {
        $conditions[] = 'created_at <= :end_date';
        $params[':end_date'] = $endDate->format('Y-m-d H:i:s');
      }

      if ($agentId) {
        $conditions[] = 'agent_id = :agent_id';
        $params[':agent_id'] = $agentId;
      }

      $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

      $sql = "SELECT 
                priority,
                COUNT(*) as count
              FROM :table_rag_agent_objectives 
              {$whereClause}
              GROUP BY priority
              ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low')";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $distribution = [];
      $total = 0;

      while ($row = $stmt->fetch()) {
        $count = (int)$row['count'];
        $distribution[$row['priority']] = $count;
        $total += $count;
      }

      // Calculate percentages
      $result = [];
      foreach ($distribution as $priority => $count) {
        $result[$priority] = [
          'count' => $count,
          'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0
        ];
      }

      return $result;
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Get comprehensive objective metrics summary
   *
   * Combines all objective metrics into a single comprehensive report.
   *
   * @param DateTime|null $startDate Start of time period
   * @param DateTime|null $endDate End of time period
   * @param string|null $agentId Optional agent ID to filter by
   * @return array Comprehensive metrics summary
   */
  public function getObjectiveMetricsSummary(
    ?DateTime $startDate = null,
    ?DateTime $endDate = null,
    ?string $agentId = null
  ): array {
    return [
      'success_metrics' => $this->calculateSuccessRate($startDate, $endDate, $agentId),
      'completion_time_metrics' => $this->calculateAverageCompletionTime($startDate, $endDate, $agentId),
      'performance_impact' => $this->measurePerformanceImpact($startDate, $endDate, $agentId),
      'priority_distribution' => $this->getPriorityDistribution($startDate, $endDate, $agentId),
      'period' => [
        'start_date' => $startDate ? $startDate->format('Y-m-d H:i:s') : 'all time',
        'end_date' => $endDate ? $endDate->format('Y-m-d H:i:s') : 'now'
      ],
      'agent_id' => $agentId ?? 'all agents'
    ];
  }
}
