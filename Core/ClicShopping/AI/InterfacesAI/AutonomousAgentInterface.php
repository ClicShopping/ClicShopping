<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\InterfacesAI;

use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation;

/**
 * AutonomousAgentInterface
 *
 * Interface that agents must implement to support autonomous features including
 * local objective definition, peer evaluation, and collaborative feedback.
 *
 * Purpose:
 * - Enable agents to define and pursue domain-specific goals autonomously
 * - Support inter-agent evaluation for quality assurance
 * - Facilitate collaborative feedback and improvement
 * - Maintain system coherence through peer review
 *
 * Autonomous Agent Flow:
 * 1. Agent identifies opportunity → createLocalObjective()
 * 2. Objective approved → executeObjective()
 * 3. Output produced → Peer agents call evaluatePeerOutput()
 * 4. Feedback delivered → receiveFeedback()
 * 5. Agent learns and improves
 *
 * Requirements Validated:
 * - Requirement 1.1: Local objective creation
 * - Requirement 3.3: Peer output evaluation
 * - Requirement 5.4: Feedback acknowledgment
 * - Requirement 8.3: Collaboration capability
 *
 * @package ClicShopping\AI\InterfacesAI
 * @version 1.0.0
 * @since 2026-01-28
 */
interface AutonomousAgentInterface
{
  /**
   * Create a local objective for autonomous execution
   *
   * Allows an agent to define a goal it wants to pursue within its domain
   * of expertise. The objective will be registered in the ObjectiveRegistry
   * and may require orchestrator approval if conflicts are detected.
   *
   * The agent should only create objectives that:
   * - Fall within its domain of expertise
   * - Improve system performance or user experience
   * - Do not conflict with system-wide constraints
   * - Have measurable success criteria
   *
   * Example objectives:
   * - AnalyticsAgent: "Optimize query performance for product searches"
   * - ReasoningAgent: "Improve reasoning chain clarity for complex queries"
   * - ValidationAgent: "Enhance validation coverage for SQL injection patterns"
   *
   * @param string $goalStatement Clear description of what the agent wants to achieve
   * @param array $successCriteria Measurable criteria to determine objective completion
   *                               Example: ['response_time' => '< 200ms', 'accuracy' => '> 95%']
   * @param string $priority Priority level: 'low', 'medium', 'high', 'critical'
   *
   * @return LocalObjective The created objective with unique ID and pending status
   *
   * @throws \InvalidArgumentException If goal statement is empty or priority is invalid
   * @throws \RuntimeException If objective creation fails
   *
   * @see LocalObjective
   * @see ObjectiveRegistry::registerObjective()
   *
   * Validates: Requirements 1.1, 1.2
   */
  public function createLocalObjective(
    string $goalStatement,
    array $successCriteria,
    string $priority
  ): LocalObjective;

  /**
   * Execute a local objective
   *
   * Performs the work required to complete the objective. The agent should:
   * - Execute within the objective's estimated completion time
   * - Produce measurable results aligned with success criteria
   * - Handle errors gracefully and report failures
   * - Update objective status throughout execution
   *
   * The return value should contain the output produced by the objective
   * execution, which will be subject to peer evaluation.
   *
   * Execution flow:
   * 1. Validate objective is approved and active
   * 2. Perform the objective's work
   * 3. Collect metrics and results
   * 4. Update objective status (completed/failed)
   * 5. Return output for evaluation
   *
   * @param LocalObjective $objective The objective to execute (must be in 'approved' or 'active' status)
   *
   * @return mixed The output produced by objective execution (structure depends on objective type)
   *               Examples:
   *               - SQL query optimization: ['original_time' => 500, 'optimized_time' => 150, 'query' => '...']
   *               - Validation enhancement: ['new_patterns' => [...], 'coverage_increase' => 15]
   *
   * @throws \InvalidArgumentException If objective is not in executable status
   * @throws \RuntimeException If execution fails
   *
   * @see LocalObjective::setStatus()
   * @see LocalObjective::markCompleted()
   * @see LocalObjective::markFailed()
   *
   * Validates: Requirements 1.1, 2.1
   */
  public function executeObjective(LocalObjective $objective): mixed;

