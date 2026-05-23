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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class StdioTransport
 *
 * This class provides a transport layer for the MCP (Model Context Protocol) using
 * standard input/output (STDIO) pipes. It's designed to communicate with a separate
 * process (the MCP server) running on the same machine.
 */
class StdioTransport implements TransportInterface
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
   * @var resource|null The process resource returned by `proc_open`.
   */
  private $process;

  /**
   * @var array The pipes for standard input, output, and error.
   */
  private array $pipes = [];

  /**
   * @var bool The current connection status.
   */
  private bool $connected = false;

  /**
   * @var array An associative array to store transport-level statistics.
   */
  private array $stats = [
    'messages_sent' => 0,
    'messages_received' => 0,
    'errors' => 0,
    'connection_time' => null,
    'last_activity' => null
  ];

  /**
   * @var callable|null A callback function to handle incoming messages asynchronously.
   */
  private $messageCallback = null;

  /**
   * StdioTransport constructor.
   *
   * Initializes the transport with configuration and an optional logger, and validates
   * the provided configuration.
   *
   * @param array $config The configuration settings.
   * @param LoggerInterface|null $logger An optional PSR-3 logger instance.
   * @throws McpException If the configuration is invalid.
   */
  public function __construct(array $config, ?LoggerInterface $logger = null)
  {
    $this->config = $config;
    $this->logger = $logger ?? new NullLogger();

    $this->validateConfig();
  }

  /**
   * Validates the required configuration.
   *
   * @throws McpException If the server path is missing or not executable.
   */
  private function validateConfig(): void
  {
    if (empty($this->config['server_path'])) {
      throw new McpException('server_path is required for STDIO transport');
    }

    if (!is_executable($this->config['server_path'])) {
      throw new McpException('Server path is not executable: ' . $this->config['server_path']);
    }
  }

  /**
   * Establishes a connection to the MCP server by starting a new process.
   *
   * This method uses `proc_open` to start the MCP server as a child process and sets
   * up the necessary pipes for communication.
   *
   * @return bool True if the connection is successful.
   * @throws McpConnectionException If the process fails to start or dies immediately.
   */
  public function connect(): bool
  {
    if ($this->connected) {
      return true;
    }

    $command = $this->buildCommand();

    $descriptorspec = [
      0 => ["pipe", "r"],  // stdin
      1 => ["pipe", "w"],  // stdout
      2 => ["pipe", "w"]   // stderr
    ];

    $this->logger->info('Starting MCP server process', ['command' => $command]);

    $this->process = proc_open($command, $descriptorspec, $this->pipes);

    if (!is_resource($this->process)) {
      $this->logger->error('Failed to start MCP server process', ['command' => $command]);
      throw new McpConnectionException('Failed to start MCP server process');
    }

    // Configuration non-bloquante pour la lecture
    if (!stream_set_blocking($this->pipes[1], false)) {
      $this->logger->warning('Failed to set non-blocking mode on stdout');
    }

    if (!stream_set_blocking($this->pipes[2], false)) {
      $this->logger->warning('Failed to set non-blocking mode on stderr');
    }

    // Vérification que le processus est toujours en vie
    $status = proc_get_status($this->process);
    if (!$status['running']) {
      $this->logger->error('MCP server process died immediately', ['status' => $status]);
      $this->cleanup();
      throw new McpConnectionException('MCP server process died immediately');
    }

    $this->connected = true;
    $this->stats['connection_time'] = time();
    $this->stats['last_activity'] = time();

    $this->logger->info('MCP server process started successfully', ['pid' => $status['pid']]);
    return true;
  }

  /**
   * Sends a message to the MCP server via the standard input pipe.
   *
   * @param array $message The message to send.
   * @return array|null The response from the server, or null if a timeout occurs.
   * @throws McpConnectionException If not connected or if writing to the pipe fails.
   */
  public function send(array $message): ?array
  {
    if (!$this->connected) {
      throw new McpConnectionException('Not connected to MCP server');
    }

    $jsonMessage = json_encode($message) . "\n";

    $this->logger->debug('Sending MCP message', [
      'method' => $message['method'] ?? 'unknown',
      'id' => $message['id'] ?? 'notification',
      'size' => strlen($jsonMessage)
    ]);

    if (fwrite($this->pipes[0], $jsonMessage) === false) {
      $this->stats['errors']++;
      $this->logger->error('Failed to write to MCP server stdin');
      throw new McpConnectionException('Failed to write to MCP server');
    }

    $this->stats['messages_sent']++;
    $this->stats['last_activity'] = time();

    // Pour les notifications, pas de réponse attendue
    if (!isset($message['id'])) {
      return [];
    }

    return $this->readResponse($message['id']);
  }

  /**
   * Reads a response from the MCP server's standard output.
   *
   * @param int $expectedId The ID of the message to wait for.
   * @return array|null The decoded JSON response, or null on timeout.
   * @throws McpConnectionException If the process dies during communication.
   */
  private function readResponse(int $expectedId): ?array
  {
    $timeout = $this->config['timeout'] ?? 30;
    $startTime = time();
    $buffer = '';

    while (time() - $startTime < $timeout) {
      // Vérification que le processus est toujours en vie
      $status = proc_get_status($this->process);
      if (!$status['running']) {
        $this->logger->error('MCP server process died during communication');
        $this->connected = false;
        throw new McpConnectionException('MCP server process died');
      }

      // Lecture de stderr pour les erreurs
      $stderr = fread($this->pipes[2], 1024);
      if ($stderr) {
        $this->logger->warning('MCP server stderr', ['output' => trim($stderr)]);
      }

      // Lecture de stdout pour les réponses
      $chunk = fread($this->pipes[1], 1024);
      if ($chunk !== false && $chunk !== '') {
        $buffer .= $chunk;

        // Traitement des lignes complètes
        while (($pos = strpos($buffer, "\n")) !== false) {
          $line = substr($buffer, 0, $pos);
          $buffer = substr($buffer, $pos + 1);

          if (!empty(trim($line))) {
            $response = json_decode($line, true);

            if (json_last_error() === JSON_ERROR_NONE) {
              $this->stats['messages_received']++;
              $this->stats['last_activity'] = time();

              $this->logger->debug('Received MCP response', [
                'id' => $response['id'] ?? 'notification',
                'method' => $response['method'] ?? 'response',
                'has_error' => isset($response['error'])
              ]);

              // Vérification que c'est la bonne réponse
              if (isset($response['id']) && $response['id'] === $expectedId) {
                return $response;
              }

              // Log des réponses inattendues
              if (isset($response['id'])) {
                $this->logger->warning('Received unexpected response ID', [
                  'expected' => $expectedId,
                  'received' => $response['id']
                ]);
              }
            } else {
              $this->logger->error('Invalid JSON received from MCP server', [
                'line' => $line,
                'json_error' => json_last_error_msg()
              ]);
            }
          }
        }
      }

      usleep(10000); // 10ms
    }

    $this->stats['errors']++;
    $this->logger->warning('MCP response timeout', [
      'expected_id' => $expectedId,
      'timeout' => $timeout
    ]);

    return null;
  }

  /**
   * Builds the command to start the server process.
   *
   * @return string The shell command.
   */
  private function buildCommand(): string
  {
    $serverPath = $this->config['server_path'];
    $serverArgs = $this->config['server_args'] ?? [];

    // Échapper les arguments pour la sécurité
    $escapedArgs = array_map('escapeshellarg', $serverArgs);

    return escapeshellcmd($serverPath) . ' ' . implode(' ', $escapedArgs);
  }

  /**
   * Disconnects from the MCP server by terminating the process.
   */
  public function disconnect(): void
  {
    if (!$this->connected) {
      return;
    }

    $this->logger->info('Disconnecting from MCP server');
    $this->cleanup();
    $this->connected = false;
  }

  /**
   * Cleans up resources related to the process.
   *
   * This method ensures that all pipes are closed and the child process is terminated
   * gracefully, and then forcefully if necessary.
   */
  private function cleanup(): void
  {
    // Fermeture des pipes
    foreach ($this->pipes as $pipe) {
      if (is_resource($pipe)) {
        fclose($pipe);
      }
    }
    $this->pipes = [];

    // Terminaison propre du processus
    if (is_resource($this->process)) {
      $status = proc_get_status($this->process);
      if ($status['running']) {
        // Tentative de terminaison propre
        proc_terminate($this->process, SIGTERM);

        // Attendre un peu pour la terminaison propre
        $waited = 0;
        while ($waited < 5) {
          $status = proc_get_status($this->process);
          if (!$status['running']) {
            break;
          }
          sleep(1);
          $waited++;
        }

        // Forcer la terminaison si nécessaire
        if ($status['running']) {
          proc_terminate($this->process, SIGKILL);
        }
      }

      proc_close($this->process);
    }
  }

  /**
   * Checks if the connection is active by checking if the process is running.
   *
   * @return bool True if connected and the process is running, false otherwise.
   */
  public function isConnected(): bool
  {
    if (!$this->connected) {
      return false;
    }

    // Vérification que le processus est toujours en vie
    if (is_resource($this->process)) {
      $status = proc_get_status($this->process);
      if (!$status['running']) {
        $this->logger->info('MCP server process has terminated');
        $this->connected = false;
        return false;
      }
    }

    return true;
  }

  /**
   * Configures the transport options.
   *
   * @param array $options The options to merge with the current configuration.
   */
  public function setOptions(array $options): void
  {
    $this->config = array_merge($this->config, $options);
  }

  /**
   * Gets the transport statistics.
   *
   * @return array The statistics array.
   */
  public function getStats(): array
  {
    return $this->stats;
  }

  /**
   * Destructor to ensure the process is terminated and resources are cleaned up.
   */
  public function __destruct()
  {
    $this->disconnect();
  }

  /**
   * Sets the callback function for message handling.
   *
   * For the StdioTransport, this callback is typically used for handling asynchronous
   * messages or "notifications" that don't have a direct response ID.
   *
   * @param callable $callback The callback function.
   */
  public function onMessage(callable $callback): void
  {
    $this->messageCallback = $callback;
  }

  /**
   * Closes the transport.
   *
   * This is an alias for the `disconnect` method, provided for consistency with other
   * transport implementations.
   */
  public function close(): void
  {
    $this->disconnect();
  }
}