<?php
/**
 * ClicShopping AI - Actor-Critic Communication Protocol
 *
 * @copyright 2024 ClicShopping
 * @license MIT
 * @version 1.0.0
 */

namespace ClicShopping\AI\Infrastructure\Communication;

/**
 * Base message class for actor-critic communication
 * Provides versioning, validation, and serialization
 */
class Message
{
  private string $messageId;
  private string $messageType;
  private string $version;
  private string $senderId;
  private string $recipientId;
  private array $payload;
  private array $metadata;
  private \DateTimeImmutable $timestamp;
  private ?string $correlationId;
  private int $retryCount;

  public const VERSION_1_0 = '1.0';
  public const VERSION_CURRENT = self::VERSION_1_0;

  // Message types
  public const TYPE_ACTION_REQUEST = 'action_request';
  public const TYPE_ACTION_RESPONSE = 'action_response';
  public const TYPE_EVALUATION_REQUEST = 'evaluation_request';
  public const TYPE_EVALUATION_RESPONSE = 'evaluation_response';
  public const TYPE_FEEDBACK_DELIVERY = 'feedback_delivery';
  public const TYPE_FEEDBACK_ACKNOWLEDGMENT = 'feedback_acknowledgment';
  public const TYPE_ERROR = 'error';

  /**
   * Constructor
   *
   * @param string $messageType Message type
   * @param string $senderId Sender agent ID
   * @param string $recipientId Recipient agent ID
   * @param array $payload Message payload
   * @param string|null $correlationId Correlation ID for request-response tracking
   * @param string $version Protocol version
   */
  public function __construct(
    string $messageType,
    string $senderId,
    string $recipientId,
    array $payload,
    ?string $correlationId = null,
    string $version = self::VERSION_CURRENT
  ) {
    $this->messageId = $this->generateMessageId();
    $this->messageType = $messageType;
    $this->version = $version;
    $this->senderId = $senderId;
    $this->recipientId = $recipientId;
    $this->payload = $payload;
    $this->correlationId = $correlationId;
    $this->timestamp = new \DateTimeImmutable();
    $this->retryCount = 0;
    $this->metadata = [
      'created_at' => $this->timestamp->format('Y-m-d H:i:s.u'),
      'protocol_version' => $version
    ];
  }

  /**
   * Generate unique message ID
   *
   * @return string Message ID
   */
  private function generateMessageId(): string
  {
    return 'msg_' . uniqid() . '_' . bin2hex(random_bytes(8));
  }

  /**
   * Get message ID
   *
   * @return string
   */
  public function getMessageId(): string
  {
    return $this->messageId;
  }

  /**
   * Get message type
   *
   * @return string
   */
  public function getMessageType(): string
  {
    return $this->messageType;
  }

  /**
   * Get protocol version
   *
   * @return string
   */
  public function getVersion(): string
  {
    return $this->version;
  }

  /**
   * Get sender ID
   *
   * @return string
   */
  public function getSenderId(): string
  {
    return $this->senderId;
  }

  /**
   * Get recipient ID
   *
   * @return string
   */
  public function getRecipientId(): string
  {
    return $this->recipientId;
  }

  /**
   * Get payload
   *
   * @return array
   */
  public function getPayload(): array
  {
    return $this->payload;
  }

  /**
   * Get metadata
   *
   * @return array
   */
  public function getMetadata(): array
  {
    return $this->metadata;
  }

  /**
   * Get timestamp
   *
   * @return \DateTimeImmutable
   */
  public function getTimestamp(): \DateTimeImmutable
  {
    return $this->timestamp;
  }

  /**
   * Get correlation ID
   *
   * @return string|null
   */
  public function getCorrelationId(): ?string
  {
    return $this->correlationId;
  }

  /**
   * Get retry count
   *
   * @return int
   */
  public function getRetryCount(): int
  {
    return $this->retryCount;
  }

  /**
   * Increment retry count
   *
   * @return void
   */
  public function incrementRetryCount(): void
  {
    $this->retryCount++;
  }

  /**
   * Add metadata
   *
   * @param string $key Metadata key
   * @param mixed $value Metadata value
   * @return void
   */
  public function addMetadata(string $key, mixed $value): void
  {
    $this->metadata[$key] = $value;
  }

  /**
   * Serialize message to array
   *
   * @return array
   */
  public function toArray(): array
  {
    return [
      'message_id' => $this->messageId,
      'message_type' => $this->messageType,
      'version' => $this->version,
      'sender_id' => $this->senderId,
      'recipient_id' => $this->recipientId,
      'payload' => $this->payload,
      'metadata' => $this->metadata,
      'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
      'correlation_id' => $this->correlationId,
      'retry_count' => $this->retryCount
    ];
  }

  /**
   * Serialize message to JSON
   *
   * @return string
   */
  public function toJson(): string
  {
    return json_encode($this->toArray(), JSON_PRETTY_PRINT);
  }

  /**
   * Create message from array
   *
   * @param array $data Message data
   * @return self
   * @throws \InvalidArgumentException If data invalid
   */
  public static function fromArray(array $data): self
  {
    if (!isset($data['message_type'], $data['sender_id'], $data['recipient_id'], $data['payload'])) {
      throw new \InvalidArgumentException('Missing required message fields');
    }

    $message = new self(
      $data['message_type'],
      $data['sender_id'],
      $data['recipient_id'],
      $data['payload'],
      $data['correlation_id'] ?? null,
      $data['version'] ?? self::VERSION_CURRENT
    );

    if (isset($data['message_id'])) {
      $message->messageId = $data['message_id'];
    }

    if (isset($data['retry_count'])) {
      $message->retryCount = (int)$data['retry_count'];
    }

    if (isset($data['metadata']) && is_array($data['metadata'])) {
      $message->metadata = array_merge($message->metadata, $data['metadata']);
    }

    return $message;
  }

  /**
   * Create message from JSON
   *
   * @param string $json JSON string
   * @return self
   * @throws \InvalidArgumentException If JSON invalid
   */
  public static function fromJson(string $json): self
  {
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
    }

    return self::fromArray($data);
  }

  /**
   * Validate message structure
   *
   * @return bool
   */
  public function isValid(): bool
  {
    return !empty($this->messageId) &&
           !empty($this->messageType) &&
           !empty($this->senderId) &&
           !empty($this->recipientId) &&
           is_array($this->payload);
  }
}
