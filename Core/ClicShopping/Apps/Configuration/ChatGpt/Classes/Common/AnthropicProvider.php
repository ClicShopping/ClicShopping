<?php
/**
 * Anthropic Provider Implementation
 *
 * Implements LLM provider interface for Anthropic Claude API.
 * Supports the current Claude catalog (Opus 5, Sonnet 5, Haiku 4.5).
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\AbstractLLMProvider;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ModelManager;

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
    $model = $this->mapModelName($options['model'] ?? $this->model);

    $body = [
      'model' => $model,
      'messages' => $options['messages'] ?? [
        ['role' => 'user', 'content' => $prompt]
      ],
      'temperature' => $options['temperature'] ?? $this->temperature,
      'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
    ];

    return ModelManager::normalizeAnthropicOptions($model, $body);
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

    // A safety decline is an HTTP 200 with no usable content; check it before reading content[0].
    if (($data['stop_reason'] ?? null) === 'refusal') {
      throw new \RuntimeException('Anthropic refused the request: ' . ($data['stop_details']['category'] ?? 'unknown'));
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
   * Delegates to the catalog chokepoint so there is ONE alias table.
   * Example: 'anth-opus' => 'claude-opus-5'.
   *
   * @param string $model Short or full model name
   * @return string Full API model name
   */
  private function mapModelName(string $model): string
  {
    return ModelManager::mapAnthropicModelName($model);
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
    $model = $this->mapModelName($this->model);
    $options = ModelManager::normalizeAnthropicOptions($model, $this->llphantModelOptions());

    $config = new AnthropicConfig(
      model: $model,
      maxTokens: $options['max_tokens'] ?? 1024,
      modelOptions: array_diff_key($options, ['max_tokens' => null]),
      apiKey: $this->apiKey
    );

    return new AnthropicChat($config);
  }
}
