<?php
/**
 * EvaluationRetryHandler Class
 *
 * Handles retry logic for failed evaluations.
 * Implements retry with different evaluators on failure and timeout handling.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;
use InvalidArgumentException;

class EvaluationRetryHandler
{
  private AgentCapabilityRegistry $capabilityRegistry;
  private $db;
  private bool $debug;
  
  // Configuration constants
  private const MAX_RETRY_ATTEMPTS = 3;
  private const RETRY_DELAY_SECONDS = 2;
  private const EVALUATION_TIMEOUT_SECONDS = 30;
  
  /**
   * Constructor
   *
   * Initializes the retry handler with required dependencies.
   */
  public function __construct()
  {
    $this->capabilityRegistry = new AgentCapabilityRegistry();
    $this->db = Registry::get('Db');
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }
  
  /**
   * Retry evaluation with different evaluator
   *
   * Attempts to retry a failed evaluation by selecting a different qualified
   * evaluator. Tracks retry attempts and excludes previously failed evaluators.
   *
   * @param string $outputId The output ID being evaluated
   * @param string $outputType The output type
   * @param mixed $output The output to evaluate
   * @param array $criteria Evaluation criteria
   * @param string $failedEvaluatorId The evaluator that failed
   * @param array $previousAttempts Array of previous evaluator IDs that failed
   * @return AgentEvaluation|null The evaluation result or null if all retries failed
   * @throws InvalidArgumentException If parameters are invalid
   */
  public function retryEvaluation(
    string $outputId,
    string $outputType,
    mixed $output,
    array $criteria,
    string $failedEvaluatorId,
    array $previousAttempts = []
  ): ?AgentEvaluation {
    // Validate parameters
    if (empty($outputId)) {
      throw new InvalidArgumentException('Output ID cannot be empty');
    }
    
    if (empty($outputType)) {
      throw new InvalidArgumentException('Output type cannot be empty');
    }
    
    // Check if max retries exceeded
    $attemptCount = count($previousAttempts) + 1; // +1 for current failed attempt
    if ($attemptCount >= self::MAX_RETRY_ATTEMPTS) {
      if ($this->debug) {
        error_log("EvaluationRetryHandler: Max retry attempts ({$attemptCount}) exceeded for output {$outputId}");
      }
      
      $this->logRetryFailure($outputId, $outputType, $failedEvaluatorId, $previousAttempts);
      return null;
    }
    
    try {
      // Get producer agent ID from criteria
      $producerAgentId = $criteria['producer_agent_id'] ?? 'unknown';
      
      // Build exclusion list (failed evaluator + previous attempts + producer)
      $excludedEvaluators = array_merge($previousAttempts, [$failedEvaluatorId, $producerAgentId]);
      
      // Select alternative evaluator
      $alternativeEvaluator = $this->selectAlternativeEvaluator(
        $outputType,
        $excludedEvaluators
      );
      
      if ($alternativeEvaluator === null) {
        if ($this->debug) {
          error_log("EvaluationRetryHandler: No alternative evaluators available for output {$outputId}");
        }
        
        $this->logRetryFailure($outputId, $outputType, $failedEvaluatorId, $previousAttempts);
        return null;
      }
      
      // Log retry attempt
      $this->logRetryAttempt($outputId, $outputType, $failedEvaluatorId, $alternativeEvaluator, $attemptCount);
      
      // Wait before retry (exponential backoff)
      $delaySeconds = self::RETRY_DELAY_SECONDS * pow(2, $attemptCount - 1);
      sleep(min($delaySeconds, 10)); // Cap at 10 seconds
      
      // Attempt evaluation with alternative evaluator
      $evaluation = $this->attemptEvaluationWithTimeout(
        $alternativeEvaluator,
        $outputId,
        $output,
        $criteria
      );
      
      if ($evaluation !== null) {
        if ($this->debug) {
          error_log("EvaluationRetryHandler: Retry successful with evaluator {$alternativeEvaluator} for output {$outputId}");
        }
        
        $this->logRetrySuccess($outputId, $outputType, $alternativeEvaluator, $attemptCount);
        return $evaluation;
      }
      
      // If evaluation failed, recursively retry with next evaluator
      $updatedAttempts = array_merge($previousAttempts, [$failedEvaluatorId]);
      return $this->retryEvaluation(
        $outputId,
        $outputType,
        $output,
        $criteria,
        $alternativeEvaluator,
        $updatedAttempts
      );
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("EvaluationRetryHandler: Retry failed - " . $e->getMessage());
      }
      
      $this->logRetryFailure($outputId, $outputType, $failedEvaluatorId, $previousAttempts);
      return null;
    }
  }
  
  /**
   * Attempt evaluation with timeout
   *
   * Attempts to perform an evaluation with a timeout to prevent hanging.
   * Returns null if evaluation times out or fails.
   *
   * @param string $evaluatorId The evaluator agent ID
   * @param string $outputId The output ID
   * @param mixed $output The output to evaluate
   * @param array $criteria Evaluation criteria
   * @return AgentEvaluation|null The evaluation or null if failed/timeout
   */
  private function attemptEvaluationWithTimeout(
    string $evaluatorId,
    string $outputId,
    mixed $output,
    array $criteria
  ): ?AgentEvaluation {
    $startTime = time();
    
    try {
      // TODO: In production, this would use actual agent communication with timeout
      // For now, we'll simulate evaluation with timeout check
      
      // Simulate evaluation work
      $elapsedTime = time() - $startTime;
      if ($elapsedTime > self::EVALUATION_TIMEOUT_SECONDS) {
        if ($this->debug) {
          error_log("EvaluationRetryHandler: Evaluation timeout for evaluator {$evaluatorId}");
        }
        return null;
      }
      
      // Create mock evaluation (replace with actual agent communication)
      $scores = [
        'accuracy' => 0.85,
        'completeness' => 0.80,
        'efficiency' => 0.75,
        'clarity' => 0.90
      ];
      
      $feedback = "Retry evaluation from {$evaluatorId}: The output meets criteria.";
      
      $strengths = [
        "Successful retry evaluation",
        "Alternative evaluator perspective"
      ];
      
      $improvements = [
        "Consider original feedback as well"
      ];
      
      return new AgentEvaluation(
        $evaluatorId,
        $outputId,
        $scores,
        $feedback,
        $strengths,
        $improvements
      );
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("EvaluationRetryHandler: Evaluation attempt failed - " . $e->getMessage());
      }
      return null;
    }
  }
  
  /**
   * Select alternative evaluator
   *
   * Selects an alternative evaluator for retry, excluding previously
   * failed evaluators and the producer agent.
   *
   * @param string $outputType The output type
   * @param array $excludedEvaluators Array of evaluator IDs to exclude
   * @return string|null The alternative evaluator ID or null if none available
   */
  private function selectAlternativeEvaluator(
    string $outputType,
    array $excludedEvaluators
  ): ?string {
    // Get all capable evaluators
    $capableEvaluators = $this->capabilityRegistry->getCapableEvaluators($outputType, 'competent');
    
    // Filter out excluded evaluators
    $availableEvaluators = array_filter($capableEvaluators, function($evaluator) use ($excludedEvaluators) {
      return !in_array($evaluator['agent_id'], $excludedEvaluators, true);
    });
    
    // If no competent evaluators available, try novice level
    if (empty($availableEvaluators)) {
      $capableEvaluators = $this->capabilityRegistry->getCapableEvaluators($outputType, 'novice');
      $availableEvaluators = array_filter($capableEvaluators, function($evaluator) use ($excludedEvaluators) {
        return !in_array($evaluator['agent_id'], $excludedEvaluators, true);
      });
    }
    
    // Return first available evaluator or null
    if (!empty($availableEvaluators)) {
      $firstEvaluator = reset($availableEvaluators);
      return $firstEvaluator['agent_id'];
    }
    
    return null;
  }
  
  /**
   * Log retry attempt
   *
   * Logs a retry attempt to the database for monitoring and analysis.
   *
   * @param string $outputId The output ID
   * @param string $outputType The output type
   * @param string $failedEvaluatorId The failed evaluator ID
   * @param string $retryEvaluatorId The retry evaluator ID
   * @param int $attemptNumber The attempt number
   */
  private function logRetryAttempt(
    string $outputId,
    string $outputType,
    string $failedEvaluatorId,
    string $retryEvaluatorId,
    int $attemptNumber
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_evaluation_retries 
              (output_id, output_type, failed_evaluator_id, retry_evaluator_id, 
               attempt_number, status, created_at)
              VALUES (:output_id, :output_type, :failed_evaluator_id, :retry_evaluator_id,
                      :attempt_number, 'attempting', NOW())";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':output_id', $outputId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->bindValue(':failed_evaluator_id', $failedEvaluatorId);
      $stmt->bindValue(':retry_evaluator_id', $retryEvaluatorId);
      $stmt->bindValue(':attempt_number', $attemptNumber);
      $stmt->execute();
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("EvaluationRetryHandler: Failed to log retry attempt - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Log retry success
   *
   * Logs a successful retry to the database.
   *
   * @param string $outputId The output ID
   * @param string $outputType The output type
   * @param string $successfulEvaluatorId The successful evaluator ID
   * @param int $attemptNumber The attempt number
   */
  private function logRetrySuccess(
    string $outputId,
    string $outputType,
    string $successfulEvaluatorId,
    int $attemptNumber
  ): void {
    try {
      $sql = "UPDATE :table_rag_evaluation_retries 
              SET status = 'success', resolved_at = NOW()
              WHERE output_id = :output_id 
              AND retry_evaluator_id = :retry_evaluator_id
              AND attempt_number = :attempt_number";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':output_id', $outputId);
      $stmt->bindValue(':retry_evaluator_id', $successfulEvaluatorId);
      $stmt->bindValue(':attempt_number', $attemptNumber);
      $stmt->execute();
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("EvaluationRetryHandler: Failed to log retry success - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Log retry failure
   *
   * Logs a failed retry (all attempts exhausted) to the database.
   *
   * @param string $outputId The output ID
   * @param string $outputType The output type
   * @param string $lastFailedEvaluatorId The last failed evaluator ID
   * @param array $previousAttempts Array of previous evaluator IDs
   */
  private function logRetryFailure(
    string $outputId,
    string $outputType,
    string $lastFailedEvaluatorId,
    array $previousAttempts
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_evaluation_retries 
              (output_id, output_type, failed_evaluator_id, retry_evaluator_id, 
               attempt_number, status, failure_reason, created_at, resolved_at)
              VALUES (:output_id, :output_type, :failed_evaluator_id, NULL,
                      :attempt_number, 'failed', :failure_reason, NOW(), NOW())";
      
      $attemptCount = count($previousAttempts) + 1;
      $failureReason = "All retry attempts exhausted. Failed evaluators: " . 
                       implode(', ', array_merge($previousAttempts, [$lastFailedEvaluatorId]));
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':output_id', $outputId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->bindValue(':failed_evaluator_id', $lastFailedEvaluatorId);
      $stmt->bindValue(':attempt_number', $attemptCount);
      $stmt->bindValue(':failure_reason', $failureReason);
      $stmt->execute();
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("EvaluationRetryHandler: Failed to log retry failure - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Get max retry attempts
   *
   * @return int The maximum number of retry attempts
   */
  public function getMaxRetryAttempts(): int
  {
    return self::MAX_RETRY_ATTEMPTS;
  }
  
  /**
   * Get evaluation timeout
   *
   * @return int The evaluation timeout in seconds
   */
  public function getEvaluationTimeout(): int
  {
    return self::EVALUATION_TIMEOUT_SECONDS;
  }
  
  /**
   * Get retry statistics
   *
   * Retrieves statistics about evaluation retries.
   *
   * @param array $filters Optional filters (output_type, date_range, etc.)
   * @return array Statistics array
   */
  public function getRetryStatistics(array $filters = []): array
  {
    try {
      $sql = "SELECT 
                COUNT(*) as total_retries,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_retries,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_retries,
                AVG(attempt_number) as avg_attempts,
                output_type
              FROM :table_rag_evaluation_retries";
      
      $conditions = [];
      $params = [];
      
      if (!empty($filters['output_type'])) {
        $conditions[] = "output_type = :output_type";
        $params[':output_type'] = $filters['output_type'];
      }
      
      if (!empty($filters['start_date'])) {
        $conditions[] = "created_at >= :start_date";
        $params[':start_date'] = $filters['start_date'];
      }
      
      if (!empty($filters['end_date'])) {
        $conditions[] = "created_at <= :end_date";
        $params[':end_date'] = $filters['end_date'];
      }
      
      if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
      }
      
      $sql .= " GROUP BY output_type";
      
      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();
      
      $stats = [
        'total_retries' => 0,
        'successful_retries' => 0,
        'failed_retries' => 0,
        'success_rate' => 0.0,
        'avg_attempts' => 0.0,
        'by_output_type' => []
      ];
      
      while ($row = $stmt->fetch()) {
        $totalRetries = (int)$row['total_retries'];
        $successfulRetries = (int)$row['successful_retries'];
        
        $stats['total_retries'] += $totalRetries;
        $stats['successful_retries'] += $successfulRetries;
        $stats['failed_retries'] += (int)$row['failed_retries'];
        
        $stats['by_output_type'][$row['output_type']] = [
          'total_retries' => $totalRetries,
          'successful_retries' => $successfulRetries,
          'failed_retries' => (int)$row['failed_retries'],
          'success_rate' => $totalRetries > 0 ? round($successfulRetries / $totalRetries * 100, 2) : 0.0,
          'avg_attempts' => round((float)$row['avg_attempts'], 2)
        ];
      }
      
      if ($stats['total_retries'] > 0) {
        $stats['success_rate'] = round($stats['successful_retries'] / $stats['total_retries'] * 100, 2);
      }
      
      return $stats;
      
    } catch (Exception $e) {
      return [
        'total_retries' => 0,
        'successful_retries' => 0,
        'failed_retries' => 0,
        'success_rate' => 0.0,
        'error' => $e->getMessage()
      ];
    }
  }
}
