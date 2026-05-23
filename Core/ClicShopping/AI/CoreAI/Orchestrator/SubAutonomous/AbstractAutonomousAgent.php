<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\AI\InterfacesAI\AutonomousAgentInterface;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Registry;
use ClicShopping\AI\CoreAI\Orchestrator\SubAbstention\AgentAbstentionManager;

/**
 * AbstractAutonomousAgent
 *
 * Base implementation class providing common autonomous agent functionality.
 * CoreAI can extend this class to inherit default behavior for autonomous features
 * while customizing specific methods for their domain.
 *
 * This class provides:
 * - Default implementations for optional autonomous methods
 * - Common utility methods for objective and evaluation management
 * - Integration with ObjectiveRegistry and AgentCapabilityRegistry
 * - Logging and error handling
 *
 * CoreAI extending this class should:
 * - Override getEvaluationCapabilities() to declare their expertise
 * - Implement domain-specific objective execution logic
 * - Customize evaluation criteria for their output types
 * - Define collaboration rules based on their capabilities
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @version 1.0.0
 * @since 2026-01-28
 */
abstract class AbstractAutonomousAgent implements AutonomousAgentInterface
{
  protected ObjectiveRegistry $objectiveRegistry;
  protected AgentCapabilityRegistry $capabilityRegistry;
  protected EvaluationEngine $evaluationEngine;
  protected FeedbackManager $feedbackManager;
  protected AgentAbstentionManager $abstentionManager;
  protected SecurityLogger $securityLogger;
  protected bool $debug;
  protected string $agentId;

  /**
   * Constructor
   *
   * @param string $agentId Unique identifier for this agent
   * @param bool $debug Enable debug logging
   */
  public function __construct(string $agentId, bool $debug = false)
  {
    $this->agentId = $agentId;
    $this->debug = $debug;
    $this->securityLogger = new SecurityLogger();

    $db = Registry::get('Db');

    // Initialize autonomous components
    $this->objectiveRegistry = new ObjectiveRegistry($db, $debug);
    $this->capabilityRegistry = new AgentCapabilityRegistry($db, $debug);
    $this->evaluationEngine = new EvaluationEngine($db, $debug);
    $this->feedbackManager = new FeedbackManager($db, $debug);
    $this->abstentionManager = new AgentAbstentionManager();

    // Register agent capabilities on initialization
    $this->registerCapabilities();
  }

