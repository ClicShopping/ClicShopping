<?php
/**
 * Abstract LLM Provider Base Class
 *
 * Provides common functionality for all LLM provider implementations.
 * Concrete providers extend this class and implement provider-specific logic.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Common
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use LLPhant\Chat\ChatInterface;

/**
 * Class AbstractLLMProvider
 *
 * Base class implementing common functionality for LLM providers.
 * Handles configuration management, validation, and getter methods.
 * Concrete providers must implement buildRequestBody() and parseResponse().
 */
abstract class AbstractLLMProvider implements LLMProviderInterface
{
  /**
   * @var string Provider name (e.g., 'openai', 'anthropic')
   */
  protected string $name;

  /**
   * @var string API base URL
   */
  protected string $apiUrl;

  /**
   * @var string|null API key for authentication (null if not required)
   */
  protected ?string $apiKey;

  /**
   * @var string Model/engine name
   */
  protected string $model;

  /**
   * @var int Request timeout in seconds
   */
  protected int $timeout;

  /**
   * @var int Maximum tokens for response
   */
  protected int $maxTokens;

  /**
   * @var float Temperature for response generation (0.0 - 2.0)
   */
  protected float $temperature;

  /**
   * @var array Provider capabilities
   */
  protected array $capabilities;

  /**
   * @var array<int, string> Config keys the caller asked for. Only these reach LLPhant, so an
   *                         inert configured default does not start capping every answer.
   */
  protected array $explicitOptions = [];

  /**
   * Constructor
   *
   * Initializes the provider with configuration.
   * Validates configuration after initialization.
   *
   * @param array $config Configuration array with keys:
   *                      - 'name' => string (required)
   *                      - 'api_url' => string (required)
   *                      - 'api_key' => string|null (optional)
   *                      - 'model' => string (required)
   *                      - 'timeout' => int (default: 30)
   *                      - 'max_tokens' => int (default: 4096)
   *                      - 'temperature' => float (default: 0.7)
   *                      - 'capabilities' => array (default: [])
   * @throws \InvalidArgumentException If configuration is invalid
   */
  public function __construct(array $config)
  {
    $this->name = $config['name'] ?? '';
    $this->apiUrl = $config['api_url'] ?? '';
    $this->apiKey = $config['api_key'] ?? null;
    $this->model = $config['model'] ?? '';
    $this->timeout = $config['timeout'] ?? 30;
    $this->maxTokens = $config['max_tokens'] ?? 4096;
    $this->temperature = $config['temperature'] ?? 0.7;
    $this->capabilities = $config['capabilities'] ?? [];
    $this->explicitOptions = $config['explicit_options'] ?? [];

    $this->validateConfig();
  }

  /**
   * Did the caller ask for this option, rather than inheriting a configured default?
   *
   * @param string $key Config key, as passed to LLMProviderFactory::create()
   * @return bool True when the caller supplied it
   */
  protected function isExplicit(string $key): bool
  {
    return in_array($key, $this->explicitOptions, true);
  }

  /**
   * Generation options handed to LLPhant, in OpenAI wire format. LLPhant spreads them into the
   * request body, so Anthropic and Ollama override this with their own keys.
   *
   * @return array<string, mixed> Empty when the caller bounded nothing
   */
  protected function llphantModelOptions(): array
  {
    $options = [];

    if ($this->isExplicit('max_tokens') && $this->maxTokens > 0) {
      $options['max_tokens'] = $this->maxTokens;
    }

    if ($this->isExplicit('temperature')) {
      $options['temperature'] = $this->temperature;
    }

    return $options;
  }

  /**
   * Get provider name
   *
   * @return string Provider name
   */
  public function getName(): string
  {
    return $this->name;
  }

  /**
   * Get API base URL
   *
   * @return string API URL
   */
  public function getApiUrl(): string
  {
    return $this->apiUrl;
  }

  /**
   * Get API key
   *
   * @return string|null API key or null if not required
   */
  public function getApiKey(): ?string
  {
    return $this->apiKey;
  }

  /**
   * Get model name
   *
   * @return string Model name
   */
  public function getModel(): string
  {
    return $this->model;
  }

  /**
   * Get request timeout
   *
   * @return int Timeout in seconds
   */
  public function getTimeout(): int
  {
    return $this->timeout;
  }

  /**
   * Get maximum tokens
   *
   * @return int Maximum tokens
   */
  public function getMaxTokens(): int
  {
    return $this->maxTokens;
  }

  /**
   * Get temperature
   *
   * @return float Temperature value
   */
  public function getTemperature(): float
  {
    return $this->temperature;
  }

  /**
   * Get provider capabilities
   *
   * @return array Capabilities array
   */
  public function getCapabilities(): array
  {
    return $this->capabilities;
  }

  /**
   * Validate provider configuration
   *
   * Checks that required configuration values are present.
   * Subclasses can override this method to add provider-specific validation.
   *
   * @return bool True if configuration is valid
   * @throws \InvalidArgumentException If configuration is invalid
   */
  public function validateConfig(): bool
  {
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

    return true;
  }

  /**
   * Build API request body
   *
   * Abstract method to be implemented by concrete providers.
   * Each provider has different request formats.
   *
   * @param string $prompt The prompt to send
   * @param array $options Additional options
   * @return array Request body for API call
   */
  abstract public function buildRequestBody(string $prompt, array $options = []): array;

  /**
   * Parse API response
   *
   * Abstract method to be implemented by concrete providers.
   * Each provider has different response formats.
   *
   * @param string $response Raw API response
   * @return string Extracted content
   */
  abstract public function parseResponse(string $response): string;

  /**
   * Get LLPhant Chat instance
   *
   * Abstract method to be implemented by concrete providers.
   * Each provider creates its specific LLPhant Chat instance.
   *
   * @return \LLPhant\Chat\ChatInterface LLPhant Chat instance
   */
  abstract public function getLLPhantChat(): ChatInterface;
}
