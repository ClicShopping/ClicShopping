<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Jobs;

use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationCalculator;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\EvaluationOutcome;
use ClicShopping\OM\Registry;

/**
 * UpdateReputationJob
 * 
 * Asynchronous job for updating critic reputation scores based on evaluation outcomes.
 * Implements retry logic with exponential backoff and dead letter queue handling.
 * 
 * Requirements: 15.1, 15.3
 */
class UpdateReputationJob
{
  private string $criticId;
  private EvaluationOutcome $outcome;
  private int $maxAttempts = 3;
  private int $backoffSeconds = 60;
  private int $currentAttempt = 0;
  private ?ReputationCalculator $calculator = null;
  private ?ReputationStore $store = null;

  /**
   * Constructor
   * 
   * @param string $criticId The ID of the critic whose reputation is being updated
   * @param EvaluationOutcome $outcome The evaluation outcome data
   */
  public function __construct(string $criticId, EvaluationOutcome $outcome)
  {
    $this->criticId = $criticId;
    $this->outcome = $outcome;
  }

  /**
   * Handle the job execution
   * 
   * This method performs the reputation update. If it fails, it will be retried
   * up to maxAttempts times with exponential backoff.
   * 
   * @return void
   * @throws \Exception If the update fails
   */
  public function handle(): void
  {
    try {
      // Initialize dependencies if not already set
      if ($this->calculator === null) {
        $this->calculator = Registry::exists('ReputationCalculator')
          ? Registry::get('ReputationCalculator')
          : new ReputationCalculator();
      }

      if ($this->store === null) {
        $this->store = Registry::exists('ReputationStore')
          ? Registry::get('ReputationStore')
          : new ReputationStore();
      }

      // Perform the reputation update
      $this->calculator->updateReputation($this->criticId, $this->outcome);

      // Log successful update
      $this->logSuccess();

    } catch (\Exception $e) {
      // Log the error
      $this->logError($e);

      // Rethrow to trigger retry mechanism
      throw $e;
    }
  }

  /**
   * Handle job failure after all retry attempts
   * 
   * This method is called when the job has failed after all retry attempts.
   * It logs the failure and moves the job to the dead letter queue.
   * 
   * @param \Exception $exception The exception that caused the final failure
   * @return void
   */
  public function failed(\Exception $exception): void
  {
    try {
      // Log to dead letter queue
      $this->logToDeadLetterQueue($exception);

      // Generate alert for administrators
      $this->generateFailureAlert($exception);

      // Log the final failure
      error_log(sprintf(
        'UpdateReputationJob: Final failure after %d attempts for critic %s: %s',
        $this->maxAttempts,
        $this->criticId,
        $exception->getMessage()
      ));

    } catch (\Exception $e) {
      // Even if logging fails, we don't want to throw
      error_log('UpdateReputationJob: Failed to log job failure: ' . $e->getMessage());
    }
  }

  /**
   * Get the number of retry attempts for this job
   * 
   * @return int Maximum number of attempts
   */
  public function getMaxAttempts(): int
  {
    return $this->maxAttempts;
  }

  /**
   * Get the backoff time in seconds
   * 
   * @return int Backoff time in seconds
   */
  public function getBackoffSeconds(): int
  {
    return $this->backoffSeconds;
  }

  /**
   * Set the current attempt number
   * 
   * @param int $attempt Current attempt number
   * @return void
   */
  public function setCurrentAttempt(int $attempt): void
  {
    $this->currentAttempt = $attempt;
  }

  /**
   * Get the current attempt number
   * 
   * @return int Current attempt number
   */
  public function getCurrentAttempt(): int
  {
    return $this->currentAttempt;
  }

  /**
   * Calculate exponential backoff delay
   * 
   * @param int $attempt The attempt number (1-based)
   * @return int Delay in seconds
   */
  public function calculateBackoff(int $attempt): int
  {
    // Exponential backoff: backoffSeconds * (2 ^ (attempt - 1))
    // Attempt 1: 60s, Attempt 2: 120s, Attempt 3: 240s
    return $this->backoffSeconds * pow(2, $attempt - 1);
  }

