<?php
/**
 * ClicShopping AI - Message Validator
 *
 * @copyright 2024 ClicShopping
 * @license MIT
 * @version 1.0.0
 */

namespace ClicShopping\AI\Infrastructure\Communication;

/**
 * Validates messages against protocol schema
 */
class MessageValidator
{
  private array $schemas;

  public function __construct()
  {
    $this->schemas = $this->loadSchemas();
  }

  /**
   * Load message schemas for validation
   *
   * @return array
   */
  private function loadSchemas(): array
  {
    return [
      Message::TYPE_ACTION_REQUEST => [
        'required_fields' => ['action_id', 'action_type', 'parameters', 'context'],
        'optional_fields' => ['priority', 'estimated_execution_time'],
        'field_types' => [
          'action_id' => 'string',
          'action_type' => 'string',
          'parameters' => 'array',
          'context' => 'array',
          'priority' => 'string',
          'estimated_execution_time' => 'integer'
        ]
      ],
      Message::TYPE_ACTION_RESPONSE => [
        'required_fields' => ['result_id', 'action_id', 'output', 'output_type', 'status'],
        'optional_fields' => ['execution_metrics', 'execution_context'],
        'field_types' => [
          'result_id' => 'string',
          'action_id' => 'string',
          'output' => 'mixed',
          'output_type' => 'string',
          'status' => 'string',
          'execution_metrics' => 'array',
          'execution_context' => 'array'
        ]
      ],
      Message::TYPE_EVALUATION_REQUEST => [
        'required_fields' => ['result_id', 'output', 'output_type', 'producer_agent_id'],
        'optional_fields' => ['execution_context', 'execution_metrics'],
        'field_types' => [
          'result_id' => 'string',
          'output' => 'mixed',
          'output_type' => 'string',
          'producer_agent_id' => 'string',
          'execution_context' => 'array',
          'execution_metrics' => 'array'
        ]
      ],
      Message::TYPE_EVALUATION_RESPONSE => [
        'required_fields' => ['evaluation_id', 'output_id', 'scores', 'feedback'],
        'optional_fields' => ['strengths', 'improvements'],
        'field_types' => [
          'evaluation_id' => 'string',
          'output_id' => 'string',
          'scores' => 'array',
          'feedback' => 'string',
          'strengths' => 'array',
          'improvements' => 'array'
        ]
      ],
      Message::TYPE_FEEDBACK_DELIVERY => [
        'required_fields' => ['feedback_id', 'output_id', 'consensus_score', 'categorized_feedback'],
        'optional_fields' => ['strengths', 'improvements'],
        'field_types' => [
          'feedback_id' => 'string',
          'output_id' => 'string',
          'consensus_score' => 'double',
          'categorized_feedback' => 'array',
          'strengths' => 'array',
          'improvements' => 'array'
        ]
      ],
      Message::TYPE_FEEDBACK_ACKNOWLEDGMENT => [
        'required_fields' => ['feedback_id', 'acknowledged'],
        'optional_fields' => ['acknowledgment_message'],
        'field_types' => [
          'feedback_id' => 'string',
          'acknowledged' => 'boolean',
          'acknowledgment_message' => 'string'
        ]
      ],
      Message::TYPE_ERROR => [
        'required_fields' => ['error_code', 'error_message'],
        'optional_fields' => ['error_details', 'original_message_id'],
        'field_types' => [
          'error_code' => 'string',
          'error_message' => 'string',
          'error_details' => 'array',
          'original_message_id' => 'string'
        ]
      ]
    ];
  }

