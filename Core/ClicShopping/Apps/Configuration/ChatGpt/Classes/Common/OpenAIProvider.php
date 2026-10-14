<?php
/**
 * OpenAI Provider Implementation
 *
 * Implements LLM provider interface for OpenAI API.
 * Supports GPT-4, GPT-4.1, GPT-5 and reasoning models (o1, o3, o4).
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\AbstractLLMProvider;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ProviderManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ModelManager;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\OpenAIChat;
use LLPhant\OpenAIConfig;

/**
 * Class OpenAIProvider
 *
 * OpenAI-specific implementation of the LLM provider interface.
 * Handles OpenAI's request/response format and special cases like reasoning models.
 */
class OpenAIProvider extends AbstractLLMProvider
{
  /**
   * Build API request body for OpenAI
   *
   * Constructs request body in OpenAI's format. Per-model parameter differences (token budget
   * spelling, temperature support) come from ModelManager, the single definition.
   *
   * @param string $prompt The prompt to send
   * @param array $options Optional parameters:
   *                       - 'model' => string: Override model
   *                       - 'temperature' => float: Override temperature
   *                       - 'max_tokens' => int: Override max tokens
   *                       - 'messages' => array: Use custom messages format
   * @return array Request body formatted for OpenAI API
   */
  public function buildRequestBody(string $prompt, array $options = []): array
  {
    $model = $options['model'] ?? $this->model;

    $body = [
      'model' => $model,
      'messages' => $options['messages'] ?? [
        ['role' => 'user', 'content' => $prompt]
      ],
    ];

    return $body + ModelManager::normalizeGenerationOptions($model, [
      'temperature' => $options['temperature'] ?? $this->temperature,
      'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
    ]);
  }

  /**
   * Parse OpenAI API response
   *
   * Extracts content from OpenAI's response format.
   * Expected format: {"choices":[{"message":{"content":"..."}}]}
   *
   * @param string $response Raw JSON response from OpenAI API
   * @return string Extracted content text
   * @throws \RuntimeException If response format is invalid
   */
  public function parseResponse(string $response): string
  {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException('Invalid JSON response from OpenAI: ' . json_last_error_msg());
    }

    // Check for API error
    if (isset($data['error'])) {
      $errorMessage = $data['error']['message'] ?? 'Unknown error';
      throw new \RuntimeException('OpenAI API error: ' . $errorMessage);
    }

    // Extract content from response
    if (isset($data['choices'][0]['message']['content'])) {
      return $data['choices'][0]['message']['content'];
    }

    throw new \RuntimeException('Invalid OpenAI response format: missing choices[0].message.content');
  }

  /**
   * Same rewrite as buildRequestBody(), from the same single definition: a model that reasons
   * rejects temperature, and gpt-4.1/gpt-5 spell the budget max_completion_tokens.
   *
   * @return array<string, mixed> Generation options in OpenAI wire format
   */
  protected function llphantModelOptions(): array
  {
    return ModelManager::normalizeGenerationOptions($this->model, parent::llphantModelOptions());
  }

  /**
   * Get LLPhant Chat instance for OpenAI
   *
   * Creates and returns an OpenAIChat instance configured for this provider.
   *
   * @return ChatInterface OpenAIChat instance
   * @throws \RuntimeException If configuration is invalid
   */
  public function getLLPhantChat():ChatInterface
  {
    $config = new OpenAIConfig();
    $config->apiKey = $this->apiKey;
    $config->model = $this->model;
    $config->modelOptions = $this->llphantModelOptions();

    // Apply the OpenAI organisation header (OpenAIConfig has no org field); no-op when unset.
    ProviderManager::applyOpenAiOrganisation($config, ModelManager::getProviderApiKey('openai')['organisation'] ?? null);

    return new OpenAIChat($config);
  }
}
