<?php
/**
 * ClicShopping AI - Agent Local Objectives and Evaluation System
 *
 * @copyright 2025 ClicShopping(tm). All rights reserved.
 * @license   MIT License
 * @version   1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use InvalidArgumentException;

/**
 * EvaluationResult Class
 *
 * Aggregated result from multiple agent evaluations.
 * This class combines multiple AgentEvaluation instances to produce
 * a consensus score, determine if correction is needed, and aggregate feedback.
 *
 * Requirements: 3.5, 6.2
 */
class EvaluationResult
{
  private string $outputId;
  private array $evaluations;
  private float $consensusScore;
  private bool $consensusReached;
  private array $aggregatedFeedback;
  private bool $requiresCorrection;
  private ?string $correctionReason;

  // Configuration constants
  private const CONSENSUS_THRESHOLD = 0.15; // Maximum standard deviation for consensus
  private const QUALITY_THRESHOLD = 0.70; // Minimum score to avoid correction
  private const MIN_EVALUATIONS = 1; // Minimum number of evaluations required

  /**
   * Constructor
   *
   * Creates an evaluation result by aggregating multiple evaluations.
   *
   * @param array $evaluations Array of AgentEvaluation objects
   * @throws InvalidArgumentException If evaluations array is empty or contains invalid objects
   */
  public function __construct(array $evaluations)
  {
    // Validate evaluations array
    if (empty($evaluations)) {
      throw new InvalidArgumentException('Evaluations array cannot be empty');
    }

    if (count($evaluations) < self::MIN_EVALUATIONS) {
      throw new InvalidArgumentException('At least ' . self::MIN_EVALUATIONS . ' evaluation(s) required');
    }

    // Validate all elements are AgentEvaluation instances
    foreach ($evaluations as $evaluation) {
      if (!($evaluation instanceof AgentEvaluation)) {
        throw new InvalidArgumentException('All evaluations must be AgentEvaluation instances');
      }
    }

    // Verify all evaluations are for the same output
    $firstOutputId = $evaluations[0]->getOutputId();
    foreach ($evaluations as $evaluation) {
      if ($evaluation->getOutputId() !== $firstOutputId) {
        throw new InvalidArgumentException('All evaluations must be for the same output');
      }
    }

    $this->evaluations = $evaluations;
    $this->outputId = $firstOutputId;

    // Calculate consensus score
    $this->consensusScore = $this->calculateConsensusScore();

    // Determine if consensus was reached
    $this->consensusReached = $this->determineConsensusReached();

    // Aggregate feedback from all evaluations
    $this->aggregatedFeedback = $this->aggregateFeedback();

    // Determine if correction is required
    $this->requiresCorrection = $this->consensusScore < self::QUALITY_THRESHOLD;

    // Set correction reason if needed
    $this->correctionReason = $this->requiresCorrection 
      ? "Consensus score ({$this->consensusScore}) below quality threshold (" . self::QUALITY_THRESHOLD . ")"
      : null;
  }

  /**
   * Calculates the consensus score from all evaluations
   *
   * @return float The average overall score from all evaluations
   */
  private function calculateConsensusScore(): float
  {
    $totalScore = 0.0;
    $count = count($this->evaluations);

    foreach ($this->evaluations as $evaluation) {
      $totalScore += $evaluation->getOverallScore();
    }

    return $totalScore / $count;
  }

  /**
   * Determines if consensus was reached based on score variance
   *
   * Consensus is reached when the standard deviation of scores
   * is below the consensus threshold.
   *
   * @return bool True if consensus was reached, false otherwise
   */
  private function determineConsensusReached(): bool
  {
    // If only one evaluation, consensus is automatically reached
    if (count($this->evaluations) === 1) {
      return true;
    }

    // Calculate standard deviation of overall scores
    $scores = [];
    foreach ($this->evaluations as $evaluation) {
      $scores[] = $evaluation->getOverallScore();
    }

    $mean = array_sum($scores) / count($scores);
    $variance = 0.0;

    foreach ($scores as $score) {
      $variance += pow($score - $mean, 2);
    }

    $variance /= count($scores);
    $stdDev = sqrt($variance);

    return $stdDev <= self::CONSENSUS_THRESHOLD;
  }

