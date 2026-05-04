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

/**
 * ConsensusResult Class
 *
 * Represents the result of a consensus-building process among multiple evaluators.
 * Contains the final consensus score, participating agents, agreement metrics,
 * and whether consensus was successfully reached.
 *
 * Requirements: 6.2, 6.4
 */
class ConsensusResult
{
  private string $sessionId;
  private string $outputId;
  private array $participatingAgents;
  private array $initialScores;
  private bool $consensusReached;
  private ?float $finalScore;
  private float $agreementLevel;
  private array $outliers;
  private ?string $discussionLog;
  private DateTimeImmutable $createdAt;
  private ?DateTimeImmutable $resolvedAt;

  /**
   * Constructor
   *
   * @param string $outputId The ID of the output being evaluated
   * @param array $participatingAgents Array of agent IDs participating in consensus
   * @param array $initialScores Array of initial scores from each agent
   * @param bool $consensusReached Whether consensus was reached
   * @param float|null $finalScore The final consensus score (null if not reached)
   * @param float $agreementLevel The level of agreement (0.0 - 1.0, based on std deviation)
   * @param array $outliers Array of outlier evaluations
   * @param string|null $discussionLog Log of discussion if one occurred
   */
  public function __construct(
    string $outputId,
    array $participatingAgents,
    array $initialScores,
    bool $consensusReached,
    ?float $finalScore,
    float $agreementLevel,
    array $outliers = [],
    ?string $discussionLog = null
  ) {
    $this->sessionId = $this->generateSessionId();
    $this->outputId = $outputId;
    $this->participatingAgents = $participatingAgents;
    $this->initialScores = $initialScores;
    $this->consensusReached = $consensusReached;
    $this->finalScore = $finalScore;
    $this->agreementLevel = $agreementLevel;
    $this->outliers = $outliers;
    $this->discussionLog = $discussionLog;
    $this->createdAt = new DateTimeImmutable();
    $this->resolvedAt = $consensusReached ? new DateTimeImmutable() : null;
  }

  /**
   * Generates a unique session ID
   *
   * @return string A unique identifier for this consensus session
   */
  private function generateSessionId(): string
  {
    return uniqid('consensus_', true);
  }

  /**
   * Gets the session ID
   *
   * @return string The unique session identifier
   */
  public function getSessionId(): string
  {
    return $this->sessionId;
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
   * Gets the participating agents
   *
   * @return array Array of agent IDs that participated
   */
  public function getParticipatingAgents(): array
  {
    return $this->participatingAgents;
  }

  /**
   * Gets the initial scores
   *
   * @return array Array of initial scores from each agent
   */
  public function getInitialScores(): array
  {
    return $this->initialScores;
  }

  /**
   * Checks if consensus was reached
   *
   * @return bool True if consensus was reached, false otherwise
   */
  public function isConsensusReached(): bool
  {
    return $this->consensusReached;
  }

  /**
   * Gets the final consensus score
   *
   * @return float|null The final score, or null if consensus not reached
   */
  public function getFinalScore(): ?float
  {
    return $this->finalScore;
  }

  /**
   * Gets the agreement level
   *
   * @return float The level of agreement (0.0 = no agreement, 1.0 = perfect agreement)
   */
  public function getAgreementLevel(): float
  {
    return $this->agreementLevel;
  }

  /**
   * Gets the outlier evaluations
   *
   * @return array Array of outlier evaluation data
   */
  public function getOutliers(): array
  {
    return $this->outliers;
  }

  /**
   * Gets the discussion log
   *
   * @return string|null The discussion log, or null if no discussion occurred
   */
  public function getDiscussionLog(): ?string
  {
    return $this->discussionLog;
  }

  /**
   * Gets the creation timestamp
   *
   * @return DateTimeImmutable When the consensus session was created
   */
  public function getCreatedAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Gets the resolution timestamp
   *
   * @return DateTimeImmutable|null When consensus was reached, or null if not reached
   */
  public function getResolvedAt(): ?DateTimeImmutable
  {
    return $this->resolvedAt;
  }

  /**
   * Converts the consensus result to an array for serialization
   *
   * @return array Associative array containing all consensus data
   */
  public function toArray(): array
  {
    return [
      'session_id' => $this->sessionId,
      'output_id' => $this->outputId,
      'participating_agents' => $this->participatingAgents,
      'initial_scores' => $this->initialScores,
      'consensus_reached' => $this->consensusReached,
      'final_score' => $this->finalScore,
      'agreement_level' => $this->agreementLevel,
      'outliers' => $this->outliers,
      'discussion_log' => $this->discussionLog,
      'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
      'resolved_at' => $this->resolvedAt ? $this->resolvedAt->format('Y-m-d H:i:s') : null
    ];
  }
}
