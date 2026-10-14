<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Jobs;

use ClicShopping\OM\Registry;

/**
 * JobQueue
 * 
 * Manages asynchronous job execution with retry logic and exponential backoff.
 * Provides a simple database-backed queue for reputation update jobs.
 * 
 * Requirements: 15.1, 15.3
 */
class JobQueue
{
  private $db;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Push a job onto the queue
   * 
   * @param UpdateReputationJob $job The job to queue
   * @return int The queue ID
   */
  public function push(UpdateReputationJob $job): int
  {
    $sql = "INSERT INTO :table_rag_agent_reputation_update_queue 
            (critic_id, evaluation_id, outcome_data, status, attempts, created_at, next_retry_at)
            VALUES (:critic_id, :evaluation_id, :outcome_data, 'pending', 0, NOW(), NOW())";

    $this->db->prepare($sql);
    $this->db->bindValue(':critic_id', $job->getCriticId());
    $this->db->bindValue(':evaluation_id', $job->getOutcome()->evaluationId);
    $this->db->bindValue(':outcome_data', json_encode($job->getOutcome()));
    $this->db->execute();

    return $this->db->lastInsertId();
  }

  /**
   * Process pending jobs from the queue
   * 
   * @param int $limit Maximum number of jobs to process
   * @return array Array of results with 'success' and 'failed' counts
   */
  public function processPending(int $limit = 10): array
  {
    $results = [
      'success' => 0,
      'failed' => 0,
      'retried' => 0
    ];

    // Get pending jobs that are ready to be processed
    $sql = "SELECT * FROM :table_rag_agent_reputation_update_queue 
            WHERE status IN ('pending', 'retrying') 
            AND next_retry_at <= NOW()
            ORDER BY created_at ASC 
            LIMIT :limit";

    $this->db->prepare($sql);
    $this->db->bindInt(':limit', $limit);
    $this->db->execute();

    $jobs = $this->db->fetchAll();

    foreach ($jobs as $jobData) {
      $result = $this->processJob($jobData);
      $results[$result]++;
    }

    return $results;
  }

  /**
   * Process a single job
   * 
   * @param array $jobData Job data from database
   * @return string Result: 'success', 'failed', or 'retried'
   */
  private function processJob(array $jobData): string
  {
    $queueId = $jobData['queue_id'];
    $criticId = $jobData['critic_id'];
    $attempts = (int)$jobData['attempts'] + 1;

    try {
      // Mark job as processing
      $this->updateJobStatus($queueId, 'processing', $attempts);

      // Reconstruct the job
      $outcome = json_decode($jobData['outcome_data'], true);
      $evaluationOutcome = $this->reconstructEvaluationOutcome($outcome);
      
      $job = new UpdateReputationJob($criticId, $evaluationOutcome);
      $job->setCurrentAttempt($attempts);

      // Execute the job
      $job->handle();

      // Mark as completed
      $this->updateJobStatus($queueId, 'completed', $attempts);

      return 'success';

    } catch (\Exception $e) {
      // Check if we should retry
      if ($attempts < 3) {
        // Calculate backoff delay
        $backoffSeconds = 60 * pow(2, $attempts - 1);
        $nextRetryAt = date('Y-m-d H:i:s', time() + $backoffSeconds);

        // Mark for retry
        $this->updateJobForRetry($queueId, $attempts, $e->getMessage(), $nextRetryAt);

        error_log(sprintf(
          'JobQueue: Job %d will retry in %d seconds (attempt %d/3)',
          $queueId,
          $backoffSeconds,
          $attempts
        ));

        return 'retried';

      } else {
        // Max attempts reached - mark as failed
        $this->updateJobStatus($queueId, 'failed', $attempts, $e->getMessage());

        // Call the failed handler
        $outcome = json_decode($jobData['outcome_data'], true);
        $evaluationOutcome = $this->reconstructEvaluationOutcome($outcome);
        $job = new UpdateReputationJob($criticId, $evaluationOutcome);
        $job->setCurrentAttempt($attempts);
        $job->failed($e);

        return 'failed';
      }
    }
  }

  /**
   * Update job status
   * 
   * @param int $queueId Queue ID
   * @param string $status New status
   * @param int $attempts Number of attempts
   * @param string|null $errorMessage Error message if failed
   * @return void
   */
  private function updateJobStatus(int $queueId, string $status, int $attempts, ?string $errorMessage = null): void
  {
    $sql = "UPDATE :table_rag_agent_reputation_update_queue 
            SET status = :status, 
                attempts = :attempts, 
                error_message = :error_message,
                updated_at = NOW()";

    if ($status === 'completed') {
      $sql .= ", completed_at = NOW()";
    } elseif ($status === 'failed') {
      $sql .= ", failed_at = NOW()";
    }

    $sql .= " WHERE queue_id = :queue_id";

    $this->db->prepare($sql);
    $this->db->bindValue(':status', $status);
    $this->db->bindInt(':attempts', $attempts);
    $this->db->bindValue(':error_message', $errorMessage);
    $this->db->bindInt(':queue_id', $queueId);
    $this->db->execute();
  }