  /**
   * Evaluate peer agent output
   *
   * Assesses the quality of output produced by another agent. The evaluation
   * should be objective, constructive, and based on defined metrics for the
   * output type.
   *
   * Evaluation dimensions (all scored 0.0 - 1.0):
   * - Accuracy: Correctness and precision of the output
   * - Completeness: Whether all required elements are present
   * - Efficiency: Performance and resource usage
   * - Clarity: Readability and understandability
   *
   * The agent should only evaluate output types for which it has registered
   * capabilities. Evaluations should include:
   * - Numerical scores for each dimension
   * - Specific strengths identified
   * - Actionable improvement suggestions
   * - Overall justification
   *
   * Example evaluation criteria by output type:
   * - SQL queries: Syntax correctness, optimization, security, readability
   * - Reasoning chains: Logical flow, completeness, clarity, evidence quality
   * - Validation results: Coverage, false positive rate, performance
   *
   * @param string $outputType Type of output being evaluated (e.g., 'sql_query', 'reasoning_chain', 'validation_result')
   * @param mixed $output The actual output to evaluate (structure depends on output type)
   * @param array $criteria Evaluation criteria specific to this output type
   *                        Example: ['max_execution_time' => 200, 'min_accuracy' => 0.9]
   *
   * @return AgentEvaluation Complete evaluation with scores, feedback, strengths, and improvements
   *
   * @throws \InvalidArgumentException If agent lacks capability for this output type
   * @throws \RuntimeException If evaluation fails
   *
   * @see AgentEvaluation
   * @see EvaluationMetrics::getMetrics()
   * @see AgentCapabilityRegistry::hasCapability()
   *
   * Validates: Requirements 3.3, 4.2, 4.3, 4.4
   */
  public function evaluatePeerOutput(
    string $outputType,
    mixed $output,
    array $criteria
  ): AgentEvaluation;

  /**
   * Receive and process feedback from peer agents
   *
   * Handles feedback delivered by other agents after they evaluate this agent's
   * output. The agent should:
   * - Acknowledge receipt of feedback
   * - Analyze feedback for actionable improvements
   * - Optionally respond with clarifications
   * - Learn from feedback to improve future outputs
   *
   * Feedback structure:
   * [
   *     'feedback_id' => string,           // Unique feedback identifier
   *     'source_agent_id' => string,       // Agent who provided feedback
   *     'output_id' => string,             // Output that was evaluated
   *     'feedback_type' => string,         // 'correctness', 'efficiency', 'completeness', 'best_practice'
   *     'feedback_text' => string,         // Detailed feedback message
   *     'strengths' => array,              // Identified strengths
   *     'improvements' => array,           // Suggested improvements
   *     'overall_score' => float,          // Overall evaluation score (0.0-1.0)
   *     'timestamp' => string              // When feedback was created
   * ]
   *
   * The agent should acknowledge feedback and may optionally respond:
   * - Acknowledge: Confirm receipt and understanding
   * - Respond: Provide clarifications or ask questions
   * - Learn: Adjust behavior based on feedback patterns
   *
   * @param array $feedback Structured feedback from peer agent (see format above)
   *
   * @return void
   *
   * @throws \InvalidArgumentException If feedback structure is invalid
   * @throws \RuntimeException If feedback processing fails
   *
   * @see FeedbackManager::acknowledgeFeedback()
   * @see FeedbackManager::getFeedbackHistory()
   *
   * Validates: Requirements 5.2, 5.4
   */
  public function receiveFeedback(array $feedback): void;

  /**
   * Get evaluation capabilities
   *
   * Returns the list of output types this agent is qualified to evaluate,
   * along with the agent's capability level for each type.
   *
   * Capability levels:
   * - 'novice': Basic understanding, can provide simple feedback
   * - 'competent': Good understanding, can provide detailed evaluation
   * - 'expert': Deep expertise, evaluations carry higher weight in consensus
   *
   * The agent should only claim capabilities for output types it truly
   * understands. Capability levels should reflect actual expertise.
   *
   * Example capabilities:
   * - AnalyticsAgent: ['sql_query' => 'expert', 'data_analysis' => 'expert', 'reasoning_chain' => 'competent']
   * - ReasoningAgent: ['reasoning_chain' => 'expert', 'validation_result' => 'competent', 'sql_query' => 'novice']
   * - ValidationAgent: ['validation_result' => 'expert', 'security_check' => 'expert', 'sql_query' => 'competent']
   *
   * @return array Associative array mapping output types to capability levels
   *               Format: ['output_type' => 'capability_level', ...]
   *               Example: ['sql_query' => 'expert', 'reasoning_chain' => 'competent']
   *
   * @see AgentCapabilityRegistry::registerCapability()
   * @see EvaluationEngine::selectEvaluators()
   *
   * Validates: Requirements 7.1, 7.2
   */
  public function getEvaluationCapabilities(): array;

