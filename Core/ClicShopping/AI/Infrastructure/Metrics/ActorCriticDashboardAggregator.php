<?php
/**
 * ActorCriticDashboardAggregator
 * 
 * Aggregates actor-critic metrics for dashboard display.
 * Provides comprehensive statistics, trends, and visualizations.
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
 * ActorCriticDashboardAggregator Class
 * 
 * Aggregates and formats metrics for dashboard display including:
 * - Actor and critic registration counts
 * - Utilization statistics
 * - Performance trends
 * - Coordination metrics
 * - Alert summaries
 */
class ActorCriticDashboardAggregator
{
  private ActorMetricsCollector $actorMetrics;
  private CriticMetricsCollector $criticMetrics;
  private $db;
  private string $prefix;
  
  public function __construct()
  {
    $this->actorMetrics = new ActorMetricsCollector();
    $this->criticMetrics = new CriticMetricsCollector();
    $this->db = Registry::get('Db');
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
  }
  
  /**
   * Exports dashboard data as JSON
   *
   * @param int $days Number of days
   * @return string JSON formatted data
   */
  public function exportJSON(int $days = 30): string
  {
    $data = $this->getDashboardData($days);
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }
  
  /**
   * Gets complete dashboard data
   *
   * @param int $days Number of days for metrics (default: 30)
   * @return array Complete dashboard data
   */
  public function getDashboardData(int $days = 30): array
  {
    return [
      'summary' => $this->getSummary(),
      'actor_metrics' => $this->getActorMetricsSummary($days),
      'critic_metrics' => $this->getCriticMetricsSummary($days),
      'coordination_metrics' => $this->getCoordinationMetrics($days),
      'utilization' => $this->getUtilizationMetrics(),
      'alerts' => $this->getAlertsSummary(),
      'trends' => $this->getTrends($days),
      'top_performers' => $this->getTopPerformers($days),
      'generated_at' => date('Y-m-d H:i:s')
    ];
  }
  
