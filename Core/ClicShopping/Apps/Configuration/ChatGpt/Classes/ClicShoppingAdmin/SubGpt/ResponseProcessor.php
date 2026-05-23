<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Infrastructure\Prompt\PromptOptimizer;
use ClicShopping\AI\Infrastructure\Response\ResponseNormalizer;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Common\LLMProviderFactory;

use function defined;
use function is_null;

/**
 * ResponseProcessor
 *
 * Manages GPT response generation and processing.
 * Extracted from Gpt.php as part of code refactoring (Task 9).
 *
 * Responsibilities:
 * - Process prompts and generate responses
 * - Handle model-specific API calls
 * - Track token usage
 * - Implement fallback logic
 * - Normalize responses
 * - Build API request bodies
 */
class ResponseProcessor
{
  // Store last token usage for retrieval
  private static $lastTokenUsage = null;

  /**
   * Get the maximum token value from configuration or parameter
   *
   * @param int|null $maxtoken Optional maximum token value
   * @return int The maximum token value to use
   */
  private static function getMaxTokens(?int $maxtoken = null): int
  {
    if (is_null($maxtoken)) {
      return (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN;
    }
    return $maxtoken;
  }

  /**
   * Call LLM with model-specific handling and integrated components
   *
   * This method integrates:
   * - PromptOptimizer: Optimizes prompts for model context length limits
   * - ResponseNormalizer: Normalizes responses from different models
   * - Fallback Logic: Tries alternative models on failure
   * - Comprehensive Logging: Tracks all operations for debugging
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
    $securityLogger = new SecurityLogger();
    $debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';

    if ($debug) {
      $securityLogger->logSecurityEvent(
        "callWithModel: Starting call with model '$model'",
        'info'
      );
    }

    // Step 1: Validate prompt
    if (empty($prompt) || !is_string($prompt) || trim($prompt) === '') {
      throw new \Exception("Prompt cannot be empty");
    }

    // Step 2: Optimize prompt for model context length
    $optimizer = new PromptOptimizer();
    
    if ($optimizer->exceedsLimit($prompt, $model)) {
      if ($debug) {
        $originalTokens = $optimizer->estimateTokenCount($prompt);
        $securityLogger->logSecurityEvent(
          "callWithModel: Prompt exceeds limit for model '$model' ($originalTokens tokens). Optimizing...",
          'warning'
        );
      }
      
      $optimizedPrompt = $optimizer->optimizeForModel($prompt, $model);
      
      if ($debug) {
        $optimizedTokens = $optimizer->estimateTokenCount($optimizedPrompt);
        $securityLogger->logSecurityEvent(
          "callWithModel: Prompt optimized from $originalTokens to $optimizedTokens tokens",
          'info'
        );
      }
    } else {
      $optimizedPrompt = $prompt;
      
      if ($debug) {
        $tokens = $optimizer->estimateTokenCount($prompt);
        $securityLogger->logSecurityEvent(
          "callWithModel: Prompt within limits ($tokens tokens). No optimization needed.",
          'info'
        );
      }
    }

    // Step 3: Call the model with optimized prompt
    try {
      if ($debug) {
        $securityLogger->logSecurityEvent(
          "callWithModel: Calling model '$model' with optimized prompt",
          'info'
        );
      }

      $rawResponse = self::getGptResponse($optimizedPrompt, $maxtoken, $temperature, $model, $max);

      if ($rawResponse === false) {
        throw new \Exception("Model '$model' returned false response");
      }

      if ($debug) {
        $securityLogger->logSecurityEvent(
          "callWithModel: Received response from model '$model' (" . strlen($rawResponse) . " chars)",
          'info'
        );
      }

      // Step 4: Normalize response
      $normalizer = new ResponseNormalizer();
      $normalizedResponse = $normalizer->normalize($rawResponse, $model);

      if ($debug) {
        $responseType = $normalizedResponse['response_type'] ?? 'unknown';
        $securityLogger->logSecurityEvent(
          "callWithModel: Response normalized successfully (Type: $responseType)",
          'info'
        );
      }

      return $normalizedResponse;

    } catch (\Exception $e) {
      // Step 5: Fallback logic - try alternative model
      if ($debug) {
        $securityLogger->logSecurityEvent(
          "callWithModel: Model '$model' failed: " . $e->getMessage(),
          'error'
        );
      }

      $fallbackModel = self::getFallbackModel($model);

      if ($fallbackModel !== null) {
        if ($debug) {
          $securityLogger->logSecurityEvent(
            "callWithModel: Trying fallback model '$fallbackModel'",
            'warning'
          );
        }

        try {
          return self::callWithModel($optimizedPrompt, $fallbackModel, $maxtoken, $temperature, $max);
        } catch (\Exception $fallbackException) {
          if ($debug) {
            $securityLogger->logSecurityEvent(
              "callWithModel: Fallback model '$fallbackModel' also failed: " . $fallbackException->getMessage(),
              'error'
            );
          }
          
          throw new \Exception(
            "Primary model '$model' and fallback model '$fallbackModel' both failed. " .
            "Primary error: " . $e->getMessage() . ". " .
            "Fallback error: " . $fallbackException->getMessage()
          );
        }
      }

      throw new \Exception("Model '$model' failed and no fallback available: " . $e->getMessage());
    }
  }

  /**
   * Get fallback model for a failed model
   *
   * @param string $failedModel The model that failed
   * @return string|null Fallback model name or null if no fallback available
   */
  private static function getFallbackModel(string $failedModel): ?string
  {
    $fallbackChain = [
      'gpt-5' => 'gpt-4.1-mini',
      'gpt-5-mini' => 'gpt-4.1-mini',
      'gpt-5-nano' => 'gpt-4.1-mini',
      'gpt-4.1-mini' => 'gpt-4.1-mini',
      'gpt-4.1-nano' => 'gpt-4.1-nano',
      'gpt-4o' => 'anth-sonnet',
      'anth-sonnet' => 'anth-sonnet',
      'anth-opus' => 'anth-sonnet',
      'anth-haiku' => 'anth-sonnet',
      'mistral-large-latest' => 'gpt-4.1-mini',
    ];

    return $fallbackChain[$failedModel] ?? null;
  }

  /**
   * Retrieves a chat response based on the provided parameters and model configuration.
   * 
   * @deprecated This method is maintained for backward compatibility only.
   *             New code should use getGptResponse() instead.
   *
   * @param string $question The input question or prompt to be processed.
   * @param int|null $maxtoken The maximum number of tokens for the response, or null for default.
   * @param float|null $temperature The sampling temperature for response generation, or null for default.
   * @param string|null $engine The engine to be used for processing, or null for default.
   * @param int|null $max The maximum number of responses to return, or null for default.
   * @return mixed The chat response generated by the selected model.
   */
  public static function getChat(string $question, int|null $maxtoken = null, ?float $temperature = null, ?string $engine = null, int|null $max = 1): mixed
  {
    if (!defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL')) {
      define('CLICSHOPPING_APP_CHATGPT_CH_MODEL', 'gpt-5-mini');
    }

    $model = $engine ?? CLICSHOPPING_APP_CHATGPT_CH_MODEL;

    if (strpos($model, 'gpt') === 0) {
      $maxtoken = self::getMaxTokens($maxtoken);
      $temperature = $temperature ?? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE;
      
      $tokenParams = ModelManager::getModelApiParameters($model, $maxtoken);
      
      if (ModelManager::isReasoningApiModel($model)) {
        $parameters = array_merge([
          'reasoning_effort' => CLICSHOPPING_APP_CHATGPT_CH_REASONING_EFFORT,
          'verbosity' => CLICSHOPPING_APP_CHATGPT_CH_VERBOSITY,
          'messages' => [
            ['role' => 'user', 'content' => $question]
          ],
          'user' => AdministratorAdmin::getUserAdmin()
        ], $tokenParams);
      } else {
        $parameters = array_merge([
          'temperature' => $temperature,
          'top_p' => (float)CLICSHOPPING_APP_CHATGPT_CH_TOP_P,
          'frequency_penalty' => (float)CLICSHOPPING_APP_CHATGPT_CH_FREQUENCY_PENALITY,
          'presence_penalty' => (float)CLICSHOPPING_APP_CHATGPT_CH_PRESENCE_PENALITY,
          'stop' => ['\n'],
          'n' => $max,
          'user' => AdministratorAdmin::getUserAdmin(),
          'messages' => [
            ['role' => 'system', 'content' => 'You are an e-commerce expert in marketing.'],
            ['role' => 'user', 'content' => $question]
          ]
        ], $tokenParams);
      }
      
      if (!empty(CLICSHOPPING_APP_CHATGPT_CH_ORGANIZATION)) {
        $parameters['organization'] = CLICSHOPPING_APP_CHATGPT_CH_ORGANISATION;
      }
      
      if ($engine !== null) {
        $parameters['model'] = $engine;
      }
      
      $client = ProviderManager::getOpenAiGpt($parameters);
    } elseif (strpos($model, 'anth') === 0) {
      $client = ProviderManager::getAnthropicChat($model, $maxtoken);
    } elseif (strpos($model, 'mistral') === 0) {
      $client = ProviderManager::getMistralChat($model, $maxtoken);
    } elseif (strpos($model, 'ollama') === 0 || str_contains($model, ':latest')) {
      $client = ProviderManager::getOllamaChat($model);
    } elseif (strpos($model, 'openai/') === 0) {
      $client = ProviderManager::getLmStudioChat($model);
    } else {
      $client = ProviderManager::getLmStudioChat($model);
    }

    return $client;
  }

  /**
   * Get GPT response (PUBLIC API - NO CHANGES TO SIGNATURE)
   * 
   * REFACTORED INTERNALLY: Now uses LLMProviderInterface for provider abstraction
   * while maintaining 100% backward compatibility with all existing usages.
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
    if (ConfigManager::checkGptStatus() === false) {
      return false;
    }

    if (empty($question) || !is_string($question) || trim($question) === '') {
      return false;
    }

    if (is_null($engine)) {
      $engine = CLICSHOPPING_APP_CHATGPT_CH_MODEL;
    }

    // Validate and sanitize prompt
    $prompt = trim($question);

    if (preg_match('/<script[\s>]/i', $prompt) || preg_match('/<iframe[\s>]/i', $prompt)) {
      error_log("SECURITY: Blocked dangerous HTML tags in prompt: " . substr($prompt, 0, 100));
      throw new \Exception("Requête bloquée pour des raisons de sécurité");
    }
    
    $maxPromptLength = defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_PROMPT_LENGTH') ? CLICSHOPPING_APP_CHATGPT_RA_MAX_PROMPT_LENGTH : 100000;
    
    if (strlen($prompt) > $maxPromptLength) {
      $prompt = substr($prompt, 0, $maxPromptLength);
      error_log("WARNING: Prompt truncated to {$maxPromptLength} characters (security limit)");
    }
    
    $prompt = htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8');
    
    if (empty($prompt)) {
      error_log("WARNING Ajax ChatGpt: Prompt is empty after validation for: " . substr($question, 0, 100));
      $prompt = htmlspecialchars(trim($question), ENT_QUOTES, 'UTF-8');
    }

    $prompt = HTMLOverrideCommon::removeInvisibleCharacters($prompt);

    if (empty($prompt)) {
      throw new \Exception("Prompt is empty after validation and sanitization");
    }

    try {
      $chatgpt_debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';

      if ($chatgpt_debug) {
        error_log("[INFO SEARCH] [ResponseProcessor::getGptResponse] Starting provider detection for engine: {$engine}");
      }

      $providerName = self::detectProviderFromEngine($engine);

      if ($chatgpt_debug) {
       error_log("[INFO VALIDATED] [ResponseProcessor::getGptResponse] Provider detected: {$providerName} for engine: {$engine}");

        error_log(sprintf(
          "[INFO EDIT] [ResponseProcessor::getGptResponse] Request params - model: %s, max_tokens: %s, temperature: %s, prompt_length: %d",
          $engine,
          $maxtoken ?? 'null',
          $temperature ?? 'null',
          strlen($prompt)
        ));
      }

      $factory = LLMProviderFactory::getInstance();

      $provider = $factory->create($providerName, [
        'model' => $engine,
        'max_tokens' => $maxtoken,
        'temperature' => $temperature,
      ]);

      if ($chatgpt_debug) {
        error_log("[INFO VALIDATED] [ResponseProcessor::getGptResponse] Provider instance created successfully: " . get_class($provider));
      }

      try {
        if ($chatgpt_debug) {
          error_log("[INFO SEARCH] [ResponseProcessor::getGptResponse] Attempting to get LLPhant chat instance from provider");
        }
        $chat = $provider->getLLPhantChat();

        if ($chatgpt_debug) {
          error_log("[INFO VALIDATED] [ResponseProcessor::getGptResponse] Using provider {$providerName} via getLLPhantChat()");
        }
      } catch (\Exception $e) {
        error_log("[INFO WARNING] [ResponseProcessor::getGptResponse] Provider {$providerName} getLLPhantChat() failed: {$e->getMessage()}, falling back to getChat()");
        
        $chat = self::getChat($prompt, $maxtoken, $temperature, $engine, $max);
        
        error_log("[INFO VALIDATED] [ResponseProcessor::getGptResponse] Fallback to getChat() successful");
      }
      if ($chatgpt_debug) {
        error_log(sprintf(
          "[INFO SEARCH] [ResponseProcessor::getGptResponse] Calling generateText() with prompt (first 100 chars): %s",
          substr($prompt, 0, 100)
        ));
      }

      $result = $chat->generateText($prompt);

      if ($chatgpt_debug) {
        error_log(sprintf(
          "[INFO VALIDATED] [ResponseProcessor::getGptResponse] generateText() returned result - length: %d, first 100 chars: %s",
          strlen($result),
          substr($result, 0, 100)
        ));
      }

      // Extract usage metrics
      if (method_exists($chat, 'getLastResponse')) {
        if ($chatgpt_debug) {
          error_log("[INFO SEARCH] [ResponseProcessor::getGptResponse] Attempting to extract token usage from last response");
        }

        $lastResponse = $chat->getLastResponse();
        
        if (!is_null($lastResponse) && isset($lastResponse['usage'])) {
          self::$lastTokenUsage = [
            'prompt_tokens' => $lastResponse['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $lastResponse['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $lastResponse['usage']['total_tokens'] ?? 0
          ];
          if ($chatgpt_debug) {
            error_log(sprintf(
              '[INFO USAGE] [ResponseProcessor::getGptResponse] Token usage for model %s: prompt=%d, completion=%d, total=%d',
              $engine,
              self::$lastTokenUsage['prompt_tokens'],
              self::$lastTokenUsage['completion_tokens'],
              self::$lastTokenUsage['total_tokens']
            ));
          }
        } else {
          self::$lastTokenUsage = null;
          if ($chatgpt_debug) {
            error_log(sprintf(
              '[INFO WARNING] [ResponseProcessor::getGptResponse] No token usage data in response for model %s',
              $engine
            ));
          }
        }
      } else {
        if ($chatgpt_debug) {
          error_log("[INFO SEARCH] [ResponseProcessor::getGptResponse] getLastResponse() not available, estimating token usage");
        }

        $promptTokens = (int)ceil(strlen($prompt) / 4);
        $completionTokens = (int)ceil(strlen($result) / 4);

        self::$lastTokenUsage = [
          'prompt_tokens' => $promptTokens,
          'completion_tokens' => $completionTokens,
          'total_tokens' => $promptTokens + $completionTokens
        ];

        if ($chatgpt_debug) {
          error_log(sprintf(
            '[INFO USAGE] Estimated token usage for model %s (provider: %s): prompt=%d, completion=%d, total=%d',
            $engine,
            get_class($chat),
            $promptTokens,
            $completionTokens,
            $promptTokens + $completionTokens
          ));
        }

        error_log("[INFO VALIDATED] [ResponseProcessor::getGptResponse] Request completed successfully");
      }

      return $result;
      
    } catch (\Exception $e) {
      error_log(sprintf(
        "❌ [ResponseProcessor::getGptResponse] Error occurred - Message: %s, File: %s, Line: %d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
      ));
      error_log("[error] [ResponseProcessor::getGptResponse] Stack trace: " . $e->getTraceAsString());
      
      return false;
    }
  }

  /**
   * Detect LLM provider from model/engine name
   * 
   * @param string|null $engine Model/engine name
   * @return string Provider name
   */
  private static function detectProviderFromEngine(?string $engine): string
  {
    if ($engine === null || trim($engine) === '') {
      return 'openai';
    }

    $modelProviderMap = ModelManager::getModelProviderMap();

    if (isset($modelProviderMap[$engine])) {
      return $modelProviderMap[$engine];
    }

    if (defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True') {
      error_log("WARNING: Unknown model '$engine' in detectProviderFromEngine(). Using default provider 'openai'.");
    }

    return 'openai';
  }

  /**
   * Get the last token usage from the most recent API call
   * 
   * @return array|null Array with 'prompt_tokens', 'completion_tokens', 'total_tokens' or null if not available
   */
  public static function getLastTokenUsage(): ?array
  {
    return self::$lastTokenUsage;
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
    // Note: Deprecation warning in comment only
    // This method is maintained for backward compatibility
    
    $body = [
      'model' => $model ?? CLICSHOPPING_APP_CHATGPT_CH_MODEL,
      'temperature' => $temperature ?? (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE,
      'max_tokens' => $maxTokens ?? (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN,
    ];

    switch ($provider) {
      case 'openai':
      case 'mistral':
      case 'lmstudio':
        $body['messages'] = [
          ['role' => 'user', 'content' => $prompt]
        ];
        break;

      case 'anthropic':
        $body['messages'] = [
          ['role' => 'user', 'content' => $prompt]
        ];
        $body['max_tokens'] = $maxTokens ?? 1024;
        break;

      case 'ollama':
        $body['prompt'] = $prompt;
        unset($body['messages']);
        break;
    }

    return $body;
  }
}
