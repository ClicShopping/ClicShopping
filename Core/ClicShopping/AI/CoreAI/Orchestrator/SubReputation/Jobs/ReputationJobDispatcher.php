<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Jobs;

use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\EvaluationOutcome;
use ClicShopping\OM\Registry;

/**
 * ReputationJobDispatcher
 * 
 * Provides a simple interface for dispatching reputation update jobs to the queue.
 * This class acts as a facade to simplify job queuing throughout the application.
 * 
 * Requirements: 15.1, 15.3
 */
class ReputationJobDispatcher
{
  private JobQueue $queue;

  /**
   * Constructor
   */
  public function __construct()
  {
    if (!Registry::exists('ReputationJobQueue')) {
      Registry::set('ReputationJobQueue', new JobQueue());
    }
    $this->queue = Registry::get('ReputationJobQueue');
  }

  /**
   * Dispatch a reputation update job
   * 
   * @param string $criticId The critic ID
   * @param EvaluationOutcome $outcome The evaluation outcome
   * @return int The queue ID
   */
  public function dispatch(string $criticId, EvaluationOutcome $outcome): int
  {
    $job = new UpdateReputationJob($criticId, $outcome);
    return $this->queue->push($job);
  }

  /**
   * Dispatch multiple reputation update jobs
   * 
   * @param array $jobs Array of ['criticId' => string, 'outcome' => EvaluationOutcome]
   * @return array Array of queue IDs
   */
  public function dispatchBatch(array $jobs): array
  {
    $queueIds = [];
    
    foreach ($jobs as $jobData) {
      if (!isset($jobData['criticId']) || !isset($jobData['outcome'])) {
        error_log('ReputationJobDispatcher: Invalid job data in batch, skipping');
        continue;
      }
      
      $queueIds[] = $this->dispatch($jobData['criticId'], $jobData['outcome']);
    }
    
    return $queueIds;
  }

  /**
   * Get queue statistics
   * 
   * @return array Statistics about the queue
   */
  public function getStatistics(): array
  {
    return $this->queue->getStatistics();
  }

  /**
   * Get failed jobs
   * 
   * @param int $limit Maximum number of jobs to return
   * @return array Array of failed job data
   */
  public function getFailedJobs(int $limit = 50): array
  {
    return $this->queue->getFailedJobs($limit);
  }

  /**
   * Retry a failed job
   * 
   * @param int $queueId Queue ID
   * @return bool True if job was reset for retry
   */
  public function retryFailedJob(int $queueId): bool
  {
    return $this->queue->retryFailedJob($queueId);
  }

  /**
   * Retry all failed jobs
   * 
   * @return int Number of jobs reset for retry
   */
  public function retryAllFailedJobs(): int
  {
    $failedJobs = $this->queue->getFailedJobs(1000);
    $count = 0;
    
    foreach ($failedJobs as $job) {
      if ($this->queue->retryFailedJob($job['queue_id'])) {
        $count++;
      }
    }
    
    return $count;
  }

  /**
   * Clean up old completed jobs
   * 
   * @param int $daysOld Number of days to keep completed jobs
   * @return int Number of jobs deleted
   */
  public function cleanupOldJobs(int $daysOld = 7): int
  {
    return $this->queue->cleanupOldJobs($daysOld);
  }

  /**
   * Get the underlying queue instance
   * 
   * @return JobQueue
   */
  public function getQueue(): JobQueue
  {
    return $this->queue;
  }
}
