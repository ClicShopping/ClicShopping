<?php
/**
 * Progress Response Handler for Long-Running RAG Queries
 * 
 * Provides progressive feedback to users during long-running query execution.
 * Sends progress updates, processing messages, and completion notifications.
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Response;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * ProgressResponseHandler Class
 * 
 * Manages progressive response updates during long-running query execution.
 * 
 * Features:
 * - Progress update sending with percentage completion
 * - Initial processing message for user feedback
 * - Completion message with execution time
 * - Configurable update interval (default: 5 seconds)
 * - Estimated time remaining calculation
 * 
 * Update Strategy:
 * - Send initial processing message immediately
 * - Send progress updates every 5 seconds
 * - Send completion message when query finishes
 * - Include estimated time remaining when available
 * 
 * Usage:
 * ```php
 * $handler = new ProgressResponseHandler();
 * 
 * // Send initial processing message
 * $handler->sendProcessingMessage($query, $cacheState);
 * 
 * // During execution, send progress updates
 * if ($handler->shouldSendUpdate($elapsedTime)) {
 *   $handler->sendProgressUpdate("Processing query...", 50.0, 10);
 * }
 * 
 * // Send completion message
 * $handler->sendCompletionMessage($executionTime);
 * ```
 */
class ProgressResponseHandler
{
  private bool $debug = false;
  private ?SecurityLogger $logger = null;
  private int $updateInterval = 5; // seconds
  private float $lastUpdateTime = 0.0;

  /**
   * Constructor
   * 
   * @param bool $debug Enable debug logging
   * @param int $updateInterval Interval between progress updates in seconds (default: 5)
   */
  public function __construct(bool $debug = false, int $updateInterval = 5)
  {
    $this->debug = $debug;
    $this->updateInterval = $updateInterval;
    $this->lastUpdateTime = microtime(true);
    
    // Initialize logger
    try {
      $this->logger = new SecurityLogger('info');
    } catch (\Exception $e) {
      error_log("ProgressResponseHandler: Failed to initialize SecurityLogger: " . $e->getMessage());
    }
    
    if ($this->debug) {
      error_log("ProgressResponseHandler: Initialized with update interval={$updateInterval}s");
    }
  }

  /**
   * Send progress update to client
   * 
   * @param string $message Progress message
   * @param float $percentComplete Completion percentage (0-100)
   * @param int|null $estimatedSecondsRemaining Estimated time remaining in seconds
   * @return void
   */
  public function sendProgressUpdate(
    string $message,
    float $percentComplete,
    ?int $estimatedSecondsRemaining = null
  ): void {
    $percentComplete = max(0.0, min(100.0, $percentComplete));
    
    $update = [
      'type' => 'progress',
      'message' => $message,
      'percent_complete' => round($percentComplete, 1),
      'timestamp' => time()
    ];
    
    if ($estimatedSecondsRemaining !== null) {
      $update['estimated_seconds_remaining'] = $estimatedSecondsRemaining;
      $update['estimated_completion'] = date('H:i:s', time() + $estimatedSecondsRemaining);
    }
    
    $this->sendToClient($update);
    
    $this->lastUpdateTime = microtime(true);
    
    if ($this->logger !== null) {
      try {
        $this->logger->logSecurityEvent(
          "Progress update sent: {$message} ({$percentComplete}%)",
          'info',
          $update
        );
      } catch (\Exception $e) {
        error_log("ProgressResponseHandler: Failed to log progress update: " . $e->getMessage());
      }
    }
    
    if ($this->debug) {
      $eta = $estimatedSecondsRemaining !== null ? " (ETA: {$estimatedSecondsRemaining}s)" : "";
      error_log("ProgressResponseHandler: Progress update sent: {$percentComplete}%{$eta}");
    }
  }

  /**
   * Send initial processing message
   * 
   * @param string $query User query being processed
   * @param array $cacheState Cache state from CacheStateDetector
   * @return void
   */
  public function sendProcessingMessage(string $query, array $cacheState): void
  {
    $state = $cacheState['state'] ?? 'unknown';
    
    if ($state === 'cold' || $state === 'expired') {
      $message = "Processing your query. First execution, this may take a moment...";
      $expectedTime = "1-2 minutes";
    } else {
      $message = "Processing your query...";
      $expectedTime = "a few seconds";
    }
    
    $processingMessage = [
      'type' => 'processing',
      'message' => $message,
      'query' => substr($query, 0, 100),
      'cache_state' => $state,
      'expected_time' => $expectedTime,
      'timestamp' => time()
    ];
    
    $this->sendToClient($processingMessage);
    
    if ($this->logger !== null) {
      try {
        $this->logger->logSecurityEvent(
          "Query processing started (cache state: {$state})",
          'info',
          $processingMessage
        );
      } catch (\Exception $e) {
        error_log("ProgressResponseHandler: Failed to log processing message: " . $e->getMessage());
      }
    }
    
    if ($this->debug) {
      error_log("ProgressResponseHandler: Processing message sent (cache state: {$state})");
    }
  }