  /**
   * Aggregates feedback from all evaluations
   *
   * Combines strengths and improvements from all evaluations,
   * removing duplicates and organizing by frequency.
   *
   * @return array Aggregated feedback structure
   */
  private function aggregateFeedback(): array
  {
    $allStrengths = [];
    $allImprovements = [];
    $allFeedback = [];

    foreach ($this->evaluations as $evaluation) {
      // Collect all feedback text
      $allFeedback[] = [
        'evaluator' => $evaluation->getEvaluatorAgentId(),
        'feedback' => $evaluation->getFeedback(),
        'score' => $evaluation->getOverallScore()
      ];

      // Collect strengths
      foreach ($evaluation->getStrengths() as $strength) {
        if (!in_array($strength, $allStrengths, true)) {
          $allStrengths[] = $strength;
        }
      }

      // Collect improvements
      foreach ($evaluation->getImprovements() as $improvement) {
        if (!in_array($improvement, $allImprovements, true)) {
          $allImprovements[] = $improvement;
        }
      }
    }

    return [
      'strengths' => $allStrengths,
      'improvements' => $allImprovements,
      'detailed_feedback' => $allFeedback,
      'consensus_score' => $this->consensusScore,
      'consensus_reached' => $this->consensusReached,
      'evaluator_count' => count($this->evaluations)
    ];
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
   * Gets all individual evaluations
   *
   * @return array Array of AgentEvaluation objects
   */
  public function getEvaluations(): array
  {
    return $this->evaluations;
  }

  /**
   * Gets the consensus score
   *
   * @return float The average score from all evaluations (0.0 - 1.0)
   */
  public function getConsensusScore(): float
  {
    return $this->consensusScore;
  }

  /**
   * Checks if consensus was reached
   *
   * @return bool True if evaluators reached consensus, false otherwise
   */
  public function isConsensusReached(): bool
  {
    return $this->consensusReached;
  }

  /**
   * Checks if correction is required
   *
   * Correction is required when the consensus score falls below
   * the configured quality threshold.
   *
   * @return bool True if correction is needed, false otherwise
   */
  public function requiresCorrection(): bool
  {
    return $this->requiresCorrection;
  }

  /**
   * Gets the reason for correction requirement
   *
   * @return string|null The reason correction is needed, or null if not required
   */
  public function getCorrectionReason(): ?string
  {
    return $this->correctionReason;
  }

  /**
   * Gets the aggregated feedback
   *
   * Returns a structured array containing:
   * - strengths: Array of unique strengths identified
   * - improvements: Array of unique improvement suggestions
   * - detailed_feedback: Array of individual feedback with evaluator info
   * - consensus_score: The calculated consensus score
   * - consensus_reached: Whether consensus was achieved
   * - evaluator_count: Number of evaluators
   *
   * @return array Aggregated feedback structure
   */
  public function getAggregatedFeedback(): array
  {
    return $this->aggregatedFeedback;
  }

  /**
   * Gets the number of evaluations
   *
   * @return int The count of individual evaluations
   */
  public function getEvaluationCount(): int
  {
    return count($this->evaluations);
  }

  /**
   * Gets the score range (min and max scores)
   *
   * @return array Array with 'min' and 'max' keys
   */
  public function getScoreRange(): array
  {
    $scores = array_map(function($eval) {
      return $eval->getOverallScore();
    }, $this->evaluations);

    return [
      'min' => min($scores),
      'max' => max($scores),
      'range' => max($scores) - min($scores)
    ];
  }

  /**
   * Converts the evaluation result to an array for serialization
   *
   * @return array Associative array containing all result data
   */
  public function toArray(): array
  {
    $evaluationsArray = array_map(function($eval) {
      return $eval->toArray();
    }, $this->evaluations);

    return [
      'output_id' => $this->outputId,
      'consensus_score' => $this->consensusScore,
      'consensus_reached' => $this->consensusReached,
      'requires_correction' => $this->requiresCorrection,
      'correction_reason' => $this->correctionReason,
      'evaluation_count' => count($this->evaluations),
      'score_range' => $this->getScoreRange(),
      'aggregated_feedback' => $this->aggregatedFeedback,
      'individual_evaluations' => $evaluationsArray
    ];
  }
}
