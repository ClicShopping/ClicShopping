<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Batch;

use ClicShopping\OM\Registry;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationCache;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;

/**
 * BatchCacheWarmer
 * 
 * Warms the reputation cache in batches for improved performance.
 * Preloads frequently accessed reputation data into cache.
 * 
 * Requirements: 15.2, 15.3
 */
class BatchCacheWarmer
{
  private $db;
  private $cache;
  private $store;
  private int $batchSize;

  /**
   * Constructor
   * 
   * @param int $batchSize Number of reputations to warm in each batch (default: 100)
   */
  public function __construct(int $batchSize = 100)
  {
    $this->db = Registry::get('Db');
    $this->cache = new ReputationCache();
    $this->store = new ReputationStore();
    $this->batchSize = $batchSize;
  }

  /**
   * Warm cache for all active critics
   * 
   * @return array Results with success/failure counts
   */
  public function warmAllActive(): array
  {
    $startTime = microtime(true);

    // Get all active critics (those with recent evaluations)
    $sql = "SELECT DISTINCT r.critic_id
            FROM :table_rag_agent_reputation r
            INNER JOIN :table_rag_agent_reputation_evaluation_outcomes o
              ON r.critic_id = o.critic_id
            WHERE o.evaluated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY r.reputation_score DESC";

    $this->db->prepare($sql);
    $this->db->execute();

    $criticIds = [];
    while ($row = $this->db->fetch()) {
      $criticIds[] = $row['critic_id'];
    }

    $results = $this->warmBatch($criticIds);
    
    $duration = (microtime(true) - $startTime) * 1000;
    $results['duration_ms'] = $duration;

    error_log(sprintf(
      'BatchCacheWarmer: Warmed %d critics in %.2fms',
      $results['success'],
      $duration
    ));

    return $results;
  }

  /**
   * Warm cache for top critics by reputation
   * 
   * @param int $limit Number of top critics to warm (default: 50)
   * @return array Results with success/failure counts
   */
  public function warmTopCritics(int $limit = 50): array
  {
    $startTime = microtime(true);

    $sql = "SELECT critic_id
            FROM :table_rag_agent_reputation
            WHERE status = 'established'
            ORDER BY reputation_score DESC
            LIMIT :limit";

    $this->db->prepare($sql);
    $this->db->bindInt(':limit', $limit);
    $this->db->execute();

    $criticIds = [];
    while ($row = $this->db->fetch()) {
      $criticIds[] = $row['critic_id'];
    }

    $results = $this->warmBatch($criticIds);
    
    $duration = (microtime(true) - $startTime) * 1000;
    $results['duration_ms'] = $duration;

    error_log(sprintf(
      'BatchCacheWarmer: Warmed top %d critics in %.2fms',
      $results['success'],
      $duration
    ));

    return $results;
  }

  /**
   * Warm cache for a batch of critics
   * 
   * @param array $criticIds Array of critic IDs
   * @return array Results with success/failure counts
   */
  public function warmBatch(array $criticIds): array
  {
    $results = [
      'total' => count($criticIds),
      'success' => 0,
      'failed' => 0,
      'cached' => 0,
      'errors' => []
    ];

    if (empty($criticIds)) {
      return $results;
    }

    // Process in chunks
    $chunks = array_chunk($criticIds, $this->batchSize);

    foreach ($chunks as $chunkIndex => $chunk) {
      try {
        // Fetch all reputations for this batch in one query
        $reputations = $this->fetchReputationsBatch($chunk);

        // Cache each reputation
        foreach ($chunk as $criticId) {
          try {
            // Check if already cached
            $cached = $this->cache->get($criticId);
            if ($cached !== null) {
              $results['cached']++;
              $results['success']++;
              continue;
            }

            // Get reputation from batch results
            if (!isset($reputations[$criticId])) {
              $results['failed']++;
              $results['errors'][] = "Reputation not found for critic: $criticId";
              continue;
            }

            // Cache the reputation
            $this->cache->set($criticId, $reputations[$criticId]);
            $results['success']++;

          } catch (\Exception $e) {
            $results['failed']++;
            $results['errors'][] = "Error caching $criticId: " . $e->getMessage();
            error_log("BatchCacheWarmer: Error caching $criticId: " . $e->getMessage());
          }
        }

        error_log(sprintf(
          'BatchCacheWarmer: Processed chunk %d/%d (%d critics)',
          $chunkIndex + 1,
          count($chunks),
          count($chunk)
        ));

      } catch (\Exception $e) {
        $results['failed'] += count($chunk);
        $results['errors'][] = "Batch error: " . $e->getMessage();
        error_log("BatchCacheWarmer: Batch error: " . $e->getMessage());
      }
    }

    return $results;
  }

