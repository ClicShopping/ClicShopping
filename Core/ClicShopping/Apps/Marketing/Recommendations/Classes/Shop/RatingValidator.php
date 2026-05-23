<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Classes\Shop;

class RatingValidator
{
  /**
   * Validates and sanitizes a rating value from user input.
   *
   * @param mixed $rating The rating value to validate
   * @return int|null Returns a valid rating (1-5) or null if invalid
   */
  public static function validateRating($rating): ?int
  {
    // Check if rating exists and is not empty
    if (!isset($rating) || $rating === '' || $rating === null) {
      self::logValidationError('Rating is missing or empty', ['rating' => $rating]);
      return null;
    }

    // Convert to string for validation
    $ratingString = (string)$rating;
    
    // Remove any whitespace
    $ratingString = trim($ratingString);
    
    // Check if it's a valid numeric value
    if (!is_numeric($ratingString)) {
      self::logValidationError('Rating is not numeric', ['rating' => $rating, 'sanitized' => $ratingString]);
      return null;
    }
    
    // Convert to float first to handle decimal values properly
    $ratingFloat = (float)$ratingString;
    
    // Check if it's within valid range (1-5)
    if ($ratingFloat < 1 || $ratingFloat > 5) {
      self::logValidationError('Rating is out of valid range (1-5)', [
        'rating' => $rating, 
        'sanitized' => $ratingString,
        'float_value' => $ratingFloat
      ]);
      return null;
    }
    
    // Convert to integer (round to nearest integer)
    $ratingInt = (int)round($ratingFloat);
    
    // Final validation to ensure it's still in range after rounding
    if ($ratingInt < 1 || $ratingInt > 5) {
      self::logValidationError('Rating is out of range after rounding', [
        'rating' => $rating,
        'float_value' => $ratingFloat,
        'rounded_value' => $ratingInt
      ]);
      return null;
    }
    
    // Log successful validation for monitoring
    self::logValidationSuccess($ratingInt, $rating);
    
    return $ratingInt;
  }

  /**
   * Validates rating from POST data with additional security checks.
   *
   * @param array $postData The $_POST array
   * @return int|null Returns a valid rating (1-5) or null if invalid
   */
  public static function validatePostRating(array $postData): ?int
  {
    // Check if rating key exists in POST data
    if (!array_key_exists('rating', $postData)) {
      self::logValidationError('Rating key not found in POST data', ['post_keys' => array_keys($postData)]);
      return null;
    }
    
    $rating = $postData['rating'];
    
    // Additional security check: ensure it's not an array (prevent array injection)
    if (is_array($rating)) {
      self::logValidationError('Rating cannot be an array', ['rating' => $rating]);
      return null;
    }
    
    // Check for suspicious patterns (basic XSS/injection detection)
    if (is_string($rating) && preg_match('/[<>"\']/', $rating)) {
      self::logValidationError('Rating contains potentially malicious characters', ['rating' => $rating]);
      return null;
    }
    
    return self::validateRating($rating);
  }

  /**
   * Gets a safe default rating when validation fails.
   *
   * @return int The default rating value (3 = neutral)
   */
  public static function getDefaultRating(): int
  {
    return 3; // Neutral rating
  }

  /**
   * Checks if a rating is valid without returning the value.
   *
   * @param mixed $rating The rating to check
   * @return bool True if valid, false otherwise
   */
  public static function isValidRating($rating): bool
  {
    return self::validateRating($rating) !== null;
  }

  /**
   * Logs validation errors for monitoring and debugging.
   *
   * @param string $message The error message
   * @param array $context Additional context data
   * @return void
   */
  private static function logValidationError(string $message, array $context = []): void
  {
    $logMessage = sprintf(
      '[Recommendations Rating Validation Error] %s | Context: %s | IP: %s | User Agent: %s',
      $message,
      json_encode($context),
      $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    );
    
    error_log($logMessage);
  }

  /**
   * Logs successful validation for monitoring.
   *
   * @param int $validRating The validated rating
   * @param mixed $originalRating The original input
   * @return void
   */
  private static function logValidationSuccess(int $validRating, $originalRating): void
  {
    // Only log in debug mode to avoid log spam
    if (defined('CLICSHOPPING_DEBUG') && CLICSHOPPING_DEBUG) {
      $logMessage = sprintf(
        '[Recommendations Rating Validation Success] Validated rating: %d | Original: %s',
        $validRating,
        json_encode($originalRating)
      );
      
      error_log($logMessage);
    }
  }

  /**
   * Sanitizes rating input for display purposes.
   *
   * @param mixed $rating The rating to sanitize
   * @return string The sanitized rating string
   */
  public static function sanitizeForDisplay($rating): string
  {
    $validatedRating = self::validateRating($rating);
    
    if ($validatedRating === null) {
      return 'N/A';
    }
    
    return (string)$validatedRating;
  }
}
