<?php
/**
 * AuditLogger
 *
 * Implements comprehensive audit logging for all autonomous agent actions
 * including objective creation, evaluations, feedback, and authorization attempts.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;

class AuditLogger
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
   * Log autonomous action
   *
   * @param string $agentId Agent identifier
   * @param string $actionType Type of action (objective_creation, evaluation, feedback, etc.)
   * @param string $outcome Outcome of the action (success, failure, denied)
   * @param array $details Additional details about the action
   * @return bool True if logged successfully
   */
  public function logAction(string $agentId, string $actionType, string $outcome, array $details = []): bool
  {
    try {
      $Qinsert = $this->db->prepare('
        INSERT INTO :table_rag_agent_audit_log
        (agent_id, action_type, outcome, details, timestamp)
        VALUES
        (:agent_id, :action_type, :outcome, :details, NOW())
      ');
      
      $Qinsert->bindValue(':agent_id', $agentId);
      $Qinsert->bindValue(':action_type', $actionType);
      $Qinsert->bindValue(':outcome', $outcome);
      $Qinsert->bindValue(':details', json_encode($details));
      $Qinsert->execute();
      
      return true;
    } catch (Exception $e) {
      error_log("Failed to log audit action: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Log objective creation
   *
   * @param string $agentId Agent identifier
   * @param string $objectiveId Objective identifier
   * @param string $outcome Outcome (success, denied, failed)
   * @param array $objectiveData Objective data
   * @return bool True if logged successfully
   */
  public function logObjectiveCreation(string $agentId, string $objectiveId, string $outcome, array $objectiveData = []): bool
  {
    $details = [
      'objective_id' => $objectiveId,
      'goal_statement' => $objectiveData['goal_statement'] ?? '',
      'priority' => $objectiveData['priority'] ?? '',
      'estimated_completion_time' => $objectiveData['estimated_completion_time'] ?? 0
    ];
    
    return $this->logAction($agentId, 'objective_creation', $outcome, $details);
  }
  
  /**
   * Log evaluation action
   *
   * @param string $evaluatorAgentId Evaluator agent identifier
   * @param string $outputId Output identifier
   * @param string $producerAgentId Producer agent identifier
   * @param string $outcome Outcome (success, denied, failed)
   * @param array $evaluationData Evaluation data
   * @return bool True if logged successfully
   */
  public function logEvaluation(
    string $evaluatorAgentId, 
    string $outputId, 
    string $producerAgentId,
    string $outcome, 
    array $evaluationData = []
  ): bool {
    $details = [
      'output_id' => $outputId,
      'producer_agent_id' => $producerAgentId,
      'output_type' => $evaluationData['output_type'] ?? '',
      'overall_score' => $evaluationData['overall_score'] ?? null
    ];
    
    return $this->logAction($evaluatorAgentId, 'evaluation', $outcome, $details);
  }
  
  /**
   * Log feedback delivery
   *
   * @param string $sourceAgentId Source agent identifier
   * @param string $targetAgentId Target agent identifier
   * @param string $feedbackId Feedback identifier
   * @param string $outcome Outcome (success, failed)
   * @return bool True if logged successfully
   */
  public function logFeedbackDelivery(
    string $sourceAgentId, 
    string $targetAgentId, 
    string $feedbackId,
    string $outcome
  ): bool {
    $details = [
      'feedback_id' => $feedbackId,
      'target_agent_id' => $targetAgentId
    ];
    
    return $this->logAction($sourceAgentId, 'feedback_delivery', $outcome, $details);
  }
  
  /**
   * Log authorization attempt
   *
   * @param string $agentId Agent identifier
   * @param string $actionType Type of action attempted
   * @param bool $authorized Whether authorization was granted
   * @param string $reason Reason for denial (if applicable)
   * @return bool True if logged successfully
   */
  public function logAuthorizationAttempt(
    string $agentId, 
    string $actionType, 
    bool $authorized,
    string $reason = ''
  ): bool {
    $outcome = $authorized ? 'authorized' : 'denied';
    $details = [
      'attempted_action' => $actionType,
      'denial_reason' => $reason
    ];
    
    return $this->logAction($agentId, 'authorization_attempt', $outcome, $details);
  }
  
  /**
   * Log objective status change
   *
   * @param string $agentId Agent identifier
   * @param string $objectiveId Objective identifier
   * @param string $oldStatus Old status
   * @param string $newStatus New status
   * @param string $reason Reason for change
   * @return bool True if logged successfully
   */
  public function logObjectiveStatusChange(
    string $agentId,
    string $objectiveId,
    string $oldStatus,
    string $newStatus,
    string $reason = ''
  ): bool {
    $details = [
      'objective_id' => $objectiveId,
      'old_status' => $oldStatus,
      'new_status' => $newStatus,
      'reason' => $reason
    ];
    
    return $this->logAction($agentId, 'objective_status_change', 'success', $details);
  }
  
  /**
   * Log consensus session
   *
   * @param string $sessionId Session identifier
   * @param array $participatingAgents Array of agent identifiers
   * @param string $outcome Outcome (consensus_reached, timeout, escalated)
   * @param array $sessionData Session data
   * @return bool True if logged successfully
   */
  public function logConsensusSession(
    string $sessionId,
    array $participatingAgents,
    string $outcome,
    array $sessionData = []
  ): bool {
    $details = [
      'session_id' => $sessionId,
      'participating_agents' => $participatingAgents,
      'output_id' => $sessionData['output_id'] ?? '',
      'final_score' => $sessionData['final_score'] ?? null
    ];
    
    // Log for each participating agent
    foreach ($participatingAgents as $agentId) {
      $this->logAction($agentId, 'consensus_session', $outcome, $details);
    }
    
    return true;
  }
  
  /**
   * Log objective collaboration
   *
   * @param array $agentIds Array of collaborating agent identifiers
   * @param string $objectiveId Objective identifier
   * @param string $actionType Type of collaboration action (merge, join, leave)
   * @return bool True if logged successfully
   */
  public function logObjectiveCollaboration(
    array $agentIds,
    string $objectiveId,
    string $actionType
  ): bool {
    $details = [
      'objective_id' => $objectiveId,
      'collaborating_agents' => $agentIds,
      'collaboration_action' => $actionType
    ];
    
    // Log for each collaborating agent
    foreach ($agentIds as $agentId) {
      $this->logAction($agentId, 'objective_collaboration', 'success', $details);
    }
    
    return true;
  }
  
  /**
   * Get audit log for agent
   *
   * @param string $agentId Agent identifier
   * @param int $limit Maximum number of records to return
   * @param int $offset Offset for pagination
   * @return array Array of audit log entries
   */
  public function getAgentAuditLog(string $agentId, int $limit = 100, int $offset = 0): array
  {
    $Qlog = $this->db->prepare('
      SELECT 
        audit_id,
        agent_id,
        action_type,
        outcome,
        details,
        timestamp
      FROM :table_rag_agent_audit_log
      WHERE agent_id = :agent_id
      ORDER BY timestamp DESC
      LIMIT :limit OFFSET :offset
    ');
    
    $Qlog->bindValue(':agent_id', $agentId);
    $Qlog->bindInt(':limit', $limit);
    $Qlog->bindInt(':offset', $offset);
    $Qlog->execute();
    
    $logs = [];
    while ($Qlog->fetch()) {
      $logs[] = [
        'audit_id' => $Qlog->valueInt('audit_id'),
        'agent_id' => $Qlog->value('agent_id'),
        'action_type' => $Qlog->value('action_type'),
        'outcome' => $Qlog->value('outcome'),
        'details' => json_decode($Qlog->value('details'), true),
        'timestamp' => $Qlog->value('timestamp')
      ];
    }
    
    return $logs;
  }
  
  /**
   * Get audit log by action type
   *
   * @param string $actionType Action type
   * @param int $limit Maximum number of records to return
   * @param int $offset Offset for pagination
   * @return array Array of audit log entries
   */
  public function getAuditLogByActionType(string $actionType, int $limit = 100, int $offset = 0): array
  {
    $Qlog = $this->db->prepare('
      SELECT 
        audit_id,
        agent_id,
        action_type,
        outcome,
        details,
        timestamp
      FROM :table_rag_agent_audit_log
      WHERE action_type = :action_type
      ORDER BY timestamp DESC
      LIMIT :limit OFFSET :offset
    ');
    
    $Qlog->bindValue(':action_type', $actionType);
    $Qlog->bindInt(':limit', $limit);
    $Qlog->bindInt(':offset', $offset);
    $Qlog->execute();
    
    $logs = [];
    while ($Qlog->fetch()) {
      $logs[] = [
        'audit_id' => $Qlog->valueInt('audit_id'),
        'agent_id' => $Qlog->value('agent_id'),
        'action_type' => $Qlog->value('action_type'),
        'outcome' => $Qlog->value('outcome'),
        'details' => json_decode($Qlog->value('details'), true),
        'timestamp' => $Qlog->value('timestamp')
      ];
    }
    
    return $logs;
  }
  
  /**
   * Get failed actions for review
   *
   * @param int $limit Maximum number of records to return
   * @return array Array of failed action entries
   */
  public function getFailedActions(int $limit = 50): array
  {
    $Qlog = $this->db->prepare('
      SELECT 
        audit_id,
        agent_id,
        action_type,
        outcome,
        details,
        timestamp
      FROM :table_rag_agent_audit_log
      WHERE outcome IN ("failed", "denied")
      ORDER BY timestamp DESC
      LIMIT :limit
    ');
    
    $Qlog->bindInt(':limit', $limit);
    $Qlog->execute();
    
    $logs = [];
    while ($Qlog->fetch()) {
      $logs[] = [
        'audit_id' => $Qlog->valueInt('audit_id'),
        'agent_id' => $Qlog->value('agent_id'),
        'action_type' => $Qlog->value('action_type'),
        'outcome' => $Qlog->value('outcome'),
        'details' => json_decode($Qlog->value('details'), true),
        'timestamp' => $Qlog->value('timestamp')
      ];
    }
    
    return $logs;
  }
  
  /**
   * Get audit statistics
   *
   * @param string|null $agentId Optional agent identifier to filter by
   * @param string|null $startDate Optional start date (Y-m-d format)
   * @param string|null $endDate Optional end date (Y-m-d format)
   * @return array Statistics array
   */
  public function getAuditStatistics(?string $agentId = null, ?string $startDate = null, ?string $endDate = null): array
  {
    $whereConditions = [];
    $params = [];
    
    if ($agentId !== null) {
      $whereConditions[] = 'agent_id = :agent_id';
      $params[':agent_id'] = $agentId;
    }
    
    if ($startDate !== null) {
      $whereConditions[] = 'timestamp >= :start_date';
      $params[':start_date'] = $startDate . ' 00:00:00';
    }
    
    if ($endDate !== null) {
      $whereConditions[] = 'timestamp <= :end_date';
      $params[':end_date'] = $endDate . ' 23:59:59';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $Qstats = $this->db->prepare("
      SELECT 
        action_type,
        outcome,
        COUNT(*) as count
      FROM :table_rag_agent_audit_log
      $whereClause
      GROUP BY action_type, outcome
    ");
    
    foreach ($params as $key => $value) {
      $Qstats->bindValue($key, $value);
    }
    
    $Qstats->execute();
    
    $statistics = [
      'total_actions' => 0,
      'by_action_type' => [],
      'by_outcome' => []
    ];
    
    while ($Qstats->fetch()) {
      $actionType = $Qstats->value('action_type');
      $outcome = $Qstats->value('outcome');
      $count = $Qstats->valueInt('count');
      
      $statistics['total_actions'] += $count;
      
      if (!isset($statistics['by_action_type'][$actionType])) {
        $statistics['by_action_type'][$actionType] = 0;
      }
      $statistics['by_action_type'][$actionType] += $count;
      
      if (!isset($statistics['by_outcome'][$outcome])) {
        $statistics['by_outcome'][$outcome] = 0;
      }
      $statistics['by_outcome'][$outcome] += $count;
    }
    
    return $statistics;
  }
  
  /**
   * Purge old audit logs
   *
   * @param int $daysToKeep Number of days to keep logs
   * @return int Number of records deleted
   */
  public function purgeOldLogs(int $daysToKeep = 90): int
  {
    try {
      $Qdelete = $this->db->prepare('
        DELETE FROM :table_rag_agent_audit_log
        WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)
      ');
      
      $Qdelete->bindInt(':days', $daysToKeep);
      $Qdelete->execute();
      
      return $Qdelete->rowCount();
    } catch (Exception $e) {
      error_log("Failed to purge old audit logs: " . $e->getMessage());
      return 0;
    }
  }
}
