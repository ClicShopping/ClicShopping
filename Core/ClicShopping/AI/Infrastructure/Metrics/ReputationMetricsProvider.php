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
 * Reputation Metrics Provider
 * 
 * Provides reputation metrics and data for dashboard display.
 * Follows the same pattern as ActorCriticMetricsProvider.
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4
 */
class ReputationMetricsProvider
{
  private string $prefix;

  public function __construct()
  {
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
  }

  /**
   * Get all reputation metrics
   * 
   * @param int $periodDays Period in days for metrics
   * @return array Complete metrics data
   */
  public function getAllMetrics(int $periodDays = 7): array
  {
    return [
      'reputation_stats' => $this->getReputationStats(),
      'top_critics' => $this->getTopCritics(10),
      'declining_critics' => $this->getDecliningCritics($periodDays),
      'reputation_distribution' => $this->getReputationDistribution(),
      'reputation_alerts' => $this->getReputationAlerts(10),
      'reputation_trends' => $this->getReputationTrends($periodDays)
    ];
  }

  /**
   * Get global reputation statistics
   * 
   * @return array Reputation stats
   */
  public function getReputationStats(): array
  {
    try {
      $result = DoctrineOrm::select("
        SELECT 
          COUNT(*) as total_critics,
          AVG(reputation_score) as avg_reputation,
          MIN(reputation_score) as min_reputation,
          MAX(reputation_score) as max_reputation,
          SUM(CASE WHEN status = 'bootstrapping' THEN 1 ELSE 0 END) as bootstrapping_count,
          SUM(CASE WHEN status = 'establishing' THEN 1 ELSE 0 END) as establishing_count,
          SUM(CASE WHEN status = 'established' THEN 1 ELSE 0 END) as established_count,
          SUM(total_evaluations) as total_evaluations
        FROM {$this->prefix}rag_agent_reputation
      ");

      $stats = $result[0] ?? [];

      return [
        'total_critics' => (int)($stats['total_critics'] ?? 0),
        'avg_reputation' => round($stats['avg_reputation'] ?? 0, 3),
        'min_reputation' => round($stats['min_reputation'] ?? 0, 3),
        'max_reputation' => round($stats['max_reputation'] ?? 0, 3),
        'bootstrapping_count' => (int)($stats['bootstrapping_count'] ?? 0),
        'establishing_count' => (int)($stats['establishing_count'] ?? 0),
        'established_count' => (int)($stats['established_count'] ?? 0),
        'total_evaluations' => (int)($stats['total_evaluations'] ?? 0)
      ];
    } catch (\Exception $e) {
      error_log('ReputationMetricsProvider: Failed to get reputation stats - ' . $e->getMessage());
      return $this->getEmptyStats();
    }
  }

  /**
   * Get top critics by reputation
   * 
   * @param int $limit Number of critics to return
   * @return array Top critics
   */
  public function getTopCritics(int $limit = 10): array
  {
    try {
      $results = DoctrineOrm::select("
        SELECT 
          critic_id,
          reputation_score,
          consensus_alignment,
          feedback_quality,
          consistency_score,
          expertise_accuracy,
          total_evaluations,
          status,
          calculated_at
        FROM {$this->prefix}rag_agent_reputation
        ORDER BY reputation_score DESC
        LIMIT {$limit}
      ");

      $critics = [];
      foreach ($results as $row) {
        $critics[] = [
          'critic_id' => $row['critic_id'],
          'reputation_score' => round($row['reputation_score'], 3),
          'consensus_alignment' => round($row['consensus_alignment'], 3),
          'feedback_quality' => round($row['feedback_quality'], 3),
          'consistency_score' => round($row['consistency_score'], 3),
          'expertise_accuracy' => round($row['expertise_accuracy'], 3),
          'total_evaluations' => (int)$row['total_evaluations'],
          'status' => $row['status'],
          'calculated_at' => $row['calculated_at']
        ];
      }

      return $critics;
    } catch (\Exception $e) {
      error_log('ReputationMetricsProvider: Failed to get top critics - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get critics with declining reputation
   * 
   * @param int $periodDays Period to check for decline
   * @return array Declining critics
   */
  public function getDecliningCritics(int $periodDays = 7): array
  {
    try {
      $results = DoctrineOrm::select("
        SELECT 
          r.critic_id,
          r.reputation_score as current_reputation,
          MIN(h.old_reputation) as old_reputation,
          (r.reputation_score - MIN(h.old_reputation)) as reputation_change,
          r.total_evaluations,
          r.status
        FROM {$this->prefix}rag_agent_reputation r
        INNER JOIN {$this->prefix}rag_agent_reputation_history h ON r.critic_id = h.critic_id
        WHERE h.recorded_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)
        GROUP BY r.critic_id, r.reputation_score, r.total_evaluations, r.status
        HAVING reputation_change < -0.05
        ORDER BY reputation_change ASC
        LIMIT 10
      ");

      $critics = [];
      foreach ($results as $row) {
        $critics[] = [
          'critic_id' => $row['critic_id'],
          'current_reputation' => round($row['current_reputation'], 3),
          'old_reputation' => round($row['old_reputation'], 3),
          'reputation_change' => round($row['reputation_change'], 3),
          'total_evaluations' => (int)$row['total_evaluations'],
          'status' => $row['status']
        ];
      }

      return $critics;
    } catch (\Exception $e) {
      error_log('ReputationMetricsProvider: Failed to get declining critics - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get reputation score distribution
   * 
   * @return array Distribution data for histogram
   */
  public function getReputationDistribution(): array
  {
    try {
      $results = DoctrineOrm::select("
        SELECT 
          FLOOR(reputation_score * 10) / 10 as score_bucket,
          COUNT(*) as count
        FROM {$this->prefix}rag_agent_reputation
        GROUP BY score_bucket
        ORDER BY score_bucket
      ");

      $distribution = [];
      foreach ($results as $row) {
        $distribution[] = [
          'score_bucket' => round($row['score_bucket'], 1),
          'count' => (int)$row['count']
        ];
      }

      return $distribution;
    } catch (\Exception $e) {
      error_log('ReputationMetricsProvider: Failed to get reputation distribution - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get recent reputation alerts
   * 
   * @param int $limit Number of alerts to return
   * @return array Recent alerts
   */
  public function getReputationAlerts(int $limit = 10): array
  {
    try {
      $results = DoctrineOrm::select("
        SELECT 
          alert_id,
          critic_id,
          alert_type,
          severity,
          message,
          context,
          acknowledged,
          created_at
        FROM {$this->prefix}rag_agent_reputation_alerts
        WHERE acknowledged = 0
        ORDER BY created_at DESC
        LIMIT {$limit}
      ");

      $alerts = [];
      foreach ($results as $row) {
        $alerts[] = [
          'alert_id' => (int)$row['alert_id'],
          'critic_id' => $row['critic_id'],
          'alert_type' => $row['alert_type'],
          'severity' => $row['severity'],
          'message' => $row['message'],
          'context' => json_decode($row['context'] ?? '{}', true),
          'created_at' => $row['created_at']
        ];
      }

      return $alerts;
    } catch (\Exception $e) {
      error_log('ReputationMetricsProvider: Failed to get reputation alerts - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get reputation trends over time
   * 
   * @param int $periodDays Period for trend data
   * @return array Trend data
   */
  public function getReputationTrends(int $periodDays = 30): array
  {
    try {
      $results = DoctrineOrm::select("
        SELECT 
          DATE(calculated_at) as date,
          AVG(reputation_score) as avg_reputation,
          MIN(reputation_score) as min_reputation,
          MAX(reputation_score) as max_reputation,
          COUNT(*) as critic_count
        FROM {$this->prefix}rag_agent_reputation
        WHERE calculated_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)
        GROUP BY DATE(calculated_at)
        ORDER BY date ASC
      ");

      $trends = [];
      foreach ($results as $row) {
        $trends[] = [
          'date' => $row['date'],
          'avg_reputation' => round($row['avg_reputation'], 3),
          'min_reputation' => round($row['min_reputation'], 3),
          'max_reputation' => round($row['max_reputation'], 3),
          'critic_count' => (int)$row['critic_count']
        ];
      }

      return $trends;
    } catch (\Exception $e) {
      error_log('ReputationMetricsProvider: Failed to get reputation trends - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get reputation history for a specific critic
   * 
   * @param string $criticId Critic ID
   * @param int $periodDays Period for history
   * @return array History data
   */
  public function getReputationHistory(string $criticId, int $periodDays = 30): array
  {
    try {
      $results = DoctrineOrm::select("
        SELECT 
          history_id,
          evaluation_id,
          consensus_score,
          critic_score,
          alignment_delta,
          reputation_impact,
          old_reputation,
          new_reputation,
          recorded_at
        FROM {$this->prefix}rag_agent_reputation_history
        WHERE critic_id = :critic_id
        AND recorded_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)
        ORDER BY recorded_at ASC
      ", ['critic_id' => $criticId]);

      $history = [];
      foreach ($results as $row) {
        $history[] = [
          'history_id' => (int)$row['history_id'],
          'evaluation_id' => $row['evaluation_id'],
          'consensus_score' => round($row['consensus_score'], 3),
          'critic_score' => round($row['critic_score'], 3),
          'alignment_delta' => round($row['alignment_delta'], 3),
          'reputation_impact' => round($row['reputation_impact'], 3),
          'old_reputation' => round($row['old_reputation'], 3),
          'new_reputation' => round($row['new_reputation'], 3),
          'recorded_at' => $row['recorded_at']
        ];
      }

      return $history;
    } catch (\Exception $e) {
      error_log('ReputationMetricsProvider: Failed to get reputation history - ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get empty stats for error cases
   * 
   * @return array Empty stats
   */
  private function getEmptyStats(): array
  {
    return [
      'total_critics' => 0,
      'avg_reputation' => 0,
      'min_reputation' => 0,
      'max_reputation' => 0,
      'bootstrapping_count' => 0,
      'establishing_count' => 0,
      'established_count' => 0,
      'total_evaluations' => 0
    ];
  }
}
