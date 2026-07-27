<?php
/**
 * LM Studio Provider Implementation
 *
 * Implements LLM provider interface for LM Studio local models.
 * LM Studio provides an OpenAI-compatible API for local models.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\AbstractLLMProvider;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\LmStudioChat;
use LLPhant\LmStudioConfig;

/**
 * Class LMStudioProvider
 *
 * LM Studio-specific implementation of the LLM provider interface.
 * Uses OpenAI-compatible format but runs locally.
 * No API key required for local LM Studio instances.
 */
class LMStudioProvider extends AbstractLLMProvider
{
  /**
   * Build API request body for LM Studio
   *
   * Constructs request body in OpenAI-compatible format.
   * LM Studio uses the same format as OpenAI for compatibility.
   *
   * @param string $prompt The prompt to send
   * @param array $options Optional parameters:
   *                       - 'model' => string: Override model
   *                       - 'temperature' => float: Override temperature
   *                       - 'max_tokens' => int: Override max tokens
   *                       - 'messages' => array: Use custom messages format
   * @return array Request body formatted for LM Studio API
   */
  public function buildRequestBody(string $prompt, array $options = []): array
  {
    return [
      'model' => $options['model'] ?? $this->model,
      'messages' => $options['messages'] ?? [
        ['role' => 'user', 'content' => $prompt]
      ],
      'temperature' => $options['temperature'] ?? $this->temperature,
      'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
    ];
  }

  /**
   * Parse LM Studio API response
   *
   * Extracts content from LM Studio's response format.
   * Uses OpenAI-compatible format: {"choices":[{"message":{"content":"..."}}]}
   *
   * @param string $response Raw JSON response from LM Studio API
   * @return string Extracted content text
   * @throws \RuntimeException If response format is invalid
   */
  public function parseResponse(string $response): string
  {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException('Invalid JSON response from LM Studio: ' . json_last_error_msg());
    }

    // Check for API error
    if (isset($data['error'])) {
      $errorMessage = $data['error']['message'] ?? 'Unknown error';
      throw new \RuntimeException('LM Studio API error: ' . $errorMessage);
    }

    // Extract content from response (OpenAI-compatible format)
    if (isset($data['choices'][0]['message']['content'])) {
      return $data['choices'][0]['message']['content'];
    }

    throw new \RuntimeException('Invalid LM Studio response format: missing choices[0].message.content');
  }

  /**
   * Validate provider configuration
   *
   * Overrides parent validation to skip API key requirement.
   * LM Studio runs locally and doesn't require API keys.
   *
   * @return bool True if configuration is valid
   * @throws \InvalidArgumentException If configuration is invalid
   */
  public function validateConfig(): bool
  {
    // Validate base configuration (name, url, model)
    if (empty($this->name)) {
      throw new \InvalidArgumentException('Provider name is required');
    }

    if (empty($this->apiUrl)) {
      throw new \InvalidArgumentException('API URL is required');
    }

    if (empty($this->model)) {
      throw new \InvalidArgumentException('Model name is required');
    }

    if ($this->timeout <= 0) {
      throw new \InvalidArgumentException('Timeout must be greater than 0');
    }

    if ($this->maxTokens <= 0) {
      throw new \InvalidArgumentException('Max tokens must be greater than 0');
    }

    if ($this->temperature < 0.0 || $this->temperature > 2.0) {
      throw new \InvalidArgumentException('Temperature must be between 0.0 and 2.0');
    }

    // Note: API key is NOT required for LM Studio
    return true;
  }

  /**
   * Get LLPhant Chat instance for LM Studio
   *
   * Creates and returns an LmStudioChat instance configured for this provider.
   *
   * @return ChatInterface LmStudioChat instance
   * @throws \RuntimeException If configuration is invalid
   */
  public function getLLPhantChat(): ChatInterface
  {
    $config = new LmStudioConfig();
    $config->model = $this->model;
    
    // LmStudioChat adds '/v1/chat/completions' to the base URL automatically
    // So we need to strip it if it's already present in our apiUrl
    $baseUrl = $this->apiUrl;
    if (str_ends_with($baseUrl, '/v1/chat/completions')) {
      $baseUrl = substr($baseUrl, 0, -strlen('/v1/chat/completions'));
    }
    $config->url = $baseUrl;
    $config->modelOptions = $this->llphantModelOptions();

    return new LmStudioChat($config);
  }
}
