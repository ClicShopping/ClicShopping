<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;


use ClicShopping\AI\Security\SecurityLogger;

/**
 * MemoryStatistics Class
 *
 * Responsible for collecting and analyzing memory usage statistics.
 * Separated from ConversationMemory to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Record operations (interactions_stored, context_retrieved, references_resolved)
 * - Calculate success rates
 * - Calculate average response times
 * - Generate statistics reports
 */

class MemoryStatistics
{
  private SecurityLogger $logger;
  private bool $debug;
  private array $stats = [];

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
    $this->initializeStats();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "MemoryStatistics initialized",
        'info'
      );
    }
  }

  /**
   * Record an operation
   *
   * @param string $operation Operation name (interactions_stored, context_retrieved, etc.)
   * @param bool $success Whether the operation succeeded
   * @param float|null $responseTime Optional response time in seconds
   * @return void
   */
  public function recordOperation(string $operation, bool $success, ?float $responseTime = null): void
  {
    // Initialize operation stats if not exists
    if (!isset($this->stats[$operation])) {
      $this->stats[$operation] = [
        'total' => 0,
        'success' => 0,
        'failure' => 0,
        'success_rate' => 0.0,
        'total_response_time' => 0.0,
        'avg_response_time' => 0.0,
      ];
    }

    // Update counters
    $this->stats[$operation]['total']++;
    if ($success) {
      $this->stats[$operation]['success']++;
    } else {
      $this->stats[$operation]['failure']++;
    }

    // Update response time if provided
    if ($responseTime !== null) {
      $this->stats[$operation]['total_response_time'] += $responseTime;
      $this->stats[$operation]['avg_response_time'] = 
        $this->stats[$operation]['total_response_time'] / $this->stats[$operation]['total'];
    }

    // Calculate success rate
    $this->stats[$operation]['success_rate'] = 
      ($this->stats[$operation]['success'] / $this->stats[$operation]['total']) * 100;

    if ($this->debug) {
      $status = $success ? 'SUCCESS' : 'FAILURE';
      $this->logger->logSecurityEvent(
        "Operation recorded: {$operation} - {$status}",
        'info'
      );
    }
  }

  /**
   * Get all statistics
   *
   * @return array Statistics array
   */
  public function getStats(): array
  {
    return $this->stats;
  }

  /**
   * Get statistics for a specific operation
   *
   * @param string $operation Operation name
   * @return array|null Operation stats or null if not found
   */
  public function getOperationStats(string $operation): ?array
  {
    return $this->stats[$operation] ?? null;
  }

  /**
   * Get overall success rate across all operations
   *
   * @return float Success rate percentage
   */
  public function getOverallSuccessRate(): float
  {
    $totalOperations = 0;
    $totalSuccess = 0;

    foreach ($this->stats as $operation => $data) {
      $totalOperations += $data['total'];
      $totalSuccess += $data['success'];
    }

    if ($totalOperations === 0) {
      return 100.0;
    }

    return ($totalSuccess / $totalOperations) * 100;
  }

  /**
   * Get overall average response time across all operations
   *
   * @return float Average response time in seconds
   */
  public function getOverallAvgResponseTime(): float
  {
    $totalTime = 0.0;
    $totalOperations = 0;

    foreach ($this->stats as $operation => $data) {
      if ($data['total_response_time'] > 0) {
        $totalTime += $data['total_response_time'];
        $totalOperations += $data['total'];
      }
    }

    if ($totalOperations === 0) {
      return 0.0;
    }

    return $totalTime / $totalOperations;
  }

  /**
   * Get a summary report
   *
   * @return array Summary report
   */
  public function getSummaryReport(): array
  {
    return [
      'overall_success_rate' => round($this->getOverallSuccessRate(), 2),
      'overall_avg_response_time' => round($this->getOverallAvgResponseTime(), 3),
      'operations' => $this->stats,
      'timestamp' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Initialize statistics structure
   *
   * @return void
   */
  private function initializeStats(): void
  {
    $this->stats = [
      'interactions_stored' => [
        'total' => 0,
        'success' => 0,
        'failure' => 0,
        'success_rate' => 0.0,
        'total_response_time' => 0.0,
        'avg_response_time' => 0.0,
      ],
      'context_retrieved' => [
        'total' => 0,
        'success' => 0,
        'failure' => 0,
        'success_rate' => 0.0,
        'total_response_time' => 0.0,
        'avg_response_time' => 0.0,
      ],
      'references_resolved' => [
        'total' => 0,
        'success' => 0,
        'failure' => 0,
        'success_rate' => 0.0,
        'total_response_time' => 0.0,
        'avg_response_time' => 0.0,
      ],
    ];
  }
}