  /**
   * Register agent's evaluation capabilities
   *
   * Called during initialization to register this agent's capabilities
   * in the AgentCapabilityRegistry. Subclasses should override
   * getEvaluationCapabilities() to define their expertise.
   */
  protected function registerCapabilities(): void
  {
    $capabilities = $this->getEvaluationCapabilities();

    foreach ($capabilities as $outputType => $level) {
      try {
        $this->capabilityRegistry->registerCapability(
          $this->agentId,
          $outputType,
          $level
        );

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Registered capability: {$this->agentId} -> {$outputType} ({$level})",
            'info'
          );
        }
      } catch (\Exception $e) {
        $this->securityLogger->logSecurityEvent(
          "Failed to register capability {$outputType}: " . $e->getMessage(),
          'error'
        );
      }
    }
  }

  /**
   * Create a local objective
   *
   * Default implementation that creates and registers an objective.
   * Subclasses can override to add domain-specific validation or logic.
   *
   * @param string $goalStatement Clear description of the goal
   * @param array $successCriteria Measurable success criteria
   * @param string $priority Priority level
   * @return LocalObjective The created objective
   */
  public function createLocalObjective(
    string $goalStatement,
    array $successCriteria,
    string $priority
  ): LocalObjective {
    // Estimate completion time based on priority (can be overridden)
    $estimatedTime = match ($priority) {
      'critical' => 300,  // 5 minutes
      'high' => 900,      // 15 minutes
      'medium' => 1800,   // 30 minutes
      'low' => 3600,      // 1 hour
      default => 1800
    };

    $objective = new LocalObjective(
      $this->agentId,
      $goalStatement,
      $successCriteria,
      $priority,
      $estimatedTime
    );

    // Register with ObjectiveRegistry
    $objectiveId = $this->objectiveRegistry->registerObjective($objective);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Created objective {$objectiveId}: {$goalStatement}",
        'info'
      );
    }

    return $objective;
  }

  /**
   * Execute an objective
   *
   * Abstract method that must be implemented by subclasses.
   * Each agent defines how to execute objectives in its domain.
   *
   * @param LocalObjective $objective The objective to execute
   * @return mixed The output produced by execution
   */
  abstract public function executeObjective(LocalObjective $objective): mixed;

  /**
   * Evaluate peer output
   *
   * Default implementation that performs basic evaluation.
   * Subclasses should override to provide domain-specific evaluation logic.
   *
   * @param string $outputType Type of output
   * @param mixed $output The output to evaluate
   * @param array $criteria Evaluation criteria
   * @return AgentEvaluation The evaluation result
   */
  public function evaluatePeerOutput(
    string $outputType,
    mixed $output,
    array $criteria
  ): AgentEvaluation {
    // Verify agent has capability for this output type
    if (!$this->capabilityRegistry->hasCapability($this->agentId, $outputType)) {
      throw new \InvalidArgumentException(
        "Agent {$this->agentId} lacks capability to evaluate {$outputType}"
      );
    }

    // Perform evaluation (subclasses should override for domain-specific logic)
    $scores = $this->performEvaluation($outputType, $output, $criteria);

    // Create evaluation object
    $evaluation = new AgentEvaluation(
      $this->agentId,
      $output['output_id'] ?? uniqid('output_'),
      $scores,
      $scores['feedback'] ?? 'Evaluation completed',
      $scores['strengths'] ?? [],
      $scores['improvements'] ?? []
    );

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Evaluated {$outputType}: score={$evaluation->getOverallScore()}",
        'info'
      );
    }

    return $evaluation;
  }

  /**
   * Perform evaluation logic
   *
   * Protected method that subclasses can override to implement
   * domain-specific evaluation logic.
   *
   * @param string $outputType Type of output
   * @param mixed $output The output to evaluate
   * @param array $criteria Evaluation criteria
   * @return array Evaluation scores and feedback
   */
  protected function performEvaluation(string $outputType, mixed $output, array $criteria): array
  {
    // Default implementation provides basic scores
    // Subclasses should override for domain-specific evaluation
    return [
      'accuracy_score' => 0.8,
      'completeness_score' => 0.8,
      'efficiency_score' => 0.8,
      'clarity_score' => 0.8,
      'feedback' => 'Default evaluation - override performEvaluation() for domain-specific logic',
      'strengths' => ['Output structure is valid'],
      'improvements' => ['Consider domain-specific optimizations']
    ];
  }

  /**
   * Receive feedback
   *
   * Default implementation that acknowledges feedback and logs it.
   * Subclasses can override to implement learning mechanisms.
   *
   * @param array $feedback Feedback from peer agent
   */
  public function receiveFeedback(array $feedback): void
  {
    // Validate feedback structure
    $required = ['feedback_id', 'source_agent_id', 'output_id', 'feedback_type', 'feedback_text'];
    foreach ($required as $field) {
      if (!isset($feedback[$field])) {
        throw new \InvalidArgumentException("Missing required feedback field: {$field}");
      }
    }

    // Acknowledge feedback
    $this->feedbackManager->acknowledgeFeedback(
      $feedback['feedback_id'],
      $this->agentId,
      null // No response by default
    );

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Received feedback from {$feedback['source_agent_id']}: {$feedback['feedback_type']}",
        'info'
      );
    }

    // Subclasses can override to implement learning from feedback
    $this->learnFromFeedback($feedback);
  }

  /**
   * Learn from feedback
   *
   * Protected method that subclasses can override to implement
   * learning mechanisms based on received feedback.
   *
   * @param array $feedback The feedback to learn from
   */
  protected function learnFromFeedback(array $feedback): void
  {
    // Default implementation does nothing
    // Subclasses can override to adjust behavior based on feedback
  }

  /**
   * Get evaluation capabilities
   *
   * Abstract method that must be implemented by subclasses.
   * Each agent declares what output types it can evaluate.
   *
   * @return array Mapping of output types to capability levels
   */
  abstract public function getEvaluationCapabilities(): array;

  /**
   * Check if agent can collaborate
   *
   * Default implementation checks if objective is in agent's domain.
   * Subclasses can override for more sophisticated collaboration logic.
   *
   * @param LocalObjective $objective The objective to evaluate
   * @return bool True if agent can collaborate
   */
  public function canCollaborate(LocalObjective $objective): bool
  {
    // Default: check if objective's goal mentions any of our capabilities
    $capabilities = array_keys($this->getEvaluationCapabilities());
    $goalStatement = strtolower($objective->getGoalStatement());

    foreach ($capabilities as $capability) {
      if (str_contains($goalStatement, strtolower($capability))) {
        return true;
      }
    }

    return false;
  }

  /**
   * Get agent ID
   *
   * @return string The agent's unique identifier
   */
  public function getAgentId(): string
  {
    return $this->agentId;
  }

  /**
   * Evaluate confidence for a task
   *
   * Default implementation delegates to AgentAbstentionManager.
   * Subclasses can override to provide domain-specific confidence evaluation.
   *
   * @param string $task Task description or identifier
   * @param array $context Task context and parameters
   * @return float Confidence score (0.0-1.0)
   *
   * Validates: Requirement 15.1
   */
  public function evaluateConfidence(string $task, array $context): float
  {
    return $this->abstentionManager->evaluateConfidence(
      $this->agentId,
      $task,
      $context
    );
  }

  /**
   * Determine if agent should abstain from task
   *
   * Default implementation delegates to AgentAbstentionManager.
   * Subclasses can override for custom abstention logic.
   *
   * @param float $confidence Confidence score
   * @return bool True if agent should abstain
   *
   * Validates: Requirement 15.2
   */
  public function shouldAbstain(float $confidence): bool
  {
    return $this->abstentionManager->shouldAbstain($this->agentId, $confidence);
  }

  /**
   * Get abstention decision with recommended action
   *
   * Default implementation delegates to AgentAbstentionManager.
   * Returns complete decision with action, reason, and suggested delegate.
   *
   * @param float $confidence Confidence score
   * @return array Decision structure
   *
   * Validates: Requirements 15.2, 15.3
   */
  public function getAbstentionDecision(float $confidence): array
  {
    return $this->abstentionManager->getAbstentionDecision(
      $this->agentId,
      $confidence,
      'unknown' // Task type can be passed if available
    );
  }
}
