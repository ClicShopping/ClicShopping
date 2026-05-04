<?php
/**
 * ClicShopping AI - Agent Local Objectives and Evaluation System
 *
 * @copyright 2025 ClicShopping(tm). All rights reserved.
 * @license   MIT License
 * @version   1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * AgentEvaluation Class
 *
 * Represents a single agent's evaluation of peer output.
 * This class encapsulates all evaluation data including dimension scores,
 * overall score, feedback, strengths, and improvement suggestions.
 *
 * Requirements: 3.3, 3.4
 */
class AgentEvaluation
{
  private string $evaluationId;
  private string $evaluatorAgentId;
  private string $outputId;
  private float $accuracyScore;
  private float $completenessScore;
  private float $efficiencyScore;
  private float $clarityScore;
  private float $overallScore;
  private string $feedback;
  private array $strengths;
  private array $improvements;
  private DateTimeImmutable $evaluatedAt;

  /**
   * Constructor
   *
   * Creates a new agent evaluation with score validation.
   *
   * @param string $evaluatorAgentId The ID of the agent performing the evaluation
   * @param string $outputId The ID of the output being evaluated
   * @param array $scores Array containing dimension scores (accuracy, completeness, efficiency, clarity)
   * @param string $feedback Textual feedback explaining the evaluation
   * @param array $strengths Array of identified strengths in the output
   * @param array $improvements Array of suggested improvements
   * @throws InvalidArgumentException If scores are invalid or required fields are missing
   */
  public function __construct(
    string $evaluatorAgentId,
    string $outputId,
    array $scores,
    string $feedback,
    array $strengths,
    array $improvements
  ) {
    // Validate evaluator agent ID
    if (empty($evaluatorAgentId)) {
      throw new InvalidArgumentException('Evaluator agent ID cannot be empty');
    }

    // Validate output ID
    if (empty($outputId)) {
      throw new InvalidArgumentException('Output ID cannot be empty');
    }

    // Validate scores array contains all required dimensions
    $requiredDimensions = ['accuracy', 'completeness', 'efficiency', 'clarity'];
    foreach ($requiredDimensions as $dimension) {
      if (!isset($scores[$dimension])) {
        throw new InvalidArgumentException("Missing required score dimension: {$dimension}");
      }
    }

    // Validate and set dimension scores
    $this->accuracyScore = $this->validateScore($scores['accuracy'], 'accuracy');
    $this->completenessScore = $this->validateScore($scores['completeness'], 'completeness');
    $this->efficiencyScore = $this->validateScore($scores['efficiency'], 'efficiency');
    $this->clarityScore = $this->validateScore($scores['clarity'], 'clarity');

    // Calculate overall score as simple average (can be weighted in future)
    $this->overallScore = ($this->accuracyScore + $this->completenessScore + 
                          $this->efficiencyScore + $this->clarityScore) / 4.0;

    // Validate feedback
    if (empty($feedback)) {
      throw new InvalidArgumentException('Feedback cannot be empty');
    }

    // Set properties
    $this->evaluationId = $this->generateEvaluationId();
    $this->evaluatorAgentId = $evaluatorAgentId;
    $this->outputId = $outputId;
    $this->feedback = $feedback;
    $this->strengths = $strengths;
    $this->improvements = $improvements;
    $this->evaluatedAt = new DateTimeImmutable();
  }

  /**
   * Validates a score value
   *
   * @param mixed $score The score to validate
   * @param string $dimensionName The name of the dimension (for error messages)
   * @return float The validated score
   * @throws InvalidArgumentException If score is not between 0.0 and 1.0
   */
  private function validateScore($score, string $dimensionName): float
  {
    if (!is_numeric($score)) {
      throw new InvalidArgumentException("{$dimensionName} score must be numeric");
    }

    $scoreFloat = (float)$score;

    if ($scoreFloat < 0.0 || $scoreFloat > 1.0) {
      throw new InvalidArgumentException("{$dimensionName} score must be between 0.0 and 1.0, got {$scoreFloat}");
    }

    return $scoreFloat;
  }

  /**
   * Generates a unique evaluation ID
   *
   * @return string A unique identifier for this evaluation
   */
  private function generateEvaluationId(): string
  {
    return uniqid('eval_', true);
  }

  /**
   * Gets the evaluation ID
   *
   * @return string The unique evaluation identifier
   */
  public function getEvaluationId(): string
  {
    return $this->evaluationId;
  }

  /**
   * Gets the evaluator agent ID
   *
   * @return string The ID of the agent that performed the evaluation
   */
  public function getEvaluatorAgentId(): string
  {
    return $this->evaluatorAgentId;
  }

  /**
   * Gets the output ID
   *
   * @return string The ID of the evaluated output
   */
  public function getOutputId(): string
  {
    return $this->outputId;
  }

  /**
   * Gets the accuracy score
   *
   * @return float The accuracy dimension score (0.0 - 1.0)
   */
  public function getAccuracyScore(): float
  {
    return $this->accuracyScore;
  }

  /**
   * Gets the completeness score
   *
   * @return float The completeness dimension score (0.0 - 1.0)
   */
  public function getCompletenessScore(): float
  {
    return $this->completenessScore;
  }

  /**
   * Gets the efficiency score
   *
   * @return float The efficiency dimension score (0.0 - 1.0)
   */
  public function getEfficiencyScore(): float
  {
    return $this->efficiencyScore;
  }

  /**
   * Gets the clarity score
   *
   * @return float The clarity dimension score (0.0 - 1.0)
   */
  public function getClarityScore(): float
  {
    return $this->clarityScore;
  }

  /**
   * Gets the overall score
   *
   * @return float The weighted average overall score (0.0 - 1.0)
   */
  public function getOverallScore(): float
  {
    return $this->overallScore;
  }

  /**
   * Gets the feedback text
   *
   * @return string The textual feedback explaining the evaluation
   */
  public function getFeedback(): string
  {
    return $this->feedback;
  }

  /**
   * Gets the identified strengths
   *
   * @return array Array of strength descriptions
   */
  public function getStrengths(): array
  {
    return $this->strengths;
  }

  /**
   * Gets the suggested improvements
   *
   * @return array Array of improvement suggestions
   */
  public function getImprovements(): array
  {
    return $this->improvements;
  }

  /**
   * Gets the evaluation timestamp
   *
   * @return DateTimeImmutable When the evaluation was performed
   */
  public function getEvaluatedAt(): DateTimeImmutable
  {
    return $this->evaluatedAt;
  }

  /**
   * Converts the evaluation to an array for serialization
   *
   * @return array Associative array containing all evaluation data
   */
  public function toArray(): array
  {
    return [
      'evaluation_id' => $this->evaluationId,
      'evaluator_agent_id' => $this->evaluatorAgentId,
      'output_id' => $this->outputId,
      'scores' => [
        'accuracy' => $this->accuracyScore,
        'completeness' => $this->completenessScore,
        'efficiency' => $this->efficiencyScore,
        'clarity' => $this->clarityScore,
        'overall' => $this->overallScore
      ],
      'feedback' => $this->feedback,
      'strengths' => $this->strengths,
      'improvements' => $this->improvements,
      'evaluated_at' => $this->evaluatedAt->format('Y-m-d H:i:s')
    ];
  }
}
