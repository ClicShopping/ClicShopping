<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\OM\HTML;
use function defined;

/**
 * ModelManager
 *
 * Manages GPT model information and selection.
 * Extracted from Gpt.php as part of code refactoring (Task 9).
 *
 * Responsibilities:
 * - Provide list of available models
 * - Generate model selection UI
 * - Manage model-specific parameters
 * - Check model capabilities (reasoning, context length)
 * - Map model names between providers
 */
class ModelManager
{
  /**
   * Retrieves an array of GPT models with their corresponding IDs and textual descriptions.
   *
   * Model Capability Legend:
   * - Embeddings: Supports vector embeddings for semantic search
   * - Reasoning: Advanced reasoning capabilities for complex queries
   * - Analytics: SQL generation and data analysis
   * - Web Search: Can perform web searches
   * - Context: Maximum context window size
   *
   * REMOVED MODELS (Do not meet criteria):
   * - gpt-4: 8K context limit (too small for RAG BI prompts)
   * - gpt-3.5-turbo: No embeddings, limited capabilities
   * - gpt-oss (openai/gpt-oss-20b): 8K context, no embeddings, inconsistent SQL generation
   * - Ollama models: Excluded per requirements (use LM Studio instead)
   *
   * @return array An array of GPT models, where each model is represented as an associative array containing 'id' and 'text' keys.
   */
  public static function getGptModel(): array
  {
    $array = [
      // ============================================
      // GPT-5 SERIES (Future - Best Performance)
      // ============================================
      // Context: 200K+ | Embeddings: Yes | Reasoning: Yes | Analytics: Yes | Web Search: Yes
        ['id' => 'gpt-5.5',       'text' => 'OpenAI GPT-5.5 (1.05M context, embeddings, reasoning, web search)', 'provider' => 'openai'],
        ['id' => 'gpt-5.5-mini',  'text' => 'OpenAI GPT-5.5 mini (400K context, embeddings, reasoning, web search)', 'provider' => 'openai'],

        ['id' => 'gpt-5.4',       'text' => 'OpenAI GPT-5.4 (1.05M context, embeddings, reasoning, web search)', 'provider' => 'openai'],
        ['id' => 'gpt-5.4-mini',  'text' => 'OpenAI GPT-5.4 mini (400K context, embeddings, reasoning, web search)', 'provider' => 'openai'],

        ['id' => 'gpt-5.2',       'text' => 'OpenAI GPT-5.2 (200K context, embeddings, reasoning, web search)', 'provider' => 'openai'],
        ['id' => 'gpt-5.2-mini',  'text' => 'OpenAI GPT-5.2 mini (128K context, embeddings, reasoning)', 'provider' => 'openai'],

        ['id' => 'gpt-5.1',       'text' => 'OpenAI GPT-5.1 (200K context, embeddings, reasoning)', 'provider' => 'openai'],
        ['id' => 'gpt-5.1-mini',  'text' => 'OpenAI GPT-5.1 mini (128K context, embeddings, reasoning)', 'provider' => 'openai'],

      // ============================================
      // GPT-4.1 SERIES (Latest Stable)
      // ============================================
      // Context: 128K | Embeddings: Yes | Reasoning: Yes | Analytics: Yes
        ['id' => 'gpt-4.1',       'text' => 'OpenAI GPT-4.1 (128K context, embeddings, reasoning)', 'provider' => 'openai'],
        ['id' => 'gpt-4.1-mini',  'text' => 'OpenAI GPT-4.1 mini **Recommended** (64K context, embeddings, reasoning)', 'provider' => 'openai'],


      // ============================================
      // ANTHROPIC MODELS (Alternative Provider)
      // ============================================
      // Context: 200K | Embeddings: Yes | Reasoning: Yes | Analytics: Yes
      ['id' => 'anth-sonnet', 'text' => 'Anthropic Claude Sonnet 4.6 (200K context, embeddings, reasoning)', 'provider' => 'anthropic'],
      ['id' => 'anth-opus', 'text' => 'Anthropic Claude Opus 4.8 (200K context, embeddings, reasoning)', 'provider' => 'anthropic'],
      ['id' => 'anth-haiku', 'text' => 'Anthropic Claude Haiku 4.5 (200K context, embeddings, fast)', 'provider' => 'anthropic'],

      // ============================================
      // MISTRAL MODELS (Alternative Provider)
      // ============================================
      // Context: 128K | Embeddings: Yes | Reasoning: Yes | Analytics: Yes
      ['id' => 'mistral-large-latest', 'text' => 'Mistral Large Latest (128K context, embeddings, reasoning)', 'provider' => 'mistral'],

      // ============================================
      // GEMINI MODELS (Alternative Provider)
      // ============================================
      // Context: 1M | Embeddings: Yes | Reasoning: Yes | Analytics: Yes
      ['id' => 'gemini-2.5-flash', 'text' => 'gemini-2.5-flash  (1M context, embeddings, reasoning)', 'provider' => 'gemini'],

      // ============================================
      // LM STUDIO MODELS (Local Deployment)
      // ============================================
      // Context: 16K | Embeddings: No | Reasoning: Yes | Analytics: Limited
      // NOTE: Local models have limited capabilities but provide privacy and cost benefits
        ['id' => 'openai/gpt-oss-120b',     'text' => 'LM Studio GPT-OSS 120B (120K context, reasoning, local)', 'provider' => 'lmstudio'],
        ['id' => 'qwen/qwen3.6-35b-a3b',    'text' => 'LM Studio Qwen3.6 35B-A3B (262K context, reasoning, local)', 'provider' => 'lmstudio'],
        ['id' => 'qwen/qwen3.6-27b',        'text' => 'LM Studio Qwen3.6 27B (262K context, reasoning, local)', 'provider' => 'lmstudio'],
        ['id' => 'openai/gpt-oss-20b',      'text' => 'LM Studio GPT-OSS 20B (16K context, reasoning, local — chat only, not AI pipeline)', 'provider' => 'lmstudio'],
        ['id' => 'qwen/qwen3-4b',           'text' => 'LM Studio Qwen3 4B (16K context, local — chat only, not AI pipeline)', 'provider' => 'lmstudio'],
        ['id' => 'microsoft/phi-4',         'text' => 'LM Studio Phi-4 (16K context, local — chat only, not AI pipeline)', 'provider' => 'lmstudio'],
    ];

    return $array;
  }

