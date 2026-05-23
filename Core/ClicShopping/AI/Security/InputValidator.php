<?php
/**
 * Input Validator Class
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\AI\Security;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * Class InputValidator
 * Provides comprehensive input validation for database operations
 * Ensures data integrity and prevents injection attacks
 */
class InputValidator
{
  private static ?SecurityLogger $logger = null;

  /**
   * Returns a singleton instance of the SecurityLogger
   * Initializes the logger with specified parameters if not already created
   *
   * @param string $logLevel Minimum log level to record (debug, info, warning, error)
   * @param int $maxLogSize Maximum log file size in bytes before rotation
   * @param int $logRotations Number of log rotations to maintain
   * @return SecurityLogger Instance of SecurityLogger
   */
  private static function getLogger(): SecurityLogger
  {
    if (self::$logger === null) {
      self::$logger = new SecurityLogger();
    }
    return self::$logger;
  }

  /**
   * Logs security-related events
   * Delegates logging to the SecurityLogger instance
   *
   * @param string $message Security event message
   * @param string $level Log level (info, warning, error)
   * @return void
   */

  private static function logSecurityEvent(string $message, string $level = 'warning'): void
  {
    self::getLogger()->logSecurityEvent($message, $level);
  }

    /**
     * Validates SQL query string
     * Checks for potentially dangerous SQL patterns and injection attempts
     *
     * @param string $query SQL query to validate
     * @return array Validation result with 'valid' boolean and 'issues' array
     */
  public static function validateSqlQuery(string $query): array
  {
    $issues = [];

    // Define dangerous SQL patterns
    $dangerousPatterns = self::getDangerousPatterns();

    // Check for dangerous patterns
    foreach ($dangerousPatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        $issues[] = "Potentially malicious SQL pattern detected: " . htmlspecialchars($pattern);
      }
    }

    // Check for balanced quotes
    if (!self::areQuotesBalanced($query, "'")) {
      $issues[] = "Unbalanced single quotes in query";
    }

    if (!self::areQuotesBalanced($query, "\"")) {
      $issues[] = "Unbalanced double quotes in query";
    }

