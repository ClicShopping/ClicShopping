<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin;

// Import SubGpt classes
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ConfigManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ModelManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ProviderManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ResponseProcessor;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\DataManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\UIGenerator;

/**
 * Gpt
 *
 * Main facade class for GPT functionality.
 * This class delegates all method calls to specialized SubGpt classes.
 *
 * REFACTORED: 2026-02-08 (Task 9 - Code Refactoring Phase 2)
 * - Reduced from 1667 lines to ~280 lines (83% reduction)
 * - Extracted functionality into 6 SubGpt classes
 * - Maintains 100% backward compatibility
 *
 * Architecture:
 * - ConfigManager: Configuration and status management
 * - ModelManager: Model information and selection
 * - ProviderManager: Provider initialization
 * - ResponseProcessor: Response generation and processing
 * - DataManager: Data persistence and analytics
 * - UIGenerator: UI component generation
 *
 * @see SubGpt/README.md for detailed documentation
 */
class Gpt
{
  // ========================================
  // Configuration & Status Methods
  // Delegated to: ConfigManager
  // ========================================

  /**
   * Checks the status of the GPT integration by verifying application constants and API key configuration.
   *
   * @return bool Returns true if the GPT integration is enabled and properly configured, otherwise false.
   */
  public static function checkGptStatus(): bool
  {
    return ConfigManager::checkGptStatus();
  }

  /**
   * Securely retrieves the OpenAI API key for use in API calls.
   *
   * @return string|null The API key or null if not configured
   */
  public static function getEnvironment(): string|null
  {
    return ConfigManager::getEnvironment();
  }

  /**
   * Generates the AJAX URL for the requested script.
   *
   * @param bool $chatGpt Determines whether to return the URL for the chatGpt script (true)
   *                       or the chatGptSEO script (false).
   * @return string Returns the appropriate AJAX URL based on the parameter.
   */
  public static function getAjaxUrl(bool $chatGpt = true): string
  {
    return ConfigManager::getAjaxUrl($chatGpt);
  }

  /**
   * Generates the URL for the AJAX SEO multilanguage functionality.
   *
   * @return string The fully constructed URL for the AJAX SEO multilanguage script.
   */
  public static function getAjaxSeoMultilanguageUrl(): string
  {
    return ConfigManager::getAjaxSeoMultilanguageUrl();
  }

  /**
   * Retrieves the SerpApi key from configuration
   *
   * @return string La clé API SerpApi ou chaîne vide si non configurée
   */
  public static function getSerpApiKey(): string
  {
    return ConfigManager::getSerpApiKey();
  }

  /**
   * Checks if SerpApi is available and configured
   *
   * @return bool True si une clé SerpApi valide est disponible
   */
  public static function isSerpApiAvailable(): bool
  {
    return ConfigManager::isSerpApiAvailable();
  }

  // ========================================
  // Model Management Methods
  // Delegated to: ModelManager
  // ========================================

  /**
   * Retrieves the default model.
   *
   * @return string the model.
   */
  public static function defaultModel(): string
  {
   return ModelManager::defaultModel();
  }

  /**
   * Retrieves an array of GPT models with their corresponding IDs and textual descriptions.
   *
   * @return array An array of GPT models, where each model is represented as an associative array containing 'id' and 'text' keys.
   */
  public static function getGptModel(): array
  {
    return ModelManager::getGptModel();
  }

  /**
   * Retrieves the GPT model to use as a technical fallback when the primary
   * model fails due to API errors, timeouts, or rate limits.
   * Should maintain similar capabilities to the primary model to ensure
   * consistent behavior.
   *
   * @return string Model ID of the technical fallback GPT model.
   */
  public static function getTechnicalFallbackModel(): string
  {
    return ModelManager::getTechnicalFallbackModel();
  }

  /**
   * Retrieves the GPT model to use for the first level of quality escalation.
   * Intended for cases where the primary model's output is insufficient
   * in reasoning, accuracy, or context handling.
   *
   * @return string Model ID of the first-level escalation GPT model.
   */
  public static function getEscalationModelLevel1(): string
  {
    return ModelManager::getEscalationModelLevel1();
  }

  /**
   * Generates and returns an HTML select field for GPT model options.
   *
   * @return string The HTML select field containing GPT model options.
   */
  public static function getGptModalMenu(): string
  {
    return ModelManager::getGptModalMenu();
  }

