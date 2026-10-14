<?php
/**
 * FaqParser
 *
 * Parses JSON FAQ content into structured FAQ objects with validation.
 * Ensures FAQ data integrity and provides descriptive error messages.
 *
 * @package ClicShopping
 * @subpackage AI\Ecommerce\FAQ
 * @version 1.0
 * @date 2026-05-03
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ;

use ClicShopping\OM\HTML;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * FaqParser Class
 *
 * Parses and validates JSON FAQ content for product FAQ storage.
 * Provides comprehensive validation and sanitization to ensure data integrity.
 *
 * Usage:
 * ```php
 * $parser = new FaqParser();
 * $result = $parser->parse($jsonContent);
 * if ($result['success']) {
 *   $faqData = $result['data'];
 * } else {
 *   echo $result['error'];
 * }
 * ```
 */
class FaqParser
{
  /**
   * Maximum length for FAQ questions (in characters)
   */
  private const MAX_QUESTION_LENGTH = 500;

  /**
   * Maximum length for FAQ answers (in characters)
   */
  private const MAX_ANSWER_LENGTH = 2000;

  /**
   * Debug mode flag
   * @var bool
   */
  private bool $debug = false;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug mode for detailed logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
  }

  /**
   * Parse JSON FAQ content into structured array
   *
   * This method decodes JSON content, validates the structure,
   * sanitizes the data, and returns a standardized result array.
   *
   * @param string $jsonContent JSON string containing FAQ data in format: [{"q":"Question?","a":"Answer."}]
   * @return array{success: bool, data: array|null, error: string|null} Standardized result array
   *
   * @example
   * ```php
   * $parser = new FaqParser();
   * $result = $parser->parse('[{"q":"What is this?","a":"This is an answer."}]');
   * if ($result['success']) {
   *   foreach ($result['data'] as $item) {
   *     echo $item['q'] . ' - ' . $item['a'];
   *   }
   * }
   * ```
   */
  public function parse(string $jsonContent): array
  {
    if ($this->debug) {
      error_log('[FaqParser] Starting parse operation');
    }

    // Step 1: Validate input is not empty
    if (empty(trim($jsonContent))) {
      $error = 'FAQ content is empty';
      error_log('[FaqParser] Error: ' . $error);
      return [
        'success' => false,
        'data' => null,
        'error' => $error
      ];
    }

    // Step 2: Decode JSON
    $faqData = json_decode($jsonContent, true);

    // Check for JSON syntax errors
    if (json_last_error() !== JSON_ERROR_NONE) {
      $error = 'Invalid JSON syntax: ' . json_last_error_msg();
      error_log('[FaqParser] Error: ' . $error);
      if ($this->debug) {
        error_log('[FaqParser] JSON content: ' . substr($jsonContent, 0, 200));
      }
      return [
        'success' => false,
        'data' => null,
        'error' => $error
      ];
    }

    if ($this->debug) {
      error_log('[FaqParser] JSON decoded successfully, ' . count($faqData) . ' items found');
    }

    // Step 3: Validate structure
    $validationResult = $this->validate($faqData);

    if (!$validationResult['valid']) {
      $error = implode('; ', $validationResult['errors']);
      error_log('[FaqParser] Validation failed: ' . $error);
      return [
        'success' => false,
        'data' => null,
        'error' => $error
      ];
    }

    if ($this->debug) {
      error_log('[FaqParser] Validation passed');
    }

    // Step 4: Sanitize content
    $sanitizedData = $this->sanitize($faqData);

    if ($this->debug) {
      error_log('[FaqParser] Sanitization completed');
    }

    // Step 5: Return success with sanitized data
    if ($this->debug) {
      error_log('[FaqParser] Parse operation completed successfully');
    }

    return [
      'success' => true,
      'data' => $sanitizedData,
      'error' => null
    ];
  }

  /**
   * Validate FAQ structure
   *
   * Validates that the FAQ data is properly structured with required fields,
   * non-empty values, and within length limits.
   *
   * @param array $faqData Decoded FAQ array
   * @return array{valid: bool, errors: array} Validation result with error messages
   */
  private function validate(array $faqData): array
  {
    $errors = [];

    if ($this->debug) {
      error_log('[FaqParser] Starting validation');
    }

    // Rule 1: Must be an array
    if (!is_array($faqData)) {
      $errors[] = 'FAQ data must be an array';
      error_log('[FaqParser] Validation error: FAQ data must be an array');
      return ['valid' => false, 'errors' => $errors];
    }

    // Rule 2: Must not be empty
    if (empty($faqData)) {
      $errors[] = 'FAQ data is empty';
      error_log('[FaqParser] Validation error: FAQ data is empty');
      return ['valid' => false, 'errors' => $errors];
    }

    // Rule 3: Validate each FAQ item
    foreach ($faqData as $index => $item) {
      $itemNumber = $index + 1;

      // Check if item is an array
      if (!is_array($item)) {
        $error = "FAQ item #{$itemNumber} is not an array";
        $errors[] = $error;
        error_log('[FaqParser] Validation error: ' . $error);
        continue;
      }

      // Check for required 'q' key
      if (!isset($item['q'])) {
        $error = "FAQ item #{$itemNumber} missing required field 'q' (question)";
        $errors[] = $error;
        error_log('[FaqParser] Validation error: ' . $error);
      }

      // Check for required 'a' key
      if (!isset($item['a'])) {
        $error = "FAQ item #{$itemNumber} missing required field 'a' (answer)";
        $errors[] = $error;
        error_log('[FaqParser] Validation error: ' . $error);
      }

      // If keys exist, validate content
      if (isset($item['q'])) {
        // Check if question is a string
        if (!is_string($item['q'])) {
          $error = "FAQ item #{$itemNumber} question must be a string";
          $errors[] = $error;
          error_log('[FaqParser] Validation error: ' . $error);
        } else {
          // Check if question is empty after trimming
          if (empty(trim($item['q']))) {
            $error = "FAQ item #{$itemNumber} question is empty";
            $errors[] = $error;
            error_log('[FaqParser] Validation error: ' . $error);
          }

          // Check question length
          if (mb_strlen($item['q']) > self::MAX_QUESTION_LENGTH) {
            $error = "FAQ item #{$itemNumber} question exceeds maximum length of " . self::MAX_QUESTION_LENGTH . " characters";
            $errors[] = $error;
            error_log('[FaqParser] Validation error: ' . $error);
          }
        }
      }

      if (isset($item['a'])) {
        // Check if answer is a string
        if (!is_string($item['a'])) {
          $error = "FAQ item #{$itemNumber} answer must be a string";
          $errors[] = $error;
          error_log('[FaqParser] Validation error: ' . $error);
        } else {
          // Check if answer is empty after trimming
          if (empty(trim($item['a']))) {
            $error = "FAQ item #{$itemNumber} answer is empty";
            $errors[] = $error;
            error_log('[FaqParser] Validation error: ' . $error);
          }

          // Check answer length
          if (mb_strlen($item['a']) > self::MAX_ANSWER_LENGTH) {
            $error = "FAQ item #{$itemNumber} answer exceeds maximum length of " . self::MAX_ANSWER_LENGTH . " characters";
            $errors[] = $error;
            error_log('[FaqParser] Validation error: ' . $error);
          }
        }
      }
    }

    if ($this->debug && empty($errors)) {
      error_log('[FaqParser] Validation completed successfully, no errors found');
    }

    // Return validation result
    return [
      'valid' => empty($errors),
      'errors' => $errors
    ];
  }

  /**
   * Sanitize FAQ content
   *
   * Sanitizes FAQ data using ClicShopping security methods:
   * - Removes invisible characters and control characters
   * - Encodes HTML entities to prevent XSS attacks
   * - Normalizes whitespace and line breaks
   *
   * @param array $faqData Validated FAQ array
   * @return array Sanitized FAQ array
   */
  private function sanitize(array $faqData): array
  {
    if ($this->debug) {
      error_log('[FaqParser] Starting sanitization of ' . count($faqData) . ' FAQ items');
    }

    $sanitized = [];

    foreach ($faqData as $index => $item) {
      // Sanitize question using ClicShopping security methods
      $question = $this->sanitizeString($item['q'] ?? '');

      // Sanitize answer using ClicShopping security methods
      $answer = $this->sanitizeString($item['a'] ?? '');

      // Add sanitized item to result
      $sanitized[] = [
        'q' => $question,
        'a' => $answer
      ];

      if ($this->debug) {
        error_log('[FaqParser] Sanitized FAQ item #' . ($index + 1) . ' - Q: ' . mb_strlen($question) . ' chars, A: ' . mb_strlen($answer) . ' chars');
      }
    }

    if ($this->debug) {
      error_log('[FaqParser] Sanitization completed successfully for ' . count($sanitized) . ' items');
    }

    return $sanitized;
  }

  /**
   * Sanitize a single string
   *
   * Uses ClicShopping security methods for comprehensive sanitization:
   * - HTMLOverrideCommon::removeInvisibleCharacters() for control character removal
   * - HTML::outputProtected() for XSS prevention (htmlspecialchars with ENT_QUOTES | ENT_HTML5)
   * - Trim whitespace and normalize line breaks
   *
   * @param string $str String to sanitize
   * @return string Sanitized string
   */
  private function sanitizeString(string $str): string
  {
    // Step 1: Trim whitespace
    $str = trim($str);

    // Step 2: Remove invisible characters (null bytes, control characters, etc.)
    // Uses ClicShopping's security method from HTMLOverrideCommon
    $str = HTMLOverrideCommon::removeInvisibleCharacters($str);

    // Step 3: Normalize line breaks (convert all to \n)
    $str = str_replace(["\r\n", "\r"], "\n", $str);

    // Step 4: Protect against XSS by encoding HTML entities
    // Uses ClicShopping's HTML::outputProtected() which applies htmlspecialchars with ENT_QUOTES | ENT_HTML5
    $str = HTML::outputProtected($str);

    // Step 5: Normalize multiple spaces to single space (but preserve newlines)
    $lines = explode("\n", $str);
    $lines = array_map(function($line) {
      return preg_replace('/\s+/', ' ', trim($line));
    }, $lines);
    $str = implode("\n", $lines);

    // Step 6: Final trim
    $str = trim($str);

    return $str;
  }
}