  /**
   * Validate message against schema
   *
   * @param Message $message Message to validate
   * @return ValidationResult
   */
  public function validate(Message $message): ValidationResult
  {
    $errors = [];

    // Basic validation
    if (!$message->isValid()) {
      $errors[] = 'Message structure is invalid';
      return new ValidationResult(false, $errors);
    }

    // Version validation
    if (!$this->isVersionSupported($message->getVersion())) {
      $errors[] = "Unsupported protocol version: {$message->getVersion()}";
    }

    // Message type validation
    $messageType = $message->getMessageType();
    if (!isset($this->schemas[$messageType])) {
      $errors[] = "Unknown message type: {$messageType}";
      return new ValidationResult(false, $errors);
    }

    // Payload validation
    $schema = $this->schemas[$messageType];
    $payload = $message->getPayload();

    // Check required fields
    foreach ($schema['required_fields'] as $field) {
      if (!isset($payload[$field])) {
        $errors[] = "Missing required field: {$field}";
      }
    }

    // Check field types
    foreach ($payload as $field => $value) {
      if (isset($schema['field_types'][$field])) {
        $expectedType = $schema['field_types'][$field];
        if (!$this->validateFieldType($value, $expectedType)) {
          $actualType = gettype($value);
          $errors[] = "Invalid type for field '{$field}': expected {$expectedType}, got {$actualType}";
        }
      }
    }

    // Validate specific message types
    $errors = array_merge($errors, $this->validateMessageTypeSpecific($message));

    return new ValidationResult(empty($errors), $errors);
  }

  /**
   * Check if protocol version is supported
   *
   * @param string $version Version string
   * @return bool
   */
  private function isVersionSupported(string $version): bool
  {
    $supportedVersions = [Message::VERSION_1_0];
    return in_array($version, $supportedVersions, true);
  }

  /**
   * Validate field type
   *
   * @param mixed $value Field value
   * @param string $expectedType Expected type
   * @return bool
   */
  private function validateFieldType(mixed $value, string $expectedType): bool
  {
    return match ($expectedType) {
      'string' => is_string($value),
      'integer' => is_int($value),
      'double', 'float' => is_float($value) || is_int($value),
      'boolean' => is_bool($value),
      'array' => is_array($value),
      'mixed' => true,
      default => false
    };
  }

  /**
   * Validate message type specific rules
   *
   * @param Message $message Message to validate
   * @return array Validation errors
   */
  private function validateMessageTypeSpecific(Message $message): array
  {
    $errors = [];
    $payload = $message->getPayload();

    switch ($message->getMessageType()) {
      case Message::TYPE_EVALUATION_RESPONSE:
        // Validate scores structure
        if (isset($payload['scores'])) {
          $requiredScores = ['accuracy', 'completeness', 'efficiency', 'clarity'];
          foreach ($requiredScores as $scoreType) {
            if (!isset($payload['scores'][$scoreType])) {
              $errors[] = "Missing required score: {$scoreType}";
            } elseif (!is_numeric($payload['scores'][$scoreType]) ||
                      $payload['scores'][$scoreType] < 0 ||
                      $payload['scores'][$scoreType] > 1) {
              $errors[] = "Invalid score value for {$scoreType}: must be between 0 and 1";
            }
          }
        }
        break;

      case Message::TYPE_ACTION_RESPONSE:
        // Validate status
        if (isset($payload['status'])) {
          $validStatuses = ['success', 'partial', 'failed'];
          if (!in_array($payload['status'], $validStatuses, true)) {
            $errors[] = "Invalid status: must be one of " . implode(', ', $validStatuses);
          }
        }
        break;

      case Message::TYPE_FEEDBACK_DELIVERY:
        // Validate consensus score
        if (isset($payload['consensus_score'])) {
          if (!is_numeric($payload['consensus_score']) ||
              $payload['consensus_score'] < 0 ||
              $payload['consensus_score'] > 1) {
            $errors[] = "Invalid consensus_score: must be between 0 and 1";
          }
        }
        break;
    }

    return $errors;
  }

  /**
   * Validate message compatibility with version
   *
   * @param Message $message Message to validate
   * @param string $targetVersion Target version
   * @return bool
   */
  public function isCompatibleWith(Message $message, string $targetVersion): bool
  {
    // For now, only 1.0 exists, so all 1.0 messages are compatible
    // In future versions, implement backward compatibility checks
    return $message->getVersion() === $targetVersion ||
           $this->isBackwardCompatible($message->getVersion(), $targetVersion);
  }

  /**
   * Check backward compatibility between versions
   *
   * @param string $messageVersion Message version
   * @param string $targetVersion Target version
   * @return bool
   */
  private function isBackwardCompatible(string $messageVersion, string $targetVersion): bool
  {
    // Implement version compatibility matrix
    // For now, only 1.0 exists
    return $messageVersion === $targetVersion;
  }
}
