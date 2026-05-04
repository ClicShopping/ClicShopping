<?php
/**
 * ClicShopping AI - Agent Local Objectives and Evaluation System
 *
 * @copyright 2025 ClicShopping(tm). All rights reserved.
 * @license   MIT License
 * @version   1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;
use InvalidArgumentException;

/**
 * ConsensusBuilder Class
 *
 * Builds consensus from multiple agent evaluations.
 * Calculates agreement levels using standard deviation, identifies outliers,
 * initiates discussions for divergent evaluations, and resolves consensus.
 *
 * Requirements: 6.1, 6.2, 6.3, 6.4, 6.5
 */
class ConsensusBuilder
{
  private $db;
  private bool $debug;
  private ExpertiseWeightingSystem $expertiseWeighting;

  // Configuration constants
  private const CONSENSUS_THRESHOLD = 0.15; // Maximum standard deviation for consensus
  private const OUTLIER_THRESHOLD = 2.0; // Z-score threshold for outlier detection
  private const MIN_EVALUATORS = 2; // Minimum evaluators for consensus
  private const DISCUSSION_TIMEOUT = 300; // 5 minutes timeout for discussions

  /**
   * Constructor
   *
   * Initializes the consensus builder with database connection and expertise weighting system.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->expertiseWeighting = new ExpertiseWeightingSystem();
  }

  /**
   * Build consensus from multiple evaluations
   *
   * Main entry point for consensus building. Analyzes evaluations,
   * calculates agreement, identifies outliers, and initiates discussion
   * if needed to reach consensus.
   *
   * @param array $evaluations Array of AgentEvaluation objects
   * @return ConsensusResult The consensus result
   * @throws InvalidArgumentException If evaluations array is invalid
   * @throws Exception If consensus building fails
   */
  public function buildConsensus(array $evaluations): ConsensusResult
  {
    // Validate evaluations array
    if (empty($evaluations)) {
      throw new InvalidArgumentException('Evaluations array cannot be empty');
    }

    if (count($evaluations) < self::MIN_EVALUATORS) {
      throw new InvalidArgumentException(
        'At least ' . self::MIN_EVALUATORS . ' evaluations required for consensus'
      );
    }

    // Validate all elements are AgentEvaluation instances
    foreach ($evaluations as $evaluation) {
      if (!($evaluation instanceof AgentEvaluation)) {
        throw new InvalidArgumentException('All evaluations must be AgentEvaluation instances');
      }
    }

    // Verify all evaluations are for the same output
    $outputId = $evaluations[0]->getOutputId();
    $outputType = null;
    
    foreach ($evaluations as $evaluation) {
      if ($evaluation->getOutputId() !== $outputId) {
        throw new InvalidArgumentException('All evaluations must be for the same output');
      }
      // Get output type from first evaluation if available
      if ($outputType === null && method_exists($evaluation, 'getOutputType')) {
        $outputType = $evaluation->getOutputType();
      }
    }

    try {
      // Extract scores and agent IDs
      $scores = [];
      $participatingAgents = [];
      $initialScores = [];

      foreach ($evaluations as $evaluation) {
        $agentId = $evaluation->getEvaluatorAgentId();
        $score = $evaluation->getOverallScore();
        
        $scores[] = $score;
        $participatingAgents[] = $agentId;
        $initialScores[$agentId] = $score;
      }

      // Calculate agreement level
      $agreementLevel = $this->calculateAgreement($scores);

      // Identify outliers
      $outliers = $this->identifyOutliers($evaluations);

      // Determine if consensus is reached
      $consensusReached = $agreementLevel >= (1.0 - self::CONSENSUS_THRESHOLD);

      // Calculate final score using expertise weighting if output type is available
      if ($outputType !== null) {
        $finalScore = $this->expertiseWeighting->calculateWeightedConsensus($evaluations, $outputType);
        
        if ($this->debug) {
          error_log(sprintf(
            "ConsensusBuilder: Using expertise-weighted consensus score %.2f for output type '%s'",
            $finalScore,
            $outputType
          ));
        }
      } else {
        // Fallback to standard weighted calculation
        $finalScore = $this->calculateFinalScore($scores, $outliers);
        
        if ($this->debug) {
          error_log("ConsensusBuilder: Output type not available, using standard weighted score");
        }
      }

      // If consensus not reached, initiate discussion
      $discussionLog = null;
      if (!$consensusReached && count($evaluations) >= self::MIN_EVALUATORS) {
        try {
          $discussionSession = $this->initiateDiscussion($evaluations);
          $resolvedResult = $this->resolveDiscussion($discussionSession);
          
          if ($resolvedResult->isConsensusReached()) {
            $consensusReached = true;
            $finalScore = $resolvedResult->getFinalScore();
            $discussionLog = json_encode($discussionSession->toArray());
          }
        } catch (Exception $e) {
          if ($this->debug) {
            error_log("ConsensusBuilder: Discussion failed - " . $e->getMessage());
          }
          // Continue with original consensus result
        }
      }

      // Create consensus result
      $result = new ConsensusResult(
        $outputId,
        $participatingAgents,
        $initialScores,
        $consensusReached,
        $finalScore,
        $agreementLevel,
        $outliers,
        $discussionLog
      );

      // Persist consensus session to database
      $this->persistConsensusSession($result);

      return $result;

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ConsensusBuilder: Failed to build consensus - " . $e->getMessage());
      }
      throw new Exception('Failed to build consensus: ' . $e->getMessage());
    }
  }

  /**
   * Calculate agreement level using standard deviation
   *
   * Calculates how much evaluators agree based on the standard deviation
   * of their scores. Lower standard deviation = higher agreement.
   * Returns a value between 0.0 (no agreement) and 1.0 (perfect agreement).
   *
   * @param array $scores Array of numerical scores
   * @return float Agreement level (0.0 - 1.0)
   * @throws InvalidArgumentException If scores array is invalid
   */
  public function calculateAgreement(array $scores): float
  {
    if (empty($scores)) {
      throw new InvalidArgumentException('Scores array cannot be empty');
    }

    // Single score means perfect agreement
    if (count($scores) === 1) {
      return 1.0;
    }

    // Calculate mean
    $mean = array_sum($scores) / count($scores);

    // Calculate variance
    $variance = 0.0;
    foreach ($scores as $score) {
      $variance += pow($score - $mean, 2);
    }
    $variance /= count($scores);

    // Calculate standard deviation
    $stdDev = sqrt($variance);

    // Convert standard deviation to agreement level
    // stdDev of 0 = perfect agreement (1.0)
    // stdDev of 0.15 or higher = no agreement (0.0)
    $agreementLevel = max(0.0, 1.0 - ($stdDev / self::CONSENSUS_THRESHOLD));

    return $agreementLevel;
  }

  /**
   * Identify outlier evaluations
   *
   * Identifies evaluations with scores that significantly deviate from
   * the mean using z-score analysis. Outliers are evaluations with
   * z-scores exceeding the outlier threshold.
   *
   * @param array $evaluations Array of AgentEvaluation objects
   * @return array Array of outlier data with agent_id, score, and z_score
   */
  public function identifyOutliers(array $evaluations): array
  {
    if (count($evaluations) < 3) {
      // Need at least 3 evaluations for meaningful outlier detection
      return [];
    }

    // Extract scores
    $scores = array_map(function($eval) {
      return $eval->getOverallScore();
    }, $evaluations);

    // Calculate mean and standard deviation
    $mean = array_sum($scores) / count($scores);
    
    $variance = 0.0;
    foreach ($scores as $score) {
      $variance += pow($score - $mean, 2);
    }
    $variance /= count($scores);
    $stdDev = sqrt($variance);

    // Avoid division by zero
    if ($stdDev == 0) {
      return [];
    }

    // Identify outliers using z-score
    $outliers = [];
    foreach ($evaluations as $evaluation) {
      $score = $evaluation->getOverallScore();
      $zScore = abs(($score - $mean) / $stdDev);

      if ($zScore > self::OUTLIER_THRESHOLD) {
        $outliers[] = [
          'agent_id' => $evaluation->getEvaluatorAgentId(),
          'score' => $score,
          'z_score' => $zScore,
          'deviation' => abs($score - $mean)
        ];
      }
    }

    return $outliers;
  }

  /**
   * Initiate discussion for divergent evaluations
   *
   * Creates a discussion session when evaluations diverge significantly.
   * The discussion allows agents to reconcile their differences and
   * reach consensus through structured dialogue.
   *
   * @param array $divergentEvaluations Array of AgentEvaluation objects with divergent scores
   * @return DiscussionSession The initiated discussion session
   * @throws InvalidArgumentException If evaluations array is invalid
   */
  public function initiateDiscussion(array $divergentEvaluations): DiscussionSession
  {
    if (empty($divergentEvaluations)) {
      throw new InvalidArgumentException('Divergent evaluations array cannot be empty');
    }

    // Verify all are AgentEvaluation instances
    foreach ($divergentEvaluations as $evaluation) {
      if (!($evaluation instanceof AgentEvaluation)) {
        throw new InvalidArgumentException('All evaluations must be AgentEvaluation instances');
      }
    }

    // Get output ID from first evaluation
    $outputId = $divergentEvaluations[0]->getOutputId();

    // Create discussion session
    $session = new DiscussionSession(
      $outputId,
      $divergentEvaluations,
      self::DISCUSSION_TIMEOUT
    );

    // Add initial messages from each evaluator explaining their score
    foreach ($divergentEvaluations as $evaluation) {
      $message = sprintf(
        "Initial evaluation score: %.2f. Feedback: %s",
        $evaluation->getOverallScore(),
        $evaluation->getFeedback()
      );

      $session->addMessage(
        $evaluation->getEvaluatorAgentId(),
        $message,
        [
          'type' => 'initial_position',
          'score' => $evaluation->getOverallScore(),
          'strengths' => $evaluation->getStrengths(),
          'improvements' => $evaluation->getImprovements()
        ]
      );
    }

    if ($this->debug) {
      error_log(sprintf(
        "ConsensusBuilder: Initiated discussion session %s for output %s with %d evaluators",
        $session->getSessionId(),
        $outputId,
        count($divergentEvaluations)
      ));
    }

    return $session;
  }

  /**
   * Resolve discussion and reach consensus
   *
   * Attempts to resolve a discussion session by analyzing the dialogue,
   * finding common ground, and determining a consensus score.
   * If consensus cannot be reached within the timeout, escalation is required.
   *
   * @param DiscussionSession $session The discussion session to resolve
   * @return ConsensusResult The resolved consensus result
   * @throws Exception If discussion resolution fails or times out
   */
  public function resolveDiscussion(DiscussionSession $session): ConsensusResult
  {
    // Check if already resolved
    if ($session->isResolved()) {
      return $this->createConsensusFromSession($session);
    }

    // Check for timeout
    if ($session->hasTimedOut()) {
      throw new Exception(
        'Discussion session timed out without reaching consensus. Escalation required.'
      );
    }

    // Get divergent evaluations
    $evaluations = $session->getDivergentEvaluations();
    
    // Extract scores
    $scores = array_map(function($eval) {
      return $eval->getOverallScore();
    }, $evaluations);

    // Attempt to find consensus through iterative refinement
    // In a real implementation, this would involve actual agent communication
    // For now, we'll use a simplified approach:
    
    // 1. Calculate weighted average, giving less weight to outliers
    $outliers = $this->identifyOutliers($evaluations);
    $outlierAgents = array_column($outliers, 'agent_id');
    
    $weightedSum = 0.0;
    $totalWeight = 0.0;
    
    foreach ($evaluations as $evaluation) {
      $weight = in_array($evaluation->getEvaluatorAgentId(), $outlierAgents, true) ? 0.5 : 1.0;
      $weightedSum += $evaluation->getOverallScore() * $weight;
      $totalWeight += $weight;
    }
    
    $proposedScore = $totalWeight > 0 ? $weightedSum / $totalWeight : array_sum($scores) / count($scores);

    // 2. Simulate discussion rounds (in production, this would be actual agent dialogue)
    $session->addMessage(
      'consensus_builder',
      sprintf(
        "Proposed consensus score: %.2f based on weighted average of evaluations",
        $proposedScore
      ),
      ['type' => 'proposal', 'score' => $proposedScore]
    );

    // 3. Check if proposed score is acceptable (within reasonable range of all scores)
    $maxDeviation = 0.0;
    foreach ($scores as $score) {
      $deviation = abs($score - $proposedScore);
      $maxDeviation = max($maxDeviation, $deviation);
    }

    // If maximum deviation is within threshold, consensus is reached
    if ($maxDeviation <= self::CONSENSUS_THRESHOLD) {
      $session->resolve($proposedScore);
      
      $session->addMessage(
        'consensus_builder',
        sprintf(
          "Consensus reached with score %.2f (max deviation: %.3f)",
          $proposedScore,
          $maxDeviation
        ),
        ['type' => 'resolution', 'final_score' => $proposedScore]
      );

      if ($this->debug) {
        error_log(sprintf(
          "ConsensusBuilder: Discussion resolved with consensus score %.2f",
          $proposedScore
        ));
      }

      return $this->createConsensusFromSession($session);
    }

    // If we reach here, consensus could not be reached
    throw new Exception(
      'Failed to reach consensus through discussion. Maximum deviation: ' . $maxDeviation
    );
  }

  /**
   * Calculate final score from evaluations
   *
   * Calculates the final consensus score, optionally excluding outliers
   * or applying weighted averaging.
   *
   * @param array $scores Array of numerical scores
   * @param array $outliers Array of outlier data
   * @return float The final consensus score
   */
  private function calculateFinalScore(array $scores, array $outliers): float
  {
    if (empty($scores)) {
      return 0.0;
    }

    // If no outliers, simple average
    if (empty($outliers)) {
      return array_sum($scores) / count($scores);
    }

    // Calculate weighted average, reducing weight of outliers
    $outlierScores = array_column($outliers, 'score');
    $weightedSum = 0.0;
    $totalWeight = 0.0;

    foreach ($scores as $score) {
      $weight = in_array($score, $outlierScores, true) ? 0.5 : 1.0;
      $weightedSum += $score * $weight;
      $totalWeight += $weight;
    }

    return $totalWeight > 0 ? $weightedSum / $totalWeight : array_sum($scores) / count($scores);
  }

  /**
   * Create ConsensusResult from a resolved DiscussionSession
   *
   * @param DiscussionSession $session The resolved discussion session
   * @return ConsensusResult The consensus result
   */
  private function createConsensusFromSession(DiscussionSession $session): ConsensusResult
  {
    $evaluations = $session->getDivergentEvaluations();
    $outputId = $session->getOutputId();
    
    $participatingAgents = [];
    $initialScores = [];
    
    foreach ($evaluations as $evaluation) {
      $agentId = $evaluation->getEvaluatorAgentId();
      $participatingAgents[] = $agentId;
      $initialScores[$agentId] = $evaluation->getOverallScore();
    }

    $outliers = $this->identifyOutliers($evaluations);
    $scores = array_map(function($eval) {
      return $eval->getOverallScore();
    }, $evaluations);
    $agreementLevel = $this->calculateAgreement($scores);

    return new ConsensusResult(
      $outputId,
      $participatingAgents,
      $initialScores,
      $session->isResolved(),
      $session->getResolvedScore(),
      $agreementLevel,
      $outliers,
      json_encode($session->toArray())
    );
  }

  /**
   * Persist consensus session to database
   *
   * Stores the consensus session in the rag_agent_consensus_sessions table.
   *
   * @param ConsensusResult $result The consensus result to persist
   * @throws Exception If database operation fails
   */
  private function persistConsensusSession(ConsensusResult $result): void
  {
    try {
      $sql = "INSERT INTO :table_rag_agent_consensus_sessions 
              (session_id, output_id, participating_agents, initial_scores, 
               consensus_reached, final_score, discussion_log, created_at, resolved_at)
              VALUES (:session_id, :output_id, :participating_agents, :initial_scores,
                      :consensus_reached, :final_score, :discussion_log, :created_at, :resolved_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':session_id', $result->getSessionId());
      $stmt->bindValue(':output_id', $result->getOutputId());
      $stmt->bindValue(':participating_agents', json_encode($result->getParticipatingAgents()));
      $stmt->bindValue(':initial_scores', json_encode($result->getInitialScores()));
      $stmt->bindValue(':consensus_reached', $result->isConsensusReached() ? 1 : 0);
      $stmt->bindValue(':final_score', $result->getFinalScore());
      $stmt->bindValue(':discussion_log', $result->getDiscussionLog());
      $stmt->bindValue(':created_at', $result->getCreatedAt()->format('Y-m-d H:i:s'));
      $stmt->bindValue(':resolved_at', $result->getResolvedAt() ? $result->getResolvedAt()->format('Y-m-d H:i:s') : null);
      $stmt->execute();

      if ($this->debug) {
        error_log(sprintf(
          "ConsensusBuilder: Persisted consensus session %s for output %s",
          $result->getSessionId(),
          $result->getOutputId()
        ));
      }

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ConsensusBuilder: Failed to persist consensus session - " . $e->getMessage());
      }
      throw new Exception('Failed to persist consensus session: ' . $e->getMessage());
    }
  }

  /**
   * Get consensus threshold
   *
   * @return float The consensus threshold (maximum standard deviation)
   */
  public function getConsensusThreshold(): float
  {
    return self::CONSENSUS_THRESHOLD;
  }

  /**
   * Get outlier threshold
   *
   * @return float The outlier threshold (z-score)
   */
  public function getOutlierThreshold(): float
  {
    return self::OUTLIER_THRESHOLD;
  }

  /**
   * Get minimum evaluators required
   *
   * @return int The minimum number of evaluators
   */
  public function getMinEvaluators(): int
  {
    return self::MIN_EVALUATORS;
  }

  /**
   * Get discussion timeout
   *
   * @return int The discussion timeout in seconds
   */
  public function getDiscussionTimeout(): int
  {
    return self::DISCUSSION_TIMEOUT;
  }

  /**
   * Get expertise weighting system
   *
   * Returns the expertise weighting system instance used by this consensus builder.
   *
   * @return ExpertiseWeightingSystem The expertise weighting system
   */
  public function getExpertiseWeightingSystem(): ExpertiseWeightingSystem
  {
    return $this->expertiseWeighting;
  }
}
