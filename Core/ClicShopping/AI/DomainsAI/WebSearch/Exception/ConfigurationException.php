<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Exception;

/**
 * ConfigurationException - Exception thrown when configuration validation fails
 *
 * This exception is thrown when:
 * - Required configuration constants are missing or empty
 * - API keys are invalid or improperly formatted
 * - Required database tables are missing or empty
 * - No properly configured search engines are available
 *
 * The exception message should provide detailed information about what configuration
 * is missing or invalid to help administrators resolve the issue quickly.
 *
 * Requirements: 19.5
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Exception
 */
class ConfigurationException extends \RuntimeException
{
  /**
   * Constructor
   *
   * @param string $message Detailed error message explaining the configuration issue
   * @param int $code Error code (default: 0)
   * @param \Throwable|null $previous Previous exception for exception chaining
   */
  public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
  {
    // Prefix message with context for easier debugging
    $contextMessage = "[Configuration Error] " . $message;
    
    parent::__construct($contextMessage, $code, $previous);
  }

  /**
   * Create exception for missing API key
   *
   * @param string $constantName Name of the missing configuration constant
   * @return self
   */
  public static function missingApiKey(string $constantName): self
  {
    return new self(
      "SerpAPI key not configured. Please set {$constantName} in your configuration. " .
      "You can obtain an API key from https://serpapi.com/"
    );
  }

  /**
   * Create exception for invalid API key format
   *
   * @param string $constantName Name of the configuration constant
   * @param string $reason Reason why the API key is invalid
   * @return self
   */
  public static function invalidApiKey(string $constantName, string $reason): self
  {
    return new self(
      "SerpAPI key in {$constantName} is invalid: {$reason}. " .
      "Please verify your API key at https://serpapi.com/manage-api-key"
    );
  }

  /**
   * Create exception for missing database table
   *
   * @param string $tableName Name of the missing table
   * @return self
   */
  public static function missingTable(string $tableName): self
  {
    return new self(
      "Required database table '{$tableName}' does not exist. " .
      "Please run the database migration script to create the required tables."
    );
  }

  /**
   * Create exception for empty database table
   *
   * @param string $tableName Name of the empty table
   * @param string $requirement What is required in the table
   * @return self
   */
  public static function emptyTable(string $tableName, string $requirement): self
  {
    return new self(
      "Database table '{$tableName}' exists but is empty. {$requirement}. " .
      "Please configure at least one entry in the admin interface."
    );
  }

  /**
   * Create exception for no available engines
   *
   * @param array $attemptedModes Array of mode identifiers that were attempted
   * @return self
   */
  public static function noAvailableEngines(array $attemptedModes): self
  {
    $modesStr = implode(', ', $attemptedModes);
    
    return new self(
      "No properly configured search engines available for modes: {$modesStr}. " .
      "Please check the following:\n" .
      "1. CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI is set and valid\n" .
      "2. For RAG WebSearch: clic_rag_websearch table contains active sites (status = 1)\n" .
      "3. Database connection is working properly"
    );
  }

  /**
   * Create exception for no selected modes
   *
   * @return self
   */
  public static function noModesSelected(): self
  {
    return new self(
      "No search modes selected. The routing decision must specify at least one mode. " .
      "This is likely a bug in IntentRouter or ModeSelector."
    );
  }

  /**
   * Create exception for general configuration validation failure
   *
   * @param string $component Component name that failed validation
   * @param string $details Detailed explanation of the failure
   * @return self
   */
  public static function validationFailed(string $component, string $details): self
  {
    return new self(
      "Configuration validation failed for {$component}: {$details}"
    );
  }
}
