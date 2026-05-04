<?php
/**
 * EvaluationEngine Class
 *
 * Core engine for inter-agent evaluation.
 * Coordinates the evaluation process by selecting qualified evaluators,
 * requesting evaluations from peer agents, aggregating results, and
 * triggering corrections when quality thresholds are not met.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\AI\Config\AutonomousConfig;
use ClicShopping\AI\CoreAI\Orchestrator\CorrectionAgent;
use ClicShopping\OM\Registry;
use Exception;
use InvalidArgumentException;

class EvaluationEngine
{
  private AgentCapabilityRegistry $capabilityRegistry;
  private EvaluationMetrics $metrics;
  private $db;
  private bool $debug;
  private AuthorizationManager $authManager;
  private AuditLogger $auditLogger;
  private UnauthorizedActionHandler $unauthorizedHandler;
  private ?\ClicShopping\AI\Config\AutonomousConfig $autonomousConfig = null;
  private SelfEvaluationPrevention $selfEvaluationPrevention;
  
  // Configuration constants
  private const DEFAULT_EVALUATOR_COUNT = 3;
  private const MIN_EVALUATOR_COUNT = 1;
  private const MAX_EVALUATOR_COUNT = 10;
  private const QUALITY_THRESHOLD = 0.70;
  
  /**
   * Constructor
   *
   * Initializes the evaluation engine with required dependencies.
   */
  public function __construct()
  {
    $this->capabilityRegistry = new AgentCapabilityRegistry();
    $this->metrics = new EvaluationMetrics();
    $this->db = Registry::get('Db');
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->authManager = new AuthorizationManager();
    $this->auditLogger = new AuditLogger();
    $this->unauthorizedHandler = new UnauthorizedActionHandler();
    
    $this->autonomousConfig = new AutonomousConfig($this->debug);  
    $this->selfEvaluationPrevention = new SelfEvaluationPrevention();
  }
  
  /**
   * Evaluate output from an agent
   *
   * Main entry point for evaluating agent output. Orchestrates the complete
   * evaluation workflow:
   * 1. Select qualified peer evaluators
   * 2. Request evaluations from each evaluator
   * 3. Aggregate evaluations into consensus result
   * 4. Trigger correction if quality threshold not met
   *
   * @param string $outputId Unique identifier for the output
   * @param string $outputType Type of output (sql_query, reasoning_chain, etc.)
   * @param mixed $output The actual output to evaluate
   * @param array $context Additional context for evaluation
   * @return EvaluationResult Aggregated evaluation result
   * @throws InvalidArgumentException If parameters are invalid
   * @throws Exception If evaluation process fails
   */
  public function evaluateOutput(
    string $outputId,
    string $outputType,
    mixed $output,
    array $context
  ): EvaluationResult {
    // Validate parameters
    if (empty($outputId)) {
      throw new InvalidArgumentException('Output ID cannot be empty');
    }
    
    if (empty($outputType)) {
      throw new InvalidArgumentException('Output type cannot be empty');
    }
    
    if (!$this->metrics->hasMetrics($outputType)) {
      throw new InvalidArgumentException("No metrics defined for output type: {$outputType}");
    }
    
    // Extract producer agent ID from context
    $producerAgentId = $context['producer_agent_id'] ?? 'unknown';
    
    if ($producerAgentId !== 'unknown') {
      $this->selfEvaluationPrevention->trackOutputProducer(
        $outputId,
        $producerAgentId,
        $outputType
      );
    }
    
    // Determine number of evaluators to select
    $evaluatorCount = $context['evaluator_count'] ?? $this->autonomousConfig->getDefaultEvaluators();
    $minEvaluators = $this->autonomousConfig->getMinEvaluators();
    $maxEvaluators = $this->autonomousConfig->getMaxEvaluators();
    $evaluatorCount = max($minEvaluators, min($maxEvaluators, $evaluatorCount));
    
    try {
      // Step 1: Select qualified evaluators
      $evaluators = $this->selectEvaluators($outputType, $producerAgentId, $evaluatorCount);
      
      if (empty($evaluators)) {
        throw new Exception("No qualified evaluators found for output type: {$outputType}");
      }
      
      // Step 2: Request evaluations from each evaluator
      $evaluations = [];
      foreach ($evaluators as $evaluatorId) {
        try {
          $evaluation = $this->requestEvaluation(
            $evaluatorId,
            $outputId,
            $output,
            $this->buildEvaluationCriteria($outputType)
          );
          
          $evaluations[] = $evaluation;
          
          // Persist evaluation to database
          $this->persistEvaluation($evaluation, $outputType, $producerAgentId);
          
        } catch (Exception $e) {
          // Log evaluation failure but continue with other evaluators
          if ($this->debug) {
            error_log("EvaluationEngine: Failed to get evaluation from {$evaluatorId}: " . $e->getMessage());
          }
        }
      }
      
      // Ensure we have at least one evaluation
      if (empty($evaluations)) {
        throw new Exception('All evaluation requests failed');
      }
      
      // Step 3: Aggregate evaluations
      $result = $this->aggregateEvaluations($evaluations);
      
      // Step 4: Trigger correction if needed
      if ($result->requiresCorrection()) {
        $this->triggerCorrection($result);
      }
      
      return $result;
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("EvaluationEngine: Evaluation failed - " . $e->getMessage());
      }
      throw $e;
    }
  }
  
  /**
   * Select qualified evaluators for an output type
   *
   * Selects peer agents capable of evaluating the specified output type,
   * excluding the producer agent to prevent self-evaluation.
   * Returns agents ordered by capability level (expert > competent > novice).
   * Falls back to ValidationAgent if no qualified evaluators are found.
   *

   * self-evaluation prevention at the evaluator selection stage.
   *
   * @param string $outputType Type of output to evaluate
   * @param string $producerAgentId ID of the agent that produced the output
   * @param int $count Number of evaluators to select (default: 3)
   * @return array Array of evaluator agent IDs
   * @throws InvalidArgumentException If count is invalid
   */
  public function selectEvaluators(
    string $outputType,
    string $producerAgentId,
    int $count = null
  ): array {
    
    if ($count === null) {
      $count = $this->autonomousConfig->getDefaultEvaluators();
    }
    
    // Validate count using config values
    $minEvaluators = $this->autonomousConfig->getMinEvaluators();
    $maxEvaluators = $this->autonomousConfig->getMaxEvaluators();
    
    if ($count < $minEvaluators || $count > $maxEvaluators) {
      throw new InvalidArgumentException(
        "Evaluator count must be between {$minEvaluators} and {$maxEvaluators}"
      );
    }
    
    // Get all capable evaluators for this output type
    $capableEvaluators = $this->capabilityRegistry->getCapableEvaluators($outputType, 'competent');
    
    // Filter out the producer agent (prevent self-evaluation)
    $eligibleEvaluators = array_filter($capableEvaluators, function($evaluator) use ($producerAgentId) {
      return $evaluator['agent_id'] !== $producerAgentId;
    });
    
    // If no eligible evaluators found, try with lower capability level
    if (empty($eligibleEvaluators)) {
      $capableEvaluators = $this->capabilityRegistry->getCapableEvaluators($outputType, 'novice');
      $eligibleEvaluators = array_filter($capableEvaluators, function($evaluator) use ($producerAgentId) {
        return $evaluator['agent_id'] !== $producerAgentId;
      });
    }
    
    
    if (empty($eligibleEvaluators)) {
      if ($this->debug) {
        error_log("EvaluationEngine: No qualified evaluators found for {$outputType}, using ValidationAgent as fallback");
      }
      
      // Use ValidationAgent as fallback (unless it's the producer)
      if ($producerAgentId !== 'ValidationAgent') {
        return ['ValidationAgent'];
      }
      
      // If ValidationAgent is the producer, log warning and return empty
      if ($this->debug) {
        error_log("EvaluationEngine: WARNING - No evaluators available for {$outputType} (ValidationAgent is producer)");
      }
      return [];
    }
    
    // Extract agent IDs
    $evaluatorIds = array_map(function($evaluator) {
      return $evaluator['agent_id'];
    }, $eligibleEvaluators);
    
    
    // This provides an additional layer of protection by using the
    // SelfEvaluationPrevention system to validate the selected evaluators
    $validatedEvaluatorIds = $this->selfEvaluationPrevention->validateEvaluatorSelection(
      $evaluatorIds,
      'output_' . $outputType, // Create a pseudo output ID for validation
      $producerAgentId
    );
    
    // Limit to requested count
    return array_slice($validatedEvaluatorIds, 0, $count);
  }
  
  /**
   * Request evaluation from a specific evaluator agent
   *
   * Sends an evaluation request to a peer agent with the output,
   * context, and evaluation criteria. This is a placeholder that
   * would integrate with the actual agent communication system.
   *

   * the evaluator is not the producer before allowing evaluation.
   *
   * @param string $evaluatorAgentId ID of the evaluator agent
   * @param string $outputId ID of the output being evaluated
   * @param mixed $output The output to evaluate
   * @param array $criteria Evaluation criteria for this output type
   * @return AgentEvaluation The evaluation from the peer agent
   * @throws Exception If evaluation request fails or self-evaluation detected
   */
  public function requestEvaluation(
    string $evaluatorAgentId,
    string $outputId,
    mixed $output,
    array $criteria
  ): AgentEvaluation {
    
    if (!$this->selfEvaluationPrevention->canEvaluate($evaluatorAgentId, $outputId)) {
      // Violation has already been logged by SelfEvaluationPrevention
      throw new Exception(
        "Self-evaluation prevented: Agent {$evaluatorAgentId} cannot evaluate its own output {$outputId}"
      );
    }
    
    // Check authorization before allowing evaluation
    $outputType = $criteria['output_type'] ?? 'unknown';
    
    $authorized = $this->authManager->verifyEvaluationAuth($evaluatorAgentId, $outputType);
    
    if (!$authorized) {
      $this->auditLogger->logEvaluation(
        $evaluatorAgentId,
        $outputId,
        'unknown',
        'denied',
        ['output_type' => $outputType]
      );
      
      $this->unauthorizedHandler->handleUnauthorizedAction(
        $evaluatorAgentId,
        'evaluate_output',
        'Agent not authorized to evaluate this output type',
        ['output_id' => $outputId, 'output_type' => $outputType]
      );
      
      throw new Exception("Agent {$evaluatorAgentId} not authorized to evaluate output type: {$outputType}");
    }
    
    // TODO: This is a placeholder for actual agent communication
    // In a real implementation, this would:
    // 1. Send a message to the evaluator agent
    // 2. Wait for the agent to perform evaluation
    // 3. Receive and parse the evaluation response
    
    // For now, we'll create a mock evaluation
    // This should be replaced with actual agent communication
    
    try {
      // Simulate evaluation scores (in production, these come from the actual agent)
      $scores = [
        'accuracy' => 0.85,
        'completeness' => 0.80,
        'efficiency' => 0.75,
        'clarity' => 0.90
      ];
      
      $feedback = "Evaluation from {$evaluatorAgentId}: The output meets most criteria with good accuracy and clarity.";
      
      $strengths = [
        "Clear structure and organization",
        "Accurate implementation of requirements"
      ];
      
      $improvements = [
        "Could improve efficiency in certain areas",
        "Consider adding more detailed documentation"
      ];
      
      $evaluation = new AgentEvaluation(
        $evaluatorAgentId,
        $outputId,
        $scores,
        $feedback,
        $strengths,
        $improvements
      );
      
      // Log successful evaluation
      $this->auditLogger->logEvaluation(
        $evaluatorAgentId,
        $outputId,
        'unknown',
        'success',
        [
          'output_type' => $outputType,
          'overall_score' => $evaluation->getOverallScore()
        ]
      );
      
      return $evaluation;
      
    } catch (Exception $e) {
      // Log failed evaluation
      $this->auditLogger->logEvaluation(
        $evaluatorAgentId,
        $outputId,
        'unknown',
        'failed',
        ['output_type' => $outputType, 'error' => $e->getMessage()]
      );
      
      throw new Exception("Failed to request evaluation from {$evaluatorAgentId}: " . $e->getMessage());
    }
  }
  
  /**
   * Aggregate multiple evaluations into a single result
   *
   * Combines multiple AgentEvaluation instances into an EvaluationResult
   * that includes consensus score, aggregated feedback, and correction
   * determination.
   *
   * @param array $evaluations Array of AgentEvaluation objects
   * @return EvaluationResult Aggregated evaluation result
   * @throws InvalidArgumentException If evaluations array is invalid
   */
  public function aggregateEvaluations(array $evaluations): EvaluationResult
  {
    if (empty($evaluations)) {
      throw new InvalidArgumentException('Evaluations array cannot be empty');
    }
    
    // Validate all elements are AgentEvaluation instances
    foreach ($evaluations as $evaluation) {
      if (!($evaluation instanceof AgentEvaluation)) {
        throw new InvalidArgumentException('All evaluations must be AgentEvaluation instances');
      }
    }
    
    // Create and return EvaluationResult
    // The EvaluationResult constructor handles all aggregation logic
    return new EvaluationResult($evaluations);
  }
  
  /**
   * Trigger correction for low-quality output
   *
   * Integrates with the CorrectionAgent to trigger correction when
   * evaluation results indicate quality below threshold.
   *
   * @param EvaluationResult $result The evaluation result requiring correction
   * @throws Exception If correction triggering fails
   */
  public function triggerCorrection(EvaluationResult $result): void
  {
    try {
      // Build error context from evaluation result
      $errorContext = [
        'output_id' => $result->getOutputId(),
        'consensus_score' => $result->getConsensusScore(),
        'evaluation_feedback' => $result->getAggregatedFeedback(),
        'correction_reason' => $result->getCorrectionReason(),
        'evaluator_count' => $result->getEvaluationCount(),
        'consensus_reached' => $result->isConsensusReached()
      ];
      
      // Initialize CorrectionAgent
      $correctionAgent = new CorrectionAgent('evaluation_engine');
      
      // Attempt correction
      $correctionResult = $correctionAgent->attemptCorrection($errorContext);
      
      if ($this->debug) {
        $status = $correctionResult['success'] ? 'succeeded' : 'failed';
        error_log("EvaluationEngine: Correction {$status} for output {$result->getOutputId()}");
      }
      
      // Log correction attempt
      $this->logCorrectionAttempt($result, $correctionResult);
      
    } catch (Exception $e) {
      if ($this->debug) {
        error_log("EvaluationEngine: Failed to trigger correction - " . $e->getMessage());
      }
      throw new Exception('Failed to trigger correction: ' . $e->getMessage());
    }
  }
  
  /**
   * Build evaluation criteria for an output type
   *
   * Retrieves the evaluation criteria from EvaluationMetrics for
   * the specified output type.
   *
   * @param string $outputType The output type
   * @return array Evaluation criteria including metrics and weights
   */
  private function buildEvaluationCriteria(string $outputType): array
  {
    return [
      'metrics' => $this->metrics->getMetrics($outputType),
      'weights' => $this->metrics->getWeights($outputType),
      'output_type' => $outputType
    ];
  }
  
  /**
   * Persist evaluation to database
   *
   * Stores an evaluation in the rag_agent_evaluations table.
   *
   * @param AgentEvaluation $evaluation The evaluation to persist
   * @param string $outputType The output type
   * @param string $producerAgentId The producer agent ID
   * @throws Exception If database operation fails
   */
  private function persistEvaluation(
    AgentEvaluation $evaluation,
    string $outputType,
    string $producerAgentId
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_agent_evaluations 
              (evaluation_id, evaluator_agent_id, output_id, output_type, producer_agent_id,
               accuracy_score, completeness_score, efficiency_score, clarity_score, overall_score,
               feedback, strengths, improvements, evaluated_at)
              VALUES (:evaluation_id, :evaluator_agent_id, :output_id, :output_type, :producer_agent_id,
                      :accuracy_score, :completeness_score, :efficiency_score, :clarity_score, :overall_score,
                      :feedback, :strengths, :improvements, :evaluated_at)";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':evaluation_id', $evaluation->getEvaluationId());
      $stmt->bindValue(':evaluator_agent_id', $evaluation->getEvaluatorAgentId());
      $stmt->bindValue(':output_id', $evaluation->getOutputId());
      $stmt->bindValue(':output_type', $outputType);
      $stmt->bindValue(':producer_agent_id', $producerAgentId);
      $stmt->bindValue(':accuracy_score', $evaluation->getAccuracyScore());
      $stmt->bindValue(':completeness_score', $evaluation->getCompletenessScore());
      $stmt->bindValue(':efficiency_score', $evaluation->getEfficiencyScore());
      $stmt->bindValue(':clarity_score', $evaluation->getClarityScore());
      $stmt->bindValue(':overall_score', $evaluation->getOverallScore());
      $stmt->bindValue(':feedback', $evaluation->getFeedback());
      $stmt->bindValue(':strengths', json_encode($evaluation->getStrengths()));
      $stmt->bindValue(':improvements', json_encode($evaluation->getImprovements()));
      $stmt->bindValue(':evaluated_at', $evaluation->getEvaluatedAt()->format('Y-m-d H:i:s'));
      $stmt->execute();
      
    } catch (Exception $e) {
      throw new Exception('Failed to persist evaluation: ' . $e->getMessage());
    }
  }
  
  /**
   * Log correction attempt
   *
   * Logs the correction attempt for monitoring and analysis.
   *
   * @param EvaluationResult $result The evaluation result
   * @param array $correctionResult The correction result
   */
  private function logCorrectionAttempt(EvaluationResult $result, array $correctionResult): void
  {
    try {
      // This could be expanded to store in a dedicated corrections log table
      if ($this->debug) {
        error_log(sprintf(
          "EvaluationEngine: Correction attempt for output %s - Success: %s, Score: %.2f",
          $result->getOutputId(),
          $correctionResult['success'] ? 'Yes' : 'No',
          $result->getConsensusScore()
        ));
      }
    } catch (Exception $e) {
      // Silently fail logging to not disrupt main flow
      if ($this->debug) {
        error_log("EvaluationEngine: Failed to log correction attempt - " . $e->getMessage());
      }
    }
  }
  
  /**
   * Get evaluation statistics
   *
   * Retrieves statistics about evaluations performed.
   *
   * @param array $filters Optional filters (output_type, date_range, etc.)
   * @return array Statistics array
   */
  public function getEvaluationStatistics(array $filters = []): array
  {
    try {
      $stats = [
        'total_evaluations' => 0,
        'average_score' => 0.0,
        'evaluations_by_type' => [],
        'evaluations_requiring_correction' => 0,
        'consensus_rate' => 0.0
      ];
      
      // Build base query
      $sql = "SELECT 
                COUNT(*) as total_evaluations,
                AVG(overall_score) as average_score,
                output_type,
                SUM(CASE WHEN overall_score < :quality_threshold THEN 1 ELSE 0 END) as low_quality_count
              FROM :table_rag_agent_evaluations";
      
      $conditions = [];
      $params = [':quality_threshold' => self::QUALITY_THRESHOLD];
      
      // Apply filters
      if (!empty($filters['output_type'])) {
        $conditions[] = "output_type = :output_type";
        $params[':output_type'] = $filters['output_type'];
      }
      
      if (!empty($filters['start_date'])) {
        $conditions[] = "evaluated_at >= :start_date";
        $params[':start_date'] = $filters['start_date'];
      }
      
      if (!empty($filters['end_date'])) {
        $conditions[] = "evaluated_at <= :end_date";
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
      
      $totalEvaluations = 0;
      $totalScore = 0.0;
      $totalLowQuality = 0;
      
      while ($row = $stmt->fetch()) {
        $count = (int)$row['total_evaluations'];
        $totalEvaluations += $count;
        $totalScore += (float)$row['average_score'] * $count;
        $totalLowQuality += (int)$row['low_quality_count'];
        
        $stats['evaluations_by_type'][$row['output_type']] = [
          'count' => $count,
          'average_score' => round((float)$row['average_score'], 2)
        ];
      }
      
      $stats['total_evaluations'] = $totalEvaluations;
      $stats['average_score'] = $totalEvaluations > 0 ? round($totalScore / $totalEvaluations, 2) : 0.0;
      $stats['evaluations_requiring_correction'] = $totalLowQuality;
      
      return $stats;
      
    } catch (Exception $e) {
      return [
        'total_evaluations' => 0,
        'average_score' => 0.0,
        'evaluations_by_type' => [],
        'evaluations_requiring_correction' => 0,
        'error' => $e->getMessage()
      ];
    }
  }
  
  /**
   * Get quality threshold
   *
   * @return float The quality threshold
   */
  public function getQualityThreshold(): float
  {
    
    return $this->autonomousConfig->getEvaluationScoreThreshold();
  }
  
  /**
   * Get evaluation metrics instance
   *
   * @return EvaluationMetrics The metrics instance
   */
  public function getMetrics(): EvaluationMetrics
  {
    return $this->metrics;
  }
  
  /**
   * Get capability registry instance
   *
   * @return AgentCapabilityRegistry The registry instance
   */
  public function getCapabilityRegistry(): AgentCapabilityRegistry
  {
    return $this->capabilityRegistry;
  }
  
  /**
   * Get self-evaluation prevention instance
   *

   * for testing and external monitoring.
   *
   * @return SelfEvaluationPrevention The self-evaluation prevention instance
   */
  public function getSelfEvaluationPrevention(): SelfEvaluationPrevention
  {
    return $this->selfEvaluationPrevention;
  }
}