  /**
   * Send completion message
   * 
   * @param float $executionTime Total execution time in seconds
   * @return void
   */
  public function sendCompletionMessage(float $executionTime): void
  {
    $completionMessage = [
      'type' => 'completion',
      'message' => "Query processed successfully",
      'execution_time' => round($executionTime, 2),
      'execution_time_formatted' => $this->formatExecutionTime($executionTime),
      'timestamp' => time()
    ];
    
    $this->sendToClient($completionMessage);
    
    if ($this->logger !== null) {
      try {
        $this->logger->logSecurityEvent(
          "Query processing completed in {$executionTime}s",
          'info',
          $completionMessage
        );
      } catch (\Exception $e) {
        error_log("ProgressResponseHandler: Failed to log completion message: " . $e->getMessage());
      }
    }
    
    if ($this->debug) {
      error_log("ProgressResponseHandler: Completion message sent (execution time: {$executionTime}s)");
    }
  }

  /**
   * Check if progress update is needed based on elapsed time
   * 
   * @param float $elapsedSeconds Total elapsed time since query started
   * @return bool True if update should be sent
   */
  public function shouldSendUpdate(float $elapsedSeconds): bool
  {
    $currentTime = microtime(true);
    $timeSinceLastUpdate = $currentTime - $this->lastUpdateTime;
    
    $shouldSend = $timeSinceLastUpdate >= $this->updateInterval;
    
    if ($this->debug && $shouldSend) {
      error_log("ProgressResponseHandler: Update interval reached ({$timeSinceLastUpdate}s >= {$this->updateInterval}s)");
    }
    
    return $shouldSend;
  }

  /**
   * Send data to client
   * 
   * @param array $data Data to send to client
   * @return void
   */
  private function sendToClient(array $data): void
  {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (!headers_sent()) {
      header('Content-Type: application/json');
      header('X-Progress-Update: true');
    }
    
    echo $json . "\n";
    
    if (ob_get_level() > 0) {
      ob_flush();
    }
    flush();
    
    if ($this->debug) {
      error_log("ProgressResponseHandler: Data sent to client: " . substr($json, 0, 200));
    }
  }

  /**
   * Format execution time for display
   * 
   * Converts execution time in seconds to human-readable format.
   * 
   * @param float $seconds Execution time in seconds
   * @return string Formatted time (e.g., "1m 30s", "45s")
   */
  private function formatExecutionTime(float $seconds): string
  {
    if ($seconds < 60) {
      return round($seconds, 1) . 's';
    }
    
    $minutes = floor($seconds / 60);
    $remainingSeconds = round($seconds % 60);
    
    return "{$minutes}m {$remainingSeconds}s";
  }

  /**
   * Calculate estimated time remaining
   * 
   * @param float $elapsedSeconds Time elapsed so far
   * @param float $percentComplete Current completion percentage (0-100)
   * @return int|null Estimated seconds remaining, or null if cannot estimate
   */
  public function calculateEstimatedTimeRemaining(
    float $elapsedSeconds,
    float $percentComplete
  ): ?int {
    if ($percentComplete <= 0 || $percentComplete >= 100) {
      return null;
    }
    
    $estimatedTotalTime = $elapsedSeconds / ($percentComplete / 100);
    
    $remainingTime = $estimatedTotalTime - $elapsedSeconds;
    
    return max(1, (int)round($remainingTime));
  }

  /**
   * Get update interval
   * 
   * Returns the configured update interval in seconds.
   * 
   * @return int Update interval in seconds
   */
  public function getUpdateInterval(): int
  {
    return $this->updateInterval;
  }

  /**
   * Set update interval
   * 
   * @param int $seconds Update interval in seconds
   * @return void
   */
  public function setUpdateInterval(int $seconds): void
  {
    $this->updateInterval = max(1, $seconds);
    
    if ($this->debug) {
      error_log("ProgressResponseHandler: Update interval set to {$this->updateInterval}s");
    }
  }

  /**
   * Reset last update time
   * 
   * @return void
   */
  public function resetLastUpdateTime(): void
  {
    $this->lastUpdateTime = microtime(true);
    
    if ($this->debug) {
      error_log("ProgressResponseHandler: Last update time reset");
    }
  }

  /**
   * Get statistics
   * 
   * @return array Statistics
   */
  public function getStats(): array
  {
    $currentTime = microtime(true);
    $timeSinceLastUpdate = $currentTime - $this->lastUpdateTime;
    
    return [
      'update_interval' => $this->updateInterval,
      'last_update_time' => $this->lastUpdateTime,
      'time_since_last_update' => round($timeSinceLastUpdate, 2),
      'debug_enabled' => $this->debug,
      'logger_enabled' => $this->logger !== null
    ];
  }
}
