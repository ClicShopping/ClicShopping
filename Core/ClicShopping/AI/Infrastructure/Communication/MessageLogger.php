<?php
/**
 * ClicShopping AI - Message Logger
 *
 * @copyright 2024 ClicShopping
 * @license MIT
 */

namespace ClicShopping\AI\Infrastructure\Communication;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Logs and traces actor-critic messages
 */
class MessageLogger
{
  private \ClicShopping\OM\Db $db;
  private string $logFile;
  private bool $enableDatabaseLogging;
  private bool $enableFileLogging;

  /**
   * Constructor
   *
   * @param bool $enableDatabaseLogging Enable database logging
   * @param bool $enableFileLogging Enable file logging
   */
  public function __construct(
    bool $enableDatabaseLogging = true,
    bool $enableFileLogging = true
  ) {
    $this->db = Registry::get('Db');
    $this->logFile = CLICSHOPPING::getWorkDirectory('Log') . '/actor_critic_messages.log';
    $this->enableDatabaseLogging = $enableDatabaseLogging;
    $this->enableFileLogging = $enableFileLogging;
  }

  /**
   * Log message sent
   *
   * @param Message $message Message sent
   * @return void
   */
  public function logMessageSent(Message $message): void
  {
    $logData = [
      'event' => 'message_sent',
      'message_id' => $message->getMessageId(),
      'message_type' => $message->getMessageType(),
      'sender_id' => $message->getSenderId(),
      'recipient_id' => $message->getRecipientId(),
      'correlation_id' => $message->getCorrelationId(),
      'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s.u')
    ];

    $this->log($logData);

