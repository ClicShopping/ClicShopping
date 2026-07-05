<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use LLPhant\Chat\LLMStudioChat;
use LLPhant\Chat\LmStudioChat;
use LLPhant\Chat\MistralAIChat;
use LLPhant\Chat\OllamaChat;
use LLPhant\Chat\OpenAIChat;
use LLPhant\LmStudioConfig;
use LLPhant\MistralAIConfig;
use LLPhant\GeminiOpenAIConfig;
use LLPhant\OpenAIConfig;
use LLPhant\OllamaConfig;
use LLPhant\AnthropicConfig;
use LLPhant\Chat\AnthropicChat;

use function defined;
use function is_null;

/**
 * ProviderManager
 *
 * Manages LLM provider initialization and configuration.
 * Extracted from Gpt.php as part of code refactoring (Task 9).
 *
 * Responsibilities:
 * - Initialize OpenAI chat instances
 * - Initialize Ollama chat instances
 * - Initialize LM Studio chat instances
 * - Initialize Anthropic chat instances
 * - Initialize Mistral chat instances
 * - Provide unified chat instance retrieval
 * - Build provider-specific configurations
 */
class ProviderManager
{
  /**
   * Initializes and returns an instance of OpenAIChat configured with the given parameters.
   * 
   * NOTE: This method continues to use LLPhant's OpenAIChat for backward compatibility.
   * The provider interface is used for new code and parallel execution.
   *
   * @param array|null $parameters Optional parameters for configuring the OpenAI model, such as model type and options.
   * @param string|null $api_key Optional API key override
   * @return mixed The configured OpenAIChat instance.
   */
  public static function getOpenAiGpt(array|null $parameters, string|null $api_key = null): mixed
  {
    $config = new OpenAIConfig();

    if (is_null($api_key)) {
      $api_key = ModelManager::getProviderApiKey('openai')['api_key'] ?: null;
    }

    $config->apiKey = $api_key;

    if (!is_null($parameters) && array_key_exists('model', $parameters)) {
      $config->model = $parameters['model'];
      $config->modelOptions = $parameters;
    } elseif (!is_null($parameters)) {
      $config->model = ModelManager::defaultModel();
      $config->modelOptions = $parameters;
    }

    $chat = new OpenAIChat($config);

    return $chat;
  }

  /**
   * Creates an Ollama chat instance using LLPhant library
   * 
   * NOTE: This method continues to use LLPhant's OllamaChat for backward compatibility.
   * The provider interface is used for new code and parallel execution.
   * 
   * @param string $model Model name (default: 'mistral:7b')
   * @return mixed The configured OllamaChat instance
   */
  public static function getOllamaChat(string $model = 'mistral:7b'): mixed
  {
    $config = new OllamaConfig();
    $config->model = $model;
    $chat = new OllamaChat($config);

    return $chat;
  }

