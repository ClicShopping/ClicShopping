<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Async;



/**
 * AsyncOperationManager Class
 *
 * Executes independent operations in parallel to minimize total latency.
 * Provides timeout handling and graceful degradation when operations fail.
 *
 * Purpose:
 * - Execute multiple independent operations concurrently
 * - Handle timeouts for individual operations
 * - Provide graceful degradation when operations fail
 * - Aggregate results efficiently
 *
 * Requirements: REQ-4.1, REQ-4.2, REQ-4.3
 * Task: 4.1 - Implement AsyncOperationManager
 * Created: 2025-12-11
 *
 * Note: This is a simplified implementation for PHP without true async support.
 * For production with high concurrency, consider using ReactPHP, Amp, or Swoole.
 */

class AsyncOperationManager
{
  /**
   * Default timeout in milliseconds
   */
  private const DEFAULT_TIMEOUT_MS = 200;

  /**
   * Execute multiple operations in parallel
   *
   * Takes an array of callable operations and executes them, handling timeouts
   * and errors gracefully. If an operation fails or times out, it returns null
   * for that operation and continues with others.
   *
   * @param array $operations Associative array of operation name => callable
   * @param int $timeoutMs Maximum time in milliseconds for all operations (default: 200ms)
   * @return array Associative array of operation name => result (null if failed/timeout)
   *
   * Example:
   * ```php
   * $operations = [
   *   'memory' => fn() => $this->loadMemory(),
   *   'embeddings' => fn() => $this->searchEmbeddings(),
   *   'context' => fn() => $this->retrieveContext()
   * ];
   * $results = $manager->executeParallel($operations, 200);
   * ```
   */
  public function executeParallel(array $operations, int $timeoutMs = self::DEFAULT_TIMEOUT_MS): array
  {
    $results = [];
    $startTime = microtime(true);

    // Execute all operations sequentially (PHP limitation without async extensions)
    // In a true async environment, these would run concurrently
    foreach ($operations as $key => $operation) {
      try {
        // Check if we've exceeded the global timeout
        $elapsed = (microtime(true) - $startTime) * 1000;
        if ($elapsed >= $timeoutMs) {
          $this->logTimeout($key, $elapsed, $timeoutMs);
          $results[$key] = null;
          continue;
        }

        // Calculate remaining timeout for this operation
        $remainingTimeout = $timeoutMs - $elapsed;

        // Execute operation with timeout
        $results[$key] = $this->executeWithTimeout($operation, $remainingTimeout, $key);

      } catch (\Exception $e) {
        // Graceful degradation: log error and continue with other operations
        $this->logError($key, $e);
        $results[$key] = null;
      }
    }

    return $results;
  }

  /**
   * Execute a single operation with timeout
   *
   * Executes a callable operation and checks if it exceeds the timeout.
   * This is a simplified implementation - for production, use proper async libraries.
   *
   * @param callable $operation The operation to execute
   * @param float $timeoutMs Maximum time in milliseconds
   * @param string $operationName Name of the operation (for logging)
   * @return mixed Result of the operation
   * @throws \RuntimeException If operation times out
   */
  private function executeWithTimeout(callable $operation, float $timeoutMs, string $operationName)
  {
    $startTime = microtime(true);

    // Execute the operation
    $result = $operation();

    // Check if it exceeded the timeout
    $duration = (microtime(true) - $startTime) * 1000;
    if ($duration > $timeoutMs) {
      $this->logTimeout($operationName, $duration, $timeoutMs);
      throw new \RuntimeException("Operation timeout: {$operationName} took {$duration}ms (limit: {$timeoutMs}ms)");
    }

    return $result;
  }

