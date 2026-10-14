<?php
/**
 * Ollama Provider Implementation
 *
 * Implements LLM provider interface for Ollama local models.
 * Supports local LLM models like Llama 2, Mistral, etc.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\AbstractLLMProvider;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\OllamaChat;
use LLPhant\OllamaConfig;

/**
 * Class OllamaProvider
 *
 * Ollama-specific implementation of the LLM provider interface.
 * Handles Ollama's request/response format (uses 'prompt' instead of 'messages').
 * No API key required for local Ollama instances.
 */
class OllamaProvider extends AbstractLLMProvider
{
  /**
   * Build API request body for Ollama
   *
   * Constructs request body in Ollama's format.
   * Note: Ollama uses 'prompt' field instead of 'messages' array.
   *
   * @param string $prompt The prompt to send
   * @param array $options Optional parameters:
   *                       - 'model' => string: Override model
   *                       - 'temperature' => float: Override temperature
   *                       - 'max_tokens' => int: Override max tokens (mapped to num_predict)
   *                       - 'stream' => bool: Enable streaming (default: false)
   * @return array Request body formatted for Ollama API
   */
  public function buildRequestBody(string $prompt, array $options = []): array
  {
    return [
      'model' => $options['model'] ?? $this->model,
      'prompt' => $prompt,
      'temperature' => $options['temperature'] ?? $this->temperature,
      'options' => [
        'num_predict' => $options['max_tokens'] ?? $this->maxTokens,
      ],
      'stream' => $options['stream'] ?? false,
    ];
  }

  /**
   * Parse Ollama API response
   *
   * Extracts content from Ollama's response format.
   * Expected format: {"response":"..."}
   *
   * @param string $response Raw JSON response from Ollama API
   * @return string Extracted content text
   * @throws \RuntimeException If response format is invalid
   */
  public function parseResponse(string $response): string
  {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException('Invalid JSON response from Ollama: ' . json_last_error_msg());
    }

    // Check for API error
    if (isset($data['error'])) {
      $errorMessage = is_string($data['error']) ? $data['error'] : ($data['error']['message'] ?? 'Unknown error');
      throw new \RuntimeException('Ollama API error: ' . $errorMessage);
    }

    // Extract content from response
    if (isset($data['response'])) {
      return $data['response'];
    }

    throw new \RuntimeException('Invalid Ollama response format: missing response field');
  }

  /**
   * Validate provider configuration
   *
   * Overrides parent validation to skip API key requirement.
   * Ollama runs locally and doesn't require API keys.
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

    // Note: API key is NOT required for Ollama
    return true;
  }

  /**
   * Get LLPhant Chat instance for Ollama
   *
   * Creates and returns an OllamaChat instance configured for this provider.
   *
   * @return ChatInterface OllamaChat instance
   * @throws \RuntimeException If configuration is invalid
   */
  public function getLLPhantChat(): ChatInterface
  {
    $config = new OllamaConfig();
    $config->model = $this->model;
    $config->url = $this->apiUrl;
    $config->modelOptions = $this->llphantModelOptions();

    return new OllamaChat($config);
  }

  /**
   * Ollama does not speak the OpenAI wire format: generation settings live under `options`,
   * and the output budget is `num_predict`. Sending max_tokens would simply be ignored.
   *
   * @return array<string, mixed> Generation options in Ollama wire format
   */
  protected function llphantModelOptions(): array
  {
    $standard = parent::llphantModelOptions();
    $options = [];

    if (isset($standard['max_tokens'])) {
      $options['num_predict'] = $standard['max_tokens'];
    }

    if (isset($standard['temperature'])) {
      $options['temperature'] = $standard['temperature'];
    }

    return $options === [] ? [] : ['options' => $options];
  }
}
