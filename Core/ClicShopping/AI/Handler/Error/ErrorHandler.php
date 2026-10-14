<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Handler\Error;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ResponseProcessor;

/**
 * ErrorHandler Class
 *
 * Responsible for error handling, retry logic, and error detection.
 * Separated from OrchestratorAgent to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Execute operations with automatic retry
 * - Detect temporary vs permanent errors
 * - Build error responses (delegates to ResponseProcessor)
 * - Manage retry attempts and delays
 *
 */

class ErrorHandler
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private ?ResponseProcessor $responseProcessor;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param ResponseProcessor|null $responseProcessor Optional ResponseProcessor for error responses
   */
  public function __construct(bool $debug = false, ?ResponseProcessor $responseProcessor = null)
  {
    $this->debug = $debug;
    $this->securityLogger = new SecurityLogger();
    $this->responseProcessor = $responseProcessor;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("ErrorHandler initialized", 'info');
    }
  }

  /**
   * Execute operation with automatic retry
   *
   * Executes a callable operation with automatic retry on temporary errors.
   * Supports configurable max retries and retry delay.
   *
   * @param callable $operation Operation to execute (should return array with 'success' key)
   * @param array $options Retry options (max_retries, retry_delay)
   * @return array Operation result with retry info if applicable
   */
  public function handleWithRetry(callable $operation, array $options = []): array
  {
    $maxRetries = $options['max_retries'] ?? 2;
    $retryDelay = $options['retry_delay'] ?? 1; // seconds
    $attempt = 0;
    $lastError = null;

    while ($attempt <= $maxRetries) {
      try {
        $result = $operation();

        // If success, return result
        if ($result['success'] ?? false) {
          // Add retry info if needed
          if ($attempt > 0) {
            $result['retry_info'] = [
              'attempts' => $attempt + 1,
              'succeeded_on_retry' => true
            ];
          }
          return $result;
        }

        // If failure but not a temporary error, don't retry
        if (!$this->isTemporaryError($result['error'] ?? '')) {
          return $result;
        }

        $lastError = $result;

      } catch (\Exception $e) {
        $lastError = [
          'success' => false,
          'error' => $e->getMessage()
        ];

        // If not a temporary error, don't retry
        if (!$this->isTemporaryError($e->getMessage())) {
          throw $e;
        }
      }

      $attempt++;

      // If we still have retries, wait and log
      if ($attempt <= $maxRetries) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Retry attempt {$attempt}/{$maxRetries}",
            'info'
          );
        }

        sleep($retryDelay);
      }
    }

    // All retries failed
    if ($lastError) {
      $lastError['retry_info'] = [
        'attempts' => $maxRetries + 1,
        'all_failed' => true
      ];
      return $lastError;
    }

    // Fallback
    return ['success' => false, 'error' => 'Failed after multiple retries'];
  }

  /**
   * Check if error is temporary and can be retried
   *
   * Determines if an error is temporary (network, timeout, rate limit, etc.)
   * or permanent (validation, permission, etc.) based on pattern matching.
   *
   * @param string $errorMessage Error message
   * @return bool True if temporary error (can retry), false if permanent
   */
  public function isTemporaryError(string $errorMessage): bool
  {
    $temporaryPatterns = [
      '/timeout/i',
      '/timed out/i',
      '/time limit/i',
      '/connection.*failed/i',
      '/connection.*refused/i',
      '/connection.*reset/i',
      '/temporarily unavailable/i',
      '/service unavailable/i',
      '/too many requests/i',
      '/rate limit/i',
      '/deadlock/i',
      '/lock wait timeout/i',
    ];

    foreach ($temporaryPatterns as $pattern) {
      if (preg_match($pattern, $errorMessage)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Build error response with context
   *
   * Delegates to ResponseProcessor if available, otherwise creates basic error response.
   *
   * @param string $message Error message
   * @param array $context Error context
   * @return array Error response array
   */
  public function buildErrorResponse(string $message, array $context = []): array
  {
    // Delegate to ResponseProcessor if available
    if ($this->responseProcessor) {
      return $this->responseProcessor->buildErrorResponse($message, $context);
    }

    // Fallback: basic error response
    return [
      'success' => false,
      'type' => 'error',
      'error' => $message,
      'error_details' => $context,
      'can_retry' => $this->isTemporaryError($message),
      'timestamp' => date('Y-m-d H:i:s'),
    ];
  }
}