  /**
   * Check if agent can collaborate on an objective
   *
   * Determines whether this agent can contribute to another agent's objective.
   * Collaboration is beneficial when:
   * - Objectives are complementary (different aspects of same goal)
   * - Agent has relevant expertise for the objective
   * - Collaboration would improve outcome quality or efficiency
   * - No resource conflicts exist
   *
   * The agent should analyze the objective's:
   * - Goal statement and success criteria
   * - Required capabilities and resources
   * - Alignment with agent's domain expertise
   * - Potential for meaningful contribution
   *
   * Example collaboration scenarios:
   * - AnalyticsAgent + ReasoningAgent: Complex data analysis with interpretation
   * - ValidationAgent + SecurityAgent: Comprehensive security validation
   * - SemanticAgent + AnalyticsAgent: Hybrid semantic + analytical queries
   *
   * @param LocalObjective $objective The objective to evaluate for collaboration potential
   *
   * @return bool True if agent can meaningfully contribute to the objective, false otherwise
   *
   * @see ObjectiveRegistry::detectConflicts()
   * @see ConflictDetector::suggestMerge()
   *
   * Validates: Requirements 8.1, 8.3
   */
  public function canCollaborate(LocalObjective $objective): bool;

  /**
   * Evaluate confidence for a task
   *
   * Calculates the agent's confidence level (0.0-1.0) in its ability to
   * successfully complete a given task. The confidence score should reflect:
   * - Agent's historical performance on similar tasks
   * - Availability of required context and information
   * - Task complexity relative to agent's capabilities
   * - Current system load and resource availability
   *
   * Confidence scoring guidelines:
   * - 0.0-0.3: Very low confidence (should abstain and escalate)
   * - 0.3-0.5: Low confidence (should delegate to more capable peer)
   * - 0.5-0.7: Moderate confidence (can execute with caution)
   * - 0.7-0.9: High confidence (can execute normally)
   * - 0.9-1.0: Very high confidence (optimal execution expected)
   *
   * Factors to consider:
   * - Has agent successfully completed similar tasks before?
   * - Is all required context and data available?
   * - Does task complexity match agent's expertise level?
   * - Are there any ambiguities or uncertainties?
   *
   * @param string $task Task description or identifier
   * @param array $context Task context including parameters, requirements, and constraints
   *                       Example: ['task_type' => 'sql_query', 'complexity' => 0.7, 'data_available' => true]
   *
   * @return float Confidence score between 0.0 (no confidence) and 1.0 (complete confidence)
   *
   * @see AgentAbstentionManager::evaluateConfidence()
   *
   * Validates: Requirement 15.1
   */
  public function evaluateConfidence(string $task, array $context): float;

  /**
   * Determine if agent should abstain from task
   *
   * Checks whether the agent's confidence is too low to safely execute the task.
   * When confidence falls below the abstention threshold, the agent should:
   * - Refuse to execute the task
   * - Escalate to human operator for review
   * - Log the abstention with reasoning
   *
   * Abstention is a safety mechanism to prevent:
   * - Producing unreliable or incorrect results
   * - Causing system errors or failures
   * - Wasting resources on likely-to-fail attempts
   * - Degrading user experience with poor outputs
   *
   * The abstention threshold is configurable per agent type but typically
   * defaults to 0.3 (30% confidence).
   *
   * @param float $confidence Confidence score from evaluateConfidence()
   *
   * @return bool True if agent should abstain (confidence below threshold), false otherwise
   *
   * @see AgentAbstentionManager::shouldAbstain()
   * @see AgentAbstentionManager::setThresholds()
   *
   * Validates: Requirement 15.2
   */
  public function shouldAbstain(float $confidence): bool;

  /**
   * Get abstention decision with recommended action
   *
   * Analyzes confidence score and returns a complete decision including:
   * - Recommended action: 'execute', 'delegate', or 'abstain'
   * - Reasoning for the decision
   * - Suggested delegate agent (if delegation recommended)
   * - Confidence score that led to the decision
   *
   * Decision logic:
   * - confidence >= delegation_threshold (default 0.5): Execute normally
   * - abstention_threshold <= confidence < delegation_threshold: Delegate to peer
   * - confidence < abstention_threshold (default 0.3): Abstain and escalate
   *
   * Return structure:
   * [
   *     'action' => string,              // 'execute', 'delegate', or 'abstain'
   *     'reason' => string,              // Explanation for the decision
   *     'confidence' => float,           // The confidence score
   *     'suggested_delegate' => ?string  // Agent ID to delegate to (null if not applicable)
   * ]
   *
   * @param float $confidence Confidence score from evaluateConfidence()
   *
   * @return array Decision structure with action, reason, confidence, and suggested delegate
   *
   * @see AgentAbstentionManager::getAbstentionDecision()
   * @see AgentAbstentionManager::findCapableDelegate()
   *
   * Validates: Requirements 15.2, 15.3
   */
  public function getAbstentionDecision(float $confidence): array;
}
