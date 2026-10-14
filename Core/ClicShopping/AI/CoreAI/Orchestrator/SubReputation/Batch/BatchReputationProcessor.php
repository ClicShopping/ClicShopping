<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Batch;

use ClicShopping\OM\Registry;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationCalculator;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;

/**
 * BatchReputationProcessor
 * 
 * Processes reputation updates in batches for improved performance.
 * Reduces database round trips and improves throughput.
 * 
 * Requirements: 15.3
 */
class BatchReputationProcessor
{
  private $db;
  private $calculator;
  private $store;
  private int $batchSize;

  /**
   * Constructor
   * 
   * @param int $batchSize Number of updates to process in each batch (default: 50)
   */
  public function __construct(int $batchSize = 50)
  {
    $this->db = Registry::get('Db');
    $this->calculator = new ReputationCalculator();
    $this->store = new ReputationStore();
    $this->batchSize = $batchSize;
  }

  /**
   * Process reputation updates in batches
   * 
   * @param array $criticIds Array of critic IDs to update
   * @return array Results with success/failure counts
   */
  public function processBatch(array $criticIds): array
  {
    $results = [
      'total' => count($criticIds),
      'success' => 0,
      'failed' => 0,
      'errors' => []
    ];

    // Process in chunks
    $chunks = array_chunk($criticIds, $this->batchSize);

    foreach ($chunks as $chunkIndex => $chunk) {
      $startTime = microtime(true);

      try {
        // Fetch all evaluation outcomes for this batch in one query
        $outcomes = $this->fetchOutcomesBatch($chunk);

        // Calculate reputation for each critic
        foreach ($chunk as $criticId) {
          try {
            $criticOutcomes = $outcomes[$criticId] ?? [];
            
            if (empty($criticOutcomes)) {
              $results['failed']++;
              $results['errors'][] = "No outcomes found for critic: $criticId";
              continue;
            }

            // Calculate new reputation
            $reputation = $this->calculator->calculate($criticId, $criticOutcomes);

            // Store reputation (will be batched by store)
            $this->store->saveReputation($reputation);

            $results['success']++;

          } catch (\Exception $e) {
            $results['failed']++;
            $results['errors'][] = "Error processing $criticId: " . $e->getMessage();
            error_log("BatchReputationProcessor: Error processing $criticId: " . $e->getMessage());
          }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        error_log(sprintf(
          'BatchReputationProcessor: Processed chunk %d/%d (%d critics) in %.2fms',
          $chunkIndex + 1,
          count($chunks),
          count($chunk),
          $duration
        ));

      } catch (\Exception $e) {
        $results['failed'] += count($chunk);
        $results['errors'][] = "Batch error: " . $e->getMessage();
        error_log("BatchReputationProcessor: Batch error: " . $e->getMessage());
      }
    }

    return $results;
  }

  /**
   * Fetch evaluation outcomes for multiple critics in one query
   * 
   * @param array $criticIds Array of critic IDs
   * @return array Outcomes grouped by critic ID
   */
  private function fetchOutcomesBatch(array $criticIds): array
  {
    if (empty($criticIds)) {
      return [];
    }

    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($criticIds), '?'));

    $sql = "SELECT 
              critic_id,
              evaluation_id,
              critic_score,
              consensus_score,
              within_threshold,
              alignment_delta,
              feedback_accepted,
              evaluated_at
            FROM :table_rag_agent_reputation_evaluation_outcomes
            WHERE critic_id IN ($placeholders)
            ORDER BY critic_id, evaluated_at DESC";

    $this->db->prepare($sql);
    
    // Bind all critic IDs
    foreach ($criticIds as $index => $criticId) {
      $this->db->bindValue($index + 1, $criticId);
    }

    $this->db->execute();

    // Group outcomes by critic ID
    $outcomes = [];
    while ($row = $this->db->fetch()) {
      $criticId = $row['critic_id'];
      
      if (!isset($outcomes[$criticId])) {
        $outcomes[$criticId] = [];
      }

      $outcomes[$criticId][] = [
        'evaluationId' => $row['evaluation_id'],
        'criticScore' => (float)$row['critic_score'],
        'consensusScore' => (float)$row['consensus_score'],
        'withinThreshold' => (bool)$row['within_threshold'],
        'alignmentDelta' => (float)$row['alignment_delta'],
        'feedbackAccepted' => (bool)$row['feedback_accepted'],
        'evaluatedAt' => new \DateTimeImmutable($row['evaluated_at'])
      ];
    }

    return $outcomes;
  }

  /**
   * Process all pending reputation updates
   * 
   * @return array Results with success/failure counts
   */
  public function processAllPending(): array
  {
    // Get all critics with pending updates
    $sql = "SELECT DISTINCT critic_id 
            FROM :table_rag_agent_reputation_evaluation_outcomes
            WHERE evaluated_at > (
              SELECT COALESCE(MAX(calculated_at), '1970-01-01')
              FROM :table_rag_agent_reputation
              WHERE critic_id = :table_rag_agent_reputation_evaluation_outcomes.critic_id
            )";

    $this->db->prepare($sql);
    $this->db->execute();

    $criticIds = [];
    while ($row = $this->db->fetch()) {
      $criticIds[] = $row['critic_id'];
    }

    if (empty($criticIds)) {
      return [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'errors' => []
      ];
    }

    return $this->processBatch($criticIds);
  }

  /**
   * Get batch processing statistics
   * 
   * @return array Statistics about batch processing
   */
  public function getStatistics(): array
  {
    $sql = "SELECT 
              COUNT(DISTINCT critic_id) as total_critics,
              COUNT(*) as total_outcomes,
              AVG(alignment_delta) as avg_alignment,
              SUM(CASE WHEN within_threshold THEN 1 ELSE 0 END) / COUNT(*) as threshold_rate,
              SUM(CASE WHEN feedback_accepted THEN 1 ELSE 0 END) / COUNT(*) as acceptance_rate
            FROM :table_rag_agent_reputation_evaluation_outcomes
            WHERE evaluated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";

    $this->db->prepare($sql);
    $this->db->execute();

    $stats = $this->db->fetch();

    return [
      'total_critics' => (int)$stats['total_critics'],
      'total_outcomes' => (int)$stats['total_outcomes'],
      'avg_alignment' => (float)$stats['avg_alignment'],
      'threshold_rate' => (float)$stats['threshold_rate'],
      'acceptance_rate' => (float)$stats['acceptance_rate'],
      'batch_size' => $this->batchSize
    ];
  }
}
