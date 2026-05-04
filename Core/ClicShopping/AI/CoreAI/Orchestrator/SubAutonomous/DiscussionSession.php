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
 * DiscussionSession Class
 *
 * Represents a discussion session between agents to reconcile divergent evaluations.
 * Tracks the discussion process, messages exchanged, and resolution status.
 *
 * Requirements: 6.3, 6.5
 */
class DiscussionSession
{
  private string $sessionId;
  private string $outputId;
  private array $divergentEvaluations;
  private array $messages;
  private bool $resolved;
  private ?float $resolvedScore;
  private DateTimeImmutable $startedAt;
  private ?DateTimeImmutable $resolvedAt;
  private int $timeoutSeconds;

  // Configuration constants
  private const DEFAULT_TIMEOUT = 300; // 5 minutes

  /**
   * Constructor
   *
   * @param string $outputId The ID of the output being discussed
   * @param array $divergentEvaluations Array of AgentEvaluation objects with divergent scores
   * @param int $timeoutSeconds Timeout for discussion in seconds
   */
  public function __construct(
    string $outputId,
    array $divergentEvaluations,
    int $timeoutSeconds = self::DEFAULT_TIMEOUT
  ) {
    $this->sessionId = $this->generateSessionId();
    $this->outputId = $outputId;
    $this->divergentEvaluations = $divergentEvaluations;
    $this->messages = [];
    $this->resolved = false;
    $this->resolvedScore = null;
    $this->startedAt = new DateTimeImmutable();
    $this->resolvedAt = null;
    $this->timeoutSeconds = $timeoutSeconds;
  }

  /**
   * Generates a unique session ID
   *
   * @return string A unique identifier for this discussion session
   */
  private function generateSessionId(): string
  {
    return uniqid('discussion_', true);
  }

  /**
   * Adds a message to the discussion
   *
   * @param string $agentId The ID of the agent sending the message
   * @param string $message The message content
   * @param array $metadata Optional metadata about the message
   */
  public function addMessage(string $agentId, string $message, array $metadata = []): void
  {
    $this->messages[] = [
      'agent_id' => $agentId,
      'message' => $message,
      'metadata' => $metadata,
      'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
    ];
  }

  /**
   * Marks the discussion as resolved
   *
   * @param float $resolvedScore The final agreed-upon score
   */
  public function resolve(float $resolvedScore): void
  {
    $this->resolved = true;
    $this->resolvedScore = $resolvedScore;
    $this->resolvedAt = new DateTimeImmutable();
  }

  /**
   * Checks if the discussion has timed out
   *
   * @return bool True if the discussion has exceeded the timeout, false otherwise
   */
  public function hasTimedOut(): bool
  {
    $now = new DateTimeImmutable();
    $elapsed = $now->getTimestamp() - $this->startedAt->getTimestamp();
    return $elapsed > $this->timeoutSeconds;
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
   * @return string The ID of the output being discussed
   */
  public function getOutputId(): string
  {
    return $this->outputId;
  }

  /**
   * Gets the divergent evaluations
   *
   * @return array Array of AgentEvaluation objects
   */
  public function getDivergentEvaluations(): array
  {
    return $this->divergentEvaluations;
  }

  /**
   * Gets all messages in the discussion
   *
   * @return array Array of message data
   */
  public function getMessages(): array
  {
    return $this->messages;
  }

  /**
   * Checks if the discussion is resolved
   *
   * @return bool True if resolved, false otherwise
   */
  public function isResolved(): bool
  {
    return $this->resolved;
  }

  /**
   * Gets the resolved score
   *
   * @return float|null The resolved score, or null if not resolved
   */
  public function getResolvedScore(): ?float
  {
    return $this->resolvedScore;
  }

  /**
   * Gets the start timestamp
   *
   * @return DateTimeImmutable When the discussion started
   */
  public function getStartedAt(): DateTimeImmutable
  {
    return $this->startedAt;
  }

  /**
   * Gets the resolution timestamp
   *
   * @return DateTimeImmutable|null When the discussion was resolved, or null if not resolved
   */
  public function getResolvedAt(): ?DateTimeImmutable
  {
    return $this->resolvedAt;
  }

  /**
   * Gets the timeout duration
   *
   * @return int Timeout in seconds
   */
  public function getTimeoutSeconds(): int
  {
    return $this->timeoutSeconds;
  }

  /**
   * Gets the elapsed time
   *
   * @return int Elapsed time in seconds
   */
  public function getElapsedSeconds(): int
  {
    $now = new DateTimeImmutable();
    return $now->getTimestamp() - $this->startedAt->getTimestamp();
  }

  /**
   * Gets the remaining time before timeout
   *
   * @return int Remaining time in seconds (0 if timed out)
   */
  public function getRemainingSeconds(): int
  {
    $remaining = $this->timeoutSeconds - $this->getElapsedSeconds();
    return max(0, $remaining);
  }

  /**
   * Converts the discussion session to an array for serialization
   *
   * @return array Associative array containing all session data
   */
  public function toArray(): array
  {
    return [
      'session_id' => $this->sessionId,
      'output_id' => $this->outputId,
      'divergent_evaluations' => array_map(function($eval) {
        return $eval->toArray();
      }, $this->divergentEvaluations),
      'messages' => $this->messages,
      'resolved' => $this->resolved,
      'resolved_score' => $this->resolvedScore,
      'started_at' => $this->startedAt->format('Y-m-d H:i:s'),
      'resolved_at' => $this->resolvedAt ? $this->resolvedAt->format('Y-m-d H:i:s') : null,
      'timeout_seconds' => $this->timeoutSeconds,
      'elapsed_seconds' => $this->getElapsedSeconds(),
      'remaining_seconds' => $this->getRemainingSeconds(),
      'has_timed_out' => $this->hasTimedOut()
    ];
  }
}
