<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Transport;

use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConnectionException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpException;
use ClicShopping\OM\HTTP;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class SseTransport
 *
 * This class provides a transport layer for the MCP (Model Context Protocol) using
 * Server-Sent Events (SSE) over HTTP. It handles the communication details, including
 * connection management, sending messages, and error handling.
 */
class SseTransport implements TransportInterface
{
  /**
   * @var array The configuration settings for the transport.
   */
  private array $config;

  /**
   * @var LoggerInterface The logger instance for logging events.
   */
  private LoggerInterface $logger;

  /**
   * @var bool The current connection status.
   */
  private bool $connected = false;

  /**
   * @var string The base URL for the MCP server.
   */
  private string $baseUrl;

  /**
   * @var array The HTTP headers to be sent with requests.
   */
  private array $headers;

  /**
   * @var array An associative array to store transport-level statistics.
   */
  private array $stats = [
    'messages_sent' => 0,
    'messages_received' => 0,
    'errors' => 0,
    'connection_time' => null,
    'last_activity' => null,
    'http_errors' => []
  ];

  /**
   * @var array A collection of recent error messages.
   */
  private array $recentErrors = [];

  /**
   * @var int The total number of messages received.
   */
  private int $totalMessages = 0;

  /**
   * @var int The number of connection attempts.
   */
  private int $connectionAttempts = 0;

  /**
   * @var callable|null The callback function to handle incoming messages.
   */
  private $messageCallback;

  /**
   * @var int|null The timestamp of the last ping received.
   */
  private ?int $lastPing = null;

  /**
   * SseTransport constructor.
   *
   * Initializes the transport with configuration and a logger, and performs a
   * preliminary configuration validation.
   *
   * @param array $config The configuration settings.
   * @param LoggerInterface|null $logger An optional PSR-3 logger instance.
   * @throws McpException If the configuration is invalid or incomplete.
   */
  public function __construct(array $config, ?LoggerInterface $logger = null)
  {
    $this->config = $config;
    $this->logger = $logger ?? new NullLogger();

    $this->validateConfig();
    $this->setupConnection();
  }

  /**
   * Validates the required configuration.
   *
   * @throws McpException If a required configuration key is missing or invalid.
   */
  private function validateConfig(): void
  {
    $required = ['server_host', 'server_port'];
    foreach ($required as $key) {
      if (empty($this->config[$key])) {
        throw new McpException("Missing required config: {$key}");
      }
    }

    if (!is_numeric($this->config['server_port'])) {
      throw new McpException('Port must be numeric');
    }
  }

  /**
   * Sets up the HTTP connection parameters.
   *
   * This method constructs the base URL and sets the default HTTP headers, including
   * authorization headers if a token is provided in the configuration.
   */
  private function setupConnection(): void
  {
    $protocol = $this->config['ssl'] ?? false ? 'https' : 'http';
    $this->baseUrl = "{$protocol}://{$this->config['server_host']}:{$this->config['server_port']}";

    $this->headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'User-Agent' => 'ClicShopping-MCP-Client/1.0',
      'X-MCP-Protocol-Version' => '2024-11-05'
    ];