  /**
   * Get model-specific API parameters based on the model name
   *
   * @param string $model The model name (e.g., 'gpt-4o', 'gpt-4.1-mini', 'gpt-5')
   * @param int $maxtoken The maximum number of tokens
   * @return array The model-specific parameters
   */
  public static function getModelApiParameters(string $model, int $maxtoken): array
  {
    return ModelManager::getModelApiParameters($model, $maxtoken);
  }

  /**
   * Check if model uses reasoning API approach (GPT-5 style)
   *
   * @param string $model Model name
   * @return bool True if model uses reasoning API approach
   */
  public static function isReasoningApiModel(string $model): bool
  {
    return ModelManager::isReasoningApiModel($model);
  }

  /**
   * Get model context length limit
   *
   * @param string $model Model name
   * @return int Context length in tokens (defaults to 128000 if not found)
   */
  public static function getModelContextLength(string $model): int
  {
    return ModelManager::getModelContextLength($model);
  }

  /**
   * Get a model's token pricing from the DB catalog as [inputPerMillion, outputPerMillion] (USD),
   * or null when absent/zero-priced so the caller falls back to its own pricing table.
   *
   * @param string $model Technical model name
   * @return array{0:float,1:float}|null
   */
  public static function getModelPricing(string $model): ?array
  {
    return ModelManager::getModelPricing($model);
  }

  /**
   * Resolves a provider's API credential from the catalog. @see ModelManager::getProviderApiKey
   * @return array{api_key:string,organisation:?string}
   */
  public static function getProviderApiKey(string $code): array
  {
    return ModelManager::getProviderApiKey($code);
  }

  /**
   * Map Anthropic model names between internal and API formats
   *
   * @param string $model Internal model name (e.g., 'anth-sonnet')
   * @return string API model name (e.g., 'claude-3-5-sonnet-20241022')
   */
  public static function mapAnthropicModelName(string $model): string
  {
    return ModelManager::mapAnthropicModelName($model);
  }

  // ========================================
  // Provider Initialization Methods
  // Delegated to: ProviderManager
  // ========================================

  /**
   * Initializes and returns an instance of OpenAIChat configured with the given parameters.
   *
   * @param array|null $parameters Optional parameters for configuring the OpenAI model
   * @param string|null $api_key Optional API key override
   * @return mixed The configured OpenAIChat instance.
   */
  public static function getOpenAiGpt(array|null $parameters, string|null $api_key = null): mixed
  {
    return ProviderManager::getOpenAiGpt($parameters, $api_key);
  }

  /**
   * Creates an Ollama chat instance using LLPhant library
   *
   * @param string $model Model name (default: 'mistral:7b')
   * @return mixed The configured OllamaChat instance
   */
  public static function getOllamaChat(string $model = 'mistral:7b'): mixed
  {
    return ProviderManager::getOllamaChat($model);
  }

  /**
   * Crée et configure une instance de LmStudioChat
   *
   * @param string $model Le nom du modèle à utiliser (par défaut: 'openai/gpt-oss-20b')
   * @param string|null $url L'URL de l'API LM Studio (optionnel)
   * @param float|null $timeout Timeout en secondes (optionnel)
   * @return \LLPhant\Chat\LmStudioChat Instance configurée de LmStudioChat
   */
  public static function getLmStudioChat(string $model = 'openai/gpt-oss-20b', ?string $url = null, ?float $timeout = null): \LLPhant\Chat\LmStudioChat
  {
    return ProviderManager::getLmStudioChat($model, $url, $timeout);
  }

  /**
   * Creates an instance of the AnthropicChat class based on the specified model and configuration options.
   *
   * @param string $model The specific model identifier to use for the AnthropicChat instance.
   * @param int|null $maxtoken The maximum number of tokens the model can output.
   * @param array|null $modelOptions Additional configuration options for the model.
   * @return mixed An instance of AnthropicChat initialized with the provided parameters, or false on failure.
   */
  public static function getAnthropicChat(string $model, int|null $maxtoken = null, array|null $modelOptions = null): mixed
  {
    return ProviderManager::getAnthropicChat($model, $maxtoken, $modelOptions);
  }

