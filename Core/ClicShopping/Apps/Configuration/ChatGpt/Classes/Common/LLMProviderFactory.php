<?php
/**
 * LLM Provider Factory
 *
 * Factory for creating LLM provider instances with proper configuration.
 * Implements singleton pattern and supports both database and constant configuration.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Common
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\LLMProviderRegistry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\LLMProviderConfig;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\ProviderNotFoundException;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\OpenAIProvider;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\AnthropicProvider;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\OllamaProvider;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\LMStudioProvider;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\MistralProvider;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\GeminiProvider;

/**
 * Class LLMProviderFactory
 *
 * Factory for creating configured LLM provider instances.
 * Handles configuration loading from database or PHP constants.
 *
 * Usage:
 * <code>
 * $factory = LLMProviderFactory::getInstance();
 * $provider = $factory->create('openai');
 * $provider = $factory->create('anthropic', ['model' => 'claude-3-opus']);
 * </code>
 */
class LLMProviderFactory
{
  /**
   * @var LLMProviderFactory|null Singleton instance
   */
  private static ?LLMProviderFactory $instance = null;

  /**
   * @var LLMProviderRegistry Provider registry
   */
  private LLMProviderRegistry $registry;

  /**
   * @var LLMProviderConfig Configuration manager
   */
  private LLMProviderConfig $config;

  /**
   * Private constructor (singleton pattern)
   */
  private function __construct()
  {
    $this->registry = LLMProviderRegistry::getInstance();
    $this->config = LLMProviderConfig::getInstance();
  }

  /**
   * Get singleton instance
   *
   * @return LLMProviderFactory
   */
  public static function getInstance(): LLMProviderFactory
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Create a provider instance with configuration
   *
   * @param string $providerName Provider name (openai, anthropic, ollama, lmstudio, mistral)
   * @param array $overrides Optional configuration overrides
   * @return object Provider instance
   * @throws ProviderNotFoundException If provider not found
   */
  public function create(string $providerName, array $overrides = []): object
  {
    // Get provider class from registry
    $providerClass = $this->getProviderClass($providerName);

    // Load configuration for this provider
    $config = $this->loadConfig($providerName, $overrides);

    // Instantiate provider with configuration
    return new $providerClass($config);
  }

  /**
   * Get provider class name
   *
   * @param string $providerName Provider name
   * @return string Provider class name
   * @throws ProviderNotFoundException If provider not found
   */
  private function getProviderClass(string $providerName): string
  {
    // Map provider names to class names (using imported classes)
    $providerMap = [
      'openai' => OpenAIProvider::class,
      'anthropic' => AnthropicProvider::class,
      'ollama' => OllamaProvider::class,
      'lmstudio' => LMStudioProvider::class,
      'mistral' => MistralProvider::class,
      'gemini' => GeminiProvider::class,
    ];

    if (!isset($providerMap[$providerName])) {
      throw new ProviderNotFoundException("Unknown provider: {$providerName}");
    }

    return $providerMap[$providerName];
  }

  /**
   * Create provider from configuration array
   *
   * @param array $config Configuration array with 'provider' key
   * @return object Provider instance
   * @throws ProviderNotFoundException If provider not found
   */
  public function createFromConfig(array $config): object
  {
    if (!isset($config['provider'])) {
      throw new \InvalidArgumentException('Configuration must include "provider" key');
    }

    $providerName = $config['provider'];
    unset($config['provider']);

    return $this->create($providerName, $config);
  }

  /**
   * Load configuration for a provider
   *
   * Priority: overrides > database > constants
   *
   * @param string $providerName Provider name
   * @param array $overrides Configuration overrides
   * @return array Configuration array
   */
  private function loadConfig(string $providerName, array $overrides = []): array
  {
    // Start with default configuration from constants
    $config = $this->getDefaultConfig($providerName);

    // Override with database configuration if available
    $dbConfig = $this->config->get($providerName);
    if (!empty($dbConfig)) {
      $config = array_merge($config, $dbConfig);
    }

    // Apply overrides
    if (!empty($overrides)) {
      $config = array_merge($config, $overrides);
    }

    return $config;
  }