  /**
   * Returns the GPT model to use as a technical fallback in case
   * the primary model fails due to API errors, timeouts, or rate limits.
   * This model should have similar capabilities to the primary model
   * to maintain consistency in behavior.
   *
   * @return string Model ID of the technical fallback GPT model.
   */
  public static function getTechnicalFallbackModel(): string
  {
    return 'gpt-5-mini';
  }

  /**
   * Returns the GPT model to use for the first level of quality escalation.
   * This model is intended to provide higher reasoning, accuracy, or context
   * capacity than the primary model when the primary output is insufficient
   * for complex tasks or low-confidence responses.
   *
   * @return string Model ID of the first-level escalation GPT model.
   */
  public static function getEscalationModelLevel1(): string
  {
    return 'gpt-5-mini';
  }


  /**
   * Generates and returns an HTML select field for GPT model options.
   *
   * @return string The HTML select field containing GPT model options.
   */
  public static function getGptModalMenu(): string
  {
    $array = self::getGptModel();

    $menu = HTML::selectField('engine', $array, null, 'id="engine"');

    return $menu;
  }

  /**
   * Get complete model-to-provider mapping
   * 
   * Generates a mapping from model IDs to provider names based on
   * the model configurations in getGptModel().
   * 
   * This provides a single source of truth for model-provider relationships
   * and makes it easy to verify all models have valid provider mappings.
   * 
   * Valid provider names: openai, anthropic, ollama, lmstudio, mistral, gemini
   * 
   * @return array Associative array [model_id => provider_name]
   */
  public static function getModelProviderMap(): array
  {
    static $cache = null;

    // Use cached mapping if available
    if ($cache !== null) {
      return $cache;
    }

    $models = self::getGptModel();
    $mapping = [];

    // Valid providers list
    $validProviders = ['openai', 'anthropic', 'ollama', 'lmstudio', 'mistral', 'gemini'];

    foreach ($models as $model) {
      $modelId = $model['id'];
      $provider = $model['provider'] ?? 'openai'; // Default to openai if missing

      // Validate provider name
      if (!in_array($provider, $validProviders)) {
        if (defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True') {
          error_log("WARNING: Invalid provider '$provider' for model '$modelId'. Using 'openai' as default.");
        }
        $provider = 'openai';
      }

      $mapping[$modelId] = $provider;
    }

    // Cache the mapping
    $cache = $mapping;

    return $mapping;
  }

