<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * DiagnosticProvider Interface
 *
 * Interface for components that provide diagnostic and health monitoring capabilities.
 * Allows components to report their health status, explain errors, and suggest improvements.
 */
interface DiagnosticProvider
{
  /**
   * Get a comprehensive health report for the component
   *
   * Returns metrics like:
   * - success_rate: percentage of successful operations
   * - avg_response_time: average response time in seconds
   * - classification_accuracy: accuracy of classifications (if applicable)
   * - memory_usage: current memory usage
   * - error_count: number of errors in recent period
   * - cache_hit_rate: cache efficiency (if applicable)
   *
   * @return array Health report with metrics and status
   */
  public function getHealthReport(): array;

  /**
   * Explain the last error that occurred in human-readable language
   *
   * Uses AI to provide a clear explanation of what went wrong,
   * why it happened, and potential solutions.
   *
   * @return string Human-readable explanation of the last error
   */
  public function explainLastError(): string;

  /**
   * Get a list of recent errors with details
   *
   * @param int $limit Maximum number of errors to return (default: 10)
   * @return array Array of error objects with timestamp, message, context, stack_trace
   */
  public function getRecentErrors(int $limit = 10): array;

  /**
   * Analyze error patterns and suggest improvements
   *
   * Analyzes recent errors to identify patterns and suggests:
   * - Configuration adjustments
   * - Code improvements
   * - Performance optimizations
   * - User guidance improvements
   *
   * @return array Array of improvement suggestions with priority and impact
   */
  public function suggestImprovements(): array;
}
