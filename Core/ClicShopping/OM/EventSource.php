<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use CLICSHOPPING;
use ClicShopping\OM\HttpRequest\Stream;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Exception;

/**
 * Class EventSource
 *
 * A PHP implementation of the EventSource client for Server-Sent Events (SSE).
 *
 * This class allows connecting to an SSE endpoint, handling incoming events,
 * managing reconnections, and providing hooks for event processing.
 *
 * Example usage:
 * ```php
 * $eventSource = new EventSource('https://example.com/sse');
 *
 * $eventSource->onOpen(function() {
 *     echo "Connection opened\n";
 * });
 *
 * $eventSource->onMessage(function($event) {
 *     echo "Received event: " . $event->getData() . "\n";
 * });
 *
 * $eventSource->onError(function($error) {
 *     echo "Error: " . $error->getMessage() . "\n";
 * });
 *
 * $eventSource->connect();
 * ```
 */
class EventSource
{
  private string $url;
  private array $headers = [];
  private array $httpOptions = [];
  private bool $aborted = false;
  private bool $connected = false;

  // Callbacks
  private array $messageHandlers = [];
  private $onOpen = null;
  private $onError = null;
  private $onClose = null;

  // Configuration
  private int $reconnectDelay = 3000; // ms
  private int $maxReconnectAttempts = 5;
  private int $currentReconnectAttempt = 0;
  private bool $autoReconnect = true;

  private LoggerInterface $logger;
  private ?string $lastEventId = null;

  // Buffer pour le parsing des événements
  private string $eventBuffer = '';

  /**
   * Constructor
   *
   * @param string $url The URL of the EventSource endpoint
   * @param LoggerInterface|null $logger Optional PSR-3 logger for logging events and errors
   * @throws Exception if streaming is not supported
   */
  public function __construct(string $url, LoggerInterface|null $logger = null)
  {
    $this->url = $url;
    $this->logger = $logger ?? new NullLogger();

    if (!Stream::canStream()) {
      throw new Exception('Streaming is not supported on this system');
    }

    $this->setDefaultHeaders();
    $this->setDefaultHttpOptions();
  }

  /**
   * Set default headers for the EventSource connection.
   * @return void
   */
  private function setDefaultHeaders(): void
  {
    $this->headers = [
      'Accept' => 'text/event-stream',
      'Cache-Control' => 'no-cache',
      'Connection' => 'keep-alive',
      'User-Agent' => 'ClicShopping-EventSource/1.0'
    ];
  }

  /**
   * Set default HTTP options for the EventSource connection.
   * @return void
   */
  private function setDefaultHttpOptions(): void
  {
    $this->httpOptions = [
      'read_timeout' => 60,
      'connection_timeout' => 15
    ];

    // Ajout du certificat CA par défaut
    if (defined('CLICSHOPPING::BASE_DIR')) {
      $defaultCaFile = CLICSHOPPING::BASE_DIR . 'External/cacert.pem';
      if (file_exists($defaultCaFile)) {
        $this->httpOptions['cafile'] = $defaultCaFile;
      }
    }
  }

  /**
   * Set additional HTTP headers.
   *
   * @param array $headers Associative array of headers (e.g. ['Authorization'
   */
  public function setHeaders(array $headers): self
  {
    $this->headers = array_merge($this->headers, $headers);
    return $this;
  }

  /**
   * Set additional HTTP options.
   *
   * Supported options include:
   * - read_timeout: Timeout for reading data (in seconds)
   * - connection_timeout: Timeout for establishing the connection (in seconds)
   * - cafile: Path to a CA certificate file for SSL verification
   *
   * @param array $options Associative array of HTTP options
   * @return $this
   */
  public function setHttpOptions(array $options): self
  {
    $this->httpOptions = array_merge($this->httpOptions, $options);
    return $this;
  }

  /**
   * Configuration methods
   * @param int $milliseconds Delay in milliseconds before attempting to reconnect
   * @return $this
   */
  public function setReconnectDelay(int $milliseconds): self
  {
    $this->reconnectDelay = $milliseconds;
    return $this;
  }

  /**
   * Set the maximum number of reconnection attempts.
   *
   * @param int $attempts Maximum number of reconnection attempts
   * @return $this
   */
  public function setMaxReconnectAttempts(int $attempts): self
  {
    $this->maxReconnectAttempts = $attempts;
    return $this;
  }

