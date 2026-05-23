<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * Adaptive Weighting Metrics Provider
 * 
 * Provides metrics and data for actor-critic monitoring dashboard
 */
class AdaptiveWeightingMetricsProvider
{
  private string $prefix;

  public function __construct()
  {
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
  }

  /**
   * Get all actor-critic metrics
   * 
   * @param int $periodDays Period in days for metrics
   * @return array Complete metrics data
   */
  public function getAllMetrics(int $periodDays = 7): array
  {
    return [
      'registry_stats' => $this->getRegistryStats(),
      'actor_metrics' => $this->getActorMetrics($periodDays),
      'critic_metrics' => $this->getCriticMetrics($periodDays),
      'coordination_metrics' => $this->getCoordinationMetrics($periodDays),
      'utilization_metrics' => $this->getUtilizationMetrics($periodDays),
      'recent_coordinations' => $this->getRecentCoordinations(20)
    ];
  }

  /**
   * Get registry statistics (total actors and critics)
   * 
   * @return array Registry stats
   */
  public function getRegistryStats(): array
  {
    try {
      // Count total actors
      $actorResult = DoctrineOrm::select("
        SELECT COUNT(DISTINCT actor_id) as count 
        FROM {$this->prefix}rag_agent_actor_registry
      ");
      $totalActors = $actorResult[0]['count'] ?? 0;

      // Count total critics
      $criticResult = DoctrineOrm::select("
        SELECT COUNT(DISTINCT critic_id) as count 
        FROM {$this->prefix}rag_agent_critic_registry
      ");
      $totalCritics = $criticResult[0]['count'] ?? 0;

      // Calculate separation ratio
      $separationRatio = ($totalActors + $totalCritics) > 0 
        ? round($totalCritics / ($totalActors + $totalCritics) * 100, 1) 
        : 0;

      return [
        'total_actors' => $totalActors,
        'total_critics' => $totalCritics,
        'separation_ratio' => $separationRatio,
        'total_agents' => $totalActors + $totalCritics
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get registry stats - ' . $e->getMessage());
      return [
        'total_actors' => 0,
        'total_critics' => 0,
        'separation_ratio' => 0,
        'total_agents' => 0
      ];
    }
  }

  /**
   * Get actor performance metrics
   * 
   * @param int $periodDays Period in days
   * @return array Actor metrics
   */
  public function getActorMetrics(int $periodDays = 7): array
  {
    try {
      $dateFilter = "executed_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)";

      // Overall actor metrics
      $overallResult = DoctrineOrm::select("
        SELECT 
          COUNT(*) as total_executions,
          AVG(execution_time_ms) as avg_execution_time,
          AVG(quality_score) as avg_quality_score,
          SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_executions,
          SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_executions
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE {$dateFilter}
      ");

      $overall = $overallResult[0] ?? [];
      $totalExecutions = (int)($overall['total_executions'] ?? 0);
      $successRate = $totalExecutions > 0 
        ? round(($overall['successful_executions'] / $totalExecutions) * 100, 1) 
        : 0;

      // Per-actor metrics
      $actorResults = DoctrineOrm::select("
        SELECT 
          actor_id,
          COUNT(*) as executions,
          AVG(execution_time_ms) as avg_time,
          AVG(quality_score) as avg_quality,
          SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE {$dateFilter}
        GROUP BY actor_id
        ORDER BY executions DESC
        LIMIT 10
      ");

      $actorList = [];
      foreach ($actorResults as $row) {
        $executions = (int)$row['executions'];
        $actorList[] = [
          'actor_id' => $row['actor_id'],
          'executions' => $executions,
          'avg_execution_time' => round($row['avg_time'] ?? 0, 2),
          'avg_quality_score' => round($row['avg_quality'] ?? 0, 2),
          'success_rate' => $executions > 0 ? round(($row['success_count'] / $executions) * 100, 1) : 0
        ];
      }

      return [
        'total_executions' => $totalExecutions,
        'avg_execution_time' => round($overall['avg_execution_time'] ?? 0, 2),
        'avg_quality_score' => round($overall['avg_quality_score'] ?? 0, 2),
        'success_rate' => $successRate,
        'failed_executions' => (int)($overall['failed_executions'] ?? 0),
        'top_actors' => $actorList
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get actor metrics - ' . $e->getMessage());
      return [
        'total_executions' => 0,
        'avg_execution_time' => 0,
        'avg_quality_score' => 0,
        'success_rate' => 0,
        'failed_executions' => 0,
        'top_actors' => []
      ];
    }
  }

  /**
   * Get critic performance metrics
   * 
   * @param int $periodDays Period in days
   * @return array Critic metrics
   */
  public function getCriticMetrics(int $periodDays = 7): array
  {
    try {
      $dateFilter = "evaluated_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)";

      // Overall critic metrics
      $overallResult = DoctrineOrm::select("
        SELECT 
          COUNT(*) as total_evaluations,
          AVG(evaluation_time_ms) as avg_evaluation_time,
          AVG(overall_score) as avg_overall_score
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE {$dateFilter}
      ");

      $overall = $overallResult[0] ?? [];

      // Per-critic metrics
      $criticResults = DoctrineOrm::select("
        SELECT 
          critic_id,
          COUNT(*) as evaluations,
          AVG(evaluation_time_ms) as avg_time,
          AVG(overall_score) as avg_score,
          AVG(accuracy_score) as avg_accuracy,
          AVG(completeness_score) as avg_completeness
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE {$dateFilter}
        GROUP BY critic_id
        ORDER BY evaluations DESC
        LIMIT 10
      ");

      $criticList = [];
      foreach ($criticResults as $row) {
        $criticList[] = [
          'critic_id' => $row['critic_id'],
          'evaluations' => (int)$row['evaluations'],
          'avg_evaluation_time' => round($row['avg_time'] ?? 0, 2),
          'avg_overall_score' => round($row['avg_score'] ?? 0, 2),
          'avg_accuracy' => round($row['avg_accuracy'] ?? 0, 2),
          'avg_completeness' => round($row['avg_completeness'] ?? 0, 2)
        ];
      }

      return [
        'total_evaluations' => (int)($overall['total_evaluations'] ?? 0),
        'avg_evaluation_time' => round($overall['avg_evaluation_time'] ?? 0, 2),
        'avg_overall_score' => round($overall['avg_overall_score'] ?? 0, 2),
        'top_critics' => $criticList
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get critic metrics - ' . $e->getMessage());
      return [
        'total_evaluations' => 0,
        'avg_evaluation_time' => 0,
        'avg_overall_score' => 0,
        'top_critics' => []
      ];
    }
  }

  /**
   * Get coordination metrics
   * 
   * @param int $periodDays Period in days
   * @return array Coordination metrics
   */
  public function getCoordinationMetrics(int $periodDays = 7): array
  {
    try {
      $dateFilter = "created_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)";

      $result = DoctrineOrm::select("
        SELECT 
          COUNT(*) as total_coordinations,
          AVG(execution_time_ms) as avg_execution_time,
          AVG(evaluation_time_ms) as avg_evaluation_time,
          AVG(total_time_ms) as avg_total_time,
          AVG(consensus_score) as avg_consensus_score,
          AVG(num_critics) as avg_critics_per_coordination
        FROM {$this->prefix}rag_agent_coordinated_results
        WHERE {$dateFilter}
      ");

      $data = $result[0] ?? [];

      return [
        'total_coordinations' => (int)($data['total_coordinations'] ?? 0),
        'avg_execution_time' => round($data['avg_execution_time'] ?? 0, 2),
        'avg_evaluation_time' => round($data['avg_evaluation_time'] ?? 0, 2),
        'avg_total_time' => round($data['avg_total_time'] ?? 0, 2),
        'avg_consensus_score' => round($data['avg_consensus_score'] ?? 0, 2),
        'avg_critics_per_coordination' => round($data['avg_critics_per_coordination'] ?? 0, 1)
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get coordination metrics - ' . $e->getMessage());
      return [
        'total_coordinations' => 0,
        'avg_execution_time' => 0,
        'avg_evaluation_time' => 0,
        'avg_total_time' => 0,
        'avg_consensus_score' => 0,
        'avg_critics_per_coordination' => 0
      ];
    }
  }

  /**
   * Get utilization metrics
   * 
   * @param int $periodDays Period in days
   * @return array Utilization metrics
   */
  public function getUtilizationMetrics(int $periodDays = 7): array
  {
    try {
      // Calculate actor utilization
      $actorResult = DoctrineOrm::select("
        SELECT 
          SUM(execution_time_ms) as total_execution_time
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE executed_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)
      ");

      // Calculate critic utilization
      $criticResult = DoctrineOrm::select("
        SELECT 
          SUM(evaluation_time_ms) as total_evaluation_time
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE evaluated_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)
      ");

      $totalExecutionTime = $actorResult[0]['total_execution_time'] ?? 0;
      $totalEvaluationTime = $criticResult[0]['total_evaluation_time'] ?? 0;

      // Calculate utilization percentage (assuming 24/7 operation)
      $periodSeconds = $periodDays * 24 * 60 * 60 * 1000; // in milliseconds
      $actorUtilization = $periodSeconds > 0 ? round(($totalExecutionTime / $periodSeconds) * 100, 2) : 0;
      $criticUtilization = $periodSeconds > 0 ? round(($totalEvaluationTime / $periodSeconds) * 100, 2) : 0;

      return [
        'actor_utilization' => $actorUtilization,
        'critic_utilization' => $criticUtilization,
        'total_execution_time' => $totalExecutionTime,
        'total_evaluation_time' => $totalEvaluationTime
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get utilization metrics - ' . $e->getMessage());
      return [
        'actor_utilization' => 0,
        'critic_utilization' => 0,
        'total_execution_time' => 0,
        'total_evaluation_time' => 0
      ];
    }
  }

  /**
   * Get recent coordinations for timeline
   * 
   * @param int $limit Number of recent coordinations
   * @return array Recent coordinations
   */
  public function getRecentCoordinations(int $limit = 20): array
  {
    try {
      $results = DoctrineOrm::select("
        SELECT 
          coordination_id,
          action_id,
          actor_id,
          consensus_score,
          num_critics,
          total_time_ms,
          created_at
        FROM {$this->prefix}rag_agent_coordinated_results
        ORDER BY created_at DESC
        LIMIT {$limit}
      ");

      $coordinations = [];
      foreach ($results as $row) {
        $coordinations[] = [
          'coordination_id' => $row['coordination_id'],
          'action_id' => $row['action_id'],
          'actor_id' => $row['actor_id'],
          'consensus_score' => round($row['consensus_score'] ?? 0, 2),
          'num_critics' => (int)$row['num_critics'],
          'total_time_ms' => (int)$row['total_time_ms'],
          'created_at' => $row['created_at']
        ];
      }

      return $coordinations;
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get recent coordinations - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get detailed actor information
   * 
   * @param string $actorId Actor ID
   * @return array Actor details
   */
  public function getActorDetails(string $actorId): array
  {
    try {
      // Get actor capabilities
      $capabilitiesResult = DoctrineOrm::select("
        SELECT 
          action_type,
          confidence,
          domain
        FROM {$this->prefix}rag_agent_actor_registry
        WHERE actor_id = :actor_id
      ", ['actor_id' => $actorId]);

      // Get actor execution history
      $historyResult = DoctrineOrm::select("
        SELECT 
          COUNT(*) as total_executions,
          AVG(execution_time_ms) as avg_time,
          AVG(quality_score) as avg_quality,
          SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count
        FROM {$this->prefix}rag_agent_actor_executions
        WHERE actor_id = :actor_id
      ", ['actor_id' => $actorId]);

      $history = $historyResult[0] ?? [];
      $totalExecutions = (int)($history['total_executions'] ?? 0);

      return [
        'actor_id' => $actorId,
        'capabilities' => $capabilitiesResult,
        'total_executions' => $totalExecutions,
        'avg_execution_time' => round($history['avg_time'] ?? 0, 2),
        'avg_quality_score' => round($history['avg_quality'] ?? 0, 2),
        'success_rate' => $totalExecutions > 0 ? round(($history['success_count'] / $totalExecutions) * 100, 1) : 0
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get actor details - ' . $e->getMessage());
      return [
        'actor_id' => $actorId,
        'capabilities' => [],
        'total_executions' => 0,
        'avg_execution_time' => 0,
        'avg_quality_score' => 0,
        'success_rate' => 0
      ];
    }
  }

  /**
   * Get detailed critic information
   * 
   * @param string $criticId Critic ID
   * @return array Critic details
   */
  public function getCriticDetails(string $criticId): array
  {
    try {
      // Get critic capabilities
      $capabilitiesResult = DoctrineOrm::select("
        SELECT 
          output_type,
          expertise_level,
          domain
        FROM {$this->prefix}rag_agent_critic_registry
        WHERE critic_id = :critic_id
      ", ['critic_id' => $criticId]);

      // Get critic evaluation history
      $historyResult = DoctrineOrm::select("
        SELECT 
          COUNT(*) as total_evaluations,
          AVG(evaluation_time_ms) as avg_time,
          AVG(overall_score) as avg_score,
          AVG(accuracy_score) as avg_accuracy,
          AVG(completeness_score) as avg_completeness,
          AVG(efficiency_score) as avg_efficiency,
          AVG(clarity_score) as avg_clarity
        FROM {$this->prefix}rag_agent_critic_evaluations
        WHERE critic_id = :critic_id
      ", ['critic_id' => $criticId]);

      $history = $historyResult[0] ?? [];

      return [
        'critic_id' => $criticId,
        'capabilities' => $capabilitiesResult,
        'total_evaluations' => (int)($history['total_evaluations'] ?? 0),
        'avg_evaluation_time' => round($history['avg_time'] ?? 0, 2),
        'avg_overall_score' => round($history['avg_score'] ?? 0, 2),
        'avg_accuracy' => round($history['avg_accuracy'] ?? 0, 2),
        'avg_completeness' => round($history['avg_completeness'] ?? 0, 2),
        'avg_efficiency' => round($history['avg_efficiency'] ?? 0, 2),
        'avg_clarity' => round($history['avg_clarity'] ?? 0, 2)
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get critic details - ' . $e->getMessage());
      return [
        'critic_id' => $criticId,
        'capabilities' => [],
        'total_evaluations' => 0,
        'avg_evaluation_time' => 0,
        'avg_overall_score' => 0,
        'avg_accuracy' => 0,
        'avg_completeness' => 0,
        'avg_efficiency' => 0,
        'avg_clarity' => 0
      ];
    }
  }
}
