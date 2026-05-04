<?php
/**
 * SelfEvaluationPrevention Class
 *
 * Prevents agents from evaluating their own outputs to maintain
 * evaluation objectivity and integrity. Tracks output producers,
 * validates evaluator selections, and logs violation attempts.
 *
 * This class implements Requirement 14: Self-Evaluation Prevention
 * to ensure that evaluations remain unbiased by preventing agents
 * from evaluating their own work.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;

class SelfEvaluationPrevention
{
  private $db;
  private bool $debug;
  private AuditLogger $auditLogger;
  
  /**
   * Constructor
   *
   * Initializes the self-evaluation prevention system with database
   * connection and audit logging capabilities.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->auditLogger = new AuditLogger();
  }
  
  /**
   * Check if an agent can evaluate a specific output
   *
   * Verifies that the evaluator agent did not produce the output being evaluated.
   * This is the primary method for enforcing self-evaluation prevention.
   *
   * Implements Requirement 14.1: Verify agent did not produce the output
   * Implements Requirement 14.2: Reject self-evaluation attempts
   *
   * @param string $evaluatorAgentId The agent ID attempting to evaluate
   * @param string $outputId The output ID being evaluated
   * @return bool True if agent can evaluate, false if self-evaluation detected
   */
  public function canEvaluate(string $evaluatorAgentId, string $outputId): bool
  {
    try {
      // Get the producer of this output
      $producerAgentId = $this->getOutputProducer($outputId);
      
      // If producer is unknown, allow evaluation (fail open for flexibility)
      if ($producerAgentId === null || $producerAgentId === 'unknown') {
        if ($this->debug) {
          error_log("SelfEvaluationPrevention: Producer unknown for output {$outputId}, allowing evaluation");
        }
        return true;
      }
      
      // Check if evaluator is the same as producer
      $isSelfEvaluation = ($evaluatorAgentId === $producerAgentId);
      
      if ($isSelfEvaluation) {
        // Log the violation attempt
        $this->logViolationAttempt($evaluatorAgentId, $outputId, $producerAgentId);
        
        if ($this->debug) {
          error_log("SelfEvaluationPrevention: Blocked self-evaluation attempt by {$evaluatorAgentId} for output {$outputId}");
        }
        
        return false;
      }
      
      return true;
      
    } catch (Exception $e) {
      // Log error and fail open (allow evaluation) to prevent system disruption
      if ($this->debug) {
        error_log("SelfEvaluationPrevention: Error checking evaluation permission: " . $e->getMessage());
      }
      
      // Audit the error
      $this->auditLogger->logAction(
        $evaluatorAgentId,
        'self_evaluation_check_error',
        'error',
        [
          'output_id' => $outputId,
          'error' => $e->getMessage()
        ]
      );
      
      return true; // Fail open
    }
  }
  
  /**
   * Get the producer agent ID for a specific output
   *
   * Retrieves the agent ID that produced the specified output by querying
   * the evaluation records or output tracking tables.
   *
   * Implements Requirement 14.3: Maintain record of output producers
   *
   * @param string $outputId The output ID to look up
   * @return string|null The producer agent ID, or null if not found
   */
  public function getOutputProducer(string $outputId): ?string
  {
    try {
      // First, try to get producer from existing evaluations
      // (evaluations store the producer_agent_id)
      $Qproducer = $this->db->prepare('
        SELECT producer_agent_id
        FROM :table_rag_agent_evaluations
        WHERE output_id = :output_id
        LIMIT 1
      ');
      
      $Qproducer->bindValue(':output_id', $outputId);
      $Qproducer->execute();
      
      if ($Qproducer->rowCount() > 0) {
        $producerAgentId = $Qproducer->value('producer_agent_id');
        return $producerAgentId;
      }
      
      // If not found in evaluations, check if there's an output tracking table
      // (This would be added in task 24.3 for comprehensive producer tracking)
      // For now, return null if not found
      
      if ($this->debug) {
        error_log("SelfEvaluationPrevention: No producer found for output {$outputId}");
      }
      
      return null;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("SelfEvaluationPrevention: Error getting output producer: " . $e->getMessage());
      }
      return null;
    }
  }
  
  /**
   * Validate evaluator selection to ensure no self-evaluation
   *
   * Checks a list of selected evaluators against the output producer
   * to ensure none of them are attempting self-evaluation.
   *
   * Implements Requirement 14.4: Exclude producer from evaluator pool
   *
   * @param array $evaluatorIds Array of evaluator agent IDs
   * @param string $outputId The output ID being evaluated
   * @param string $producerAgentId The agent ID that produced the output
   * @return array Filtered array of evaluator IDs with producer removed
   */
  public function validateEvaluatorSelection(
    array $evaluatorIds,
    string $outputId,
    string $producerAgentId
  ): array {
    if (empty($evaluatorIds)) {
      return [];
    }
    
    // Filter out the producer agent from the evaluator list
    $validEvaluators = array_filter($evaluatorIds, function($evaluatorId) use ($producerAgentId, $outputId) {
      $isValid = ($evaluatorId !== $producerAgentId);
      
      if (!$isValid && $this->debug) {
        error_log("SelfEvaluationPrevention: Removed producer {$producerAgentId} from evaluator pool for output {$outputId}");
      }
      
      return $isValid;
    });
    
    // Re-index array to maintain sequential keys
    $validEvaluators = array_values($validEvaluators);
    
    // Log if any evaluators were removed
    $removedCount = count($evaluatorIds) - count($validEvaluators);
    if ($removedCount > 0) {
      $this->auditLogger->logAction(
        $producerAgentId,
        'evaluator_pool_filtered',
        'success',
        [
          'output_id' => $outputId,
          'original_count' => count($evaluatorIds),
          'filtered_count' => count($validEvaluators),
          'removed_count' => $removedCount
        ]
      );
    }
    
    return $validEvaluators;
  }
  
  /**
   * Log a self-evaluation violation attempt
   *
   * Records attempted self-evaluation violations to the audit log
   * for security review and monitoring.
   *
   * Implements Requirement 14.5: Log self-evaluation violations
   *
   * @param string $evaluatorAgentId The agent that attempted self-evaluation
   * @param string $outputId The output ID involved
   * @param string $producerAgentId The producer agent ID (should match evaluator)
   * @return void
   */
  public function logViolationAttempt(
    string $evaluatorAgentId,
    string $outputId,
    string $producerAgentId
  ): void {
    try {
      // Log to audit system
      $this->auditLogger->logAction(
        $evaluatorAgentId,
        'self_evaluation_violation',
        'denied',
        [
          'output_id' => $outputId,
          'producer_agent_id' => $producerAgentId,
          'violation_type' => 'self_evaluation_attempt',
          'severity' => 'medium',
          'message' => "Agent {$evaluatorAgentId} attempted to evaluate its own output {$outputId}"
        ]
      );
      
      // Also log to error log for immediate visibility
      if ($this->debug) {
        error_log("SECURITY: Self-evaluation violation attempt by {$evaluatorAgentId} for output {$outputId}");
      }
      
    } catch (Exception $e) {
      // Even if logging fails, don't throw exception to avoid disrupting the evaluation flow
      if ($this->debug) {
        error_log("SelfEvaluationPrevention: Failed to log violation attempt: " . $e->getMessage());
      }
    }
  }
  
  /**
   * Track output producer for future evaluation checks
   *
   * Stores the producer agent ID for an output to enable future
   * self-evaluation prevention checks. This method should be called
   * when an output is first created.
   *
   * Note: This is a helper method for task 24.3 (database schema updates).
   * Currently, producer tracking is done through the evaluations table.
   *
   * @param string $outputId The output ID
   * @param string $producerAgentId The agent that produced the output
   * @param string $outputType The type of output
   * @return bool True if tracking succeeded, false otherwise
   */
  public function trackOutputProducer(
    string $outputId,
    string $producerAgentId,
    string $outputType
  ): bool {
    try {
      // This method is a placeholder for task 24.3
      // When the output tracking table is created, this will insert records there
      // For now, producer tracking happens through the evaluations table
      
      if ($this->debug) {
        error_log("SelfEvaluationPrevention: Tracking output {$outputId} produced by {$producerAgentId}");
      }
      
      // Audit the tracking
      $this->auditLogger->logAction(
        $producerAgentId,
        'output_producer_tracked',
        'success',
        [
          'output_id' => $outputId,
          'output_type' => $outputType
        ]
      );
      
      return true;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("SelfEvaluationPrevention: Error tracking output producer: " . $e->getMessage());
      }
      return false;
    }
  }
  
  /**
   * Get statistics on self-evaluation prevention
   *
   * Returns metrics about blocked self-evaluation attempts and
   * overall system effectiveness.
   *
   * @param int $days Number of days to look back (default: 30)
   * @return array Statistics array with violation counts and trends
   */
  public function getPreventionStatistics(int $days = 30): array
  {
    try {
      $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
      
      // Query audit log for violation attempts
      $Qstats = $this->db->prepare('
        SELECT 
          COUNT(*) as total_violations,
          COUNT(DISTINCT JSON_EXTRACT(action_details, "$.producer_agent_id")) as unique_violators,
          DATE(created_at) as violation_date
        FROM :table_rag_agent_audit_log
        WHERE action_type = :action_type
          AND created_at >= :start_date
        GROUP BY DATE(created_at)
        ORDER BY violation_date DESC
      ');
      
      $Qstats->bindValue(':action_type', 'self_evaluation_violation');
      $Qstats->bindValue(':start_date', $startDate);
      $Qstats->execute();
      
      $dailyStats = [];
      $totalViolations = 0;
      
      while ($Qstats->fetch()) {
        $date = $Qstats->value('violation_date');
        $count = (int)$Qstats->value('total_violations');
        $dailyStats[$date] = $count;
        $totalViolations += $count;
      }
      
      return [
        'total_violations' => $totalViolations,
        'daily_stats' => $dailyStats,
        'period_days' => $days,
        'average_per_day' => $totalViolations / max(1, $days)
      ];
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("SelfEvaluationPrevention: Error getting statistics: " . $e->getMessage());
      }
      
      return [
        'total_violations' => 0,
        'daily_stats' => [],
        'period_days' => $days,
        'average_per_day' => 0,
        'error' => $e->getMessage()
      ];
    }
  }
}