  /**
   * Creates an instance of the MistralAIChat class based on the specified model and configuration options.
   *
   * @param string $model The specific model identifier to use for the MistralAIChat instance.
   * @param int|null $maxtoken Maximum tokens
   * @return \LLPhant\Chat\MistralAIChat
   * @throws \Exception
   */
  public static function getMistralChat(string $model, ?int $maxtoken = null): \LLPhant\Chat\MistralAIChat
  {
    return ProviderManager::getMistralChat($model, $maxtoken);
  }

  /**
   * Get chat instance for the specified model (centralized model detection)
   *
   * @param string|null $model The model name (null = use default from config)
   * @param array $options Optional parameters (maxtoken, temperature, etc.)
   * @return mixed The chat instance for the specified model
   * @throws \Exception If model type is not supported
   */
  public static function getChatForModel(?string $model = null, array $options = []): mixed
  {
    return ProviderManager::getChatForModel($model, $options);
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
    return ProviderManager::buildConfigForProvider($provider, $model);
  }

  // ========================================
  // Response Processing Methods
  // Delegated to: ResponseProcessor
  // ========================================

  /**
   * Call LLM with model-specific handling and integrated components
   *
   * @param string $prompt The prompt text to send to the model
   * @param string $model The model name (e.g., 'gpt-4o', 'gpt-4.1-mini', 'anth-sonnet')
   * @param int|null $maxtoken Maximum tokens for response
   * @param float|null $temperature Temperature for response generation
   * @param int|null $max Maximum number of responses
   * @return array Normalized response with consistent structure
   * @throws \Exception If all models fail or prompt is invalid
   */
  public static function callWithModel(string $prompt, string $model, ?int $maxtoken = null, ?float $temperature = null, ?int $max = 1): array
  {
    return ResponseProcessor::callWithModel($prompt, $model, $maxtoken, $temperature, $max);
  }

  /**
   * Get GPT response (PUBLIC API - NO CHANGES TO SIGNATURE)
   *
   * @param string $question The question/prompt
   * @param int|null $maxtoken Maximum tokens
   * @param float|null $temperature Temperature
   * @param string|null $engine Model/engine name
   * @param int|null $max Max responses
   * @return bool|string Response or false on error
   */
  public static function getGptResponse(string $question, int|null $maxtoken = null, ?float $temperature = null, ?string $engine = null, int|null $max = 1): bool|string
  {
    return ResponseProcessor::getGptResponse($question, $maxtoken, $temperature, $engine, $max);
  }

  /**
   * Get the last token usage from the most recent API call
   *
   * @return array|null Array with 'prompt_tokens', 'completion_tokens', 'total_tokens' or null if not available
   */
  public static function getLastTokenUsage(): ?array
  {
    return ResponseProcessor::getLastTokenUsage();
  }

  /**
   * Build API request body for a specific provider
   *
   * @param string $prompt The prompt text
   * @param string $provider Provider name
   * @param string|null $model Model name
   * @param float|null $temperature Temperature
   * @param int|null $maxTokens Max tokens
   * @return array Request body array ready for JSON encoding
   */
  public static function buildApiRequestBody(string $prompt, string $provider, ?string $model = null, ?float $temperature = null, ?int $maxTokens = null): array
  {
    return ResponseProcessor::buildApiRequestBody($prompt, $provider, $model, $temperature, $maxTokens);
  }

  // ========================================
  // Data Management Methods
  // Delegated to: DataManager
  // ========================================

  /**
   * Calculates the AI error rate from the rag_interactions log (request_type='error').
   *
   * @return bool|float Error rate as a percentage (0..100), or false when there is no data.
   */
  public static function getErrorRateGpt(): bool|float
  {
    return DataManager::getErrorRateGpt();
  }

  // ========================================
  // UI Generation Methods
  // Delegated to: UIGenerator
  // ========================================

  /**
   * Generates and returns the HTML for the GPT modal menu.
   *
   * @return string HTML content for the GPT modal menu
   */
  public static function gptModalMenu(): string
  {
    return UIGenerator::gptModalMenu();
  }

  /**
   * Generates and returns the parameters and script configuration for integrating a ChatGPT model into CKEditor.
   *
   * @return string|bool Returns the generated script as a string if successful, otherwise, returns false.
   */
  public static function gptCkeditorParameters(): string|bool
  {
    return UIGenerator::gptCkeditorParameters();
  }
}
