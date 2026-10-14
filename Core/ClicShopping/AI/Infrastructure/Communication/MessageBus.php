<?php
/**
 * ClicShopping AI - Asynchronous Message Bus
 *
 * @copyright 2024 ClicShopping
 * @license MIT
 * @version 1.0.0
 */

namespace ClicShopping\AI\Infrastructure\Communication;

use ClicShopping\OM\Registry;

/**
 * Asynchronous message bus for actor-critic communication
 * Handles message routing, retry logic, and delivery tracking
 */
class MessageBus
{
  private MessageValidator $validator;
  private MessageLogger $logger;
  private array $handlers;
  private array $pendingMessages;
  private int $maxRetries;
  private int $retryDelayMs;

  /**
   * Constructor
   *
   * @param MessageValidator $validator Message validator
   * @param MessageLogger $logger Message logger
   * @param int $maxRetries Maximum retry attempts
   * @param int $retryDelayMs Delay between retries in milliseconds
   */
  public function __construct(
    MessageValidator $validator,
    MessageLogger $logger,
    int $maxRetries = 3,
    int $retryDelayMs = 100
  ) {
    $this->validator = $validator;
    $this->logger = $logger;
    $this->handlers = [];
    $this->pendingMessages = [];
    $this->maxRetries = $maxRetries;
    $this->retryDelayMs = $retryDelayMs;
  }

  /**
   * Register message handler for specific message type
   *
   * @param string $messageType Message type
   * @param callable $handler Handler function
   * @return void
   */
  public function registerHandler(string $messageType, callable $handler): void
  {
    $this->handlers[$messageType] = $handler;
  }

  /**
   * Send message asynchronously
   *
   * @param Message $message Message to send
   * @return MessagePromise Promise for async result
   * @throws MessageValidationException If message invalid
   */
  public function send(Message $message): MessagePromise
  {
    // Validate message
    $validationResult = $this->validator->validate($message);
    if (!$validationResult->isValid()) {
      $this->logger->logValidationFailure($message, $validationResult);
      throw new MessageValidationException(
        "Message validation failed: " . $validationResult->getErrorMessage()
      );
    }

    // Log message send
    $this->logger->logMessageSent($message);

    // Create promise
    $promise = new MessagePromise($message);

    // Dispatch asynchronously
    $this->dispatchAsync($message, $promise);

    return $promise;
  }

  /**
   * Dispatch message asynchronously
   *
   * @param Message $message Message to dispatch
   * @param MessagePromise $promise Promise to resolve
   * @return void
   */
  private function dispatchAsync(Message $message, MessagePromise $promise): void
  {
    // Store pending message
    $this->pendingMessages[$message->getMessageId()] = [
      'message' => $message,
      'promise' => $promise,
      'attempts' => 0
    ];

    // Simulate async dispatch (in production, use proper async mechanism)
    $this->processMessage($message, $promise);
  }

  /**
   * Process message with retry logic
   *
   * @param Message $message Message to process
   * @param MessagePromise $promise Promise to resolve
   * @return void
   */
  private function processMessage(Message $message, MessagePromise $promise): void
  {
    $messageId = $message->getMessageId();
    $pendingData = $this->pendingMessages[$messageId] ?? null;

    if (!$pendingData) {
      $promise->reject(new \RuntimeException('Message not found in pending queue'));
      return;
    }

    try {
      // Get handler for message type
      $handler = $this->handlers[$message->getMessageType()] ?? null;

      if (!$handler) {
        throw new \RuntimeException(
          "No handler registered for message type: {$message->getMessageType()}"
        );
      }

      // Execute handler
      $result = $handler($message);

      // Log successful delivery
      $this->logger->logMessageDelivered($message);

      // Resolve promise
      $promise->resolve($result);

      // Remove from pending
      unset($this->pendingMessages[$messageId]);

    } catch (\Exception $e) {
      $pendingData['attempts']++;
      $this->pendingMessages[$messageId] = $pendingData;

      // Log failure
      $this->logger->logMessageFailed($message, $e);

      // Retry if attempts remaining
      if ($pendingData['attempts'] < $this->maxRetries) {
        $message->incrementRetryCount();
        $this->logger->logMessageRetry($message, $pendingData['attempts']);

        // Delay before retry
        usleep($this->retryDelayMs * 1000);

        // Retry
        $this->processMessage($message, $promise);
      } else {
        // Max retries exceeded
        $this->logger->logMessageMaxRetriesExceeded($message);
        $promise->reject(new MessageDeliveryException(
          "Message delivery failed after {$this->maxRetries} attempts: " . $e->getMessage(),
          0,
          $e
        ));

        // Remove from pending
        unset($this->pendingMessages[$messageId]);
      }
    }
  }

  /**
   * Send message and wait for response
   *
   * @param Message $message Message to send
   * @param int $timeoutMs Timeout in milliseconds
   * @return mixed Response
   * @throws MessageDeliveryException If delivery fails
   * @throws MessageTimeoutException If timeout exceeded
   */
  public function sendAndWait(Message $message, int $timeoutMs = 30000): mixed
  {
    $promise = $this->send($message);
    return $promise->wait($timeoutMs);
  }

  /**
   * Send request and expect response
   *
   * @param Message $request Request message
   * @param int $timeoutMs Timeout in milliseconds
   * @return Message Response message
   * @throws MessageDeliveryException If delivery fails
   * @throws MessageTimeoutException If timeout exceeded
   */
  public function sendRequest(Message $request, int $timeoutMs = 30000): Message
  {
    $response = $this->sendAndWait($request, $timeoutMs);

    if ($response instanceof Message) {
      return $response;
    }

    throw new MessageDeliveryException('Expected Message response, got ' . gettype($response));
  }

  /**
   * Get pending message count
   *
   * @return int
   */
  public function getPendingCount(): int
  {
    return count($this->pendingMessages);
  }

  /**
   * Get pending messages
   *
   * @return array
   */
  public function getPendingMessages(): array
  {
    return array_map(fn($data) => $data['message'], $this->pendingMessages);
  }

  /**
   * Clear all pending messages
   *
   * @return void
   */
  public function clearPending(): void
  {
    foreach ($this->pendingMessages as $data) {
      $data['promise']->reject(new \RuntimeException('Message bus cleared'));
    }
    $this->pendingMessages = [];
  }

  /**
   * Get statistics
   *
   * @return array
   */
  public function getStatistics(): array
  {
    return [
      'pending_messages' => count($this->pendingMessages),
      'registered_handlers' => count($this->handlers),
      'max_retries' => $this->maxRetries,
      'retry_delay_ms' => $this->retryDelayMs
    ];
  }
}
