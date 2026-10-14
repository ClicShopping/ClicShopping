<?php
/**
 * ClicShopping AI - Message Promise
 *
 * @copyright 2024 ClicShopping
 * @license MIT
 * @version 1.0.0
 */

namespace ClicShopping\AI\Infrastructure\Communication;

/**
 * Promise for asynchronous message delivery
 */
class MessagePromise
{
  private Message $message;
  private mixed $result = null;
  private ?\Exception $error = null;
  private bool $resolved = false;
  private bool $rejected = false;
  private array $thenCallbacks = [];
  private array $catchCallbacks = [];

  /**
   * Constructor
   *
   * @param Message $message Message being sent
   */
  public function __construct(Message $message)
  {
    $this->message = $message;
  }

  /**
   * Resolve promise with result
   *
   * @param mixed $result Result value
   * @return void
   */
  public function resolve(mixed $result): void
  {
    if ($this->resolved || $this->rejected) {
      return;
    }

    $this->resolved = true;
    $this->result = $result;

    // Execute then callbacks
    foreach ($this->thenCallbacks as $callback) {
      $callback($result);
    }
  }

  /**
   * Reject promise with error
   *
   * @param \Exception $error Error
   * @return void
   */
  public function reject(\Exception $error): void
  {
    if ($this->resolved || $this->rejected) {
      return;
    }

    $this->rejected = true;
    $this->error = $error;

    // Execute catch callbacks
    foreach ($this->catchCallbacks as $callback) {
      $callback($error);
    }
  }

  /**
   * Register callback for successful resolution
   *
   * @param callable $callback Callback function
   * @return self
   */
  public function then(callable $callback): self
  {
    if ($this->resolved) {
      $callback($this->result);
    } else {
      $this->thenCallbacks[] = $callback;
    }

    return $this;
  }

  /**
   * Register callback for rejection
   *
   * @param callable $callback Callback function
   * @return self
   */
  public function catch(callable $callback): self
  {
    if ($this->rejected) {
      $callback($this->error);
    } else {
      $this->catchCallbacks[] = $callback;
    }

    return $this;
  }

  /**
   * Wait for promise to resolve or reject
   *
   * @param int $timeoutMs Timeout in milliseconds
   * @return mixed Result value
   * @throws MessageTimeoutException If timeout exceeded
   * @throws MessageDeliveryException If promise rejected
   */
  public function wait(int $timeoutMs = 30000): mixed
  {
    $startTime = microtime(true);
    $timeoutSeconds = $timeoutMs / 1000;

    while (!$this->resolved && !$this->rejected) {
      if ((microtime(true) - $startTime) > $timeoutSeconds) {
        throw new MessageTimeoutException(
          "Message delivery timeout after {$timeoutMs}ms for message: {$this->message->getMessageId()}"
        );
      }

      // Small sleep to prevent busy waiting
      usleep(1000); // 1ms
    }

    if ($this->rejected) {
      throw new MessageDeliveryException(
        "Message delivery failed: " . $this->error->getMessage(),
        0,
        $this->error
      );
    }

    return $this->result;
  }

  /**
   * Check if promise is resolved
   *
   * @return bool
   */
  public function isResolved(): bool
  {
    return $this->resolved;
  }

  /**
   * Check if promise is rejected
   *
   * @return bool
   */
  public function isRejected(): bool
  {
    return $this->rejected;
  }

  /**
   * Check if promise is pending
   *
   * @return bool
   */
  public function isPending(): bool
  {
    return !$this->resolved && !$this->rejected;
  }

  /**
   * Get message
   *
   * @return Message
   */
  public function getMessage(): Message
  {
    return $this->message;
  }

  /**
   * Get result (if resolved)
   *
   * @return mixed
   */
  public function getResult(): mixed
  {
    return $this->result;
  }

  /**
   * Get error (if rejected)
   *
   * @return \Exception|null
   */
  public function getError(): ?\Exception
  {
    return $this->error;
  }
}