  /**
   * Fetch reputations for multiple critics in one query
   * 
   * @param array $criticIds Array of critic IDs
   * @return array Reputations indexed by critic ID
   */
  private function fetchReputationsBatch(array $criticIds): array
  {
    if (empty($criticIds)) {
      return [];
    }

    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($criticIds), '?'));

    $sql = "SELECT 
              critic_id,
              reputation_score,
              consensus_alignment,
              feedback_quality,
              consistency_score,
              expertise_accuracy,
              total_evaluations,
              status,
              calculated_at,
              last_decay_at
            FROM :table_rag_agent_reputation
            WHERE critic_id IN ($placeholders)";

    $this->db->prepare($sql);
    
    // Bind all critic IDs
    foreach ($criticIds as $index => $criticId) {
      $this->db->bindValue($index + 1, $criticId);
    }

    $this->db->execute();

    // Index reputations by critic ID
    $reputations = [];
    while ($row = $this->db->fetch()) {
      $reputations[$row['critic_id']] = [
        'criticId' => $row['critic_id'],
        'reputationScore' => (float)$row['reputation_score'],
        'consensusAlignment' => (float)$row['consensus_alignment'],
        'feedbackQuality' => (float)$row['feedback_quality'],
        'consistencyScore' => (float)$row['consistency_score'],
        'expertiseAccuracy' => (float)$row['expertise_accuracy'],
        'totalEvaluations' => (int)$row['total_evaluations'],
        'status' => $row['status'],
        'calculatedAt' => new \DateTimeImmutable($row['calculated_at']),
        'lastDecayAt' => new \DateTimeImmutable($row['last_decay_at'])
      ];
    }

    return $reputations;
  }

  /**
   * Clear and rewarm entire cache
   * 
   * @return array Results with success/failure counts
   */
  public function clearAndRewarm(): array
  {
    $startTime = microtime(true);

    // Clear cache
    $this->cache->clear();

    // Warm all active critics
    $results = $this->warmAllActive();
    
    $duration = (microtime(true) - $startTime) * 1000;
    $results['total_duration_ms'] = $duration;

    error_log(sprintf(
      'BatchCacheWarmer: Cleared and rewarmed cache in %.2fms',
      $duration
    ));

    return $results;
  }

  /**
   * Get cache warming statistics
   * 
   * @return array Statistics about cache warming
   */
  public function getStatistics(): array
  {
    $sql = "SELECT 
              COUNT(*) as total_reputations,
              COUNT(CASE WHEN status = 'established' THEN 1 END) as established_count,
              COUNT(CASE WHEN status = 'establishing' THEN 1 END) as establishing_count,
              COUNT(CASE WHEN status = 'bootstrapping' THEN 1 END) as bootstrapping_count,
              AVG(reputation_score) as avg_reputation
            FROM :table_rag_agent_reputation";

    $this->db->prepare($sql);
    $this->db->execute();

    $stats = $this->db->fetch();

    return [
      'total_reputations' => (int)$stats['total_reputations'],
      'established_count' => (int)$stats['established_count'],
      'establishing_count' => (int)$stats['establishing_count'],
      'bootstrapping_count' => (int)$stats['bootstrapping_count'],
      'avg_reputation' => (float)$stats['avg_reputation'],
      'batch_size' => $this->batchSize
    ];
  }
}