  /**
   * Log timeout event
   *
   *
   * @param string $operationName Name of the operation that timed out
   * @param float $duration Actual duration in milliseconds
   * @param float $timeout Timeout limit in milliseconds
   */
  private function logTimeout(string $operationName, float $duration, float $timeout): void
  {
    // Log to error log
    error_log(sprintf(
      "AsyncOperationManager: Operation '%s' timed out (%.2fms > %.2fms) - Graceful degradation: continuing with partial results",
      $operationName,
      $duration,
      $timeout
    ));
    
    // Log structured event for monitoring
    $this->logDegradationEvent($operationName, 'timeout', [
      'duration_ms' => round($duration, 2),
      'timeout_ms' => round($timeout, 2),
      'exceeded_by_ms' => round($duration - $timeout, 2),
    ]);
  }

  /**
   * Log error event
   *
   *
   * @param string $operationName Name of the operation that failed
   * @param \Exception $exception The exception that was thrown
   */
  private function logError(string $operationName, \Exception $exception): void
  {
    // Log to error log
    error_log(sprintf(
      "AsyncOperationManager: Operation '%s' failed: %s - Graceful degradation: continuing with partial results",
      $operationName,
      $exception->getMessage()
    ));
    
    // Log structured event for monitoring
    $this->logDegradationEvent($operationName, 'error', [
      'exception_type' => get_class($exception),
      'exception_message' => $exception->getMessage(),
      'exception_code' => $exception->getCode(),
      'exception_file' => $exception->getFile(),
      'exception_line' => $exception->getLine(),
    ]);
  }
  
  /**
   * Log degradation event for monitoring and alerting
   *
   *
   * This method logs degradation events in a structured format that can be:
   * - Monitored for alerting (e.g., high degradation rate)
   * - Analyzed for performance optimization
   * - Used for debugging production issues
   *
   * @param string $operationName Name of the operation that degraded
   * @param string $reason Reason for degradation (timeout, error, database_error, etc.)
   * @param array $details Additional details about the degradation
   */
  private function logDegradationEvent(string $operationName, string $reason, array $details = []): void
  {
    // Create structured log entry
    $logEntry = [
      'timestamp' => date('Y-m-d H:i:s'),
      'component' => 'AsyncOperationManager',
      'event_type' => 'graceful_degradation',
      'operation' => $operationName,
      'reason' => $reason,
      'details' => $details,
      'impact' => 'partial_results_returned',
    ];
    
    // Log as JSON for easy parsing
    error_log('DEGRADATION_EVENT: ' . json_encode($logEntry));
    
    // Also log to security logger if available
    try {
      $logger = new \ClicShopping\AI\Security\SecurityLogger();
      $logger->logStructured(
        'warning',
        'AsyncOperationManager',
        'graceful_degradation',
        $logEntry
      );
    } catch (\Exception $e) {
      // Ignore if security logger is not available
    }
  }

  /**
   * Execute operations with individual timeouts
   *
   * Similar to executeParallel, but allows specifying individual timeouts
   * for each operation instead of a global timeout.
   *
   * @param array $operations Associative array of operation name => ['callable' => callable, 'timeout' => int]
   * @return array Associative array of operation name => result (null if failed/timeout)
   *
   * Example:
   * ```php
   * $operations = [
   *   'memory' => ['callable' => fn() => $this->loadMemory(), 'timeout' => 100],
   *   'embeddings' => ['callable' => fn() => $this->searchEmbeddings(), 'timeout' => 50],
   *   'context' => ['callable' => fn() => $this->retrieveContext(), 'timeout' => 50]
   * ];
   * $results = $manager->executeWithIndividualTimeouts($operations);
   * ```
   */
  public function executeWithIndividualTimeouts(array $operations): array
  {
    $results = [];

    foreach ($operations as $key => $config) {
      try {
        $callable = $config['callable'] ?? null;
        $timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT_MS;

        if (!is_callable($callable)) {
          throw new \InvalidArgumentException("Operation '{$key}' does not have a valid callable");
        }

        // Execute operation with its specific timeout
        $results[$key] = $this->executeWithTimeout($callable, $timeout, $key);

      } catch (\Exception $e) {
        // Graceful degradation
        $this->logError($key, $e);
        $results[$key] = null;
      }
    }

    return $results;
  }
}
