<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConfigurationException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConnectionException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpProtocolException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class McpProtocol
 *
 * This class handles the low-level communication protocol for the MCP (Management & Control Panel) system.
 * It is responsible for validating configuration, managing the connection, and sending/receiving
 * requests and responses using a specified transport layer.
 */
class McpProtocol
{
  /**
   * @var array The configuration settings for the protocol.
   */
  private array $config;

  /**
   * @var LoggerInterface The logger instance for logging protocol-related events.
   */
  private LoggerInterface $logger;

  /**
   * @var TransportInterface The transport layer implementation (e.g., SSE, WebSocket).
   */
  private TransportInterface $transport;

  /**
   * @var int A counter for generating unique request IDs.
   */
  private int $requestId = 0;

  /**
   * @var array A map of pending requests to their resolvers.
   */
  private array $pendingRequests = [];

  /**
   * @var array An associative array to store protocol-level statistics.
   */
  private array $stats = [
    'requests_sent' => 0,
    'responses_received' => 0,
    'errors' => 0,
    'last_activity' => null
  ];

  /**
   * McpProtocol constructor.
   *
   * Initializes the protocol handler with configuration, a transport layer, and an optional logger.
   * It immediately validates the provided configuration to ensure all required fields are present.
   *
   * @param array $config Configuration settings.
   * @param TransportInterface $transport Transport implementation.
   * @param LoggerInterface|null $logger PSR-3 logger.
   * @throws McpConfigurationException If the configuration is missing required fields.
   */
  public function __construct(array $config, TransportInterface $transport, ?LoggerInterface $logger = null)
  {
    $this->validateConfig($config);
    $this->config = $config;
    $this->transport = $transport;
    $this->logger = $logger ?? new NullLogger();
  }

  /**
   * Validates the required configuration settings.
   *
   * This private helper method ensures that essential fields like 'server_host', 'server_port',
   * and 'token' are present in the configuration array.
   *
   * @param array $config The configuration array to validate.
   * @throws McpConfigurationException If any required configuration field is missing.
   */
  private function validateConfig(array $config): void
  {
    $required = ['server_host', 'server_port', 'token'];
    $missing = array_diff($required, array_keys($config));

    if (!empty($missing)) {
      throw new McpConfigurationException(
        'Missing required configuration: ' . implode(', ', $missing),
        $config,
        ['missing_fields' => $missing]
      );
    }
  }

  /**
   * Connects to the MCP server using the configured transport.
   *
   * This method handles the connection process and wraps any transport-level exceptions
   * in a `McpConnectionException`.
   *
   * @throws McpConnectionException If the connection fails.
   */
  final public function connect(): void
  {
    try {
      $this->transport->connect();
    } catch (\Exception $e) {
      throw new McpConnectionException(
        'Failed to connect to MCP server: ' . $e->getMessage(),
        json_encode($this->config),
        0,
        $e
      );
    }
  }

  /**
   * Sends a request to the MCP server.
   *
   * This method constructs a payload based on the method and parameters and sends it
   * through the transport layer. It handles a special 'ping' method and general messages.
   *
   * @param string $method The method name (e.g., 'ping', 'message').
   * @param array $params An array of parameters for the request.
   * @return array The response from the server.
   * @throws \InvalidArgumentException If the 'message' parameter is missing or invalid.
   * @throws McpProtocolException|McpConnectionException If the transport fails or the server returns an error.
   */
  public function sendRequest(string $method, array $params = []): array
  {
    if ($method === 'ping') {
      $payload = ['message' => 'ping'];
    } elseif (isset($params['message']) && is_string($params['message'])) {
      $payload = ['message' => $params['message']];
    } else {
      throw new \InvalidArgumentException('Missing or invalid "message" parameter');
    }

    return $this->transport->send($payload);
  }

  /**
   * Gets the transport instance used by the protocol.
   *
   * @return TransportInterface The transport instance.
   */
  public function getTransport(): TransportInterface
  {
    return $this->transport;
  }

  /**
   * Gets statistics about the MCP connection and protocol usage.
   *
   * @return array An associative array of combined protocol and transport statistics.
   */
  final public function getStats(): array
  {
    return array_merge(
      $this->stats,
      $this->transport->getStats()
    );
  }

  /**
   * Closes the connection to the MCP server.
   *
   * This method delegates the disconnection task to the underlying transport layer.
   */
  final public function disconnect(): void
  {
    $this->transport->disconnect();
  }

  /**
   * Cleans up resources when the object is destroyed.
   *
   * The destructor ensures the connection is closed to prevent resource leaks.
   */
  public function __destruct()
  {
    $this->disconnect();
  }
}