  /**
   * Check if the job should be retried
   * 
   * @return bool True if the job should be retried
   */
  public function shouldRetry(): bool
  {
    return $this->currentAttempt < $this->maxAttempts;
  }

  /**
   * Log successful job execution
   * 
   * @return void
   */
  private function logSuccess(): void
  {
    error_log(sprintf(
      'UpdateReputationJob: Successfully updated reputation for critic %s (evaluation: %s)',
      $this->criticId,
      $this->outcome->evaluationId
    ));
  }

  /**
   * Log job execution error
   * 
   * @param \Exception $exception The exception that occurred
   * @return void
   */
  private function logError(\Exception $exception): void
  {
    error_log(sprintf(
      'UpdateReputationJob: Failed to update reputation for critic %s (attempt %d/%d): %s',
      $this->criticId,
      $this->currentAttempt,
      $this->maxAttempts,
      $exception->getMessage()
    ));
  }

  /**
   * Log job to dead letter queue
   * 
   * @param \Exception $exception The exception that caused the failure
   * @return void
   */
  private function logToDeadLetterQueue(\Exception $exception): void
  {
    try {
      $db = Registry::get('Db');

      $sql = "INSERT INTO :table_rag_agent_reputation_update_queue 
              (critic_id, evaluation_id, outcome_data, status, attempts, error_message, created_at, failed_at)
              VALUES (:critic_id, :evaluation_id, :outcome_data, 'failed', :attempts, :error_message, NOW(), NOW())";

      $db->prepare($sql);
      $db->bindValue(':critic_id', $this->criticId);
      $db->bindValue(':evaluation_id', $this->outcome->evaluationId);
      $db->bindValue(':outcome_data', json_encode($this->outcome));
      $db->bindValue(':attempts', $this->maxAttempts);
      $db->bindValue(':error_message', $exception->getMessage());
      $db->execute();

    } catch (\Exception $e) {
      error_log('UpdateReputationJob: Failed to log to dead letter queue: ' . $e->getMessage());
    }
  }

  /**
   * Generate failure alert for administrators
   * 
   * @param \Exception $exception The exception that caused the failure
   * @return void
   */
  private function generateFailureAlert(\Exception $exception): void
  {
    try {
      $db = Registry::get('Db');

      $sql = "INSERT INTO :table_rag_agent_reputation_alerts 
              (critic_id, alert_type, severity, message, context, created_at)
              VALUES (:critic_id, 'job_failure', 'high', :message, :context, NOW())";

      $message = sprintf(
        'Reputation update job failed after %d attempts for critic %s',
        $this->maxAttempts,
        $this->criticId
      );

      $context = json_encode([
        'evaluation_id' => $this->outcome->evaluationId,
        'error' => $exception->getMessage(),
        'attempts' => $this->maxAttempts,
        'trace' => $exception->getTraceAsString()
      ]);

      $db->prepare($sql);
      $db->bindValue(':critic_id', $this->criticId);
      $db->bindValue(':message', $message);
      $db->bindValue(':context', $context);
      $db->execute();

    } catch (\Exception $e) {
      error_log('UpdateReputationJob: Failed to generate failure alert: ' . $e->getMessage());
    }
  }

  /**
   * Set the calculator instance (for testing)
   * 
   * @param ReputationCalculator $calculator
   * @return void
   */
  public function setCalculator(ReputationCalculator $calculator): void
  {
    $this->calculator = $calculator;
  }

  /**
   * Set the store instance (for testing)
   * 
   * @param ReputationStore $store
   * @return void
   */
  public function setStore(ReputationStore $store): void
  {
    $this->store = $store;
  }

  /**
   * Get the critic ID
   * 
   * @return string
   */
  public function getCriticId(): string
  {
    return $this->criticId;
  }

  /**
   * Get the evaluation outcome
   * 
   * @return EvaluationOutcome
   */
  public function getOutcome(): EvaluationOutcome
  {
    return $this->outcome;
  }
}