  /**
   * Get default configuration from PHP constants
   *
   * @param string $providerName Provider name
   * @return array Default configuration
   */
  private function getDefaultConfig(string $providerName): array
  {
    $config = [
      'name' => $providerName,  // Fixed: was 'provider', should be 'name'
    ];

    // Provider-specific defaults from constants
    switch ($providerName) {
      case 'openai':
        $config['api_key'] = defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY') ? CLICSHOPPING_APP_CHATGPT_CH_API_KEY : '';
        $config['model'] = defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL') ? CLICSHOPPING_APP_CHATGPT_CH_MODEL : 'gpt-5-mini';
        $config['temperature'] = defined('CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE') ? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE : 0.7;
        $config['max_tokens'] = defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 4000;
        $config['api_url'] = 'https://api.openai.com/v1/chat/completions';
        break;

      case 'anthropic':
        $config['api_key'] = defined('CLICSHOPPING_APP_CHATGPT_ANTHROPIC_API_KEY') ? CLICSHOPPING_APP_CHATGPT_ANTHROPIC_API_KEY : '';
        $config['model'] = defined('CLICSHOPPING_APP_CHATGPT_ANTHROPIC_MODEL') ? CLICSHOPPING_APP_CHATGPT_ANTHROPIC_MODEL : 'claude-sonnet-4-6';
        $config['temperature'] = defined('CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE') ? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE : 0.7;
        $config['max_tokens'] = defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 4000;
        $config['api_url'] = 'https://api.anthropic.com/v1/messages';
        break;

      case 'ollama':
        $config['api_key'] = ''; // Ollama doesn't require API key
        $config['model'] = defined('CLICSHOPPING_APP_CHATGPT_OLLAMA_MODEL') ? CLICSHOPPING_APP_CHATGPT_OLLAMA_MODEL : 'llama2';
        $config['temperature'] = defined('CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE') ? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE : 0.7;
        $config['max_tokens'] = defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 4000;
        $config['api_url'] = defined('CLICSHOPPING_APP_CHATGPT_OLLAMA_URL') ? CLICSHOPPING_APP_CHATGPT_OLLAMA_URL . '/api/generate' : 'http://localhost:11434/api/generate';
        break;

      case 'lmstudio':
        $config['api_key'] = ''; // LM Studio doesn't require API key
        $config['model'] = defined('CLICSHOPPING_APP_CHATGPT_LMSTUDIO_MODEL') ? CLICSHOPPING_APP_CHATGPT_LMSTUDIO_MODEL : 'local-model';
        $config['temperature'] = defined('CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE') ? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE : 0.7;
        $config['max_tokens'] = defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 4000;
        // Check if URL already includes the endpoint path
        if (defined('CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL')) {
          $baseUrl = CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL;
          // Only append /v1/chat/completions if not already present
          $config['api_url'] = str_ends_with($baseUrl, '/v1/chat/completions') ? $baseUrl : $baseUrl . '/v1/chat/completions';
        } else {
          $config['api_url'] = 'http://localhost:1234/v1/chat/completions';
        }
        break;

      case 'mistral':
        $config['api_key'] = defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_MISTRAL') ? CLICSHOPPING_APP_CHATGPT_CH_API_KEY_MISTRAL : '';
        $config['model'] = defined('CLICSHOPPING_APP_CHATGPT_MISTRAL_MODEL') ? CLICSHOPPING_APP_CHATGPT_MISTRAL_MODEL : 'mistral-large-latest';
        $config['temperature'] = defined('CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE') ? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE : 0.7;
        $config['max_tokens'] = defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 4000;
        $config['api_url'] = 'https://api.mistral.ai/v1/chat/completions';
        break;

      case 'gemini':
        $config['api_key'] = defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_GEMINI') ? CLICSHOPPING_APP_CHATGPT_CH_API_KEY_GEMINI : '';
        $config['model'] = defined('CLICSHOPPING_APP_CHATGPT_GEMINI_MODEL') ? CLICSHOPPING_APP_CHATGPT_GEMINI_MODEL : 'gemini-pro';
        $config['temperature'] = defined('CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE') ? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE : 0.7;
        $config['max_tokens'] = defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 4000;
        $config['api_url'] = 'https://generativelanguage.googleapis.com/v1beta/models';
        break;

      default:
        throw new ProviderNotFoundException("Unknown provider: {$providerName}");
    }

    return $config;
  }

  /**
   * Prevent cloning (singleton pattern)
   */
  private function __clone()
  {
  }

  /**
   * Prevent unserialization (singleton pattern)
   */
  public function __wakeup()
  {
    throw new \Exception("Cannot unserialize singleton");
  }
}