  /**
   * Enable or disable automatic reconnection.
   *
   * @param bool $autoReconnect True to enable auto-reconnect, false to disable
   * @return $this
   */
  public function setAutoReconnect(bool $autoReconnect): self
  {
    $this->autoReconnect = $autoReconnect;
    return $this;
  }

  /**
   * Set the read timeout for the HTTP connection.
   *
   * @param int $seconds Read timeout in seconds
   * @return $this
   */
  public function setReadTimeout(int $seconds): self
  {
    $this->httpOptions['read_timeout'] = $seconds;
    return $this;
  }

  /**
   * Set the connection timeout for the HTTP connection.
   *
   * @param int $seconds Connection timeout in seconds
   * @return $this
   */
  public function setConnectionTimeout(int $seconds): self
  {
    $this->httpOptions['connection_timeout'] = $seconds;
    return $this;
  }

  /**
   * Register a message handler callback.
   *
   * The callback will be invoked with an Event object whenever a new event is received.
   *
   * @param callable $handler Function with signature function(Event $event)
   * @return $this
   */
  public function onMessage(callable $handler): self
  {
    $this->messageHandlers[] = $handler;
    return $this;
  }

  /**
   * Register a callback for the 'open' event.
   *
   * The callback will be invoked when the connection is successfully opened.
   *
   * @param callable $handler Function with signature function()
   * @return $this
   */
  public function onOpen(callable $handler): self
  {
    $this->onOpen = $handler;
    return $this;
  }

  /**
   * Register a callback for the 'error' event.
   *
   * The callback will be invoked when an error occurs.
   *
   * @param callable $handler Function with signature function(Exception $error)
   * @return $this
   */
  public function onError(callable $handler): self
  {
    $this->onError = $handler;
    return $this;
  }

  /**
   * Register a callback for the 'close' event.
   *
   * The callback will be invoked when the connection is closed.
   *
   * @param callable $handler Function with signature function()
   * @return $this
   */
  public function onClose(callable $handler): self
  {
    $this->onClose = $handler;
    return $this;
  }

  /**
   * Connect to the EventSource endpoint and start receiving events.
   *
   * @throws Exception if already connected or if connection fails
   */
  public function connect(): void
  {
    if ($this->connected) {
      throw new Exception('EventSource is already connected');
    }

    $this->logger->info('Connecting to EventSource', ['url' => $this->url]);

    try {
      $this->performConnection();
    } catch (Exception $e) {
      $this->handleConnectionError($e);
    }
  }

  /**
   * Perform the actual connection and handle the streaming.
   * This method uses the Stream class to manage the HTTP connection and data streaming.
   * It sets up the necessary parameters and callbacks for processing incoming data.
   * @throws Exception if the connection fails
   * @return void
   */
  private function performConnection(): void
  {
    // Préparation des paramètres pour Stream
    $parameters = $this->prepareStreamParameters();

    $this->connected = true;
    $this->currentReconnectAttempt = 0;
    $this->eventBuffer = '';

    if ($this->onOpen && is_callable($this->onOpen)) {
      ($this->onOpen)();
    }

    try {
      // Utilisation du streaming de la nouvelle classe Stream
      Stream::executeStreaming($parameters, $this->processStreamData(...));
    } catch (Exception $e) {
      throw $e;
    } finally {
      $this->connected = false;

      if ($this->onClose && is_callable($this->onClose)) {
        ($this->onClose)();
      }

      // Auto-reconnect if needed
      if (!$this->aborted && $this->autoReconnect &&
        $this->currentReconnectAttempt < $this->maxReconnectAttempts) {
        $this->scheduleReconnect();
      }
    }
  }

  /**
   * Prepare the parameters for the Stream::executeStreaming method.
   *
   * @return array Associative array of parameters for Stream
   */
  private function prepareStreamParameters(): array
  {
    $headers = $this->headers;

    // Ajouter Last-Event-ID si disponible
    if ($this->lastEventId) {
      $headers['Last-Event-ID'] = $this->lastEventId;
    }

    // Convertir les headers au format attendu par Stream
    $headerStrings = [];
    foreach ($headers as $name => $value) {
      $headerStrings[] = "{$name}: {$value}";
    }

    $parameters = [
      'url' => $this->url,
      'method' => 'get',
      'header' => $headerStrings,
      'parameters' => ''
    ];

    // Ajouter les options HTTP
    return array_merge($parameters, $this->httpOptions);
  }

