<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions;

/**
 * Class McpConfigurationException
 *
 * This exception is thrown when the configuration for the MCP (Model Context Protocol)
 * service is invalid, missing, or malformed. It extends the base McpException and
 * includes additional data to provide more context about the configuration error.
 */
class McpConfigurationException extends McpException
{
  /**
   * @var array The configuration data that was being processed when the error occurred.
   */
  protected array $configData;

  /**
   * @var array A list of specific validation errors, often detailing which fields are incorrect.
   */
  protected array $validationErrors;

  /**
   * McpConfigurationException constructor.
   *
   * @param string $message The error message.
   * @param array $configData The configuration that caused the error.
   * @param array $validationErrors A list of specific validation errors.
   * @param int $code The error code.
   * @param \Throwable|null $previous The previous exception in the exception chain.
   */
  public function __construct(
    string $message = "",
    array $configData = [],
    array $validationErrors = [],
    int $code = 0,
    ?\Throwable $previous = null
  ) {
    parent::__construct($message, $code, $previous);
    $this->configData = $configData;
    $this->validationErrors = $validationErrors;
  }

  /**
   * Gets the configuration data that caused the error.
   *
   * @return array The configuration data.
   */
  final public function getConfigData(): array
  {
    return $this->configData;
  }

  /**
   * Gets the list of validation errors.
   *
   * @return array The list of validation errors.
   */
  final public function getValidationErrors(): array
  {
    return $this->validationErrors;
  }

  /**
   * Checks if a specific configuration key was responsible for an error.
   *
   * @param string $key The configuration key to check.
   * @return bool True if the key is in the validation errors, false otherwise.
   */
  final public function hasErrorForKey(string $key): bool
  {
    return isset($this->validationErrors[$key]);
  }
}