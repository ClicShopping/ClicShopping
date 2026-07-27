<?php
/**
 * Anthropic Provider Implementation
 *
 * Implements LLM provider interface for Anthropic Claude API.
 * Supports Claude 3 models (Opus, Sonnet, Haiku).
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\AbstractLLMProvider;

use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\AnthropicChat;
use LLPhant\AnthropicConfig;

/**
 * Class AnthropicProvider
 *
 * Anthropic-specific implementation of the LLM provider interface.
 * Handles Anthropic's request/response format and model name mapping.
 */
class AnthropicProvider extends AbstractLLMProvider
{
  /**
   * Build API request body for Anthropic
   *
   * Constructs request body in Anthropic's format.
   * Automatically maps short model names to full API names.
   *
   * @param string $prompt The prompt to send
   * @param array $options Optional parameters:
   *                       - 'model' => string: Override model
   *                       - 'temperature' => float: Override temperature
   *                       - 'max_tokens' => int: Override max tokens
   *                       - 'messages' => array: Use custom messages format
   * @return array Request body formatted for Anthropic API
   */
  public function buildRequestBody(string $prompt, array $options = []): array
  {
    $model = $options['model'] ?? $this->model;

    return [
      'model' => $this->mapModelName($model),
      'messages' => $options['messages'] ?? [
        ['role' => 'user', 'content' => $prompt]
      ],
      'temperature' => $options['temperature'] ?? $this->temperature,
      'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
    ];
  }

  /**
   * Parse Anthropic API response
   *
   * Extracts content from Anthropic's response format.
   * Expected format: {"content":[{"text":"..."}]}
   *
   * @param string $response Raw JSON response from Anthropic API
   * @return string Extracted content text
   * @throws \RuntimeException If response format is invalid
   */
  public function parseResponse(string $response): string
  {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException('Invalid JSON response from Anthropic: ' . json_last_error_msg());
    }

    // Check for API error
    if (isset($data['error'])) {
      $errorMessage = $data['error']['message'] ?? 'Unknown error';
      throw new \RuntimeException('Anthropic API error: ' . $errorMessage);
    }

    // Extract content from response
    if (isset($data['content'][0]['text'])) {
      return $data['content'][0]['text'];
    }

    throw new \RuntimeException('Invalid Anthropic response format: missing content[0].text');
  }

  /**
   * Map model name to Anthropic API format
   *
   * Converts short model names to full API model names.
   * Examples:
   * - 'claude-3-opus' => 'claude-3-opus-20240229'
   * - 'claude-3-sonnet' => 'claude-3-sonnet-20240229'
   * - 'claude-3-haiku' => 'claude-3-haiku-20240307'
   *
   * @param string $model Short or full model name
   * @return string Full API model name
   */
  private function mapModelName(string $model): string
  {
    // Model name mapping for convenience
    $mapping = [
      'claude-3-opus' => 'claude-3-opus-20240229',
      'claude-3-sonnet' => 'claude-3-sonnet-20240229',
      'claude-3-haiku' => 'claude-3-haiku-20240307',
      'claude-3.5-sonnet' => 'claude-3-5-sonnet-20240620',
      'claude-3-5-sonnet' => 'claude-3-5-sonnet-20240620',
    ];

    // Return mapped name if exists, otherwise return original
    return $mapping[$model] ?? $model;
  }

  /**
   * Get LLPhant Chat instance for Anthropic
   *
   * Creates and returns an AnthropicChat instance configured for this provider.
   *
   * @return ChatInterface AnthropicChat instance
   * @throws \RuntimeException If configuration is invalid
   */
  public function getLLPhantChat(): ChatInterface
  {
    // AnthropicConfig is readonly: everything goes through the constructor. Assigning afterwards
    // raised "Cannot modify readonly property" and made this whole path unusable.
    $options = $this->llphantModelOptions();

    $config = new AnthropicConfig(
      model: $this->mapModelName($this->model),
      maxTokens: $options['max_tokens'] ?? 1024,
      modelOptions: array_diff_key($options, ['max_tokens' => null]),
      apiKey: $this->apiKey
    );

    return new AnthropicChat($config);
  }
}
