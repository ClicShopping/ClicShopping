<?php
/**
 * LocalObjective Class
 *
 * Represents an autonomous goal defined by an agent within its domain of expertise.
 * This class encapsulates all information about an objective including its goal,
 * success criteria, priority, status, and lifecycle management.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use DateTimeImmutable;
use InvalidArgumentException;

class LocalObjective
{
  private string $objectiveId;
  private string $agentId;
  private string $goalStatement;
  private array $successCriteria;
  private string $priority;
  private int $estimatedCompletionTime;
  private string $status;
  private ?string $conflictsWith;
  private DateTimeImmutable $createdAt;
  private ?DateTimeImmutable $completedAt;
  private array $metrics;
  private ?string $failureReason;

  /**
   * Valid priority levels
   */
  private const VALID_PRIORITIES = ['low', 'medium', 'high', 'critical'];

  /**
   * Valid status values
   */
  private const VALID_STATUSES = ['pending', 'approved', 'active', 'completed', 'failed', 'cancelled'];

  /**
   * Constructor
   *
   * Creates a new LocalObjective with validation of all required fields.
   *
   * @param string $agentId The ID of the agent creating this objective
   * @param string $goalStatement Clear description of the objective's goal
   * @param array $successCriteria Array of criteria that define success
   * @param string $priority Priority level (low, medium, high, critical)
   * @param int $estimatedCompletionTime Estimated time to complete in seconds
   * @throws InvalidArgumentException If any parameter is invalid
   */
  public function __construct(
    string $agentId,
    string $goalStatement,
    array $successCriteria,
    string $priority,
    int $estimatedCompletionTime
  ) {
    // Validate inputs
    if (empty($agentId)) {
      throw new InvalidArgumentException('Agent ID cannot be empty');
    }

    if (empty($goalStatement)) {
      throw new InvalidArgumentException('Goal statement cannot be empty');
    }

    if (empty($successCriteria)) {
      throw new InvalidArgumentException('Success criteria cannot be empty');
    }

    if (!in_array($priority, self::VALID_PRIORITIES, true)) {
      throw new InvalidArgumentException(
        'Priority must be one of: ' . implode(', ', self::VALID_PRIORITIES)
      );
    }

    if ($estimatedCompletionTime <= 0) {
      throw new InvalidArgumentException('Estimated completion time must be positive');
    }

    // Initialize properties
    $this->objectiveId = $this->generateObjectiveId();
    $this->agentId = $agentId;
    $this->goalStatement = $goalStatement;
    $this->successCriteria = $successCriteria;
    $this->priority = $priority;
    $this->estimatedCompletionTime = $estimatedCompletionTime;
    $this->status = 'pending';
    $this->conflictsWith = null;
    $this->createdAt = new DateTimeImmutable();
    $this->completedAt = null;
    $this->metrics = [];
    $this->failureReason = null;
  }

  /**
   * Generate a unique objective ID
   *
   * @return string UUID v4 format
   */
  private function generateObjectiveId(): string
  {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

  /**
   * Get the objective ID
   *
   * @return string
   */
  public function getId(): string
  {
    return $this->objectiveId;
  }

  /**
   * Get the agent ID
   *
   * @return string
   */
  public function getAgentId(): string
  {
    return $this->agentId;
  }

  /**
   * Get the goal statement
   *
   * @return string
   */
  public function getGoalStatement(): string
  {
    return $this->goalStatement;
  }

  /**
   * Get the success criteria
   *
   * @return array
   */
  public function getSuccessCriteria(): array
  {
    return $this->successCriteria;
  }

  /**
   * Get the priority level
   *
   * @return string
   */
  public function getPriority(): string
  {
    return $this->priority;
  }

  /**
   * Get the estimated completion time in seconds
   *
   * @return int
   */
  public function getEstimatedCompletionTime(): int
  {
    return $this->estimatedCompletionTime;
  }

  /**
   * Get the current status
   *
   * @return string
   */
  public function getStatus(): string
  {
    return $this->status;
  }

  /**
   * Get the creation timestamp
   *
   * @return DateTimeImmutable
   */
  public function getCreatedAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Get the completion timestamp
   *
   * @return DateTimeImmutableImmutable|null
   */
  public function getCompletedAt(): ?DateTimeImmutable
  {
    return $this->completedAt;
  }

  /**
   * Get the metrics
   *
   * @return array
   */
  public function getMetrics(): array
  {
    return $this->metrics;
  }

  /**
   * Get the failure reason
   *
   * @return string|null
   */
  public function getFailureReason(): ?string
  {
    return $this->failureReason;
  }

  /**
   * Set the objective status
   *
   * Validates that the new status is valid and updates the status.
   *
   * @param string $status New status value
   * @throws InvalidArgumentException If status is invalid
   */
  public function setStatus(string $status): void
  {
    if (!in_array($status, self::VALID_STATUSES, true)) {
      throw new InvalidArgumentException(
        'Status must be one of: ' . implode(', ', self::VALID_STATUSES)
      );
    }

    $this->status = $status;
  }

  /**
   * Mark the objective as completed
   *
   * Sets status to 'completed', records completion time, and stores metrics.
   *
   * @param array $metrics Performance metrics for the completed objective
   */
  public function markCompleted(array $metrics): void
  {
    $this->status = 'completed';
    $this->completedAt = new DateTimeImmutable();
    $this->metrics = $metrics;
  }

  /**
   * Mark the objective as failed
   *
   * Sets status to 'failed', records completion time, and stores failure reason.
   *
   * @param string $reason Explanation of why the objective failed
   */
  public function markFailed(string $reason): void
  {
    $this->status = 'failed';
    $this->completedAt = new DateTimeImmutable();
    $this->failureReason = $reason;
  }

  /**
   * Check if the objective has a conflict
   *
   * @return bool True if there is a conflict
   */
  public function hasConflict(): bool
  {
    return $this->conflictsWith !== null;
  }

  /**
   * Set a conflict with another objective
   *
   * Records that this objective conflicts with another objective.
   *
   * @param string $conflictingObjectiveId ID of the conflicting objective
   */
  public function setConflict(string $conflictingObjectiveId): void
  {
    $this->conflictsWith = $conflictingObjectiveId;
  }

  /**
   * Clear the conflict status
   *
   * Removes the conflict marker from this objective.
   */
  public function clearConflict(): void
  {
    $this->conflictsWith = null;
  }

  /**
   * Get the conflicting objective ID
   *
   * @return string|null
   */
  public function getConflictsWith(): ?string
  {
    return $this->conflictsWith;
  }

  /**
   * Convert the objective to an array
   *
   * Serializes all objective data into an associative array suitable for
   * storage or transmission.
   *
   * @return array
   */
  public function toArray(): array
  {
    return [
      'objective_id' => $this->objectiveId,
      'agent_id' => $this->agentId,
      'goal_statement' => $this->goalStatement,
      'success_criteria' => $this->successCriteria,
      'priority' => $this->priority,
      'estimated_completion_time' => $this->estimatedCompletionTime,
      'status' => $this->status,
      'conflicts_with' => $this->conflictsWith,
      'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
      'completed_at' => $this->completedAt ? $this->completedAt->format('Y-m-d H:i:s') : null,
      'metrics' => $this->metrics,
      'failure_reason' => $this->failureReason
    ];
  }

  /**
   * Check if the objective is overdue
   *
   * Determines if the objective has exceeded its estimated completion time
   * while still in active status.
   *
   * @return bool True if the objective is overdue
   */
  public function isOverdue(): bool
  {
    if ($this->status !== 'active') {
      return false;
    }

    $now = new DateTimeImmutable();
    $elapsed = $now->getTimestamp() - $this->createdAt->getTimestamp();

    return $elapsed > $this->estimatedCompletionTime;
  }

  /**
   * Get the elapsed time since creation in seconds
   *
   * @return int
   */
  public function getElapsedTime(): int
  {
    $now = new DateTimeImmutable();
    return $now->getTimestamp() - $this->createdAt->getTimestamp();
  }

  /**
   * Get the remaining time until estimated completion in seconds
   *
   * @return int Negative if overdue
   */
  public function getRemainingTime(): int
  {
    return $this->estimatedCompletionTime - $this->getElapsedTime();
  }

  /**
   * Check if the objective is in a terminal state
   *
   * Terminal states are: completed, failed, cancelled
   *
   * @return bool
   */
  public function isTerminal(): bool
  {
    return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
  }

  /**
   * Check if the objective is active
   *
   * @return bool
   */
  public function isActive(): bool
  {
    return $this->status === 'active';
  }

  /**
   * Check if the objective is pending approval
   *
   * @return bool
   */
  public function isPending(): bool
  {
    return $this->status === 'pending';
  }
}
