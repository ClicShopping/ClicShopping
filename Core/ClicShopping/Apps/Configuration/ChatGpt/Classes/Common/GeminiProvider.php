<?php
/**
 * Gemini Provider Implementation
 *
 * Implements LLM provider interface for Google Gemini API.
 * Supports Gemini models (Gemini Pro, Gemini Ultra, etc.).
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\AbstractLLMProvider;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\OpenAIChat;
use LLPhant\GeminiOpenAIConfig;

/**
 * Class GeminiProvider
 *
 * Google Gemini-specific implementation of the LLM provider interface.
 * Handles Gemini's request/response format.
 */
class GeminiProvider extends AbstractLLMProvider
{
  /**
   * Build API request body for Gemini
   *
   * Constructs request body in Gemini's format.
   * Gemini uses 'contents' array with 'parts' structure.
   *
   * @param string $prompt The prompt to send
   * @param array $options Optional parameters:
   *                       - 'model' => string: Override model
   *                       - 'temperature' => float: Override temperature
   *                       - 'max_tokens' => int: Override max tokens (mapped to maxOutputTokens)
   *                       - 'contents' => array: Use custom contents format
   * @return array Request body formatted for Gemini API
   */
  public function buildRequestBody(string $prompt, array $options = []): array
  {
    $body = [
      'contents' => $options['contents'] ?? [
        [
          'parts' => [
            ['text' => $prompt]
          ]
        ]
      ],
      'generationConfig' => [
        'temperature' => $options['temperature'] ?? $this->temperature,
        'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
      ],
    ];

    return $body;
  }

  /**
   * Parse Gemini API response
   *
   * Extracts content from Gemini's response format.
   * Expected format: {"candidates":[{"content":{"parts":[{"text":"..."}]}}]}
   *
   * @param string $response Raw JSON response from Gemini API
   * @return string Extracted content text
   * @throws \RuntimeException If response format is invalid
   */
  public function parseResponse(string $response): string
  {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException('Invalid JSON response from Gemini: ' . json_last_error_msg());
    }

    // Check for API error
    if (isset($data['error'])) {
      $errorMessage = $data['error']['message'] ?? 'Unknown error';
      throw new \RuntimeException('Gemini API error: ' . $errorMessage);
    }

    // Extract content from response
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
      return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    throw new \RuntimeException('Invalid Gemini response format: missing candidates[0].content.parts[0].text');
  }

  /**
   * Get API URL with model
   *
   * Gemini requires the model name in the URL path.
   * Override to append model to base URL if needed.
   *
   * @return string Complete API URL with model
   */
  public function getApiUrl(): string
  {
    // If URL already contains the model, return as-is
    if (str_contains($this->apiUrl, $this->model)) {
      return $this->apiUrl;
    }

    // Otherwise, append model to URL
    // Format: https://generativelanguage.googleapis.com/v1/models/{model}:generateContent
    $baseUrl = rtrim($this->apiUrl, '/');
    
    if (str_contains($baseUrl, ':generateContent')) {
      return $baseUrl;
    }

    return $baseUrl . '/' . $this->model . ':generateContent';
  }

  /**
   * Get LLPhant Chat instance for Gemini
   *
   * Creates and returns an OpenAIChat instance configured for Gemini.
   * Gemini uses OpenAI-compatible API through LLPhant's GeminiOpenAIConfig.
   * The URL is already set by default in GeminiOpenAIConfig.
   *
   * @return ChatInterface OpenAIChat instance configured for Gemini
   * @throws \RuntimeException If configuration is invalid
   */
  public function getLLPhantChat(): ChatInterface
  {
    // GeminiOpenAIConfig already includes the correct URL by default
    $config = new GeminiOpenAIConfig($this->apiKey);
    $config->model = $this->model;
    $config->modelOptions = $this->llphantModelOptions();

    return new OpenAIChat($config);
  }
}
