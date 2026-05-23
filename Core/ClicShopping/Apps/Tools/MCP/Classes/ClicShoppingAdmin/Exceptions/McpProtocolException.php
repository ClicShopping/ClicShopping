<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions;

/**
 * Class McpProtocolException
 *
 * This exception is thrown when there is a protocol-level error during communication
 * with the MCP (Model Context Protocol) service. It provides detailed context about
 * the specific request and response data that caused the error, which is crucial
 * for debugging protocol violations.
 */
class McpProtocolException extends McpException
{
  /**
   * @var string The version of the protocol in use when the error occurred.
   */
  protected string $protocolVersion;

  /**
   * @var array The request data that was sent to the MCP service.
   */
  protected array $requestData;

  /**
   * @var array The response data received from the MCP service.
   */
  protected array $responseData;

  /**
   * McpProtocolException constructor.
   *
   * @param string $message The error message.
   * @param string $protocolVersion The protocol version in use.
   * @param array $requestData The request data that caused the error.
   * @param array $responseData The response data received.
   * @param int $code The error code.
   * @param \Throwable|null $previous The previous exception in the chain.
   */
  public function __construct(
    string $message = "",
    string $protocolVersion = "",
    array $requestData = [],
    array $responseData = [],
    int $code = 0,
    ?\Throwable $previous = null
  ) {
    parent::__construct($message, $code, $previous);
    $this->protocolVersion = $protocolVersion;
    $this->requestData = $requestData;
    $this->responseData = $responseData;
  }

  /**
   * Gets the protocol version that was in use when the error occurred.
   *
   * @return string The protocol version.
   */
  public function getProtocolVersion(): string
  {
    return $this->protocolVersion;
  }

  /**
   * Gets the request data that led to the error.
   *
   * @return array The request data.
   */
  public function getRequestData(): array
  {
    return $this->requestData;
  }

  /**
   * Gets the response data received from the service.
   *
   * @return array The response data.
   */
  public function getResponseData(): array
  {
    return $this->responseData;
  }
}