    if (!empty($this->config['token'])) {
      $this->headers['Authorization'] = 'Bearer ' . $this->config['token'];
    }
  }

  /**
   * Converts the associative headers array to the "Key: value" format expected by HTTP::getResponse.
   *
   * @param array $extra Additional headers to merge (associative).
   * @return array Indexed array of "Key: value" strings.
   */
  private function buildHeaderLines(array $extra = []): array
  {
    $merged = array_merge($this->headers, $extra);

    return array_map(
      static fn($k, $v) => "{$k}: {$v}",
      array_keys($merged),
      $merged
    );
  }

  /**
   * Sets transport options.
   *
   * @param array $options Configuration options for the transport.
   */
  public function setOptions(array $options): void
  {
    $this->config = array_merge($this->config, $options);
    $this->setupConnection();
  }

  /**
   * Establishes a connection to the MCP server.
   *
   * This method performs a health check to verify connectivity before
   * marking the transport as connected.
   *
   * @return bool True if the connection is successful.
   * @throws McpConnectionException If the connection fails for any reason.
   */
  final public function connect(): bool
  {
    if ($this->connected) {
      return true;
    }

    $healthUrl = $this->baseUrl . ($this->config['health_endpoint'] ?? '/health');

    $this->logger->info('Testing connection to MCP server', ['url' => $healthUrl]);

    $response = HTTP::getResponse([
      'url' => $healthUrl,
      'method' => 'get',
      'header' => $this->buildHeaderLines(),
    ]);

    if ($response === false) {
      $this->logger->error('Connection to MCP server failed', ['url' => $healthUrl]);
      throw new McpConnectionException("Connection failed to: {$healthUrl}");
    }

    $this->connected = true;
    $this->stats['connection_time'] = time();
    $this->stats['last_activity'] = time();

    $this->logger->info('HTTP transport connected successfully', ['url' => $this->baseUrl]);

    return true;
  }

  /**
   * Sends a message to the MCP server.
   *
   * This method uses HTTP::getResponse to send a POST request with the message payload.
   * It handles various network and HTTP errors and returns the decoded JSON response.
   *
   * @param array $message The message payload to send.
   * @return array|null The decoded JSON response from the server, or null if an error occurs.
   * @throws McpConnectionException If the request fails.
   * @throws McpException If the server returns invalid JSON.
   */
  final public function send(array $message): ?array
  {
    $endpoint = $this->config['mcp_endpoint'] ?? '/api/ai/chat/process';
    $url = $this->baseUrl . $endpoint;

    $this->stats['messages_sent']++;
    $this->stats['last_activity'] = time();

    $decoded = HTTP::getResponse([
      'url' => $url,
      'format' => 'json',
      'method' => 'post',
      'parameters' => $message,
      'header' => $this->buildHeaderLines(),
    ]);

    if ($decoded === false) {
      $this->stats['errors']++;
      $this->stats['http_errors'][] = [
        'time' => time(),
        'url' => $url
      ];

      $this->logger->error('MCP chat request failed', [
        'url' => $url,
        'method' => $message['method'] ?? 'unknown',
        'id' => $message['id'] ?? 'notification',
      ]);

      throw new McpConnectionException("Request failed to: {$url}");
    }

    if (isset($message['id'])) {
      return [];
    }

    $this->stats['messages_received']++;

    $this->logger->info('MCP chat request completed', [
      'method' => $message['method'] ?? 'unknown',
      'id' => $message['id'] ?? 'notification',
      'url' => $url,
    ]);

    return $decoded;
  }

  /**
   * Closes the connection to the MCP server.
   */
  final public function disconnect(): void
  {
    if ($this->connected) {
      $this->logger->info('Disconnecting HTTP transport');
      $this->connected = false;
    }
  }

  /**
   * Gets the transport statistics.
   *
   * @return array An associative array of transport statistics.
   */
  final public function getStats(): array
  {
    return array_merge($this->stats, [
      'base_url' => $this->baseUrl,
      'recent_errors' => $this->recentErrors,
      'connected' => $this->connected,
      'last_ping' => $this->lastPing,
      'total_messages' => $this->totalMessages,
      'connection_attempts' => $this->connectionAttempts
    ]);
  }

  /**
   * Checks if the transport is currently connected.
   *
   * @return bool True if connected, false otherwise.
   */
  final public function isConnected(): bool
  {
    return $this->connected;
  }

  /**
   * Handles an incoming Server-Sent Event (SSE) message.
   *
   * @param string $data The raw message data from the SSE stream.
   */
  final protected function handleMessage(string $data): void
  {
    $this->totalMessages++;

    if ($data === 'ping') {
      $this->lastPing = time();
      return;
    }

    try {
      $message = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
      if (isset($message['error'])) {
        $this->recentErrors[] = $message['error'];
        if (count($this->recentErrors) > 10) {
          array_shift($this->recentErrors);
        }
      }

      if (isset($this->messageCallback)) {
        call_user_func($this->messageCallback, $message);
      }
    } catch (\JsonException $e) {
      $this->recentErrors[] = 'Invalid JSON: ' . $e->getMessage();
    }
  }

  /**
   * Sets the callback function for message handling.
   *
   * @param callable $callback The callback function.
   */
  final public function onMessage(callable $callback): void
  {
    $this->messageCallback = $callback;
  }

  /**
   * Closes the underlying connection.
   */
  final public function close(): void
  {
    if ($this->connected) {
      $this->connected = false;
    }
  }

  /**
   * Destructor to ensure the connection is closed.
   */
  public function __destruct()
  {
    $this->close();
  }
}
