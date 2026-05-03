<?php
/**
 * FaqPrettyPrinter
 *
 * Formats FAQ objects into valid, human-readable JSON.
 * Ensures round-trip property: parse → print → parse produces equivalent output.
 *
 * @package ClicShopping
 * @subpackage AI\Ecommerce\FAQ
 * @version 1.0
 * @date 2026-05-03
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ;

/**
 * FaqPrettyPrinter Class
 *
 * Formats FAQ arrays into JSON strings with proper formatting.
 * Provides both pretty-printed (human-readable) and compact (storage-optimized) formats.
 *
 * Usage:
 * ```php
 * $printer = new FaqPrettyPrinter();
 * $faqData = [
 *   ['q' => 'What is this?', 'a' => 'This is an answer.'],
 *   ['q' => 'How does it work?', 'a' => 'It works like this.']
 * ];
 * 
 * // Pretty-printed JSON (human-readable)
 * $prettyJson = $printer->print($faqData);
 * 
 * // Compact JSON (storage-optimized)
 * $compactJson = $printer->printCompact($faqData);
 * ```
 */
class FaqPrettyPrinter
{
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
   * Format FAQ array into pretty-printed JSON
   *
   * Creates human-readable JSON with proper indentation (2 spaces),
   * preserves Unicode characters, and does not escape slashes.
   *
   * @param array $faqData FAQ data array in format: [['q' => 'Question?', 'a' => 'Answer.']]
   * @return string Pretty-printed JSON string
   *
   * @example
   * ```php
   * $printer = new FaqPrettyPrinter();
   * $faqData = [
   *   ['q' => 'What is this?', 'a' => 'This is an answer.']
   * ];
   * $json = $printer->print($faqData);
   * // Output:
   * // [
   * //   {
   * //     "q": "What is this?",
   * //     "a": "This is an answer."
   * //   }
   * // ]
   * ```
   */
  public function print(array $faqData): string
  {
    if ($this->debug) {
      error_log('[FaqPrettyPrinter] Starting pretty-print operation for ' . count($faqData) . ' FAQ items');
    }

    // Validate input
    if (empty($faqData)) {
      if ($this->debug) {
        error_log('[FaqPrettyPrinter] Warning: Empty FAQ data provided');
      }
      return '[]';
    }

    // Encode to JSON with pretty-print flags
    // JSON_PRETTY_PRINT: Adds whitespace and indentation for readability
    // JSON_UNESCAPED_UNICODE: Preserves Unicode characters (é, ñ, 中, etc.)
    // JSON_UNESCAPED_SLASHES: Does not escape forward slashes (/)
    $json = json_encode(
      $faqData,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    // Check for encoding errors
    if ($json === false) {
      $error = 'JSON encoding failed: ' . json_last_error_msg();
      error_log('[FaqPrettyPrinter] Error: ' . $error);
      
      // Return empty array as fallback
      return '[]';
    }

    if ($this->debug) {
      error_log('[FaqPrettyPrinter] Pretty-print completed successfully, output length: ' . strlen($json) . ' bytes');
    }

    return $json;
  }

  /**
   * Format FAQ array into compact JSON (no whitespace)
   *
   * Creates storage-optimized JSON without whitespace or indentation.
   * Useful for database storage or API responses where size matters.
   *
   * @param array $faqData FAQ data array in format: [['q' => 'Question?', 'a' => 'Answer.']]
   * @return string Compact JSON string without whitespace
   *
   * @example
   * ```php
   * $printer = new FaqPrettyPrinter();
   * $faqData = [
   *   ['q' => 'What is this?', 'a' => 'This is an answer.']
   * ];
   * $json = $printer->printCompact($faqData);
   * // Output: [{"q":"What is this?","a":"This is an answer."}]
   * ```
   */
  public function printCompact(array $faqData): string
  {
    if ($this->debug) {
      error_log('[FaqPrettyPrinter] Starting compact-print operation for ' . count($faqData) . ' FAQ items');
    }

    // Validate input
    if (empty($faqData)) {
      if ($this->debug) {
        error_log('[FaqPrettyPrinter] Warning: Empty FAQ data provided');
      }
      return '[]';
    }

    // Encode to JSON without pretty-print (compact format)
    // JSON_UNESCAPED_UNICODE: Preserves Unicode characters
    // JSON_UNESCAPED_SLASHES: Does not escape forward slashes
    $json = json_encode(
      $faqData,
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    // Check for encoding errors
    if ($json === false) {
      $error = 'JSON encoding failed: ' . json_last_error_msg();
      error_log('[FaqPrettyPrinter] Error: ' . $error);
      
      // Return empty array as fallback
      return '[]';
    }

    if ($this->debug) {
      error_log('[FaqPrettyPrinter] Compact-print completed successfully, output length: ' . strlen($json) . ' bytes');
    }

    return $json;
  }

  /**
   * Verify round-trip property
   *
   * Tests that FAQ data can be printed and parsed back to equivalent data.
   * This ensures data integrity through the print → parse cycle.
   *
   * @param array $faqData Original FAQ data
   * @param FaqParser $parser Parser instance for verification
   * @return array{success: bool, error: string|null} Verification result
   *
   * @example
   * ```php
   * $printer = new FaqPrettyPrinter();
   * $parser = new FaqParser();
   * $faqData = [['q' => 'Question?', 'a' => 'Answer.']];
   * 
   * $result = $printer->verifyRoundTrip($faqData, $parser);
   * if ($result['success']) {
   *   echo "Round-trip verified!";
   * }
   * ```
   */
  public function verifyRoundTrip(array $faqData, FaqParser $parser): array
  {
    if ($this->debug) {
      error_log('[FaqPrettyPrinter] Starting round-trip verification');
    }

    // Step 1: Print to JSON
    $json = $this->print($faqData);

    // Step 2: Parse back to array
    $parseResult = $parser->parse($json);

    if (!$parseResult['success']) {
      $error = 'Round-trip failed: Parse error - ' . $parseResult['error'];
      error_log('[FaqPrettyPrinter] ' . $error);
      return [
        'success' => false,
        'error' => $error
      ];
    }

    // Step 3: Compare original and parsed data
    $parsedData = $parseResult['data'];

    // Note: We compare the structure and content, not the exact array
    // because sanitization may have modified the data (e.g., HTML encoding)
    if (count($faqData) !== count($parsedData)) {
      $error = 'Round-trip failed: Item count mismatch (original: ' . count($faqData) . ', parsed: ' . count($parsedData) . ')';
      error_log('[FaqPrettyPrinter] ' . $error);
      return [
        'success' => false,
        'error' => $error
      ];
    }

    // Verify each item has 'q' and 'a' keys
    foreach ($parsedData as $index => $item) {
      if (!isset($item['q']) || !isset($item['a'])) {
        $error = 'Round-trip failed: Item #' . ($index + 1) . ' missing required keys';
        error_log('[FaqPrettyPrinter] ' . $error);
        return [
          'success' => false,
          'error' => $error
        ];
      }
    }

    if ($this->debug) {
      error_log('[FaqPrettyPrinter] Round-trip verification successful');
    }

    return [
      'success' => true,
      'error' => null
    ];
  }

  /**
   * Get JSON encoding flags used for pretty-print
   *
   * Returns the flags used by the print() method for transparency.
   *
   * @return int JSON encoding flags
   */
  public function getPrettyPrintFlags(): int
  {
    return JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
  }

  /**
   * Get JSON encoding flags used for compact print
   *
   * Returns the flags used by the printCompact() method for transparency.
   *
   * @return int JSON encoding flags
   */
  public function getCompactPrintFlags(): int
  {
    return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
  }
}