  /**
   * Get model-specific API parameters based on the model name
   * 
   * Different OpenAI models require different parameter names for token limits:
   * - max_completion_tokens: GPT-4o-mini, GPT-5 series, GPT-4.1 series
   * - max_tokens: GPT-4o, Anthropic, Mistral, LM Studio models
   *
   * @param string $model The model name (e.g., 'gpt-4o', 'gpt-4.1-mini', 'gpt-5')
   * @param int $maxtoken The maximum number of tokens
   * @return array The model-specific parameters
   */
  public static function getModelApiParameters(string $model, int $maxtoken): array
  {
    $params = [];

    // Model-specific parameter mapping
    // GPT-4o-mini, GPT-4.1 series, GPT-5 series use max_completion_tokens
    if (str_starts_with($model, 'gpt-4.1-mini') || 
        str_starts_with($model, 'gpt-4.1') ||
        str_starts_with($model, 'gpt-5')) {
      $params['max_completion_tokens'] = $maxtoken;
    } else {
      // Default for GPT-4o, Anthropic, Mistral, LM Studio, and other models
      $params['max_tokens'] = $maxtoken;
    }

    return $params;
  }

  /**
   * Check if model uses reasoning API approach (GPT-5 style)
   * 
   * GPT-5 models use reasoning_effort and verbosity parameters instead of
   * temperature, top_p, frequency_penalty, presence_penalty.
   *
   * @param string $model Model name
   * @return bool True if model uses reasoning API approach
   */
  public static function isReasoningApiModel(string $model): bool
  {
    // Get all models from the list
    $models = self::getGptModel();
    
    foreach ($models as $modelInfo) {
      if ($modelInfo['id'] === $model) {
        // Check if this is a GPT-5 series model (uses reasoning API)
        if (str_starts_with($modelInfo['id'], 'gpt-5')) {
          return true;
        }
        
        return false;
      }
    }
    
    // Model not found in list - check by prefix as fallback
    return str_starts_with($model, 'gpt-5');
  }

  /**
   * Get model context length limit
   *
   * Extracts context length from model description in getGptModel().
   * Used by PromptOptimizer for context management.
   *
   * @param string $model Model name
   * @return int Context length in tokens (defaults to 128000 if not found)
   */
  public static function getModelContextLength(string $model): int
  {
    $models = self::getGptModel();
    
    foreach ($models as $modelInfo) {
      if ($modelInfo['id'] === $model) {
        // Extract context length from text description
        // Pattern: "(\d+)K context"
        if (preg_match('/(\d+)K\s+context/i', $modelInfo['text'], $matches)) {
          $contextK = (int)$matches[1];
          return $contextK * 1000;
        }
      }
    }
    
    // Default to 128K (safe for most models)
    return 128000;
  }

  /**
   * Map Anthropic model names between internal and API formats
   *
   * @param string $model Internal model name (e.g., 'anth-sonnet')
   * @return string API model name (e.g., 'claude-3-5-sonnet-20241022')
   */
  public static function mapAnthropicModelName(string $model): string
  {
    // Note: Deprecation warning in comment only (as per task requirements)
    // This method is maintained for backward compatibility
    // LLPhant's AnthropicConfig handles model name mapping internally
    
    $mapping = [
      'anth-sonnet' => 'claude-sonnet-4-6',
      'anth-opus' => 'claude-opus-4-8',
      'anth-haiku' => 'claude-haiku-4-5-20251001'
    ];

    return $mapping[$model] ?? $model;
  }
}