  /**
   * Process incoming stream data.
   *
   * This method is called by the Stream class whenever new data is received.
   * It buffers the data and processes complete events.
   *
   * @param string $data The incoming data chunk
   * @return void
   */
  public function processStreamData(string $data): void
  {
    if ($this->aborted) {
      return;
    }

    $this->eventBuffer .= $data;

    // Traiter les événements complets (séparés par double retour ligne)
    while (($pos = strpos($this->eventBuffer, "\n\n")) !== false) {
      $eventData = substr($this->eventBuffer, 0, $pos);
      $this->eventBuffer = substr($this->eventBuffer, $pos + 2);

      if (!empty(trim($eventData))) {
        $this->processEventData($eventData);
      }
    }
  }

  /**
   * Process a single event data block.
   *
   * This method parses the event data and invokes the registered message handlers.
   *
   * @param string $eventData The raw event data block
   * @return void
   */
  private function processEventData(string $eventData): void
  {
    try {
      $event = new Event($eventData);

      // Sauvegarder l'ID pour la reconnexion
      if ($event->hasId()) {
        $this->lastEventId = $event->getId();
      }

      // Appeler tous les handlers
      foreach ($this->messageHandlers as $handler) {
        if (is_callable($handler)) {
          $handler($event);
        }
      }

      $this->logger->debug('Event processed', [
        'hasId' => $event->hasId(),
        'hasName' => $event->hasName(),
        'dataLength' => strlen($event->getData())
      ]);

    } catch (Exception $e) {
      $this->logger->warning('Failed to process event', [
        'error' => $e->getMessage(),
        'eventData' => substr($eventData, 0, 200) . '...'
      ]);

      if ($this->onError && is_callable($this->onError)) {
        ($this->onError)($e);
      }
    }
  }

  /**
   * Handle connection errors and manage reconnection attempts.
   *
   * @param Exception $e The exception that occurred
   * @return void
   * @throws Exception if maximum reconnection attempts are exceeded
   */
  private function handleConnectionError(Exception $e): void
  {
    $this->connected = false;
    $this->logger->error('EventSource connection error', [
      'error' => $e->getMessage(),
      'attempt' => $this->currentReconnectAttempt
    ]);

    if ($this->onError && is_callable($this->onError)) {
      ($this->onError)($e);
    }

    if (!$this->aborted && $this->autoReconnect &&
      $this->currentReconnectAttempt < $this->maxReconnectAttempts) {
      $this->scheduleReconnect();
    } else {
      throw $e;
    }
  }

  /**
   * Schedule a reconnection attempt after a delay.
   *
   * @return void
   */
  private function scheduleReconnect(): void
  {
    $this->currentReconnectAttempt++;
    $delay = min($this->reconnectDelay * $this->currentReconnectAttempt, 30000); // Max 30s

    $this->logger->info('Scheduling reconnect', [
      'attempt' => $this->currentReconnectAttempt,
      'delay' => $delay . 'ms'
    ]);

    usleep($delay * 1000); // Convert ms to microseconds

    if (!$this->aborted) {
      try {
        $this->performConnection();
      } catch (Exception $e) {
        $this->handleConnectionError($e);
      }
    }
  }

 /**
   * Abort the connection and stop any reconnection attempts.
   *
   * @return void
   */
  public function abort(): void
  {
    $this->aborted = true;
    $this->autoReconnect = false;
    $this->logger->info('EventSource connection aborted');
  }

  /**
   * Check if currently connected
   * @return bool True if connected, false otherwise
   */
  public function isConnected(): bool
  {
    return $this->connected;
  }

  /**
   * Check if the connection has been aborted
   * @return bool True if aborted, false otherwise
   */
  public function isAborted(): bool
  {
    return $this->aborted;
  }

  /**
   * Get the last received event ID
   * @return string|null The last event ID or null if none
   */
  public function getLastEventId(): ?string
  {
    return $this->lastEventId;
  }

  /**
   * Get the current reconnect attempt count
   * @return int The current reconnect attempt number
   */
  public function getReconnectAttempt(): int
  {
    return $this->currentReconnectAttempt;
  }

  /**
   * Get the EventSource URL
   * @return string The EventSource URL
   */
  public function getUrl(): string
  {
    return $this->url;
  }

  /**
   * Static method to check if streaming is supported on the system.
   *
   * @return bool True if streaming is supported, false otherwise
   */
  public static function isStreamingAvailable(): bool
  {
    return Stream::canStream();
  }
}