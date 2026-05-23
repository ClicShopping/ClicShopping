<?php
/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpProtocolException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConnectionException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class McpJsonRpcClient
 *
 * This class implements a JSON-RPC 2.0 client for communication with the MCP server.
 * It manages the connection, request/response cycle, and error handling,
 * providing a high-level interface for sending commands to the server.
 */
class McpJsonRpcClient
{
  /**
   * @var TransportInterface The underlying transport layer for sending and receiving data.
   */
  private TransportInterface $transport;

  /**
   * @var array The client's configuration settings.
   */
  private array $config;

  /**
   * @var LoggerInterface The logger instance for logging events and errors.
   */
  private LoggerInterface $logger;

  /**
   * @var int The counter for unique request IDs.
   */
  private int $requestId = 0;

  /**
   * @var array A map of pending requests by their ID to their respective resolvers.
   */
  private array $pending = [];

  /**
   * @var array An associative array to store client statistics.
   */
  private array $stats = [
    'requests_sent' => 0,
    'responses_received' => 0,
    'errors' => 0,
    'last_activity' => null,
    'connection_time' => null
  ];

  /**
   * McpJsonRpcClient constructor.
   *
   * Initializes the client with configuration, a transport layer, and a logger.
   * It also registers a message handler with the transport to process incoming messages.
   *
   * @param array $config The client configuration.
   * @param TransportInterface $transport The transport layer implementation.
   * @param LoggerInterface|null $logger An optional PSR-3 logger instance.
   */
  public function __construct(array $config, TransportInterface $transport, ?LoggerInterface $logger = null)
  {
    $this->config = $config;
    $this->transport = $transport;
    $this->logger = $logger ?? new NullLogger();

    // Register a global handler to process incoming messages from the transport.
    $this->transport->onMessage(function (array $msg) {
      $this->handleMessage($msg);
    });
  }

  /**
   * Establishes a connection and performs a handshake with the MCP server.
   *
   * The handshake uses a 'mcp/hello' JSON-RPC method to confirm the server's readiness
   * and capability.
   *
   * @throws McpConnectionException If the transport connection fails.
   * @throws McpProtocolException If the handshake response is invalid.
   */
  public function connect(): void
  {
    try {
      if (!$this->transport->connect()) {
        throw new McpConnectionException('Transport connect() returned false', json_encode($this->config));
      }
      $this->stats['connection_time'] = time();
    } catch (\Exception $e) {
      throw new McpConnectionException('Transport connection failed', json_encode($this->config), 0, $e);
    }

    // Handshake JSON-RPC
    $helloFrame = [
      'jsonrpc' => '2.0',
      'method' => 'mcp/hello',
      'params' => [
        'version' => '1.0',
        'agent' => 'ClicShopping-MCP',
        'capabilities' => ['chat.process', 'context.store']
      ],
      'id' => $this->nextId()
    ];

    $this->log('Sending MCP handshake');
    $resp = $this->send($helloFrame);

    if (!$resp || !isset($resp['result'])) {
      throw new McpProtocolException('Invalid handshake response', $this->config, $resp ?? []);
    }

    $this->log('Handshake successful: ' . json_encode($resp['result']));
  }

  /**
   * Sends a JSON-RPC request to the server and waits for a response.
   *
   * This method is used for requests that expect a result from the server.
   *
   * @param string $method The JSON-RPC method name.
   * @param array $params An array of parameters for the method.
   * @return array The 'result' part of the server's JSON-RPC response.
   * @throws McpProtocolException If the transport fails, no response is received, or the server returns an error.
   */
  public function sendRequest(string $method, array $params = []): array
  {
    $frame = [
      'jsonrpc' => '2.0',
      'method' => $method,
      'params' => $params,
      'id' => $this->nextId()
    ];

    $this->stats['requests_sent']++;
    $this->stats['last_activity'] = time();

    $resp = $this->send($frame);

    if (!$resp) {
      $this->stats['errors']++;
      throw new McpProtocolException('No response received', $this->config, $frame);
    }

    if (isset($resp['error'])) {
      $this->stats['errors']++;
      throw new McpProtocolException('MCP error', $this->config, $resp);
    }

    $this->stats['responses_received']++;
    return $resp['result'] ?? [];
  }

  /**
   * Sends a JSON-RPC notification to the server.
   *
   * Notifications do not expect a response and are used for fire-and-forget messages.
   *
   * @param string $method The JSON-RPC method name.
   * @param array $params An array of parameters for the method.
   */
  public function sendNotification(string $method, array $params = []): void
  {
    $frame = [
      'jsonrpc' => '2.0',
      'method' => $method,
      'params' => $params
    ];
    $this->stats['last_activity'] = time();
    $this->transport->send($frame);
  }

  /**
   * Disconnects the underlying transport from the server.
   */
  public function disconnect(): void
  {
    $this->transport->disconnect();
  }

  /**
   * Gets the client's statistics.
   *
   * @return array An associative array containing statistics like request counts and error rates.
   */
  public function getStats(): array
  {
    return $this->stats;
  }

  /**
   * Internal method to send a framed message through the transport layer.
   *
   * @param array $frame The JSON-RPC message frame to send.
   * @return array|null The response from the server, or null if a notification was sent.
   * @throws McpProtocolException If the transport layer fails to send the message.
   */
  private function send(array $frame): ?array
  {
    try {
      return $this->transport->send($frame);
    } catch (\Throwable $e) {
      $this->stats['errors']++;
      $protocolVersion = isset($frame['jsonrpc']) ? (string)$frame['jsonrpc'] : '';
      throw new McpProtocolException('Transport send() failed', $protocolVersion, $frame, [], 0, $e);
    }
  }

  /**
   * Handles an incoming message from the transport layer.
   *
   * This method processes messages, matching responses to pending requests based on their ID,
   * or logging unsolicited messages (notifications or unhandled responses).
   *
   * @param array $msg The incoming message from the server.
   */
  private function handleMessage(array $msg): void
  {
    $this->stats['last_activity'] = time();

    if (isset($msg['id']) && isset($this->pending[$msg['id']])) {
      $resolver = $this->pending[$msg['id']];
      unset($this->pending[$msg['id']]);
      $resolver($msg);
      return;
    }

    // Notifications or unmatched messages
    $this->log('Received unsolicited message: ' . json_encode($msg));
  }

  /**
   * Generates the next unique request ID.
   *
   * @return int The next request ID.
   */
  private function nextId(): int
  {
    return ++$this->requestId;
  }

  /**
   * Logs a message using the configured logger.
   *
   * @param string $message The message to log.
   */
  private function log(string $message): void
  {
    $this->logger->info('[McpJsonRpcClient] ' . $message);
  }
}
