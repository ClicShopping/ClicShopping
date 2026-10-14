<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions;

/**
 * Class McpConnectionException
 *
 * This exception is thrown when a connection to the MCP (Model Context Protocol)
 * service fails. It extends the base McpException and provides additional context
 * about the connection attempt, including details, a history of attempts, and
 * the last error message, which is useful for debugging.
 */
class McpConnectionException extends McpException
{
  /**
   * @var string Details about the connection attempt that failed.
   */
  protected string $connectionDetails;

  /**
   * @var int|null The timestamp of the last failed connection attempt.
   */
  protected ?int $lastAttempt;

  /**
   * @var int A counter for the number of connection attempts made.
   */
  protected int $attemptCount = 0;

  /**
   * @var array A history of recent connection attempts.
   */
  protected array $connectionHistory = [];

  /**
   * @var string|null The error message from the last failed attempt.
   */
  protected ?string $lastError = null;

  /**
   * McpConnectionException constructor.
   *
   * @param string $message The primary error message.
   * @param string $connectionDetails Details about the connection for debugging purposes.
   * @param int $code The error code.
   * @param \Throwable|null $previous The previous exception in the chain.
   */
  public function __construct(string $message = "", string $connectionDetails = "", int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
    $this->connectionDetails = $connectionDetails;
    $this->lastAttempt = time();
    $this->addConnectionAttempt($message);
  }

  /**
   * Adds a record of a connection attempt to the history.
   *
   * This private helper method updates the attempt count, stores the last error,
   * and adds a new entry to the `connectionHistory` array. It maintains a limited
   * history (the last 5 attempts) to prevent excessive memory usage.
   *
   * @param string $error The error message associated with the attempt.
   */
  final protected function addConnectionAttempt(string $error = ""): void
  {
    $this->attemptCount++;
    $this->lastError = $error ?: null;

    $this->connectionHistory[] = [
      'timestamp' => time(),
      'error' => $error,
      'attempt' => $this->attemptCount
    ];

    // Keep only last 5 attempts
    if (count($this->connectionHistory) > 5) {
      array_shift($this->connectionHistory);
    }
  }

  /**
   * Gets the connection details that led to the error.
   *
   * @return string The connection details.
   */
  final public function getConnectionDetails(): string
  {
    return $this->connectionDetails;
  }

  /**
   * Gets the timestamp of the last connection attempt.
   *
   * @return int The timestamp.
   */
  final public function getLastAttempt(): int
  {
    return $this->lastAttempt;
  }

  /**
   * Gets the number of connection attempts made.
   *
   * @return int The attempt count.
   */
  final public function getAttemptCount(): int
  {
    return $this->attemptCount;
  }

  /**
   * Gets the history of connection attempts.
   *
   * @return array The connection history.
   */
  final public function getConnectionHistory(): array
  {
    return $this->connectionHistory;
  }

  /**
   * Gets the error message from the last failed attempt.
   *
   * @return string|null The error message, or null if there was no error.
   */
  final public function getLastError(): ?string
  {
    return $this->lastError;
  }
}