  /**
   * Gets summary statistics
   *
   * @return array Summary statistics
   */
  public function getSummary(): array
  {
    try {
      // Count registered actors
      $actorCount = DoctrineOrm::query("
        SELECT COUNT(DISTINCT actor_id) as count
        FROM {$this->prefix}_rag_agent_actor_registry
      ")->fetch()['count'] ?? 0;

      // Count registered critics
      $criticCount = DoctrineOrm::query("
        SELECT COUNT(DISTINCT critic_id) as count
        FROM {$this->prefix}_rag_agent_critic_registry
      ")->fetch()['count'] ?? 0;

      // Calculate separation ratio
      $separationRatio = ($actorCount + $criticCount) > 0
        ? round($actorCount / ($actorCount + $criticCount), 2)
        : 0.5;

      return [
        'total_actors' => (int)$actorCount,
        'total_critics' => (int)$criticCount,
        'separation_ratio' => $separationRatio,
        'total_agents' => (int)($actorCount + $criticCount)
      ];

    } catch (\Exception $e) {
      error_log("ActorCriticDashboardAggregator: Error getting summary: " . $e->getMessage());
      return [
        'total_actors' => 0,
        'total_critics' => 0,
        'separation_ratio' => 0.5,
        'total_agents' => 0
      ];
    }
  }
  
  /**
   * Gets actor metrics summary
   *
   * @param int $days Number of days
   * @return array Actor metrics summary
   */
  public function getActorMetricsSummary(int $days = 30): array
  {
    $allMetrics = $this->actorMetrics->getAllActorsMetrics($days);

    if (empty($allMetrics)) {
      return [
        'total_executions' => 0,
        'avg_success_rate' => 0.0,
        'avg_execution_time_ms' => 0.0,
        'avg_quality_score' => 0.0,
        'by_actor' => []
      ];
    }

    $totalExecutions = 0;
    $totalSuccessRate = 0.0;
    $totalExecutionTime = 0.0;
    $totalQualityScore = 0.0;
    $actorCount = count($allMetrics);

    foreach ($allMetrics as $metrics) {
      $totalExecutions += $metrics['total_executions'];
      $totalSuccessRate += $metrics['success_rate'];
      $totalExecutionTime += $metrics['avg_execution_time_ms'];
      $totalQualityScore += $metrics['avg_quality_score'];
    }

    return [
      'total_executions' => $totalExecutions,
      'avg_success_rate' => round($totalSuccessRate / $actorCount, 2),
      'avg_execution_time_ms' => round($totalExecutionTime / $actorCount, 2),
      'avg_quality_score' => round($totalQualityScore / $actorCount, 4),
      'by_actor' => $allMetrics
    ];
  }
  
  /**
   * Gets critic metrics summary
   *
   * @param int $days Number of days
   * @return array Critic metrics summary
   */
  public function getCriticMetricsSummary(int $days = 30): array
  {
    $allMetrics = $this->criticMetrics->getAllCriticsMetrics($days);

    if (empty($allMetrics)) {
      return [
        'total_evaluations' => 0,
        'avg_evaluation_time_ms' => 0.0,
        'avg_agreement' => 0.0,
        'avg_consistency' => 0.0,
        'by_critic' => []
      ];
    }

    $totalEvaluations = 0;
    $totalEvaluationTime = 0.0;
    $totalAgreement = 0.0;
    $totalConsistency = 0.0;
    $criticCount = count($allMetrics);

    foreach ($allMetrics as $metrics) {
      $totalEvaluations += $metrics['total_evaluations'];
      $totalEvaluationTime += $metrics['avg_evaluation_time_ms'];
      $totalAgreement += $metrics['avg_agreement'];
      $totalConsistency += $metrics['agreement_consistency'];
    }

    return [
      'total_evaluations' => $totalEvaluations,
      'avg_evaluation_time_ms' => round($totalEvaluationTime / $criticCount, 2),
      'avg_agreement' => round($totalAgreement / $criticCount, 4),
      'avg_consistency' => round($totalConsistency / $criticCount, 4),
      'by_critic' => $allMetrics
    ];
  }
  
  /**
   * Gets coordination metrics
   *
   * @param int $days Number of days
   * @return array Coordination metrics
   */
  public function getCoordinationMetrics(int $days = 30): array
  {
    try {
      $sql = "
        SELECT 
          COUNT(*) as total_coordinations,
          AVG(JSON_EXTRACT(metadata, '$.execution_time')) as avg_execution_time,
          AVG(JSON_EXTRACT(metadata, '$.evaluation_time')) as avg_evaluation_time,
          AVG(JSON_EXTRACT(metadata, '$.total_time')) as avg_total_time
        FROM {$this->prefix}_rag_agent_coordinated_results
        WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
      ";

      $result = DoctrineOrm::query($sql, [$days])->fetch();

      if (!$result || $result['total_coordinations'] == 0) {
        return [
          'total_coordinations' => 0,
          'avg_execution_time_ms' => 0.0,
          'avg_evaluation_time_ms' => 0.0,
          'avg_total_time_ms' => 0.0
        ];
      }

      return [
        'total_coordinations' => (int)$result['total_coordinations'],
        'avg_execution_time_ms' => round($result['avg_execution_time'] ?? 0, 2),
        'avg_evaluation_time_ms' => round($result['avg_evaluation_time'] ?? 0, 2),
        'avg_total_time_ms' => round($result['avg_total_time'] ?? 0, 2)
      ];

    } catch (\Exception $e) {
      error_log("ActorCriticDashboardAggregator: Error getting coordination metrics: " . $e->getMessage());
      return [
        'total_coordinations' => 0,
        'avg_execution_time_ms' => 0.0,
        'avg_evaluation_time_ms' => 0.0,
        'avg_total_time_ms' => 0.0
      ];
    }
  }
  
  /**
   * Gets utilization metrics
   *
   * @return array Utilization metrics
   */
  public function getUtilizationMetrics(): array
  {
    try {
      // Get all actors
      $actors = DoctrineOrm::query("
        SELECT DISTINCT actor_id
        FROM {$this->prefix}_rag_agent_actor_registry
      ")->fetchAll();

      $actorUtilizations = [];
      $totalActorUtilization = 0.0;

      foreach ($actors as $actor) {
        $utilization = $this->actorMetrics->getActorUtilization($actor['actor_id'], 24);
        $actorUtilizations[$actor['actor_id']] = $utilization;
        $totalActorUtilization += $utilization;
      }

      $avgActorUtilization = count($actors) > 0
        ? $totalActorUtilization / count($actors)
        : 0.0;

      // Get all critics
      $critics = DoctrineOrm::query("
        SELECT DISTINCT critic_id
        FROM {$this->prefix}_rag_agent_critic_registry
      ")->fetchAll();

      $criticUtilizations = [];
      $totalCriticUtilization = 0.0;

      foreach ($critics as $critic) {
        $utilization = $this->criticMetrics->getCriticUtilization($critic['critic_id'], 24);
        $criticUtilizations[$critic['critic_id']] = $utilization;
        $totalCriticUtilization += $utilization;
      }

      $avgCriticUtilization = count($critics) > 0
        ? $totalCriticUtilization / count($critics)
        : 0.0;

      return [
        'actor_utilization' => round($avgActorUtilization, 2),
        'critic_utilization' => round($avgCriticUtilization, 2),
        'by_actor' => $actorUtilizations,
        'by_critic' => $criticUtilizations
      ];

    } catch (\Exception $e) {
      error_log("ActorCriticDashboardAggregator: Error getting utilization: " . $e->getMessage());
      return [
        'actor_utilization' => 0.0,
        'critic_utilization' => 0.0,
        'by_actor' => [],
        'by_critic' => []
      ];
    }
  }
  
  /**
   * Gets alerts summary
   *
   * @return array Alerts summary
   */
  public function getAlertsSummary(): array
  {
    $actorAlerts = $this->actorMetrics->getPerformanceAlerts();
    $criticAlerts = $this->criticMetrics->getPerformanceAlerts();

    $allAlerts = array_merge($actorAlerts, $criticAlerts);

    // Count by severity
    $bySeverity = [
      'critical' => 0,
      'error' => 0,
      'warning' => 0,
      'info' => 0
    ];

    foreach ($allAlerts as $alert) {
      $severity = $alert['severity'] ?? 'info';
      if (isset($bySeverity[$severity])) {
        $bySeverity[$severity]++;
      }
    }

    return [
      'total_alerts' => count($allAlerts),
      'by_severity' => $bySeverity,
      'actor_alerts' => $actorAlerts,
      'critic_alerts' => $criticAlerts
    ];
  }
  
  /**
   * Gets performance trends
   *
   * @param int $days Number of days
   * @return array Performance trends
   */
  public function getTrends(int $days = 30): array
  {
    try {
      // Actor execution trends
      $actorTrends = DoctrineOrm::query("
        SELECT 
          DATE(executed_at) as date,
          COUNT(*) as executions,
          AVG(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_rate,
          AVG(execution_time_ms) as avg_time_ms,
          AVG(quality_score) as avg_quality
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE executed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(executed_at)
        ORDER BY date ASC
      ", [$days])->fetchAll();

      // Critic evaluation trends
      $criticTrends = DoctrineOrm::query("
        SELECT 
          DATE(evaluated_at) as date,
          COUNT(*) as evaluations,
          AVG(evaluation_time_ms) as avg_time_ms,
          AVG(agreement_score) as avg_agreement
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE evaluated_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(evaluated_at)
        ORDER BY date ASC
      ", [$days])->fetchAll();

      return [
        'actor_trends' => $actorTrends,
        'critic_trends' => $criticTrends
      ];

    } catch (\Exception $e) {
      error_log("ActorCriticDashboardAggregator: Error getting trends: " . $e->getMessage());
      return [
        'actor_trends' => [],
        'critic_trends' => []
      ];
    }
  }
  
  /**
   * Gets top performing actors and critics
   *
   * @param int $days Number of days
   * @return array Top performers
   */
  public function getTopPerformers(int $days = 30): array
  {
    $actorMetrics = $this->actorMetrics->getAllActorsMetrics($days);
    $criticMetrics = $this->criticMetrics->getAllCriticsMetrics($days);

    // Sort actors by performance score
    uasort($actorMetrics, fn($a, $b) => $b['performance_score'] <=> $a['performance_score']);
    $topActors = array_slice($actorMetrics, 0, 5, true);

    // Sort critics by performance score
    uasort($criticMetrics, fn($a, $b) => $b['performance_score'] <=> $a['performance_score']);
    $topCritics = array_slice($criticMetrics, 0, 5, true);

    return [
      'top_actors' => $topActors,
      'top_critics' => $topCritics
    ];
  }
  
  /**
   * Exports metrics in Prometheus format
   * 
   * @return string Prometheus formatted metrics
   */
  public function exportPrometheus(): string
  {
    $output = [];
    
    // Summary metrics
    $summary = $this->getSummary();
    $output[] = "# HELP actor_critic_total_actors Total number of registered actors";
    $output[] = "# TYPE actor_critic_total_actors gauge";
    $output[] = "actor_critic_total_actors {$summary['total_actors']}";
    
    $output[] = "# HELP actor_critic_total_critics Total number of registered critics";
    $output[] = "# TYPE actor_critic_total_critics gauge";
    $output[] = "actor_critic_total_critics {$summary['total_critics']}";
    
    // Actor metrics
    $actorSummary = $this->getActorMetricsSummary(7);
    $output[] = "# HELP actor_total_executions Total actor executions";
    $output[] = "# TYPE actor_total_executions counter";
    $output[] = "actor_total_executions {$actorSummary['total_executions']}";
    
    $output[] = "# HELP actor_avg_success_rate Average actor success rate";
    $output[] = "# TYPE actor_avg_success_rate gauge";
    $output[] = "actor_avg_success_rate " . ($actorSummary['avg_success_rate'] / 100);
    
    // Critic metrics
    $criticSummary = $this->getCriticMetricsSummary(7);
    $output[] = "# HELP critic_total_evaluations Total critic evaluations";
    $output[] = "# TYPE critic_total_evaluations counter";
    $output[] = "critic_total_evaluations {$criticSummary['total_evaluations']}";
    
    $output[] = "# HELP critic_avg_agreement Average critic agreement with consensus";
    $output[] = "# TYPE critic_avg_agreement gauge";
    $output[] = "critic_avg_agreement {$criticSummary['avg_agreement']}";
    
    // Utilization
    $utilization = $this->getUtilizationMetrics();
    $output[] = "# HELP actor_utilization Actor utilization percentage";
    $output[] = "# TYPE actor_utilization gauge";
    $output[] = "actor_utilization " . ($utilization['actor_utilization'] / 100);
    
    $output[] = "# HELP critic_utilization Critic utilization percentage";
    $output[] = "# TYPE critic_utilization gauge";
    $output[] = "critic_utilization " . ($utilization['critic_utilization'] / 100);
    
    return implode("\n", $output) . "\n";
  }
}