  /**
   * Update job for retry
   * 
   * @param int $queueId Queue ID
   * @param int $attempts Number of attempts
   * @param string $errorMessage Error message
   * @param string $nextRetryAt Next retry timestamp
   * @return void
   */
  private function updateJobForRetry(int $queueId, int $attempts, string $errorMessage, string $nextRetryAt): void
  {
    $sql = "UPDATE :table_rag_agent_reputation_update_queue 
            SET status = 'retrying', 
                attempts = :attempts, 
                error_message = :error_message,
                next_retry_at = :next_retry_at,
                updated_at = NOW()
            WHERE queue_id = :queue_id";

    $this->db->prepare($sql);
    $this->db->bindInt(':attempts', $attempts);
    $this->db->bindValue(':error_message', $errorMessage);
    $this->db->bindValue(':next_retry_at', $nextRetryAt);
    $this->db->bindInt(':queue_id', $queueId);
    $this->db->execute();
  }

  /**
   * Reconstruct EvaluationOutcome from array data
   * 
   * @param array $data Outcome data
   * @return \ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\EvaluationOutcome
   */
  private function reconstructEvaluationOutcome(array $data): \ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\EvaluationOutcome
  {
    $outcome = new \ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\EvaluationOutcome();
    $outcome->evaluationId = $data['evaluationId'] ?? $data['evaluation_id'] ?? '';
    $outcome->criticId = $data['criticId'] ?? $data['critic_id'] ?? '';
    $outcome->criticScore = (float)($data['criticScore'] ?? $data['critic_score'] ?? 0.0);
    $outcome->consensusScore = (float)($data['consensusScore'] ?? $data['consensus_score'] ?? 0.0);
    $outcome->withinThreshold = (bool)($data['withinThreshold'] ?? $data['within_threshold'] ?? false);
    $outcome->alignmentDelta = (float)($data['alignmentDelta'] ?? $data['alignment_delta'] ?? 0.0);
    $outcome->feedbackAccepted = (bool)($data['feedbackAccepted'] ?? $data['feedback_accepted'] ?? false);
    $outcome->evaluatedAt = new \DateTimeImmutable($data['evaluatedAt'] ?? $data['evaluated_at'] ?? 'now');

    return $outcome;
  }

  /**
   * Get queue statistics
   * 
   * @return array Statistics about the queue
   */
  public function getStatistics(): array
  {
    $sql = "SELECT 
              status,
              COUNT(*) as count
            FROM :table_rag_agent_reputation_update_queue
            GROUP BY status";

    $this->db->prepare($sql);
    $this->db->execute();

    $stats = [];
    while ($row = $this->db->fetch()) {
      $stats[$row['status']] = (int)$row['count'];
    }

    return $stats;
  }

  /**
   * Clean up old completed jobs
   * 
   * @param int $daysOld Number of days to keep completed jobs
   * @return int Number of jobs deleted
   */
  public function cleanupOldJobs(int $daysOld = 7): int
  {
    $sql = "DELETE FROM :table_rag_agent_reputation_update_queue 
            WHERE status = 'completed' 
            AND completed_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

    $this->db->prepare($sql);
    $this->db->bindInt(':days', $daysOld);
    $this->db->execute();

    return $this->db->rowCount();
  }

  /**
   * Get failed jobs for investigation
   * 
   * @param int $limit Maximum number of jobs to return
   * @return array Array of failed job data
   */
  public function getFailedJobs(int $limit = 50): array
  {
    $sql = "SELECT * FROM :table_rag_agent_reputation_update_queue 
            WHERE status = 'failed' 
            ORDER BY failed_at DESC 
            LIMIT :limit";

    $this->db->prepare($sql);
    $this->db->bindInt(':limit', $limit);
    $this->db->execute();

    return $this->db->fetchAll();
  }

  /**
   * Retry a failed job
   * 
   * @param int $queueId Queue ID
   * @return bool True if job was reset for retry
   */
  public function retryFailedJob(int $queueId): bool
  {
    $sql = "UPDATE :table_rag_agent_reputation_update_queue 
            SET status = 'pending', 
                attempts = 0,
                error_message = NULL,
                next_retry_at = NOW(),
                updated_at = NOW()
            WHERE queue_id = :queue_id 
            AND status = 'failed'";

    $this->db->prepare($sql);
    $this->db->bindInt(':queue_id', $queueId);
    $this->db->execute();

    return $this->db->rowCount() > 0;
  }
}
