<?php
/**
 * ClicShopping AI™
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Helper;

/**
 * TypeSafetyGuard - Utility class for safe type handling
 *
 * Prevents TypeError crashes when dealing with mixed-type returns from LLM operations.
 * Provides safe string operations and type conversion with logging.
 *
 * @package ClicShopping\AI\Utils
 */
class TypeSafetyGuard
{
  /**
   * Safely extract a substring from a value of any type
   *
   * Handles string, array, null, numeric, and other types gracefully.
   * Converts non-string types to string before applying substr().
   *
   * @param mixed $value The value to extract substring from
   * @param int $start Starting position
   * @param int $length Maximum length to extract
   * @return string The extracted substring or converted value
   */
  public static function safeSubstr(mixed $value, int $start, int $length): string
  {
    // Handle null
    if ($value === null) {
      return '';
    }

    // Handle arrays - convert to JSON
    if (is_array($value)) {
      $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($jsonValue === false) {
        return '[Array conversion failed]';
      }
      return substr($jsonValue, $start, $length);
    }

    // Handle objects - try to convert to string
    if (is_object($value)) {
      if (method_exists($value, '__toString')) {
        $stringValue = (string)$value;
        return substr($stringValue, $start, $length);
      }
      return '[Object: ' . get_class($value) . ']';
    }

    // Handle booleans
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    // Handle numeric types
    if (is_numeric($value)) {
      $stringValue = (string)$value;
      return substr($stringValue, $start, $length);
    }

    // Handle strings (the expected case)
    if (is_string($value)) {
      return substr($value, $start, $length);
    }

    // Fallback for unknown types
    return '[Unknown type: ' . gettype($value) . ']';
  }

  /**
   * Ensure a value is converted to string
   *
   * Converts any type to a string representation with context logging.
   * Logs type mismatches when the value is not already a string.
   *
   * @param mixed $value The value to convert
   * @param string $context Context information for logging (e.g., "AnalyticsAgent::processBusinessQuery line 723")
   * @return string The string representation of the value
   */
  public static function ensureString(mixed $value, string $context): string
  {
    // If already a string, return as-is
    if (is_string($value)) {
      return $value;
    }

    // Log type mismatch
    $actualType = gettype($value);
    if (is_object($value)) {
      $actualType = get_class($value);
    }

    self::logTypeMismatch($context, 'string', $actualType);

    // Convert to string based on type
    if ($value === null) {
      return '';
    }

    if (is_array($value)) {
      return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[Array conversion failed]';
    }

    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    if (is_object($value)) {
      if (method_exists($value, '__toString')) {
        return (string)$value;
      }
      return '[Object: ' . get_class($value) . ']';
    }

    // Numeric or other types
    return (string)$value;
  }

  /**
   * Log type mismatch warnings
   *
   * Records when a value has an unexpected type, helping identify
   * potential bugs or API contract violations.
   *
   * @param string $context Where the type mismatch occurred
   * @param string $expected The expected type
   * @param string $actual The actual type encountered
   * @return void
   */
  public static function logTypeMismatch(string $context, string $expected, string $actual): void
  {
    error_log("[warning]️  TYPE MISMATCH in {$context}");
    error_log("   Expected: {$expected}");
    error_log("   Actual: {$actual}");
    error_log("   Stack trace: " . self::getCallerInfo());
  }

  /**
   * Get caller information for debugging
   *
   * @return string Formatted caller information
   */
  private static function getCallerInfo(): string
  {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
    $caller = $trace[3] ?? $trace[2] ?? [];

    if (empty($caller)) {
      return 'Unknown caller';
    }

    $file = $caller['file'] ?? 'unknown file';
    $line = $caller['line'] ?? 'unknown line';
    $function = $caller['function'] ?? 'unknown function';

    return "{$function} in {$file}:{$line}";
  }
}
