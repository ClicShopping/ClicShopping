<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Processor;



/**
 * PromptValidator - Validates and sanitizes prompts before LLM calls
 *
 * Responsibilities:
 * - Validate prompts before LLM calls
 * - Check for empty prompts
 * - Check for excessive length (>4096 chars)
 * - Detect and remove dangerous patterns (<script, <iframe)
 * - Provide fallback behavior when validation fails
 *
 * Requirements: REQ-8.1, REQ-8.2, REQ-8.3, REQ-8.4, REQ-8.5
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubHybridQueryProcessor
 * @since 2025-12-14
 */

class PromptValidator extends BaseQueryProcessor
{
  /**
   * Maximum prompt length in characters
   */
  private const MAX_PROMPT_LENGTH = 4096;

  /**
   * Dangerous patterns to detect and remove
   */
  private const DANGEROUS_PATTERNS = [
    '/<script.*?<\/script>/is',
    '/<iframe.*?<\/iframe>/is',
    '/<object.*?<\/object>/is',
    '/<embed.*?>/is',
  ];

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    parent::__construct($debug, 'PromptValidator');
  }

  /**
   * Process: Validate and sanitize prompt
   *
   * @param mixed $input Prompt to validate (string or array with 'prompt' key)
   * @param array $context Context information (must include 'context' key)
   * @return string Validated prompt
   */
  public function process($input, array $context = []): string
  {
    // Extract prompt from input
    $prompt = $this->extractPrompt($input);
    $validationContext = $context['context'] ?? 'unknown';

    // Validate prompt
    return $this->validatePrompt($prompt, $validationContext);
  }

  /**
   * Validate input
   *
   * @param mixed $input Input to validate
   * @return bool True if valid
   */
  public function validate($input): bool
  {
    if (is_string($input)) {
      return true;
    }
    if (is_array($input) && isset($input['prompt'])) {
      return is_string($input['prompt']);
    }
    return false;
  }

  /**
   * Validate prompt before LLM call
   *
   * Performs comprehensive validation:
   * - Empty prompt check (REQ-8.2)
   * - Length check (REQ-8.3)
   * - Dangerous pattern detection (REQ-8.4)
   *
   * @param string $prompt Prompt to validate
   * @param string $context Context where validation is performed
   * @return string Validated and sanitized prompt
   */
  public function validatePrompt(string $prompt, string $context = 'unknown'): string
  {
    try {
      // Check if prompt is empty (REQ-8.2)
      if (empty(trim($prompt))) {
        $this->logWarning("Empty prompt detected in context: {$context}");
        return '';
      }

      // Check for reasonable length (REQ-8.3)
      if (strlen($prompt) > self::MAX_PROMPT_LENGTH) {
        $this->logWarning(
          "Prompt exceeds maximum length in context: {$context}. Truncating.",
          ['original_length' => strlen($prompt), 'max_length' => self::MAX_PROMPT_LENGTH]
        );
        $prompt = substr($prompt, 0, self::MAX_PROMPT_LENGTH);
      }

      // Check for dangerous patterns (REQ-8.4)
      $prompt = $this->removeDangerousPatterns($prompt, $context);

      return $prompt;

    } catch (\Exception $e) {
      $this->logError(
        "Error validating prompt in context {$context}",
        $e,
        ['prompt_length' => strlen($prompt)]
      );
      // Return original on error (safe fallback)
      return $prompt;
    }
  }

  /**
   * Handle prompt validation failure
   *
   * Provides fallback behavior when validation fails consistently (REQ-8.5).
   * Different contexts have different fallback strategies.
   *
   * @param string $originalPrompt Original prompt that failed validation
   * @param string $context Context where validation failed
   * @return string Fallback prompt or empty string to skip
   */
  public function handlePromptValidationFailure(string $originalPrompt, string $context): string
  {
    $this->logWarning(
      "Prompt validation failed for context: {$context}. Using fallback.",
      ['original_length' => strlen($originalPrompt)]
    );

    // For query splitting, skip LLM and use simple split
    if ($context === 'query_splitting') {
      $this->logInfo("Skipping LLM query splitting, will use simple split fallback");
      return ''; // Empty string signals to skip LLM call
    }

    // For synthesis, use a simple fallback prompt
    if ($context === 'synthesis') {
      $fallback = "Combine the following results: " . substr($originalPrompt, 0, 500);
      $this->logInfo("Using synthesis fallback prompt", ['fallback_length' => strlen($fallback)]);
      return $fallback;
    }

    // Default: return sanitized version of original
    $sanitized = strip_tags($originalPrompt);
    $this->logInfo("Using sanitized fallback", ['sanitized_length' => strlen($sanitized)]);
    return $sanitized;
  }

  /**
   * Remove dangerous patterns from prompt
   *
   * Detects and removes potentially dangerous HTML/script patterns (REQ-8.4).
   *
   * @param string $prompt Prompt to sanitize
   * @param string $context Context for logging
   * @return string Sanitized prompt
   */
  private function removeDangerousPatterns(string $prompt, string $context): string
  {
    $originalPrompt = $prompt;
    $patternsFound = [];

    foreach (self::DANGEROUS_PATTERNS as $pattern) {
      if (preg_match($pattern, $prompt)) {
        $patternsFound[] = $pattern;
        $prompt = preg_replace($pattern, '', $prompt);
      }
    }

    // Also check for basic dangerous tags
    if (preg_match('/<script|<iframe|<object|<embed/i', $prompt)) {
      $patternsFound[] = 'basic_dangerous_tags';
      $prompt = preg_replace('/<script|<iframe|<object|<embed/i', '', $prompt);
    }

    if (!empty($patternsFound)) {
      $this->logWarning(
        "Dangerous patterns detected and removed in context: {$context}",
        [
          'patterns_found' => count($patternsFound),
          'original_length' => strlen($originalPrompt),
          'sanitized_length' => strlen($prompt)
        ]
      );
    }

    return $prompt;
  }

  /**
   * Extract prompt from input
   *
   * Handles both string input and array input with 'prompt' key.
   *
   * @param mixed $input Input to extract prompt from
   * @return string Extracted prompt
   */
  private function extractPrompt($input): string
  {
    if (is_string($input)) {
      return $input;
    }
    if (is_array($input) && isset($input['prompt'])) {
      return (string)$input['prompt'];
    }
    return '';
  }
}
