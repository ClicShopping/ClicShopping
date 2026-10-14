<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;


use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\InterfacesAI\DiagnosticProvider;

/**
 * DiagnosticManager Class
 *
 * Responsible for system health monitoring, error tracking, and diagnostic reporting.
 * Separated from OrchestratorAgent to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Store and manage recent errors
 * - Generate health reports with metrics
 * - Explain errors in human-readable language
 * - Analyze error patterns and suggest improvements
 * - Calculate classification accuracy
 */

class DiagnosticManager implements DiagnosticProvider
{
  private SecurityLogger $logger;
  private bool $debug;

  // Error storage
  private array $recentErrors = [];
  private int $maxErrors = 50;

  // Execution statistics
  private array $executionStats = [];

  /**
   * Constructor
   *
   * @param array $executionStats Reference to execution stats
   * @param bool $debug Enable debug logging
   */
  public function __construct(array &$executionStats, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->executionStats = &$executionStats;
    $this->debug = $debug;

    if ($this->debug) {
      $this->logger->logSecurityEvent("DiagnosticManager initialized", 'info');
    }
  }

  /**
   * Store an error for diagnostic purposes
   *
   * @param string $errorMessage Error message
   * @param string $query Original query that caused the error
   * @param array $context Additional context (intent, stack_trace, etc.)
   */
  public function storeError(string $errorMessage, string $query, array $context = []): void
  {
    $error = [
      'timestamp' => time(),
      'datetime' => date('Y-m-d H:i:s'),
      'message' => $errorMessage,
      'query' => $query,
      'context' => $context,
    ];

    // Add to beginning of array (most recent first)
    array_unshift($this->recentErrors, $error);

    // Keep only last N errors (FIFO)
    if (count($this->recentErrors) > $this->maxErrors) {
      array_pop($this->recentErrors);
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Error stored: {$errorMessage}",
        'error'
      );
    }
  }

  /**
   * Get a comprehensive health report
   *
   * @return array Health report with metrics and status
   */
  public function getHealthReport(): array
  {
    $totalRequests = $this->executionStats['total_requests'] ?? 0;
    $totalErrors = count($this->recentErrors);
    $successRate = $totalRequests > 0 ? (($totalRequests - $totalErrors) / $totalRequests) * 100 : 100;

    // Calculate average response time
    $avgResponseTime = 0;
    if (isset($this->executionStats['total_execution_time']) && $totalRequests > 0) {
      $avgResponseTime = $this->executionStats['total_execution_time'] / $totalRequests;
    }

    // Get memory usage
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);
    $memoryLimit = ini_get('memory_limit');

    // Calculate classification accuracy
    $classificationAccuracy = $this->calculateClassificationAccuracy();

    $report = [
      'status' => $successRate >= 90 ? 'healthy' : ($successRate >= 70 ? 'degraded' : 'unhealthy'),
      'timestamp' => date('Y-m-d H:i:s'),
      'metrics' => [
        'success_rate' => round($successRate, 2),
        'total_requests' => $totalRequests,
        'total_errors' => $totalErrors,
        'avg_response_time' => round($avgResponseTime, 3),
        'classification_accuracy' => round($classificationAccuracy, 2),
        'memory_usage' => $this->formatBytes($memoryUsage),
        'memory_peak' => $this->formatBytes($memoryPeak),
        'memory_limit' => $memoryLimit,
      ],
      'components' => [
        'task_planner' => 'operational',
        'plan_executor' => 'operational',
        'conversation_memory' => 'operational',
        'monitoring' => 'operational',
      ],
    ];

    return $report;
  }

  /**
   * Explain the last error in human-readable language
   *
   * @return string Human-readable explanation
   */
  public function explainLastError(): string
  {
    if (empty($this->recentErrors)) {
      return "No recent errors to explain.";
    }

    $lastError = $this->recentErrors[0];

    // Use GPT to explain the error
    $prompt = "As an AI system expert, explain this error to a user in simple terms:\n\n";
    $prompt .= "Error: {$lastError['message']}\n";
    $prompt .= "Query: {$lastError['query']}\n";
    $prompt .= "Time: {$lastError['datetime']}\n\n";
    $prompt .= "Provide:\n";
    $prompt .= "1. What went wrong\n";
    $prompt .= "2. Why it happened\n";
    $prompt .= "3. How to fix it or avoid it\n\n";
    $prompt .= "Keep it concise and user-friendly.";

    try {
      $explanation = Gpt::getGptResponse($prompt, 200);
      return $explanation;
    } catch (\Exception $e) {
      // Fallback if GPT fails
      return "Error: {$lastError['message']}\n" .
        "Query: {$lastError['query']}\n" .
        "Time: {$lastError['datetime']}\n\n" .
        "This error occurred during query processing. Please check the logs for more details.";
    }
  }

  /**
   * Get recent errors with details
   *
   * @param int $limit Maximum number of errors to return
   * @return array Array of error objects
   */
  public function getRecentErrors(int $limit = 10): array
  {
    return array_slice($this->recentErrors, 0, $limit);
  }

  /**
   * Analyze error patterns and suggest improvements
   *
   * @return array Array of improvement suggestions
   */
  public function suggestImprovements(): array
  {
    if (empty($this->recentErrors)) {
      return [
        'status' => 'no_errors',
        'message' => 'No recent errors detected. System is performing well.',
        'suggestions' => [],
      ];
    }

    $suggestions = [];

    // Analyze error patterns
    $errorTypes = [];
    foreach ($this->recentErrors as $error) {
      $errorType = $this->categorizeError($error['message']);
      $errorTypes[$errorType] = ($errorTypes[$errorType] ?? 0) + 1;
    }

    // Generate suggestions based on patterns
    foreach ($errorTypes as $type => $count) {
      $percentage = ($count / count($this->recentErrors)) * 100;

      if ($percentage > 20) {
        $suggestions[] = [
          'priority' => 'high',
          'category' => $type,
          'impact' => "Affects {$percentage}% of errors",
          'suggestion' => $this->getSuggestionForErrorType($type),
        ];
      }
    }

    // Check for performance issues
    $healthReport = $this->getHealthReport();
    if ($healthReport['metrics']['avg_response_time'] > 3.0) {
      $suggestions[] = [
        'priority' => 'medium',
        'category' => 'performance',
        'impact' => 'Slow response times detected',
        'suggestion' => 'Consider optimizing database queries, enabling caching, or increasing server resources.',
      ];
    }

    // Check for classification issues
    if ($healthReport['metrics']['classification_accuracy'] < 80) {
      $suggestions[] = [
        'priority' => 'high',
        'category' => 'classification',
        'impact' => 'Low classification accuracy',
        'suggestion' => 'Review and improve classification patterns in Semantics class. Consider adjusting threshold or adding more patterns.',
      ];
    }

    return [
      'status' => 'analyzed',
      'total_errors' => count($this->recentErrors),
      'error_types' => $errorTypes,
      'suggestions' => $suggestions,
    ];
  }

  /**
   * Categorize error by type
   *
   * @param string $errorMessage Error message
   * @return string Error category
   */
  private function categorizeError(string $errorMessage): string
  {
    $message = strtolower($errorMessage);

    if (str_contains($message, 'database') || str_contains($message, 'sql')) {
      return 'database';
    }
    if (str_contains($message, 'timeout') || str_contains($message, 'time out')) {
      return 'timeout';
    }
    if (str_contains($message, 'memory')) {
      return 'memory';
    }
    if (str_contains($message, 'validation') || str_contains($message, 'invalid')) {
      return 'validation';
    }
    if (str_contains($message, 'classification') || str_contains($message, 'intent')) {
      return 'classification';
    }
    if (str_contains($message, 'api') || str_contains($message, 'gpt')) {
      return 'api';
    }

    return 'unknown';
  }

  /**
   * Get suggestion for specific error type
   *
   * @param string $errorType Error type
   * @return string Suggestion
   */
  private function getSuggestionForErrorType(string $errorType): string
  {
    $suggestions = [
      'database' => 'Check database connection, optimize queries, and ensure proper indexing.',
      'timeout' => 'Increase timeout limits, optimize slow operations, or implement caching.',
      'memory' => 'Increase PHP memory_limit, optimize data structures, or implement pagination.',
      'validation' => 'Review input validation rules and provide better user guidance.',
      'classification' => 'Improve classification patterns, adjust thresholds, or add more training data.',
      'api' => 'Check API credentials, rate limits, and network connectivity. Consider implementing retry logic.',
      'unknown' => 'Review error logs for more details and implement specific error handling.',
    ];

    return $suggestions[$errorType] ?? $suggestions['unknown'];
  }

  /**
   * Calculate classification accuracy from recent executions
   *
   * @return float Accuracy percentage
   */
  private function calculateClassificationAccuracy(): float
  {
    $totalRequests = $this->executionStats['total_requests'] ?? 0;
    if ($totalRequests === 0) {
      return 100.0;
    }

    $classificationErrors = 0;
    foreach ($this->recentErrors as $error) {
      if ($this->categorizeError($error['message']) === 'classification') {
        $classificationErrors++;
      }
    }

    return (($totalRequests - $classificationErrors) / $totalRequests) * 100;
  }

  /**
   * Format bytes to human-readable format
   *
   * @param int $bytes Bytes
   * @return string Formatted string
   */
  private function formatBytes(int $bytes): string
  {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
      $bytes /= 1024;
      $i++;
    }

    return round($bytes, 2) . ' ' . $units[$i];
  }
}
