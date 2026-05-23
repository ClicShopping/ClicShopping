<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions;

/**
 * Class McpException
 *
 * This is the base exception class for all errors related to the MCP (Model Context Protocol).
 * It extends PHP's built-in `Exception` class and adds a `context` property to provide
 * more detailed, structured information about the error for debugging purposes.
 */
class McpException extends \Exception
{
  /**
   * @var array Additional context about the error. This can be used to store
   * relevant data like request payloads, connection details, or other variables
   * that help in debugging the cause of the exception.
   */
  protected array $context = [];

  /**
   * McpException constructor.
   *
   * @param string $message The error message.
   * @param int|string|null $code The error code.
   * @param \Throwable|null $previous The previous exception in the exception chain.
   */
  public function __construct(string $message = "", int|string|null $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }

  /**
   * Get additional context about the error.
   *
   * @return array The context array.
   */
  final public function getContext(): array
  {
    return $this->context;
  }

  /**
   * Get a formatted array of error details.
   *
   * This method provides a structured representation of the exception, including
   * the message, code, file, line number, and a string representation of the
   * stack trace, which is highly useful for logging and debugging.
   *
   * @return array An associative array containing error details.
   */
  final public function toArray(): array
  {
    return [
      'message' => $this->getMessage(),
      'code' => $this->getCode(),
      'file' => $this->getFile(),
      'line' => $this->getLine(),
      //            'context' => $this->getContext(),
      'trace' => $this->getTraceAsString()
    ];
  }
}