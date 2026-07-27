<?php
/**
 * Mistral Provider Implementation
 *
 * Implements LLM provider interface for Mistral AI API.
 * Supports Mistral models (Mistral Large, Mistral Medium, etc.).
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\AbstractLLMProvider;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\MistralAIChat;
use LLPhant\MistralAIConfig;

/**
 * Class MistralProvider
 *
 * Mistral-specific implementation of the LLM provider interface.
 * Uses OpenAI-compatible format for requests and responses.
 */
class MistralProvider extends AbstractLLMProvider
{
  /**
   * Build API request body for Mistral
   *
   * Constructs request body in Mistral's format (OpenAI-compatible).
   *
   * @param string $prompt The prompt to send
   * @param array $options Optional parameters:
   *                       - 'model' => string: Override model
   *                       - 'temperature' => float: Override temperature
   *                       - 'max_tokens' => int: Override max tokens
   *                       - 'messages' => array: Use custom messages format
   * @return array Request body formatted for Mistral API
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
   * Parse Mistral API response
   *
   * Extracts content from Mistral's response format.
   * Uses OpenAI-compatible format: {"choices":[{"message":{"content":"..."}}]}
   *
   * @param string $response Raw JSON response from Mistral API
   * @return string Extracted content text
   * @throws \RuntimeException If response format is invalid
   */
  public function parseResponse(string $response): string
  {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException('Invalid JSON response from Mistral: ' . json_last_error_msg());
    }

    // Check for API error
    if (isset($data['error'])) {
      $errorMessage = $data['error']['message'] ?? 'Unknown error';
      throw new \RuntimeException('Mistral API error: ' . $errorMessage);
    }

    // Extract content from response (OpenAI-compatible format)
    if (isset($data['choices'][0]['message']['content'])) {
      return $data['choices'][0]['message']['content'];
    }

    throw new \RuntimeException('Invalid Mistral response format: missing choices[0].message.content');
  }

  /**
   * Get LLPhant Chat instance for Mistral
   *
   * Creates and returns a MistralAIChat instance configured for this provider.
   *
   * @return ChatInterface MistralAIChat instance
   * @throws \RuntimeException If configuration is invalid
   */
  public function getLLPhantChat(): ChatInterface
  {
    $config = new MistralAIConfig();
    $config->apiKey = $this->apiKey;
    $config->model = $this->model;
    $config->modelOptions = $this->llphantModelOptions();

    return new MistralAIChat($config);
  }
}
