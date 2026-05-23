<?php
/**
 * UserInputRequiredResponse.php
 *
 * Response class for indicating that user input is required before proceeding.
 * Used for multi-turn conversations where the system needs user choice/confirmation.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Response
 * @since 2026-05-06
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Response;

/**
 * UserInputRequiredResponse Class
 *
 * Represents a response that requires user input before the system can proceed.
 * This enables multi-turn conversations in the chat interface.
 *
 * Example usage:
 * ```php
 * $response = new UserInputRequiredResponse(
 *   'mode_selection',
 *   'How would you like to analyze prices?',
 *   [
 *     ['value' => '1', 'label' => 'WebSearch', 'description' => '...'],
 *     ['value' => '2', 'label' => 'Google Shopping', 'description' => '...', 'recommended' => true],
 *     ['value' => '3', 'label' => 'Both', 'description' => '...']
 *   ],
 *   ['query' => 'compare prices...', 'intent' => 'price_comparison']
 * );
 * ```
 */
class UserInputRequiredResponse
{
  /**
   * @var string Type of input required (e.g., 'mode_selection', 'confirmation')
   */
  private string $inputType;

  /**
   * @var string Prompt message to display to user
   */
  private string $prompt;

  /**
   * @var array Options for user to choose from
   */
  private array $options;

  /**
   * @var array Context data to preserve for next turn
   */
  private array $context;

  /**
   * @var string|null Unique context ID for resuming conversation
   */
  private ?string $contextId;

  /**
   * Constructor
   *
   * @param string $inputType Type of input required
   * @param string $prompt Prompt message
   * @param array $options Array of options
   * @param array $context Context data to preserve
   * @param string|null $contextId Optional context ID (auto-generated if null)
   */
  public function __construct(
    string $inputType,
    string $prompt,
    array $options,
    array $context = [],
    ?string $contextId = null
  ) {
    $this->inputType = $inputType;
    $this->prompt = $prompt;
    $this->options = $options;
    $this->context = $context;
    $this->contextId = $contextId ?? $this->generateContextId();
  }

  /**
   * Generate unique context ID
   *
   * @return string Context ID
   */
  private function generateContextId(): string
  {
    return uniqid($this->inputType . '_', true);
  }

  /**
   * Get input type
   *
   * @return string Input type
   */
  public function getInputType(): string
  {
    return $this->inputType;
  }

  /**
   * Get prompt message
   *
   * @return string Prompt
   */
  public function getPrompt(): string
  {
    return $this->prompt;
  }

  /**
   * Get options
   *
   * @return array Options
   */
  public function getOptions(): array
  {
    return $this->options;
  }

  /**
   * Get context data
   *
   * @return array Context
   */
  public function getContext(): array
  {
    return $this->context;
  }

  /**
   * Get context ID
   *
   * @return string Context ID
   */
  public function getContextId(): string
  {
    return $this->contextId;
  }

  /**
   * Convert to array for JSON serialization
   *
   * @return array Response array
   */
  public function toArray(): array
  {
    return [
      'type' => 'user_input_required',
      'input_type' => $this->inputType,
      'prompt' => $this->prompt,
      'options' => $this->options,
      'context_id' => $this->contextId,
      'context' => $this->context
    ];
  }

  /**
   * Convert to JSON
   *
   * @return string JSON representation
   */
  public function toJson(): string
  {
    return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  /**
   * Check if this is a user input required response
   *
   * @param mixed $response Response to check
   * @return bool True if user input required
   */
  public static function isUserInputRequired($response): bool
  {
    if ($response instanceof self) {
      return true;
    }

    if (is_array($response) && isset($response['type'])) {
      return $response['type'] === 'user_input_required';
    }

    return false;
  }
}