  /**
   * Crée et configure une instance de LmStudioChat
   * 
   * NOTE: This method continues to use LLPhant's LmStudioChat for backward compatibility.
   * The provider interface is used for new code and parallel execution.
   *
   * @param string $model Le nom du modèle à utiliser (par défaut: 'openai/gpt-oss-20b')
   * @param string|null $url L'URL de l'API LM Studio (optionnel)
   * @param float|null $timeout Timeout en secondes (optionnel)
   * @return LmStudioChat Instance configurée de LmStudioChat
   */
  public static function getLmStudioChat(string $model = 'openai/gpt-oss-20b', ?string $url = null, ?float $timeout = null): LmStudioChat
  {
    // Créer la configuration
    $config = new LmStudioConfig();
    $config->model = $model;

    // Configurer l'URL si fournie, sinon utiliser la valeur par défaut ou depuis les constantes
    if ($url !== null) {
      $config->url = $url;
    } elseif (defined('CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL') && !empty(CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL)) {
      $config->url = CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL;
    } else {
      // LM Studio uses OpenAI-compatible API
      // LmStudioChat adds 'v1/chat/completions' to base_uri, so base_uri should be just the host
      $config->url = 'http://localhost:1234/';
    }

    // Configurer le timeout si fourni ou depuis les constantes
    if ($timeout !== null) {
      $config->timeout = $timeout;
    } elseif (defined('CLICSHOPPING_APP_CHATGPT_LMSTUDIO_TIMEOUT')) {
      $config->timeout = (float)CLICSHOPPING_APP_CHATGPT_LMSTUDIO_TIMEOUT;
    }

    // Configurer les options du modèle si disponibles
    if (defined('CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE')) {
      $config->modelOptions['temperature'] = (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE;
    }

    // LM Studio models need more tokens to allow reasoning with <think> tags
    // Use dedicated config if available, otherwise use higher default
    if (defined('CLICSHOPPING_APP_CHATGPT_LMSTUDIO_MAX_TOKEN')) {
      $config->modelOptions['max_tokens'] = (int)CLICSHOPPING_APP_CHATGPT_LMSTUDIO_MAX_TOKEN;
    } elseif (defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN')) {
      // For LM Studio, multiply by 3 to allow reasoning space
      // Example: 350 tokens → 1050 tokens for <think> + answer
      $baseTokens = (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN;
      $config->modelOptions['max_tokens'] = $baseTokens * 3;
      
      error_log("🔧 LM Studio: Using {$config->modelOptions['max_tokens']} tokens (base: $baseTokens × 3 for reasoning)");
    } else {
      // Default: 1000 tokens for LM Studio (allows reasoning)
      $config->modelOptions['max_tokens'] = 1000;
    }

    // Créer et retourner l'instance de LmStudioChat avec la config
    return new LmStudioChat($config);
  }

  /**
   * Creates an instance of the AnthropicChat class based on the specified model and configuration options.
   * 
   * NOTE: This method continues to use LLPhant's AnthropicChat for backward compatibility.
   * The provider interface is used for new code and parallel execution.
   *
   * @param string $model The real Anthropic technical model name from the catalog
   * (e.g. 'claude-sonnet-4-6'); legacy 'anth-*' aliases are still resolved for backward compatibility.
   * @param int|null $maxtoken The maximum number of tokens the model can output.
   *                           Defaults to the configured max token if not provided.
   * @param array|null $modelOptions Additional configuration options for the model.
   * @return mixed An instance of AnthropicChat initialized with the provided parameters, or false on failure.
   */
  public static function getAnthropicChat(string $model, int|null $maxtoken = null, array|null $modelOptions = null): mixed
  {
    $result = false;

    $api_key = ModelManager::getProviderApiKey('anthropic')['api_key'];

    if ($api_key !== '') {
      if (is_null($modelOptions)) {
        $modelOptions = [
          'temperature' => (float) CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE,
          'top_p' => (float) CLICSHOPPING_APP_CHATGPT_CH_TOP_P,
          'max_tokens_to_sample' => (int) CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN,
          'stop_sequences' => ['\n']
        ];
      }

      // $model is the real technical name from the catalog (option A). mapAnthropicModelName()
      // is a passthrough for real names and still resolves any legacy anth-* alias.
      $apiModel = ModelManager::mapAnthropicModelName($model);

      $result = new AnthropicChat(
        new AnthropicConfig($apiModel, $maxtoken, $modelOptions, $api_key)
      );
    }

    return $result;
  }

  /**
   * Creates an instance of the MistralAIChat class based on the specified model and configuration options.
   * 
   * NOTE: This method continues to use LLPhant's MistralAIChat for backward compatibility.
   * The provider interface is used for new code and parallel execution.
   *
   * @param string $model The specific model identifier to use for the MistralAIChat instance.
   * @param int|null $maxtoken Maximum tokens
   * @return MistralAIChat
   * @throws \Exception
   */
  public static function getMistralChat(string $model, ?int $maxtoken = null): MistralAIChat
  {
    $api_key = ModelManager::getProviderApiKey('mistral')['api_key'];

    if ($api_key === '') {
      throw new \Exception('You have to provide a MISTRAL_API_KEY to request Mistral AI.');
    }

    // Valid model for MistralAIChat
    $valid_models = [
      'mistral-tiny',
      'mistral-small-latest',
      'mistral-medium-latest',
      'mistral-large-latest',
      'pixtral-large-latest',
      'ministral-3b-latest',
      'ministral-8b-latest',
      'codestral-latest',
      'open-mistral-nemo',
      'open-codestral-mamba',
      'mistral-moderation-latest'
    ];

    if (empty($model) || !in_array($model, $valid_models)) {
      $model = 'mistral-large-latest';
    }

    $config = new MistralAIConfig();
    $config->apiKey = $api_key;
    $config->model = $model;

    // Appliquer la limite de tokens si spécifiée (option OpenAI-compatible)
    if (is_null($maxtoken) || $maxtoken <= 0) {
      $maxtoken = (int)(defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 0);
    }
    if ($maxtoken > 0) {
      $config->modelOptions = ['max_tokens' => $maxtoken];
    }

    try {
      return new MistralAIChat($config);
    } catch (\Exception $e) {
      throw new \Exception('Error creating MistralAIChat instance: ' . $e->getMessage());
    }
  }

  /**
   * Creates a chat instance for Google Gemini via its OpenAI-compatible endpoint.
   *
   * Uses LLPhant's GeminiOpenAIConfig (base URL preset to Google's OpenAI-compatible API) driven
   * by an OpenAIChat, with the API key resolved from the DB catalog (provider 'gemini').
   *
   * @param string $model The Gemini model identifier (e.g. gemini-2.5-flash)
   * @param int|null $maxtoken Optional max output tokens
   * @return OpenAIChat
   * @throws \Exception When no Gemini API key is configured or instantiation fails
   */
  public static function getGeminiChat(string $model, ?int $maxtoken = null): OpenAIChat
  {
    $api_key = ModelManager::getProviderApiKey('gemini')['api_key'];

    if ($api_key === '') {
      throw new \Exception('You have to provide a GEMINI_API_KEY to request Google Gemini.');
    }

    if (empty($model)) {
      $model = 'gemini-2.5-flash';
    }

    $config = new GeminiOpenAIConfig();
    $config->apiKey = $api_key;
    $config->model = $model;

    if (is_null($maxtoken) || $maxtoken <= 0) {
      $maxtoken = (int)(defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 0);
    }
    if ($maxtoken > 0) {
      $config->modelOptions = ['max_tokens' => $maxtoken];
    }

    try {
      return new OpenAIChat($config);
    } catch (\Exception $e) {
      throw new \Exception('Error creating Gemini chat instance: ' . $e->getMessage());
    }
  }

  /**
   * Get chat instance for the specified model (centralized model detection)
   * 
   * This function centralizes the logic for detecting the model type and returning
   * the appropriate chat instance. It supports:
   * - OpenAI (GPT-4, GPT-3.5, o1, o3, etc.)
   * - Anthropic (Claude)
   * - Google (Gemini)
   * - Mistral
   * - Ollama (local models)
   * - LM Studio
   * 
   * @param string|null $model The model name (null = use default from config)
   * @param array $options Optional parameters (maxtoken, temperature, etc.)
   * @return mixed The chat instance for the specified model
   * @throws \Exception If model type is not supported
   */
  public static function getChatForModel(?string $model = null, array $options = []): mixed
  {
    // Use default model if not specified
    if ($model === null) {
      $model = ModelManager::defaultModel();
    }

    // Normalize model name to lowercase for comparison
    $modelLower = strtolower($model);

    // Detect model type and return appropriate chat instance
    
    // OpenAI models (GPT-4, GPT-3.5, o1, o3, etc.)
    if (str_starts_with($modelLower, 'gpt') || 
        str_starts_with($modelLower, 'o1') || 
        str_starts_with($modelLower, 'o3')) {
      return self::getOpenAiGpt(['model' => $model] + $options);
    }
    
    // Anthropic models (Claude)
    if (str_starts_with($modelLower, 'claude') || 
        str_starts_with($modelLower, 'anth')) {
      $maxtoken = $options['maxtoken'] ?? null;
      $modelOptions = $options['modelOptions'] ?? null;
      return self::getAnthropicChat($model, $maxtoken, $modelOptions);
    }
    
    // Google Gemini models
    if (str_starts_with($modelLower, 'gemini') ||
        str_starts_with($modelLower, 'google')) {
      $maxtoken = $options['maxtoken'] ?? null;
      return self::getGeminiChat($model, $maxtoken);
    }
    
    // Mistral models
    if (str_starts_with($modelLower, 'mistral')) {
      $maxtoken = $options['maxtoken'] ?? null;
      return self::getMistralChat($model, $maxtoken);
    }
    
    // Ollama models (local models with version tags like :latest or :7b)
    if (str_starts_with($modelLower, 'ollama') || 
        str_contains($model, ':latest') || 
        str_contains($model, ':')) {
      return self::getOllamaChat($model);
    }
    
    // LM Studio models (usually prefixed with openai/)
    if (str_starts_with($modelLower, 'openai/')) {
      $url = $options['url'] ?? null;
      $timeout = $options['timeout'] ?? null;
      return self::getLmStudioChat($model, $url, $timeout);
    }
    
    // Default fallback to LM Studio for unknown models
    $url = $options['url'] ?? null;
    $timeout = $options['timeout'] ?? null;
    return self::getLmStudioChat($model, $url, $timeout);
  }

  /**
   * Build configuration for a specific provider
   * 
   * @param string $provider Provider name (openai, anthropic, mistral, lmstudio, ollama)
   * @param string|null $model Model name
   * @return array Configuration array with url, headers, model, provider, temperature, max_tokens
   */
  public static function buildConfigForProvider(string $provider, ?string $model = null): array
  {
    // Note: Deprecation warning in comment only (as per task requirements)
    // This method is maintained for backward compatibility
    // New code should use LLMProviderFactory instead
    
    $config = [
      'provider' => $provider,
      'model' => $model,
      'temperature' => defined('CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE') ? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE : 0.7,
      'max_tokens' => defined('CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN') ? (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN : 350,
    ];

    switch ($provider) {
      case 'openai':
        $config['url'] = 'https://api.openai.com/v1/chat/completions';
        $config['headers'] = [
          'Authorization' => 'Bearer ' . ModelManager::getProviderApiKey('openai')['api_key'],
          'Content-Type' => 'application/json'
        ];
        break;

      case 'anthropic':
        $config['url'] = 'https://api.anthropic.com/v1/messages';
        $config['headers'] = [
          'x-api-key' => ModelManager::getProviderApiKey('anthropic')['api_key'],
          'anthropic-version' => '2023-06-01',
          'Content-Type' => 'application/json'
        ];
        break;

      case 'mistral':
        $config['url'] = 'https://api.mistral.ai/v1/chat/completions';
        $config['headers'] = [
          'Authorization' => 'Bearer ' . ModelManager::getProviderApiKey('mistral')['api_key'],
          'Content-Type' => 'application/json'
        ];
        break;

      case 'lmstudio':
        $config['url'] = (defined('CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL') ? CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL : 'http://localhost:1234') . '/v1/chat/completions';
        $config['headers'] = [
          'Content-Type' => 'application/json'
        ];
        break;

      case 'ollama':
        $config['url'] = 'http://localhost:11434/api/generate';
        $config['headers'] = [
          'Content-Type' => 'application/json'
        ];
        break;
    }

    return $config;
  }
}
