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
      'recent_coordinations' => $this->getRecentCoordinations(20),
      'weight_anomalies' => $this->getWeightAnomalies($periodDays),
      'weight_stats' => $this->getWeightStats($periodDays),
      'top_weighted_critics' => $this->getTopWeightedCritics($periodDays),
      'consensus_comparison' => $this->getConsensusComparison($periodDays),
      'weights_by_domain' => $this->getWeightsByDomain($periodDays),
      'domain_match_quality' => $this->getDomainMatchQuality($periodDays),
      'critic_domain_performance' => $this->getCriticDomainPerformance($periodDays)
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
   * Get stored weight anomalies for the dashboard panel
   *
   * Reads anomalies persisted by {@see \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAnomalyDetector}
   * into rag_agent_weight_anomalies. The returned row shape matches the columns the
   * adaptive_weighting_dashboard template renders (critic_id, anomaly_type, severity,
   * llm_analysis, detected_at).
   *
   * @param int $days Look-back window in days
   * @return array Anomaly rows, most recent first
   */
  public function getWeightAnomalies(int $days = 7): array
  {
    try {
      $days = (int)$days;
      $results = DoctrineOrm::select("
        SELECT
          id,
          anomaly_type,
          critic_id,
          severity,
          llm_analysis,
          detected_at
        FROM {$this->prefix}rag_agent_weight_anomalies
        WHERE detected_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)
        ORDER BY detected_at DESC
      ");

      $anomalies = [];
      foreach ($results as $row) {
        $anomalies[] = [
          'id' => (int)$row['id'],
          'anomaly_type' => $row['anomaly_type'],
          'critic_id' => $row['critic_id'] ?? '',
          'severity' => $row['severity'],
          'llm_analysis' => $row['llm_analysis'] ?? '',
          'detected_at' => $row['detected_at']
        ];
      }

      return $anomalies;
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get weight anomalies - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Aggregate weight statistics for the dashboard overview card.
   * Source: rag_agent_adaptive_weights (written on every weighting).
   *
   * @param int $days Look-back window in days
   * @return array Keys: total_weight_calculations, avg_weight, total_evaluations, active_critics
   */
  public function getWeightStats(int $days = 7): array
  {
    try {
      $days = (int)$days;
      $r = DoctrineOrm::select("
        SELECT
          COUNT(*) AS total_weight_calculations,
          AVG(normalized_weight) AS avg_weight,
          COUNT(DISTINCT evaluation_id) AS total_evaluations,
          COUNT(DISTINCT critic_id) AS active_critics
        FROM {$this->prefix}rag_agent_adaptive_weights
        WHERE created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)
      ");
      $row = $r[0] ?? [];

      return [
        'total_weight_calculations' => (int)($row['total_weight_calculations'] ?? 0),
        'avg_weight' => (float)($row['avg_weight'] ?? 0),
        'total_evaluations' => (int)($row['total_evaluations'] ?? 0),
        'active_critics' => (int)($row['active_critics'] ?? 0),
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get weight stats - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Top critics by average normalized weight (dashboard table).
   * Source: rag_agent_adaptive_weights.
   *
   * @param int $days Look-back window in days
   * @param int $limit Max rows
   * @return array Rows: critic_id, avg_weight, min_weight, max_weight, weight_count
   */
  public function getTopWeightedCritics(int $days = 7, int $limit = 10): array
  {
    try {
      $days = (int)$days;
      $limit = (int)$limit;
      $results = DoctrineOrm::select("
        SELECT
          critic_id,
          AVG(normalized_weight) AS avg_weight,
          MIN(normalized_weight) AS min_weight,
          MAX(normalized_weight) AS max_weight,
          COUNT(*) AS weight_count
        FROM {$this->prefix}rag_agent_adaptive_weights
        WHERE created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)
        GROUP BY critic_id
        ORDER BY avg_weight DESC
        LIMIT {$limit}
      ");

      $critics = [];
      foreach ($results as $row) {
        $critics[] = [
          'critic_id' => $row['critic_id'],
          'avg_weight' => (float)$row['avg_weight'],
          'min_weight' => (float)$row['min_weight'],
          'max_weight' => (float)$row['max_weight'],
          'weight_count' => (int)$row['weight_count'],
        ];
      }

      return $critics;
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get top weighted critics - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Dynamic-vs-static consensus comparison for the dashboard.
   * Source: rag_agent_weight_consensus (written by WeightedConsensusBuilder).
   *
   * @param int $days Look-back window in days
   * @return array Keys: total_comparisons, avg_dynamic_consensus, avg_static_consensus,
   *               dynamic_better_percentage, recent_comparisons[]
   */
  public function getConsensusComparison(int $days = 7): array
  {
    try {
      $days = (int)$days;
      $agg = DoctrineOrm::select("
        SELECT
          COUNT(*) AS total_comparisons,
          AVG(dynamic_consensus) AS avg_dynamic_consensus,
          AVG(static_consensus) AS avg_static_consensus,
          SUM(CASE WHEN difference > 0 THEN 1 ELSE 0 END) AS dynamic_better_count
        FROM {$this->prefix}rag_agent_weight_consensus
        WHERE created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)
      ");
      $a = $agg[0] ?? [];
      $total = (int)($a['total_comparisons'] ?? 0);
      $dynamicBetterPct = $total > 0
        ? round(((int)($a['dynamic_better_count'] ?? 0) / $total) * 100, 1)
        : 0;

      $recent = DoctrineOrm::select("
        SELECT evaluation_id, dynamic_consensus, static_consensus, difference, created_at
        FROM {$this->prefix}rag_agent_weight_consensus
        WHERE created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)
        ORDER BY created_at DESC
        LIMIT 10
      ");
      $recentComparisons = [];
      foreach ($recent as $row) {
        $recentComparisons[] = [
          'evaluation_id' => $row['evaluation_id'],
          'dynamic_consensus' => (float)$row['dynamic_consensus'],
          'static_consensus' => (float)$row['static_consensus'],
          'difference' => (float)$row['difference'],
          'created_at' => $row['created_at'],
        ];
      }

      return [
        'total_comparisons' => $total,
        'avg_dynamic_consensus' => (float)($a['avg_dynamic_consensus'] ?? 0),
        'avg_static_consensus' => (float)($a['avg_static_consensus'] ?? 0),
        'dynamic_better_percentage' => $dynamicBetterPct,
        'recent_comparisons' => $recentComparisons,
      ];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get consensus comparison - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Fetch the raw adaptive-weight rows carrying per-critic domain analysis.
   *
   * Shared source for the three domain dashboard sections. Each row is one critic's
   * contribution to one weighting; the per-critic domain analysis lives in the
   * factor_analysis JSON (see extractCriticDomainAnalysis). Historical rows written
   * before 2026-06-10 carry an empty domain_analysis and are naturally skipped.
   *
   * @param int $days Look-back window in days
   * @return array<int, array<string, mixed>> Rows: evaluation_id, critic_id, normalized_weight, factor_analysis
   */
  private function fetchDomainAnalysisRows(int $days): array
  {
    return DoctrineOrm::select("
      SELECT evaluation_id, critic_id, normalized_weight, factor_analysis
      FROM {$this->prefix}rag_agent_adaptive_weights
      WHERE created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)
    ");
  }

  /**
   * Extract one critic's domain analysis from a stored factor_analysis JSON blob.
   *
   * Path: factor_analysis.dominant_factors.domain_analysis[<criticId>]. Returns null
   * when absent/empty so callers skip pre-fix rows and partial payloads transparently.
   *
   * @param string|null $factorAnalysisJson Raw JSON from the factor_analysis column
   * @param string $criticId Critic whose entry to pull
   * @return array{match_quality: string, relevant_domains: array<int, string>}|null
   */
  private function extractCriticDomainAnalysis(?string $factorAnalysisJson, string $criticId): ?array
  {
    if (empty($factorAnalysisJson) || $criticId === '') {
      return null;
    }

    $decoded = json_decode($factorAnalysisJson, true);
    if (!is_array($decoded)) {
      return null;
    }

    $domainAnalysis = $decoded['dominant_factors']['domain_analysis'] ?? null;
    if (!is_array($domainAnalysis) || !isset($domainAnalysis[$criticId]) || !is_array($domainAnalysis[$criticId])) {
      return null;
    }

    $entry = $domainAnalysis[$criticId];
    $matchQuality = is_string($entry['match_quality'] ?? null) ? strtolower($entry['match_quality']) : 'none';

    $relevantDomains = [];
    if (isset($entry['relevant_domains']) && is_array($entry['relevant_domains'])) {
      foreach ($entry['relevant_domains'] as $domain) {
        if (is_string($domain) && $domain !== '') {
          $relevantDomains[] = $domain;
        }
      }
    }

    return ['match_quality' => $matchQuality, 'relevant_domains' => $relevantDomains];
  }

  /**
   * Average adaptive weight per domain (dashboard "Weights by Domain" table).
   * Source: rag_agent_adaptive_weights factor_analysis.domain_analysis.
   *
   * @param int $days Look-back window in days
   * @return array<int, array{domain: string, avg_weight: float, weight_count: int, critic_count: int}>
   */
  public function getWeightsByDomain(int $days = 7): array
  {
    try {
      $rows = $this->fetchDomainAnalysisRows((int)$days);

      // domain => ['sum' => float, 'count' => int, 'critics' => [criticId => true]]
      $acc = [];
      foreach ($rows as $row) {
        $criticId = (string)($row['critic_id'] ?? '');
        $analysis = $this->extractCriticDomainAnalysis($row['factor_analysis'] ?? null, $criticId);
        if ($analysis === null || empty($analysis['relevant_domains'])) {
          continue;
        }

        $weight = (float)($row['normalized_weight'] ?? 0);
        foreach ($analysis['relevant_domains'] as $domain) {
          if (!isset($acc[$domain])) {
            $acc[$domain] = ['sum' => 0.0, 'count' => 0, 'critics' => []];
          }
          $acc[$domain]['sum'] += $weight;
          $acc[$domain]['count']++;
          $acc[$domain]['critics'][$criticId] = true;
        }
      }

      $result = [];
      foreach ($acc as $domain => $d) {
        $result[] = [
          'domain' => $domain,
          // $d['count'] is always >= 1: the entry only exists once incremented.
          'avg_weight' => $d['sum'] / $d['count'],
          'weight_count' => $d['count'],
          'critic_count' => count($d['critics']),
        ];
      }

      usort($result, static fn(array $a, array $b): int => $b['avg_weight'] <=> $a['avg_weight']);

      return $result;
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get weights by domain - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Distribution of domain match quality across weightings (dashboard cards).
   * Returns an empty array when no row carries domain analysis, so the template
   * (which guards on a non-empty match_quality_distribution) hides the section.
   *
   * @param int $days Look-back window in days
   * @return array{match_quality_distribution: array{high_match: int, medium_match: int, low_match: int, no_match: int}}|array{}
   */
  public function getDomainMatchQuality(int $days = 7): array
  {
    try {
      $rows = $this->fetchDomainAnalysisRows((int)$days);

      $distribution = ['high_match' => 0, 'medium_match' => 0, 'low_match' => 0, 'no_match' => 0];
      $hasData = false;
      foreach ($rows as $row) {
        $analysis = $this->extractCriticDomainAnalysis($row['factor_analysis'] ?? null, (string)($row['critic_id'] ?? ''));
        if ($analysis === null) {
          continue;
        }

        $hasData = true;
        switch ($analysis['match_quality']) {
          case 'high':
            $distribution['high_match']++;
            break;
          case 'medium':
            $distribution['medium_match']++;
            break;
          case 'low':
            $distribution['low_match']++;
            break;
          default: // 'none' or any unexpected value
            $distribution['no_match']++;
            break;
        }
      }

      return $hasData ? ['match_quality_distribution' => $distribution] : [];
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get domain match quality - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Per-critic average weight broken down by domain (dashboard "Critic Domain Performance").
   * Source: rag_agent_adaptive_weights factor_analysis.domain_analysis.
   *
   * @param int $days Look-back window in days
   * @return array<int, array{critic_id: string, domains: array<int, array{domain: string, avg_weight: float, evaluation_count: int}>}>
   */
  public function getCriticDomainPerformance(int $days = 7): array
  {
    try {
      $rows = $this->fetchDomainAnalysisRows((int)$days);

      // critic_id => domain => ['sum' => float, 'count' => int]
      $acc = [];
      foreach ($rows as $row) {
        $criticId = (string)($row['critic_id'] ?? '');
        $analysis = $this->extractCriticDomainAnalysis($row['factor_analysis'] ?? null, $criticId);
        if ($analysis === null || empty($analysis['relevant_domains'])) {
          continue;
        }

        $weight = (float)($row['normalized_weight'] ?? 0);
        foreach ($analysis['relevant_domains'] as $domain) {
          $acc[$criticId][$domain]['sum'] = ($acc[$criticId][$domain]['sum'] ?? 0.0) + $weight;
          $acc[$criticId][$domain]['count'] = ($acc[$criticId][$domain]['count'] ?? 0) + 1;
        }
      }

      $result = [];
      foreach ($acc as $criticId => $domains) {
        $domainRows = [];
        foreach ($domains as $domain => $d) {
          $domainRows[] = [
            'domain' => $domain,
            // $d['count'] is always >= 1: the entry only exists once incremented.
            'avg_weight' => $d['sum'] / $d['count'],
            'evaluation_count' => $d['count'],
          ];
        }
        usort($domainRows, static fn(array $a, array $b): int => $b['avg_weight'] <=> $a['avg_weight']);
        $result[] = ['critic_id' => $criticId, 'domains' => $domainRows];
      }

      return $result;
    } catch (\Exception $e) {
      error_log('AdaptiveWeightingMetricsProvider: Failed to get critic domain performance - ' . $e->getMessage());
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
