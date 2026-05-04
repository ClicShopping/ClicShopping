<?php
/**
 * ConsensusFallbackHandler Class
 *
 * Handles fallback to OrchestratorAgent when consensus cannot be reached.
 * Implements escalation logic for failed consensus sessions.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;
use InvalidArgumentException;

class ConsensusFallbackHandler
{
  private $db;
  private bool $debug;
  private AuditLogger $auditLogger;
  
  /**
   * Constructor
   *
   * Initializes the fallback handler with required dependencies.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->auditLogger = new AuditLogger();
  }
  
  /**
   * Handle consensus fallback
   *
   * Escalates a failed consensus session to the OrchestratorAgent for
   * final decision-making. Logs the escalation and returns the orchestrator's
   * decision.
   *
   * @param ConsensusResult $failedConsensus The failed consensus result
   * @param array $context Additional context for decision-making
   * @return array Orchestrator decision with final score and reasoning
   * @throws InvalidArgumentException If parameters are invalid
   * @throws Exception If fallback handling fails
   */
  public function handleConsensusFallback(
    ConsensusResult $failedConsensus,
    array $context = []
  ): array {
    // Validate that consensus actually failed
    if ($failedConsensus->isConsensusReached()) {
      throw new InvalidArgumentException('Consensus was reached, no fallback needed');
    }
    
    try {
      // Log escalation
      $this->logEscalation($failedConsensus, $context);
      
      // Audit the fallback action
      $this->auditLogger->logAction(
        'orchestrator_agent',
        'consensus_fallback',
        'escalated',
        [
          'output_id' => $failedConsensus->getOutputId(),
          'participating_agents' => $failedConsensus->getParticipatingAgents(),
          'initial_scores' => $failedConsensus->getInitialScores(),
          'agreement_level' => $failedConsensus->getAgreementLevel()
        ]
      );
      
      // Prepare context for orchestrator
      $orchestratorContext = $this->prepareOrchestratorContext($failedConsensus, $context);
      
      // Request orchestrator decision
      $decision = $this->requestOrchestratorDecision($orchestratorContext);
      
      // Persist fallback decision
      $this->persistFallbackDecision($failedConsensus, $decision);
      
      // Log successful fallback
      if ($this->debug) {
        error_log(sprintf(
          "ConsensusFallbackHandler: Orchestrator decision for output %s: score=%.2f",
          $failedConsensus->getOutputId(),
          $decision['final_score']
        ));
      }
      
      return $decision;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ConsensusFallbackHandler: Fallback failed - " . $e->getMessage());
      }
      throw new Exception('Consensus fallback failed: ' . $e->getMessage());
    }
  }
  
  /**
   * Prepare context for orchestrator decision
   *
   * Prepares comprehensive context information for the orchestrator
   * to make an informed decision.
   *
   * @param ConsensusResult $failedConsensus The failed consensus result
   * @param array $additionalContext Additional context
   * @return array Prepared context for orchestrator
   */
  private function prepareOrchestratorContext(
    ConsensusResult $failedConsensus,
    array $additionalContext
  ): array {
    return [
      'output_id' => $failedConsensus->getOutputId(),
      'participating_agents' => $failedConsensus->getParticipatingAgents(),
      'initial_scores' => $failedConsensus->getInitialScores(),
      'proposed_score' => $failedConsensus->getFinalScore(),
      'agreement_level' => $failedConsensus->getAgreementLevel(),
      'outliers' => $failedConsensus->getOutliers(),
      'discussion_log' => $failedConsensus->getDiscussionLog(),
      'failure_reason' => 'Consensus could not be reached among evaluators',
      'escalation_timestamp' => date('Y-m-d H:i:s'),
      'additional_context' => $additionalContext
    ];
  }
  
  /**
   * Request orchestrator decision
   *
   * Requests a final decision from the OrchestratorAgent.
   * In production, this would integrate with the actual OrchestratorAgent.
   *
   * @param array $context Context for decision-making
   * @return array Orchestrator decision
   */
  private function requestOrchestratorDecision(array $context): array
  {
    // TODO: In production, this would communicate with actual OrchestratorAgent
    // For now, we'll implement a simplified decision logic
    
    try {
      // Analyze the divergent scores
      $scores = array_values($context['initial_scores']);
      $proposedScore = $context['proposed_score'];
      $agreementLevel = $context['agreement_level'];
      
      // Orchestrator decision logic:
      // 1. If agreement level is close to threshold, use proposed score
      // 2. If scores are highly divergent, use median score
      // 3. If outliers exist, exclude them and recalculate
      
      $finalScore = $proposedScore;
      $reasoning = [];
      
      if ($agreementLevel < 0.5) {
        // High divergence - use median
        sort($scores);
        $count = count($scores);
        $finalScore = $count % 2 === 0 
          ? ($scores[$count / 2 - 1] + $scores[$count / 2]) / 2
          : $scores[floor($count / 2)];
        
        $reasoning[] = "High divergence detected (agreement: " . round($agreementLevel * 100, 1) . "%)";
        $reasoning[] = "Using median score to balance extreme evaluations";
      } else {
        // Moderate divergence - use weighted average excluding outliers
        $outliers = $context['outliers'] ?? [];
        $outlierScores = array_column($outliers, 'score');
        
        $validScores = array_filter($scores, function($score) use ($outlierScores) {
          return !in_array($score, $outlierScores, true);
        });
        
        if (!empty($validScores)) {
          $finalScore = array_sum($validScores) / count($validScores);
          $reasoning[] = "Moderate divergence detected (agreement: " . round($agreementLevel * 100, 1) . "%)";
          $reasoning[] = "Using average of non-outlier scores";
        } else {
          $reasoning[] = "All scores identified as outliers";
          $reasoning[] = "Using proposed consensus score as fallback";
        }
      }
      
      // Ensure score is within valid range
      $finalScore = max(0.0, min(1.0, $finalScore));
      
      $reasoning[] = "Orchestrator final decision: " . round($finalScore, 2);
      
      return [
        'final_score' => round($finalScore, 2),
        'decision_maker' => 'orchestrator_agent',
        'reasoning' => implode('. ', $reasoning),
        'confidence' => $this->calculateDecisionConfidence($scores, $finalScore),
        'timestamp' => date('Y-m-d H:i:s'),
        'fallback_triggered' => true
      ];
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ConsensusFallbackHandler: Orchestrator decision failed - " . $e->getMessage());
      }
      
      // Emergency fallback - use simple average
      $scores = array_values($context['initial_scores']);
      $avgScore = array_sum($scores) / count($scores);
      
      return [
        'final_score' => round($avgScore, 2),
        'decision_maker' => 'orchestrator_agent',
        'reasoning' => 'Emergency fallback: using simple average of all scores',
        'confidence' => 0.5,
        'timestamp' => date('Y-m-d H:i:s'),
        'fallback_triggered' => true,
        'error' => $e->getMessage()
      ];
    }
  }
  
  /**
   * Calculate decision confidence
   *
   * Calculates confidence level for the orchestrator's decision
   * based on score distribution.
   *
   * @param array $scores Array of evaluation scores
   * @param float $finalScore The final decided score
   * @return float Confidence level (0.0 - 1.0)
   */
  private function calculateDecisionConfidence(array $scores, float $finalScore): float
  {
    if (empty($scores)) {
      return 0.0;
    }
    
    // Calculate how close scores are to the final decision
    $deviations = array_map(function($score) use ($finalScore) {
      return abs($score - $finalScore);
    }, $scores);
    
    $avgDeviation = array_sum($deviations) / count($deviations);
    
    // Convert deviation to confidence (lower deviation = higher confidence)
    // Max deviation is 1.0 (score 0 vs score 1)
    $confidence = max(0.0, 1.0 - $avgDeviation);
    
    return round($confidence, 2);
  }
  
  /**
   * Log escalation
   *
   * Logs the consensus escalation to the database.
   *
   * @param ConsensusResult $failedConsensus The failed consensus result
   * @param array $context Additional context
   */
  private function logEscalation(ConsensusResult $failedConsensus, array $context): void
  {
    try {
      $sql = "INSERT INTO :table_rag_consensus_escalations 
              (escalation_id, output_id, session_id, participating_agents, 
               initial_scores, agreement_level, escalation_reason, 
               additional_context, escalated_at)
              VALUES (:escalation_id, :output_id, :session_id, :participating_agents,
                      :initial_scores, :agreement_level, :escalation_reason,
                      :additional_context, NOW())";
      
      $escalationId = $this->generateEscalationId();
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':escalation_id', $escalationId);
      $stmt->bindValue(':output_id', $failedConsensus->getOutputId());
      $stmt->bindValue(':session_id', $failedConsensus->getSessionId());
      $stmt->bindValue(':participating_agents', json_encode($failedConsensus->getParticipatingAgents()));
      $stmt->bindValue(':initial_scores', json_encode($failedConsensus->getInitialScores()));
      $stmt->bindValue(':agreement_level', $failedConsensus->getAgreementLevel());
      $stmt->bindValue(':escalation_reason', 'Consensus could not be reached');
      $stmt->bindValue(':additional_context', json_encode($context));
      $stmt->execute();
      
      if ($this->debug) {
        error_log("ConsensusFallbackHandler: Logged escalation {$escalationId} for output {$failedConsensus->getOutputId()}");
      }
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ConsensusFallbackHandler: Failed to log escalation - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Persist fallback decision
   *
   * Persists the orchestrator's fallback decision to the database.
   *
   * @param ConsensusResult $failedConsensus The failed consensus result
   * @param array $decision The orchestrator decision
   */
  private function persistFallbackDecision(ConsensusResult $failedConsensus, array $decision): void
  {
    try {
      $sql = "UPDATE :table_rag_consensus_escalations 
              SET final_score = :final_score,
                  decision_maker = :decision_maker,
                  reasoning = :reasoning,
                  confidence = :confidence,
                  resolved_at = NOW()
              WHERE output_id = :output_id
              AND resolved_at IS NULL
              ORDER BY escalated_at DESC
              LIMIT 1";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':final_score', $decision['final_score']);
      $stmt->bindValue(':decision_maker', $decision['decision_maker']);
      $stmt->bindValue(':reasoning', $decision['reasoning']);
      $stmt->bindValue(':confidence', $decision['confidence']);
      $stmt->bindValue(':output_id', $failedConsensus->getOutputId());
      $stmt->execute();
      
      if ($this->debug) {
        error_log("ConsensusFallbackHandler: Persisted fallback decision for output {$failedConsensus->getOutputId()}");
      }
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ConsensusFallbackHandler: Failed to persist fallback decision - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Generate escalation ID
   *
   * Generates a unique ID for the escalation.
   *
   * @return string The escalation ID
   */
  private function generateEscalationId(): string
  {
    return 'esc_' . uniqid() . '_' . bin2hex(random_bytes(8));
  }
  
  /**
   * Get escalation statistics
   *
   * Retrieves statistics about consensus escalations.
   *
   * @param array $filters Optional filters (date_range, etc.)
   * @return array Statistics array
   */
  public function getEscalationStatistics(array $filters = []): array
  {
    try {
      $sql = "SELECT 
                COUNT(*) as total_escalations,
                AVG(final_score) as avg_final_score,
                AVG(confidence) as avg_confidence,
                AVG(agreement_level) as avg_agreement_level,
                SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_escalations
              FROM :table_rag_consensus_escalations";
      
      $conditions = [];
      $params = [];
      
      if (!empty($filters['start_date'])) {
        $conditions[] = "escalated_at >= :start_date";
        $params[':start_date'] = $filters['start_date'];
      }
      
      if (!empty($filters['end_date'])) {
        $conditions[] = "escalated_at <= :end_date";
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
        'total_escalations' => (int)$row['total_escalations'],
        'resolved_escalations' => (int)$row['resolved_escalations'],
        'avg_final_score' => round((float)$row['avg_final_score'], 2),
        'avg_confidence' => round((float)$row['avg_confidence'], 2),
        'avg_agreement_level' => round((float)$row['avg_agreement_level'], 2),
        'resolution_rate' => $row['total_escalations'] > 0 
          ? round($row['resolved_escalations'] / $row['total_escalations'] * 100, 2) 
          : 0.0
      ];
      
    } catch (Exception $e) {
      return [
        'total_escalations' => 0,
        'resolved_escalations' => 0,
        'avg_final_score' => 0.0,
        'avg_confidence' => 0.0,
        'avg_agreement_level' => 0.0,
        'resolution_rate' => 0.0,
        'error' => $e->getMessage()
      ];
    }
  }
}
