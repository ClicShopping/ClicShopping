<?php
/**
 * LLM Provider Interface
 *
 * Defines the contract for all LLM provider implementations.
 * This interface ensures consistent behavior across different LLM services
 * (OpenAI, Anthropic, Ollama, LM Studio, Mistral).
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Common
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use LLPhant\Chat\ChatInterface;

/**
 * Interface LLMProviderInterface
 *
 * Standard interface for LLM provider implementations.
 * All providers must implement these methods to ensure compatibility
 * with the provider registry and factory system.
 */
interface LLMProviderInterface
{
  /**
   * Get provider name
   *
   * Returns the unique identifier for this provider.
   * Examples: 'openai', 'anthropic', 'ollama', 'lmstudio', 'mistral'
   *
   * @return string Provider name in lowercase
   */
  public function getName(): string;

  /**
   * Get API base URL
   *
   * Returns the base URL for API requests to this provider.
   * Example: 'https://api.openai.com/v1/chat/completions'
   *
   * @return string API base URL
   */
  public function getApiUrl(): string;

  /**
   * Get API key
   *
   * Returns the API key for authentication, or null if not required.
   * Some providers (Ollama, LM Studio) don't require API keys.
   *
   * @return string|null API key or null if not required
   */
  public function getApiKey(): ?string;

  /**
   * Get model name
   *
   * Returns the model/engine name to use for requests.
   * Examples: 'gpt-4', 'claude-3-opus', 'llama2'
   *
   * @return string Model name
   */
  public function getModel(): string;

  /**
   * Get request timeout
   *
   * Returns the timeout in seconds for API requests.
   * Default is typically 30 seconds.
   *
   * @return int Timeout in seconds
   */
  public function getTimeout(): int;

  /**
   * Get maximum tokens
   *
   * Returns the maximum number of tokens for response generation.
   * Default is typically 4096 tokens.
   *
   * @return int Maximum tokens
   */
  public function getMaxTokens(): int;

  /**
   * Get temperature
   *
   * Returns the temperature setting for response generation.
   * Range: 0.0 (deterministic) to 2.0 (creative)
   * Default is typically 0.7
   *
   * @return float Temperature value
   */
  public function getTemperature(): float;

  /**
   * Build API request body
   *
   * Constructs the request body for the provider's API format.
   * Each provider has different request formats, so this method
   * handles provider-specific formatting.
   *
   * @param string $prompt The prompt/question to send to the LLM
   * @param array $options Optional parameters to override defaults
   *                       - 'temperature' => float
   *                       - 'max_tokens' => int
   *                       - 'model' => string
   * @return array Request body formatted for the provider's API
   */
  public function buildRequestBody(string $prompt, array $options = []): array;

  /**
   * Parse API response
   *
   * Extracts the content from the provider's API response.
   * Each provider has different response formats, so this method
   * handles provider-specific parsing.
   *
   * @param string $response Raw JSON response from the API
   * @return string Extracted content/text from the response
   * @throws \RuntimeException If response format is invalid
   */
  public function parseResponse(string $response): string;

  /**
   * Get provider capabilities
   *
   * Returns information about what features this provider supports.
   * Capabilities include:
   * - 'embeddings' => bool: Supports vector embeddings
   * - 'reasoning' => bool: Supports reasoning/chain-of-thought
   * - 'context_length' => int: Maximum context window size
   *
   * @return array Associative array of capabilities
   */
  public function getCapabilities(): array;

  /**
   * Validate provider configuration
   *
   * Checks if the provider configuration is valid and complete.
   * Throws an exception if configuration is invalid.
   *
   * @return bool True if configuration is valid
   * @throws \InvalidArgumentException If configuration is invalid
   */
  public function validateConfig(): bool;

  /**
   * Get LLPhant Chat instance
   *
   * Returns the LLPhant Chat instance for this provider.
   * This allows direct use of LLPhant's functionality.
   *
   * @return \LLPhant\Chat\ChatInterface LLPhant Chat instance
   * @throws \RuntimeException If LLPhant Chat cannot be created
   */
  public function getLLPhantChat(): ChatInterface;
}
