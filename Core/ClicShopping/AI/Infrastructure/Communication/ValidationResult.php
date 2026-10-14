<?php
/**
 * ClicShopping AI - Validation Result
 *
 * @copyright 2024 ClicShopping
 * @license MIT
 * @version 1.0.0
 */

namespace ClicShopping\AI\Infrastructure\Communication;

/**
 * Represents the result of message validation
 */
class ValidationResult
{
  private bool $isValid;
  private array $errors;

  /**
   * Constructor
   *
   * @param bool $isValid Whether validation passed
   * @param array $errors Validation errors
   */
  public function __construct(bool $isValid, array $errors = [])
  {
    $this->isValid = $isValid;
    $this->errors = $errors;
  }

  /**
   * Check if validation passed
   *
   * @return bool
   */
  public function isValid(): bool
  {
    return $this->isValid;
  }

  /**
   * Get validation errors
   *
   * @return array
   */
  public function getErrors(): array
  {
    return $this->errors;
  }

  /**
   * Get formatted error message
   *
   * @return string
   */
  public function getErrorMessage(): string
  {
    if (empty($this->errors)) {
      return '';
    }

    return implode('; ', $this->errors);
  }

  /**
   * Convert to array
   *
   * @return array
   */
  public function toArray(): array
  {
    return [
      'is_valid' => $this->isValid,
      'errors' => $this->errors
    ];
  }
}