    return [
      'valid' => empty($issues),
      'issues' => $issues
    ];
  }

  /**
   * Returns an array of dangerous SQL patterns.
   *
   * @return array
   */
  private static function getDangerousPatterns(): array
  {
    return [
      '/;\s*DROP\s+/i',           // Prevent DROP statements
      '/;\s*DELETE\s+/i',         // Prevent DELETE statements outside of proper context
      '/[\'\"]\s*UNION\s+(ALL\s+)?SELECT/i', // Prevent UNION-based injections (quote before UNION = injection)
      '/INTO\s+OUTFILE/i',        // Prevent file operations
      '/INFORMATION_SCHEMA/i',    // Prevent schema exploration
      '/SLEEP\s*\(/i',            // Prevent time-based attacks
      '/BENCHMARK\s*\(/i',        // Prevent benchmark-based attacks
      '/LOAD_FILE\s*\(/i',        // Prevent file loading
      '/--\s/',                   // SQL comment indicators
      '/\/\*.*\*\//i'             // Multi-line comment blocks
    ];
  }

  /**
   * Checks if quotes are balanced in a string.
   *
   * @param string $query
   * @param string $quoteType
   * @return bool
   */
  private static function areQuotesBalanced(string $query, string $quoteType): bool
  {
    $escapedQuoteCount = substr_count($query, "\\{$quoteType}");
    $totalQuoteCount = substr_count($query, $quoteType);

    return ($totalQuoteCount - $escapedQuoteCount) % 2 === 0;
  }

    /**
     * Sanitizes table and column names
     * Ensures identifiers conform to expected patterns
     * Prevents SQL injection via identifier manipulation
     *
     * @param string $identifier Table or column name to sanitize
     * @return string Sanitized identifier
     */
  public static function sanitizeIdentifier(string $identifier): string
  {
      // Only allow alphanumeric characters, underscores, and specific patterns
      if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
          // If invalid characters found, strip them and log the attempt
          $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
          self::logSecurityEvent("Invalid identifier sanitized: {$identifier} -> {$sanitized}");
          return $sanitized;
      }

      return $identifier;
  }

  /**
   * Validates and sanitizes input parameters
   * Applies type-specific validation rules
   * Provides safe default values for invalid inputs
   *
   * @param mixed $input Input value to validate
   * @param string $type Expected data type
   * @param mixed $default Default value if validation fails
   * @param array $options Additional validation options
   * @return mixed Validated and sanitized input
   */
  public static function validateParameter($input, string $type, $default = null, array $options = [])
  {
    switch ($type) {
      case 'int':
          if (!is_numeric($input)) {
              self::logSecurityEvent("Invalid integer parameter sanitized: " . var_export($input, true));
              return $default ?? 0;
          }
	  
          $value = (int)$input;

          // Apply range validation if min/max are provided
          if (isset($options['min']) && $value < $options['min']) {
              self::logSecurityEvent("Integer below minimum value: $value < {$options['min']}");
              return $options['min'];
          }
          if (isset($options['max']) && $value > $options['max']) {
              self::logSecurityEvent("Integer above maximum value: $value > {$options['max']}");
              return $options['max'];
          }

          return $value;

      case 'float':
          if (!is_numeric($input)) {
              self::logSecurityEvent("Invalid float parameter sanitized: " . var_export($input, true));
              return $default ?? 0.0;
          }
          $value = (float)$input;

          // Apply range validation if min/max are provided
          if (isset($options['min']) && $value < $options['min']) {
              self::logSecurityEvent("Float below minimum value: $value < {$options['min']}");
              return $options['min'];
          }
          if (isset($options['max']) && $value > $options['max']) {
              self::logSecurityEvent("Float above maximum value: $value > {$options['max']}");
              return $options['max'];
          }

          return $value;

      case 'string':
          if (!is_string($input)) {
              self::logSecurityEvent("Invalid string parameter sanitized: " . var_export($input, true));
              return $default ?? '';
          }

          $input = trim($input);

          // Apply length validation if min/max are provided
          if (isset($options['minLength']) && strlen($input) < $options['minLength']) {
              self::logSecurityEvent("String below minimum length: " . strlen($input) . " < {$options['minLength']}");
              return $default ?? '';
          }
          if (isset($options['maxLength']) && strlen($input) > $options['maxLength']) {
              self::logSecurityEvent("String above maximum length: " . strlen($input) . " > {$options['maxLength']}");
              $input = substr($input, 0, $options['maxLength']);
          }

          // Apply pattern validation if provided
          if (isset($options['pattern']) && !preg_match($options['pattern'], $input)) {
              self::logSecurityEvent("String does not match required pattern: " . $options['pattern']);
              return $default ?? '';
          }

          // Apply HTML escaping if specified
          if (isset($options['escape']) && $options['escape'] === true) {
              return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
          }

          return $input;

      case 'bool':
          return (bool)$input;

      case 'array':
          if (!is_array($input)) {
              self::logSecurityEvent("Invalid array parameter sanitized: " . var_export($input, true));
              return $default ?? [];
          }

          // Apply array size validation if min/max are provided
          if (isset($options['minSize']) && count($input) < $options['minSize']) {
              self::logSecurityEvent("Array below minimum size: " . count($input) . " < {$options['minSize']}");
              return $default ?? [];
          }
          if (isset($options['maxSize']) && count($input) > $options['maxSize']) {
              self::logSecurityEvent("Array above maximum size: " . count($input) . " > {$options['maxSize']}");
              // Truncate array to max size
              $input = array_slice($input, 0, $options['maxSize']);
          }

          // Validate each element if elementType is provided
          if (isset($options['elementType'])) {
              foreach ($input as $key => $value) {
                  $input[$key] = self::validateParameter($value, $options['elementType'], null, 
                      isset($options['elementOptions']) ? $options['elementOptions'] : []);
              }
          }

          return $input;

      case 'email':
          if (!is_string($input)) {
              self::logSecurityEvent("Invalid email parameter type: " . gettype($input));
              return $default ?? '';
          }

          $input = trim($input);
          if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
              self::logSecurityEvent("Invalid email format: $input");
              return $default ?? '';
          }

          return $input;

      case 'url':
          if (!is_string($input)) {
              self::logSecurityEvent("Invalid URL parameter type: " . gettype($input));
              return $default ?? '';
          }

          $input = trim($input);
          if (!filter_var($input, FILTER_VALIDATE_URL)) {
              self::logSecurityEvent("Invalid URL format: $input");
              return $default ?? '';
          }

          // Additional URL validation for allowed schemes
          if (isset($options['allowedSchemes'])) {
              $scheme = parse_url($input, PHP_URL_SCHEME);
              if (!in_array($scheme, $options['allowedSchemes'], true)) {
                  self::logSecurityEvent("URL scheme not allowed: $scheme");
                  return $default ?? '';
              }
          }

          return $input;

      case 'json':
          if (!is_string($input)) {
              self::logSecurityEvent("Invalid JSON parameter type: " . gettype($input));
              return $default ?? '';
          }

          $input = trim($input);
          json_decode($input);
          if (json_last_error() !== JSON_ERROR_NONE) {
              self::logSecurityEvent("Invalid JSON format: " . json_last_error_msg());
              return $default ?? '';
          }

          return $input;

      default:
          self::logSecurityEvent("Unknown parameter type: {$type}");
          return $default;
    }
 }
  /**
   * Validates a file path to prevent path traversal attacks
   * Ensures the path is within allowed directories and has valid extensions
   *
   * @param string $path The file path to validate
   * @param array $allowedDirs Array of allowed base directories (absolute paths)
   * @param array $allowedExtensions Array of allowed file extensions (without dot)
   * @param bool $mustExist Whether the file must exist to be valid
   * @return string|false Sanitized path if valid, false otherwise
   */
  public static function validateFilePath(string $path, array $allowedDirs, array $allowedExtensions = [], bool $mustExist = true): string|false
  {
    // Basic path sanitization
    $path = trim($path);

    // Normalize directory separators
    $path = str_replace('\\', '/', $path);

    // Remove any null bytes (poison null byte attack)
    $path = str_replace("\0", '', $path);

    // Resolve the real path (resolves .., ., and symbolic links)
    $realPath = realpath($path);

    // If realpath fails or file doesn't exist when required
    if ($realPath === false || ($mustExist && !file_exists($realPath))) {
      self::logSecurityEvent("Invalid file path (not found): $path");
      return false;
    }

    // Normalize the real path
    $realPath = str_replace('\\', '/', $realPath);

    // Check if the path is within allowed directories
    $isAllowed = false;
    foreach ($allowedDirs as $allowedDir) {
      $normalizedAllowedDir = str_replace('\\', '/', realpath($allowedDir));
      if ($normalizedAllowedDir && strpos($realPath, $normalizedAllowedDir) === 0) {
        $isAllowed = true;
        break;
      }
    }

    if (!$isAllowed) {
      self::logSecurityEvent("File path outside allowed directories: $path");
      return false;
    }

    // Check file extension if extensions are specified
    if (!empty($allowedExtensions)) {
      $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
      if (!in_array($extension, array_map('strtolower', $allowedExtensions), true)) {
        self::logSecurityEvent("File has disallowed extension: $extension");
        return false;
      }
    }

    return $realPath;
  }

  /**
   * Validates HTML content for potentially malicious code
   * Removes script tags, event handlers, and other dangerous elements
   *
   * @param string $html The HTML content to validate
   * @param array $options Configuration options for HTML purification
   * @return string Sanitized HTML
   */
  public static function validateHtml(string $html, array $options = []): string
  {
    // Basic HTML sanitization
    $html = trim($html);

    // Define tags to remove completely
    $dangerousTags = [
      'script',
      'iframe',
      'object',
      'embed',
      'applet',
      'form',
      'base',
      'link',
      'meta',
      'style'
    ];

    // Allow overriding the list of dangerous tags
    if (isset($options['dangerousTags']) && is_array($options['dangerousTags'])) {
      $dangerousTags = $options['dangerousTags'];
    }

    // Remove dangerous tags
    foreach ($dangerousTags as $tag) {
      $pattern = '/<\s*' . preg_quote($tag, '/') . '[^>]*>.*?<\s*\/\s*' . preg_quote($tag, '/') . '\s*>/is';
      $html = preg_replace($pattern, '', $html);

      // Also remove self-closing tags
      $pattern = '/<\s*' . preg_quote($tag, '/') . '[^>]*\/\s*>/is';
      $html = preg_replace($pattern, '', $html);
    }

    // Remove event handlers (on*)
    $html = preg_replace('/\s+on\w+\s*=\s*(["\']?).*?\1/i', '', $html);

    // Remove javascript: URLs
    $html = preg_replace('/href\s*=\s*(["\']?)javascript:.*?\1/i', 'href="#"', $html);

    // Remove data: URLs if specified
    if (!isset($options['allowDataUrls']) || $options['allowDataUrls'] !== true) {
      $html = preg_replace('/\s+src\s*=\s*(["\']?)data:.*?\1/i', ' src="#"', $html);
    }

    return $html;
  }

  /**
   * Validates and sanitizes an array of input data (e.g., $_GET, $_POST)
   * Recursively sanitizes all string values in the array.
   *
   * @param array $input The input array to validate/sanitize
   * @return array The sanitized array
   */
  public function validateArray(array $input): array
  {
    $sanitized = [];
    foreach ($input as $key => $value) {
      if (is_array($value)) {
        $sanitized[$key] = $this->validateArray($value);
      } else if (is_string($value)) {
        $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
      } else {
        $sanitized[$key] = $value;
      }
    }
    return $sanitized;
  }
}
