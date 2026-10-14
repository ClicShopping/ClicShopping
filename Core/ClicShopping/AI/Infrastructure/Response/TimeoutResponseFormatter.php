<?php
/**
 * Timeout Response Formatter for RAG Query System
 * 
 * Formats user-friendly timeout error messages in French with actionable guidance.
 * Provides clear explanations for cold cache scenarios and retry suggestions.
 * 
 * Uses language definition files:
 * - ClicShoppingAdmin/Core/languages/english/rag_timeout_response.txt
 * - ClicShoppingAdmin/Core/languages/french/rag_timeout_response.txt
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Response;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;

/**
 * TimeoutResponseFormatter Class
 * 
 * Formats timeout error messages for different scenarios using language definitions.
 * 
 * Features:
 * - User-friendly French messages (no technical jargon)
 * - Actionable retry guidance
 * - Clear explanation of cold cache behavior
 * - Technical metadata for debugging
 * - Language definitions loaded from rag_timeout_response.txt
 * 
 * Usage:
 * ```php
 * $formatter = new TimeoutResponseFormatter();
 * 
 * // Format cold cache timeout
 * $response = $formatter->formatColdCacheTimeout($cacheState, $executionTime);
 * 
 * // Format general timeout
 * $response = $formatter->formatGeneralTimeout($executionTime);
 * ```
 */
class TimeoutResponseFormatter
{
  private mixed $language = null;

  /**
   * Constructor
   * 
   * Loads language definitions for timeout messages.
   */
  public function __construct()
  {
    // Load language definitions
    $this->language = Registry::get('Language');
    DomainConfig::loadAgnosticLanguageFile('rag_timeout_response');
  }

  /**
   * Format timeout error message for cold cache scenario
   * 
   * Provides a user-friendly explanation that the first request takes longer
   * and encourages the user to retry for faster results.
   * 
   * @param array $cacheState Cache state information from CacheStateDetector
   * @param float $executionTime Time elapsed before timeout (seconds)
   * @return array Formatted error response
   */
  public function formatColdCacheTimeout(array $cacheState, float $executionTime): array
  {
    return [
      'success' => false,
      'error' => $this->language->getDef('text_rag_timeout_cold_cache_error'),
      'error_type' => 'cold_cache_timeout',
      'retry_suggested' => true,
      'explanation' => $this->language->getDef('text_rag_timeout_cold_cache_explanation'),
      'guidance' => $this->language->getDef('text_rag_timeout_cold_cache_guidance'),
      'next_request_faster' => true,
      'cache_state' => [
        'state' => $cacheState['state'] ?? 'cold',
        'exists' => $cacheState['exists'] ?? false,
        'valid' => $cacheState['valid'] ?? false
      ],
      'execution_time' => round($executionTime, 2),
      'timeout_threshold' => 120, // Extended timeout for cold cache
      'technical_note' => 'Cold cache scenario - first execution requires full LLM processing'
    ];
  }

  /**
   * Format general timeout error message
   * 
   * Provides a generic timeout message for unexpected timeout scenarios.
   * 
   * @param float $executionTime Time elapsed before timeout (seconds)
   * @return array Formatted error response
   */
  public function formatGeneralTimeout(float $executionTime): array
  {
    return [
      'success' => false,
      'error' => $this->language->getDef('text_rag_timeout_general_error'),
      'error_type' => 'general_timeout',
      'retry_suggested' => true,
      'explanation' => $this->language->getDef('text_rag_timeout_general_explanation'),
      'guidance' => $this->language->getDef('text_rag_timeout_general_guidance'),
      'execution_time' => round($executionTime, 2),
      'timeout_threshold' => 30, // Standard timeout
      'technical_note' => 'General timeout - unexpected delay during processing'
    ];
  }

  /**
   * Get retry guidance message
   * 
   * Provides actionable guidance for users to retry their query.
   * 
   * @return string Retry guidance message in configured language
   */
  public function getRetryGuidance(): string
  {
    return $this->language->getDef('text_rag_timeout_cold_cache_guidance');
  }

  /**
   * Format progress message for long-running queries
   * 
   * Provides a progress update message to keep users informed during processing.
   * 
   * @param float $elapsedTime Time elapsed so far (seconds)
   * @param float|null $percentComplete Completion percentage (0-100)
   * @return array Progress message
   */
  public function formatProgressMessage(float $elapsedTime, ?float $percentComplete = null): array
  {
    $message = $this->language->getDef('text_rag_timeout_progress_processing');
    
    if ($percentComplete !== null) {
      $message = sprintf($this->language->getDef('text_rag_timeout_progress_with_percent'), number_format($percentComplete, 0));
    } elseif ($elapsedTime > 10) {
      $message = $this->language->getDef('text_rag_timeout_progress_taking_longer');
    }
    
    return [
      'type' => 'progress',
      'message' => $message,
      'elapsed_time' => round($elapsedTime, 2),
      'percent_complete' => $percentComplete,
      'status' => 'processing'
    ];
  }

  /**
   * Format completion message
   * 
   * Provides a success message when query completes successfully.
   * 
   * @param float $executionTime Total execution time (seconds)
   * @param bool $fromCache Whether result came from cache
   * @return array Completion message
   */
  public function formatCompletionMessage(float $executionTime, bool $fromCache = false): array
  {
    $message = $fromCache 
      ? sprintf($this->language->getDef('text_rag_timeout_completion_from_cache'), number_format($executionTime, 2))
      : sprintf($this->language->getDef('text_rag_timeout_completion_normal'), number_format($executionTime, 2));
    
    return [
      'type' => 'completion',
      'message' => $message,
      'execution_time' => round($executionTime, 2),
      'from_cache' => $fromCache,
      'status' => 'completed'
    ];
  }

  /**
   * Get current language code
   * 
   * @return string Current language code
   */
  public function getLanguage(): string
  {
    if ($this->language !== null) {
      return $this->language->get('code');
    }
    
    return 'en';
  }
}