    if ($this->enableDatabaseLogging) {
      $this->logToDatabase($message, 'sent');
    }
  }

  /**
   * Log message delivered
   *
   * @param Message $message Message delivered
   * @return void
   */
  public function logMessageDelivered(Message $message): void
  {
    $logData = [
      'event' => 'message_delivered',
      'message_id' => $message->getMessageId(),
      'message_type' => $message->getMessageType(),
      'sender_id' => $message->getSenderId(),
      'recipient_id' => $message->getRecipientId(),
      'correlation_id' => $message->getCorrelationId(),
      'retry_count' => $message->getRetryCount(),
      'timestamp' => date('Y-m-d H:i:s.u')
    ];

    $this->log($logData);

    if ($this->enableDatabaseLogging) {
      $this->updateDatabaseLog($message->getMessageId(), 'delivered');
    }
  }

  /**
   * Log message failed
   *
   * @param Message $message Message that failed
   * @param \Exception $error Error
   * @return void
   */
  public function logMessageFailed(Message $message, \Exception $error): void
  {
    $logData = [
      'event' => 'message_failed',
      'message_id' => $message->getMessageId(),
      'message_type' => $message->getMessageType(),
      'sender_id' => $message->getSenderId(),
      'recipient_id' => $message->getRecipientId(),
      'error' => $error->getMessage(),
      'error_trace' => $error->getTraceAsString(),
      'retry_count' => $message->getRetryCount(),
      'timestamp' => date('Y-m-d H:i:s.u')
    ];

    $this->log($logData, 'ERROR');

    if ($this->enableDatabaseLogging) {
      $this->updateDatabaseLog($message->getMessageId(), 'failed', $error->getMessage());
    }
  }

  /**
   * Log message retry
   *
   * @param Message $message Message being retried
   * @param int $attemptNumber Attempt number
   * @return void
   */
  public function logMessageRetry(Message $message, int $attemptNumber): void
  {
    $logData = [
      'event' => 'message_retry',
      'message_id' => $message->getMessageId(),
      'message_type' => $message->getMessageType(),
      'attempt_number' => $attemptNumber,
      'retry_count' => $message->getRetryCount(),
      'timestamp' => date('Y-m-d H:i:s.u')
    ];

    $this->log($logData, 'WARNING');
  }

  /**
   * Log max retries exceeded
   *
   * @param Message $message Message that exceeded retries
   * @return void
   */
  public function logMessageMaxRetriesExceeded(Message $message): void
  {
    $logData = [
      'event' => 'max_retries_exceeded',
      'message_id' => $message->getMessageId(),
      'message_type' => $message->getMessageType(),
      'sender_id' => $message->getSenderId(),
      'recipient_id' => $message->getRecipientId(),
      'retry_count' => $message->getRetryCount(),
      'timestamp' => date('Y-m-d H:i:s.u')
    ];

    $this->log($logData, 'ERROR');

    if ($this->enableDatabaseLogging) {
      $this->updateDatabaseLog($message->getMessageId(), 'max_retries_exceeded');
    }
  }

  /**
   * Log validation failure
   *
   * @param Message $message Message that failed validation
   * @param ValidationResult $result Validation result
   * @return void
   */
  public function logValidationFailure(Message $message, ValidationResult $result): void
  {
    $logData = [
      'event' => 'validation_failed',
      'message_id' => $message->getMessageId(),
      'message_type' => $message->getMessageType(),
      'errors' => $result->getErrors(),
      'timestamp' => date('Y-m-d H:i:s.u')
    ];

    $this->log($logData, 'ERROR');
  }

  /**
   * Log to file
   *
   * @param array $data Log data
   * @param string $level Log level
   * @return void
   */
  private function log(array $data, string $level = 'INFO'): void
  {
    if (!$this->enableFileLogging) {
      return;
    }

    $logEntry = sprintf(
      "[%s] [%s] %s\n",
      date('Y-m-d H:i:s.u'),
      $level,
      json_encode($data, JSON_UNESCAPED_SLASHES)
    );

    file_put_contents($this->logFile, $logEntry, FILE_APPEND);
  }

  /**
   * Log to database
   *
   * @param Message $message Message to log
   * @param string $status Message status
   * @return void
   */
  private function logToDatabase(Message $message, string $status): void
  {
    try {
      $sql = "INSERT INTO :table_rag_agent_actor_critic_messages 
              (message_id, message_type, version, sender_id, recipient_id, 
               correlation_id, payload, metadata, status, retry_count, created_at)
              VALUES 
              (:message_id, :message_type, :version, :sender_id, :recipient_id,
               :correlation_id, :payload, :metadata, :status, :retry_count, NOW())";

      $this->db->prepare($sql);
      $this->db->bindValue(':message_id', $message->getMessageId());
      $this->db->bindValue(':message_type', $message->getMessageType());
      $this->db->bindValue(':version', $message->getVersion());
      $this->db->bindValue(':sender_id', $message->getSenderId());
      $this->db->bindValue(':recipient_id', $message->getRecipientId());
      $this->db->bindValue(':correlation_id', $message->getCorrelationId());
      $this->db->bindValue(':payload', json_encode($message->getPayload()));
      $this->db->bindValue(':metadata', json_encode($message->getMetadata()));
      $this->db->bindValue(':status', $status);
      $this->db->bindValue(':retry_count', $message->getRetryCount());
      $this->db->execute();
    } catch (\Exception $e) {
      // Log database error to file
      $this->log([
        'event' => 'database_logging_failed',
        'error' => $e->getMessage()
      ], 'ERROR');
    }
  }

  /**
   * Update database log
   *
   * @param string $messageId Message ID
   * @param string $status New status
   * @param string|null $errorMessage Error message if failed
   * @return void
   */
  private function updateDatabaseLog(
    string $messageId,
    string $status,
    ?string $errorMessage = null
  ): void {
    try {
      $sql = "UPDATE :table_rag_agent_actor_critic_messages 
              SET status = :status, 
                  error_message = :error_message,
                  updated_at = NOW()
              WHERE message_id = :message_id";

      $this->db->prepare($sql);
      $this->db->bindValue(':status', $status);
      $this->db->bindValue(':error_message', $errorMessage);
      $this->db->bindValue(':message_id', $messageId);
      $this->db->execute();
    } catch (\Exception $e) {
      // Log database error to file
      $this->log([
        'event' => 'database_update_failed',
        'error' => $e->getMessage()
      ], 'ERROR');
    }
  }

  /**
   * Get message trace
   *
   * @param string $messageId Message ID
   * @return array Message trace
   */
  public function getMessageTrace(string $messageId): array
  {
    if (!$this->enableDatabaseLogging) {
      return [];
    }

    try {
      $sql = "SELECT * FROM :table_rag_agent_actor_critic_messages 
              WHERE message_id = :message_id 
              ORDER BY created_at ASC";

      $result = $this->db->prepare($sql);
      $result->bindValue(':message_id', $messageId);
      $result->execute();

      return $result->fetchAll();
    } catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Get message statistics
   *
   * @param int $hours Hours to look back
   * @return array Statistics
   */
  public function getStatistics(int $hours = 24): array
  {
    if (!$this->enableDatabaseLogging) {
      return [];
    }

    try {
      $sql = "SELECT 
                message_type,
                status,
                COUNT(*) as count,
                AVG(retry_count) as avg_retries,
                MAX(retry_count) as max_retries
              FROM :table_rag_agent_actor_critic_messages
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
              GROUP BY message_type, status";

      $result = $this->db->prepare($sql);
      $result->bindInt(':hours', $hours);
      $result->execute();

      return $result->fetchAll();
    } catch (\Exception $e) {
      return [];
    }
  }
}
