<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Processor;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\InterfacesAI\HybridQueryProcessorInterface;

/**
 * BaseQueryProcessor - Abstract base class for HybridQueryProcessor components
 *
 * Provides common functionality: logging, error handling, normalization, validation.
 * Foundation for QueryClassifier, QuerySplitter, ResultSynthesizer, ResultAggregator, PromptValidator.
 *
 * Requirements: REQ-1.3 (Single Responsibility), REQ-8.1 (Validation and security)
 *
 * @package ClicShopping\AI\DomainsAI\Hybrid\Processor
 * @since 2025-12-14
 */

abstract class BaseQueryProcessor implements HybridQueryProcessorInterface
{
  /**
   * @var SecurityLogger Logger instance for security events
   */
  protected SecurityLogger $logger;

  /**
   * @var bool Debug mode flag
   */
  protected bool $debug;

  /**
   * @var string Component name for logging and metadata
   */
  protected string $componentName;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param string|null $componentName Component name (auto-detected if null)
   */
  public function __construct(bool $debug = false, ?string $componentName = null)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->componentName = $componentName ?? basename(str_replace('\\', '/', get_class($this)));

    if ($this->debug) {
      $this->logInfo("Component initialized: {$this->componentName}");
    }
  }

  /**
   * Normalize query: lowercase, remove special chars, normalize whitespace
   */
  protected function normalizeQuery(string $query): string
  {
    $normalized = strtolower($query);
    $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);
    return trim(preg_replace('/\s+/', ' ', $normalized));
  }

  /**
   * Validate input is not empty
   */
  protected function validateNotEmpty($input): bool
  {
    if (is_string($input)) return !empty(trim($input));
    if (is_array($input)) return !empty($input);
    return $input !== null;
  }

  /**
   * Log info message
   */
  protected function logInfo(string $message, array $context = []): void
  {
    $this->log($message, 'info', $context);
  }

  /**
   * Log warning message
   */
  protected function logWarning(string $message, array $context = []): void
  {
    $this->log($message, 'warning', $context);
  }

  /**
   * Log error message
   */
  protected function logError(string $message, ?\Exception $exception = null, array $context = []): void
  {
    if ($exception !== null) {
      $context['exception'] = $exception->getMessage();
      $context['trace'] = $exception->getTraceAsString();
    }
    $this->log($message, 'error', $context);
  }

  /**
   * Internal log method
   */
  private function log(string $message, string $level, array $context = []): void
  {
    $fullMessage = "[{$this->componentName}] {$message}";
    if (!empty($context)) {
      $fullMessage .= " | Context: " . json_encode($context);
    }
    $this->logger->logSecurityEvent($fullMessage, $level);
  }

  /**
   * Handle error with fallback - logs error and returns fallback value
   */
  protected function handleError(string $message, ?\Exception $exception = null, $fallback = null)
  {
    $this->logError($message, $exception);
    return $fallback;
  }

  /**
   * Get metadata about the processor
   *
   * @return array Metadata information
   */
  public function getMetadata(): array
  {
    return [
      'component' => $this->componentName,
      'version' => '1.0.0',
      'debug_enabled' => $this->debug,
      'timestamp' => date('c'),
    ];
  }
}
