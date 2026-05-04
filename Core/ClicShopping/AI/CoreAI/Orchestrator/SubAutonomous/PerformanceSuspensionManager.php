<?php
/**
 * PerformanceSuspensionManager Class
 *
 * Manages automatic suspension of agents with consistently low performance.
 * Tracks evaluation scores over time and suspends agents that fall below
 * performance thresholds.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;
use InvalidArgumentException;

class PerformanceSuspensionManager
{
  private $db;
  private bool $debug;
  private AuditLogger $auditLogger;
  
  // Configuration constants
  private const LOW_PERFORMANCE_THRESHOLD = 0.60; // Score below this is considered low
  private const EVALUATION_WINDOW_DAYS = 7; // Look at last 7 days
  private const MIN_EVALUATIONS_FOR_SUSPENSION = 5; // Need at least 5 evaluations
  private const SUSPENSION_DURATION_HOURS = 24; // Suspend for 24 hours
  private const CONSECUTIVE_LOW_SCORES_THRESHOLD = 3; // 3 consecutive low scores triggers suspension
  
  /**
   * Constructor
   *
   * Initializes the suspension manager with required dependencies.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->auditLogger = new AuditLogger();
  }
  
  /**
   * Check and suspend low performing agents
   *
   * Analyzes recent evaluation scores for all agents and suspends
   * those with consistently low performance.
   *
   * @return array Array of suspended agent IDs with reasons
   */
  public function checkAndSuspendLowPerformers(): array
  {
    $suspendedAgents = [];
    
    try {
      // Get all agents with recent evaluations
      $agentsWithScores = $this->getAgentsWithRecentScores();
      
      foreach ($agentsWithScores as $agentData) {
        $agentId = $agentData['agent_id'];
        $scores = $agentData['scores'];
        $avgScore = $agentData['avg_score'];
        $evaluationCount = $agentData['evaluation_count'];
        
        // Check if agent should be suspended
        if ($this->shouldSuspendAgent($agentId, $scores, $avgScore, $evaluationCount)) {
          $reason = $this->buildSuspensionReason($scores, $avgScore, $evaluationCount);
          
          if ($this->suspendAgent($agentId, $reason)) {
            $suspendedAgents[] = [
              'agent_id' => $agentId,
              'reason' => $reason,
              'avg_score' => $avgScore,
              'evaluation_count' => $evaluationCount
            ];
            
            if ($this->debug) {
              error_log("PerformanceSuspensionManager: Suspended agent {$agentId} - {$reason}");
            }
          }
        }
      }
      
      return $suspendedAgents;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("PerformanceSuspensionManager: Check failed - " . $e->getMessage());
      }
      return [];
    }
  }
  
  /**
   * Suspend agent
   *
   * Suspends an agent's autonomous capabilities for a configured duration.
   *
   * @param string $agentId The agent ID to suspend
   * @param string $reason The suspension reason
   * @return bool True if suspended successfully
   * @throws InvalidArgumentException If agent ID is invalid
   */
  public function suspendAgent(string $agentId, string $reason): bool
  {
    if (empty($agentId)) {
      throw new InvalidArgumentException('Agent ID cannot be empty');
    }
    
    try {
      // Check if already suspended
      if ($this->isAgentSuspended($agentId)) {
        if ($this->debug) {
          error_log("PerformanceSuspensionManager: Agent {$agentId} is already suspended");
        }
        return false;
      }
      
      // Calculate suspension end time
      $suspensionEndTime = date('Y-m-d H:i:s', strtotime('+' . self::SUSPENSION_DURATION_HOURS . ' hours'));
      
      // Insert suspension record
      $sql = "INSERT INTO :table_rag_agent_suspensions 
              (suspension_id, agent_id, suspension_reason, suspension_type,
               suspended_at, suspension_end_time, status)
              VALUES (:suspension_id, :agent_id, :suspension_reason, 'performance',
                      NOW(), :suspension_end_time, 'active')";
      
      $suspensionId = $this->generateSuspensionId();
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':suspension_id', $suspensionId);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':suspension_reason', $reason);
      $stmt->bindValue(':suspension_end_time', $suspensionEndTime);
      $stmt->execute();
      
      // Audit the suspension
      $this->auditLogger->logAction(
        $agentId,
        'agent_suspended',
        'suspended',
        [
          'suspension_id' => $suspensionId,
          'reason' => $reason,
          'duration_hours' => self::SUSPENSION_DURATION_HOURS,
          'suspension_end_time' => $suspensionEndTime
        ]
      );
      
      if ($this->debug) {
        error_log("PerformanceSuspensionManager: Agent {$agentId} suspended until {$suspensionEndTime}");
      }
      
      return true;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("PerformanceSuspensionManager: Failed to suspend agent - " . $e->getMessage());
      }
      return false;
    }
  }
  
  /**
   * Unsuspend agent
   *
   * Manually unsuspends an agent before the automatic expiration.
   *
   * @param string $agentId The agent ID to unsuspend
   * @param string $reason The unsuspension reason
   * @return bool True if unsuspended successfully
   */
  public function unsuspendAgent(string $agentId, string $reason = 'Manual unsuspension'): bool
  {
    if (empty($agentId)) {
      throw new InvalidArgumentException('Agent ID cannot be empty');
    }
    
    try {
      $sql = "UPDATE :table_rag_agent_suspensions 
              SET status = 'lifted',
                  unsuspension_reason = :unsuspension_reason,
                  unsuspended_at = NOW()
              WHERE agent_id = :agent_id
              AND status = 'active'";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':unsuspension_reason', $reason);
      $stmt->execute();
      
      $rowsAffected = $stmt->rowCount();
      
      if ($rowsAffected > 0) {
        // Audit the unsuspension
        $this->auditLogger->logAction(
          $agentId,
          'agent_unsuspended',
          'unsuspended',
          ['reason' => $reason]
        );
        
        if ($this->debug) {
          error_log("PerformanceSuspensionManager: Agent {$agentId} unsuspended - {$reason}");
        }
        
        return true;
      }
      
      return false;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("PerformanceSuspensionManager: Failed to unsuspend agent - " . $e->getMessage());
      }
      return false;
    }
  }
  
  /**
   * Check if agent is suspended
   *
   * Checks if an agent is currently suspended.
   * Automatically expires suspensions that have passed their end time.
   *
   * @param string $agentId The agent ID to check
   * @return bool True if agent is suspended
   */
  public function isAgentSuspended(string $agentId): bool
  {
    try {
      // First, expire any suspensions that have passed their end time
      $this->expireOldSuspensions();
      
      // Check for active suspension
      $sql = "SELECT COUNT(*) as suspension_count
              FROM :table_rag_agent_suspensions
              WHERE agent_id = :agent_id
              AND status = 'active'
              AND suspension_end_time > NOW()";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->execute();
      
      $row = $stmt->fetch();
      return (int)$row['suspension_count'] > 0;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("PerformanceSuspensionManager: Failed to check suspension status - " . $e->getMessage());
      }
      return false;
    }
  }
  
  /**
   * Get agents with recent scores
   *
   * Retrieves all agents with evaluation scores in the recent window.
   *
   * @return array Array of agent data with scores
   */
  private function getAgentsWithRecentScores(): array
  {
    try {
      $sql = "SELECT 
                producer_agent_id as agent_id,
                GROUP_CONCAT(overall_score ORDER BY evaluated_at DESC) as scores_str,
                AVG(overall_score) as avg_score,
                COUNT(*) as evaluation_count
              FROM :table_rag_agent_evaluations
              WHERE evaluated_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
              GROUP BY producer_agent_id
              HAVING evaluation_count >= :min_evaluations";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':window_days', self::EVALUATION_WINDOW_DAYS);
      $stmt->bindValue(':min_evaluations', self::MIN_EVALUATIONS_FOR_SUSPENSION);
      $stmt->execute();
      
      $agents = [];
      while ($row = $stmt->fetch()) {
        $scores = array_map('floatval', explode(',', $row['scores_str']));
        
        $agents[] = [
          'agent_id' => $row['agent_id'],
          'scores' => $scores,
          'avg_score' => (float)$row['avg_score'],
          'evaluation_count' => (int)$row['evaluation_count']
        ];
      }
      
      return $agents;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("PerformanceSuspensionManager: Failed to get agent scores - " . $e->getMessage());
      }
      return [];
    }
  }
  
  /**
   * Should suspend agent
   *
   * Determines if an agent should be suspended based on performance metrics.
   *
   * @param string $agentId The agent ID
   * @param array $scores Array of recent scores
   * @param float $avgScore Average score
   * @param int $evaluationCount Number of evaluations
   * @return bool True if agent should be suspended
   */
  private function shouldSuspendAgent(
    string $agentId,
    array $scores,
    float $avgScore,
    int $evaluationCount
  ): bool {
    // Check if already suspended
    if ($this->isAgentSuspended($agentId)) {
      return false;
    }
    
    // Check if average score is below threshold
    if ($avgScore >= self::LOW_PERFORMANCE_THRESHOLD) {
      return false;
    }
    
    // Check for consecutive low scores
    $consecutiveLowScores = 0;
    foreach ($scores as $score) {
      if ($score < self::LOW_PERFORMANCE_THRESHOLD) {
        $consecutiveLowScores++;
        if ($consecutiveLowScores >= self::CONSECUTIVE_LOW_SCORES_THRESHOLD) {
          return true;
        }
      } else {
        $consecutiveLowScores = 0;
      }
    }
    
    // Check if majority of scores are low
    $lowScoreCount = 0;
    foreach ($scores as $score) {
      if ($score < self::LOW_PERFORMANCE_THRESHOLD) {
        $lowScoreCount++;
      }
    }
    
    $lowScorePercentage = $lowScoreCount / count($scores);
    
    // Suspend if more than 70% of scores are low
    return $lowScorePercentage > 0.70;
  }
  
  /**
   * Build suspension reason
   *
   * Builds a detailed suspension reason message.
   *
   * @param array $scores Array of recent scores
   * @param float $avgScore Average score
   * @param int $evaluationCount Number of evaluations
   * @return string Suspension reason
   */
  private function buildSuspensionReason(
    array $scores,
    float $avgScore,
    int $evaluationCount
  ): string {
    $lowScoreCount = 0;
    foreach ($scores as $score) {
      if ($score < self::LOW_PERFORMANCE_THRESHOLD) {
        $lowScoreCount++;
      }
    }
    
    $lowScorePercentage = round($lowScoreCount / count($scores) * 100, 1);
    
    return sprintf(
      "Consistently low performance detected: Average score %.2f over %d evaluations (%s%% below threshold of %.2f). Suspended for %d hours for performance review.",
      $avgScore,
      $evaluationCount,
      $lowScorePercentage,
      self::LOW_PERFORMANCE_THRESHOLD,
      self::SUSPENSION_DURATION_HOURS
    );
  }
  
  /**
   * Expire old suspensions
   *
   * Automatically expires suspensions that have passed their end time.
   */
  private function expireOldSuspensions(): void
  {
    try {
      $sql = "UPDATE :table_rag_agent_suspensions 
              SET status = 'expired',
                  unsuspended_at = NOW(),
                  unsuspension_reason = 'Automatic expiration'
              WHERE status = 'active'
              AND suspension_end_time <= NOW()";
      
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      
      $expiredCount = $stmt->rowCount();
      
      if ($expiredCount > 0 && $this->debug) {
        error_log("PerformanceSuspensionManager: Expired {$expiredCount} suspensions");
      }
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("PerformanceSuspensionManager: Failed to expire suspensions - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Generate suspension ID
   *
   * Generates a unique ID for the suspension.
   *
   * @return string The suspension ID
   */
  private function generateSuspensionId(): string
  {
    return 'susp_' . uniqid() . '_' . bin2hex(random_bytes(8));
  }
  
  /**
   * Get suspension statistics
   *
   * Retrieves statistics about agent suspensions.
   *
   * @param array $filters Optional filters (date_range, agent_id, etc.)
   * @return array Statistics array
   */
  public function getSuspensionStatistics(array $filters = []): array
  {
    try {
      $sql = "SELECT 
                COUNT(*) as total_suspensions,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_suspensions,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_suspensions,
                SUM(CASE WHEN status = 'lifted' THEN 1 ELSE 0 END) as lifted_suspensions,
                AVG(TIMESTAMPDIFF(HOUR, suspended_at, COALESCE(unsuspended_at, NOW()))) as avg_duration_hours
              FROM :table_rag_agent_suspensions";
      
      $conditions = [];
      $params = [];
      
      if (!empty($filters['agent_id'])) {
        $conditions[] = "agent_id = :agent_id";
        $params[':agent_id'] = $filters['agent_id'];
      }
      
      if (!empty($filters['start_date'])) {
        $conditions[] = "suspended_at >= :start_date";
        $params[':start_date'] = $filters['start_date'];
      }
      
      if (!empty($filters['end_date'])) {
        $conditions[] = "suspended_at <= :end_date";
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
        'total_suspensions' => (int)$row['total_suspensions'],
        'active_suspensions' => (int)$row['active_suspensions'],
        'expired_suspensions' => (int)$row['expired_suspensions'],
        'lifted_suspensions' => (int)$row['lifted_suspensions'],
        'avg_duration_hours' => round((float)$row['avg_duration_hours'], 2)
      ];
      
    } catch (Exception $e) {
      return [
        'total_suspensions' => 0,
        'active_suspensions' => 0,
        'expired_suspensions' => 0,
        'lifted_suspensions' => 0,
        'avg_duration_hours' => 0.0,
        'error' => $e->getMessage()
      ];
    }
  }
  
  /**
   * Get low performance threshold
   *
   * @return float The low performance threshold
   */
  public function getLowPerformanceThreshold(): float
  {
    return self::LOW_PERFORMANCE_THRESHOLD;
  }
  
  /**
   * Get suspension duration
   *
   * @return int The suspension duration in hours
   */
  public function getSuspensionDuration(): int
  {
    return self::SUSPENSION_DURATION_HOURS;
  }
}
