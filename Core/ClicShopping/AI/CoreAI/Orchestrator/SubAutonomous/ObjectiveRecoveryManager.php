<?php
/**
 * ObjectiveRecoveryManager Class
 *
 * Manages recovery mechanisms for failed objectives.
 * Provides resume and retry capabilities for objectives that failed
 * to complete, allowing recovery after issue resolution.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;
use InvalidArgumentException;

class ObjectiveRecoveryManager
{
  private ObjectiveRegistry $objectiveRegistry;
  private $db;
  private bool $debug;
  private AuditLogger $auditLogger;
  
  // Configuration constants
  private const MAX_RETRY_ATTEMPTS = 3;
  private const RETRY_DELAY_MINUTES = 30;
  private const RECOVERY_VALIDATION_REQUIRED = true;
  
  /**
   * Constructor
   *
   * Initializes the recovery manager with required dependencies.
   */
  public function __construct()
  {
    $this->objectiveRegistry = new ObjectiveRegistry();
    $this->db = Registry::get('Db');
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->auditLogger = new AuditLogger();
  }
  
  /**
   * Resume failed objective
   *
   * Resumes a failed objective after issue resolution.
   * Validates prerequisites before resuming and updates objective status.
   *
   * @param string $objectiveId The objective ID to resume
   * @param array $resolutionContext Context about how the issue was resolved
   * @return bool True if resumed successfully
   * @throws InvalidArgumentException If objective ID is invalid
   * @throws Exception If resume operation fails
   */
  public function resumeObjective(string $objectiveId, array $resolutionContext = []): bool
  {
    if (empty($objectiveId)) {
      throw new InvalidArgumentException('Objective ID cannot be empty');
    }
    
    try {
      // Get the objective
      $objective = $this->objectiveRegistry->getObjective($objectiveId);
      
      if ($objective === null) {
        throw new Exception("Objective {$objectiveId} not found");
      }
      
      // Verify objective is in failed status
      if ($objective->getStatus() !== 'failed') {
        throw new Exception("Objective {$objectiveId} is not in failed status (current: {$objective->getStatus()})");
      }
      
      // Validate prerequisites if required
      if (self::RECOVERY_VALIDATION_REQUIRED) {
        $validationResult = $this->validateRecoveryPrerequisites($objective, $resolutionContext);
        
        if (!$validationResult['valid']) {
          throw new Exception("Recovery validation failed: " . $validationResult['reason']);
        }
      }
      
      // Check retry count
      $retryCount = $this->getRetryCount($objectiveId);
      if ($retryCount >= self::MAX_RETRY_ATTEMPTS) {
        throw new Exception("Maximum retry attempts ({$retryCount}) exceeded for objective {$objectiveId}");
      }
      
      // Log recovery attempt
      $this->logRecoveryAttempt($objectiveId, 'resume', $resolutionContext, $retryCount + 1);
      
      // Update objective status to active
      $this->objectiveRegistry->updateObjectiveStatus($objectiveId, 'active');
      
      // Clear failure reason
      $this->clearFailureReason($objectiveId);
      
      // Audit the resume action
      $this->auditLogger->logAction(
        $objective->getAgentId(),
        'objective_resumed',
        'resumed',
        [
          'objective_id' => $objectiveId,
          'goal_statement' => $objective->getGoalStatement(),
          'retry_count' => $retryCount + 1,
          'resolution_context' => $resolutionContext
        ]
      );
      
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Resumed objective {$objectiveId} (attempt " . ($retryCount + 1) . ")");
      }
      
      return true;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Failed to resume objective - " . $e->getMessage());
      }
      throw $e;
    }
  }
  
  /**
   * Retry failed objective
   *
   * Retries a failed objective with modified parameters.
   * Allows adjusting objective parameters before retry.
   *
   * @param string $objectiveId The objective ID to retry
   * @param array $modifiedParameters Modified objective parameters
   * @return bool True if retry initiated successfully
   * @throws InvalidArgumentException If parameters are invalid
   * @throws Exception If retry operation fails
   */
  public function retryObjective(string $objectiveId, array $modifiedParameters = []): bool
  {
    if (empty($objectiveId)) {
      throw new InvalidArgumentException('Objective ID cannot be empty');
    }
    
    try {
      // Get the objective
      $objective = $this->objectiveRegistry->getObjective($objectiveId);
      
      if ($objective === null) {
        throw new Exception("Objective {$objectiveId} not found");
      }
      
      // Verify objective is in failed status
      if ($objective->getStatus() !== 'failed') {
        throw new Exception("Objective {$objectiveId} is not in failed status");
      }
      
      // Check retry count
      $retryCount = $this->getRetryCount($objectiveId);
      if ($retryCount >= self::MAX_RETRY_ATTEMPTS) {
        throw new Exception("Maximum retry attempts exceeded for objective {$objectiveId}");
      }
      
      // Apply modified parameters if provided
      if (!empty($modifiedParameters)) {
        $this->applyModifiedParameters($objectiveId, $modifiedParameters);
      }
      
      // Log recovery attempt
      $this->logRecoveryAttempt($objectiveId, 'retry', $modifiedParameters, $retryCount + 1);
      
      // Reset objective to pending status for re-approval
      $this->objectiveRegistry->updateObjectiveStatus($objectiveId, 'pending');
      
      // Clear failure reason
      $this->clearFailureReason($objectiveId);
      
      // Audit the retry action
      $this->auditLogger->logAction(
        $objective->getAgentId(),
        'objective_retried',
        'retried',
        [
          'objective_id' => $objectiveId,
          'goal_statement' => $objective->getGoalStatement(),
          'retry_count' => $retryCount + 1,
          'modified_parameters' => $modifiedParameters
        ]
      );
      
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Retrying objective {$objectiveId} (attempt " . ($retryCount + 1) . ")");
      }
      
      return true;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Failed to retry objective - " . $e->getMessage());
      }
      throw $e;
    }
  }
  
  /**
   * Get recoverable objectives
   *
   * Retrieves all failed objectives that are eligible for recovery.
   *
   * @param array $filters Optional filters (agent_id, priority, etc.)
   * @return array Array of recoverable objectives
   */
  public function getRecoverableObjectives(array $filters = []): array
  {
    try {
      $sql = "SELECT 
                o.objective_id,
                o.agent_id,
                o.goal_statement,
                o.priority,
                o.failure_reason,
                o.created_at,
                o.completed_at,
                COUNT(r.recovery_id) as retry_count
              FROM :table_rag_agent_objectives o
              LEFT JOIN :table_rag_objective_recoveries r 
                ON o.objective_id = r.objective_id
              WHERE o.status = 'failed'";
      
      $conditions = [];
      $params = [];
      
      if (!empty($filters['agent_id'])) {
        $conditions[] = "o.agent_id = :agent_id";
        $params[':agent_id'] = $filters['agent_id'];
      }
      
      if (!empty($filters['priority'])) {
        $conditions[] = "o.priority = :priority";
        $params[':priority'] = $filters['priority'];
      }
      
      if (!empty($filters['max_retries'])) {
        $sql .= " GROUP BY o.objective_id
                  HAVING retry_count < :max_retries";
        $params[':max_retries'] = $filters['max_retries'];
      } else {
        $sql .= " GROUP BY o.objective_id
                  HAVING retry_count < " . self::MAX_RETRY_ATTEMPTS;
      }
      
      if (!empty($conditions)) {
        $sql = str_replace('WHERE o.status', 'WHERE ' . implode(' AND ', $conditions) . ' AND o.status', $sql);
      }
      
      $sql .= " ORDER BY o.priority DESC, o.created_at ASC";
      
      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();
      
      $objectives = [];
      while ($row = $stmt->fetch()) {
        $objectives[] = [
          'objective_id' => $row['objective_id'],
          'agent_id' => $row['agent_id'],
          'goal_statement' => $row['goal_statement'],
          'priority' => $row['priority'],
          'failure_reason' => $row['failure_reason'],
          'created_at' => $row['created_at'],
          'failed_at' => $row['completed_at'],
          'retry_count' => (int)$row['retry_count'],
          'retries_remaining' => self::MAX_RETRY_ATTEMPTS - (int)$row['retry_count']
        ];
      }
      
      return $objectives;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Failed to get recoverable objectives - " . $e->getMessage());
      }
      return [];
    }
  }
  
  /**
   * Validate recovery prerequisites
   *
   * Validates that prerequisites are met before resuming an objective.
   *
   * @param LocalObjective $objective The objective to validate
   * @param array $resolutionContext Resolution context
   * @return array Validation result with 'valid' boolean and 'reason' string
   */
  private function validateRecoveryPrerequisites(
    LocalObjective $objective,
    array $resolutionContext
  ): array {
    // Check if resolution context is provided
    if (empty($resolutionContext)) {
      return [
        'valid' => false,
        'reason' => 'No resolution context provided'
      ];
    }
    
    // Check if issue resolution is documented
    if (empty($resolutionContext['resolution_description'])) {
      return [
        'valid' => false,
        'reason' => 'Resolution description is required'
      ];
    }
    
    // Check if agent is not suspended
    $suspensionManager = new PerformanceSuspensionManager();
    if ($suspensionManager->isAgentSuspended($objective->getAgentId())) {
      return [
        'valid' => false,
        'reason' => 'Agent is currently suspended'
      ];
    }
    
    // All validations passed
    return [
      'valid' => true,
      'reason' => 'All prerequisites met'
    ];
  }
  
  /**
   * Apply modified parameters
   *
   * Applies modified parameters to an objective before retry.
   *
   * @param string $objectiveId The objective ID
   * @param array $modifiedParameters Modified parameters
   */
  private function applyModifiedParameters(string $objectiveId, array $modifiedParameters): void
  {
    try {
      $updates = [];
      $params = [':objective_id' => $objectiveId];
      
      if (isset($modifiedParameters['priority'])) {
        $updates[] = "priority = :priority";
        $params[':priority'] = $modifiedParameters['priority'];
      }
      
      if (isset($modifiedParameters['estimated_completion_time'])) {
        $updates[] = "estimated_completion_time = :estimated_completion_time";
        $params[':estimated_completion_time'] = $modifiedParameters['estimated_completion_time'];
      }
      
      if (isset($modifiedParameters['success_criteria'])) {
        $updates[] = "success_criteria = :success_criteria";
        $params[':success_criteria'] = json_encode($modifiedParameters['success_criteria']);
      }
      
      if (!empty($updates)) {
        $sql = "UPDATE :table_rag_agent_objectives 
                SET " . implode(', ', $updates) . "
                WHERE objective_id = :objective_id";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
          $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        if ($this->debug) {
          error_log("ObjectiveRecoveryManager: Applied modified parameters to objective {$objectiveId}");
        }
      }
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Failed to apply modified parameters - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Get retry count
   *
   * Gets the number of retry attempts for an objective.
   *
   * @param string $objectiveId The objective ID
   * @return int The retry count
   */
  private function getRetryCount(string $objectiveId): int
  {
    try {
      $sql = "SELECT COUNT(*) as retry_count
              FROM :table_rag_objective_recoveries
              WHERE objective_id = :objective_id";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->execute();
      
      $row = $stmt->fetch();
      return (int)$row['retry_count'];
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Failed to get retry count - " . $e->getMessage());
      }
      return 0;
    }
  }
  
  /**
   * Log recovery attempt
   *
   * Logs a recovery attempt to the database.
   *
   * @param string $objectiveId The objective ID
   * @param string $recoveryType The recovery type (resume or retry)
   * @param array $context Recovery context
   * @param int $attemptNumber The attempt number
   */
  private function logRecoveryAttempt(
    string $objectiveId,
    string $recoveryType,
    array $context,
    int $attemptNumber
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_objective_recoveries 
              (recovery_id, objective_id, recovery_type, attempt_number,
               recovery_context, initiated_at, status)
              VALUES (:recovery_id, :objective_id, :recovery_type, :attempt_number,
                      :recovery_context, NOW(), 'initiated')";
      
      $recoveryId = $this->generateRecoveryId();
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':recovery_id', $recoveryId);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->bindValue(':recovery_type', $recoveryType);
      $stmt->bindValue(':attempt_number', $attemptNumber);
      $stmt->bindValue(':recovery_context', json_encode($context));
      $stmt->execute();
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Failed to log recovery attempt - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Clear failure reason
   *
   * Clears the failure reason for an objective.
   *
   * @param string $objectiveId The objective ID
   */
  private function clearFailureReason(string $objectiveId): void
  {
    try {
      $sql = "UPDATE :table_rag_agent_objectives 
              SET failure_reason = NULL
              WHERE objective_id = :objective_id";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->execute();
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ObjectiveRecoveryManager: Failed to clear failure reason - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Generate recovery ID
   *
   * Generates a unique ID for the recovery attempt.
   *
   * @return string The recovery ID
   */
  private function generateRecoveryId(): string
  {
    return 'rec_' . uniqid() . '_' . bin2hex(random_bytes(8));
  }
  
  /**
   * Get recovery statistics
   *
   * Retrieves statistics about objective recoveries.
   *
   * @param array $filters Optional filters (date_range, agent_id, etc.)
   * @return array Statistics array
   */
  public function getRecoveryStatistics(array $filters = []): array
  {
    try {
      $sql = "SELECT 
                COUNT(DISTINCT objective_id) as total_recovered_objectives,
                COUNT(*) as total_recovery_attempts,
                SUM(CASE WHEN recovery_type = 'resume' THEN 1 ELSE 0 END) as resume_attempts,
                SUM(CASE WHEN recovery_type = 'retry' THEN 1 ELSE 0 END) as retry_attempts,
                AVG(attempt_number) as avg_attempts_per_objective
              FROM :table_rag_objective_recoveries";
      
      $conditions = [];
      $params = [];
      
      if (!empty($filters['start_date'])) {
        $conditions[] = "initiated_at >= :start_date";
        $params[':start_date'] = $filters['start_date'];
      }
      
      if (!empty($filters['end_date'])) {
        $conditions[] = "initiated_at <= :end_date";
        $params[':end_date'] = $filters['end_date'];
      }
      
      if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
      }
      
      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();
      
      $row = $stmt->fetch();
      
      return [
        'total_recovered_objectives' => (int)$row['total_recovered_objectives'],
        'total_recovery_attempts' => (int)$row['total_recovery_attempts'],
        'resume_attempts' => (int)$row['resume_attempts'],
        'retry_attempts' => (int)$row['retry_attempts'],
        'avg_attempts_per_objective' => round((float)$row['avg_attempts_per_objective'], 2)
      ];
      
    } catch (Exception $e) {
      return [
        'total_recovered_objectives' => 0,
        'total_recovery_attempts' => 0,
        'resume_attempts' => 0,
        'retry_attempts' => 0,
        'avg_attempts_per_objective' => 0.0,
        'error' => $e->getMessage()
      ];
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
   * Get retry delay
   *
   * @return int The retry delay in minutes
   */
  public function getRetryDelay(): int
  {
    return self::RETRY_DELAY_MINUTES;
  }
}
