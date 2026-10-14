<?php

namespace ClicShopping\AI\CoreAI\Planning;

/**
 * TaskStep Class
 *
 * Represents atomic step of an execution plan
 */

class TaskStep
{
  private string $id;
  private string $type;
  private string $description;
  private array $metadata;
  private string $status = 'pending'; // pending, in_progress, completed, failed
  private $result = null;
  private ?string $error = null;
  private float $executionTime = 0.0;
  private float $startTime = 0.0;

  /**
   * Constructor
   *
   * @param string $id Unique step identifier
   * @param string $type Step type (analytics_query, semantic_search, synthesis, etc.)
   * @param string $description Step description
   * @param array $metadata Additional metadata
   */
  public function __construct(
    string $id,
    string $type,
    string $description,
    array $metadata = []
  ) {
    $this->id = $id;
    $this->type = $type;
    $this->description = $description;
    $this->metadata = $metadata;
  }

  /**
   * Starts step execution
   */
  public function start(): void
  {
    $this->status = 'in_progress';
    $this->startTime = microtime(true);
  }

  /**
   * Marks step as completed
   * 
   * @param mixed $result Step result
   */
  public function complete($result): void
  {
    $this->status = 'completed';
    $this->result = $result;

    if ($this->startTime > 0) {
      $this->executionTime = microtime(true) - $this->startTime;
    }
  }

  /**
   * Marks step as failed
   * 
   * @param string $error Error message
   */
  public function fail(string $error): void
  {
    $this->status = 'failed';
    $this->error = $error;

    if ($this->startTime > 0) {
      $this->executionTime = microtime(true) - $this->startTime;
    }
  }

  /**
   * Resets step to initial state
   */
  public function reset(): void
  {
    $this->status = 'pending';
    $this->result = null;
    $this->error = null;
    $this->executionTime = 0.0;
    $this->startTime = 0.0;
  }

  /**
   * Gets specific metadata value
   * 
   * @param string $key Metadata key
   * @param mixed $default Default value if key not found
   * @return mixed Metadata value or default
   */
  public function getMeta(string $key, $default = null)
  {
    return $this->metadata[$key] ?? $default;
  }

  /**
   * Sets metadata value
   * 
   * @param string $key Metadata key
   * @param mixed $value Metadata value
   */
  public function setMeta(string $key, $value): void
  {
    $this->metadata[$key] = $value;
  }

  /**
   * Checks if step is final
   * 
   * @return bool True if final step
   */
  public function isFinal(): bool
  {
    return $this->metadata['is_final'] ?? false;
  }

  /**
   * Checks if step can execute in parallel
   * 
   * @return bool True if can run in parallel
   */
  public function canRunParallel(): bool
  {
    return $this->metadata['can_run_parallel'] ?? false;
  }

  /**
   * Gets step dependencies
   * 
   * @return array Array of dependency step IDs
   */
  public function getDependencies(): array
  {
    return $this->metadata['depends_on'] ?? [];
  }

  // Getters
  // Need entityId ? to check

  public function getId(): string { return $this->id; }
  public function getType(): string { return $this->type; }
  public function getDescription(): string { return $this->description; }
  public function getMetadata(): array { return $this->metadata; }
  public function getStatus(): string { return $this->status; }
  public function getResult() { return $this->result; }
  public function getError(): ?string { return $this->error; }
  public function getExecutionTime(): float { return $this->executionTime; }

  // Setters
  public function setResult($result): void { $this->result = $result; }
  public function setStatus(string $status): void { $this->status = $status; }
}