<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

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
  /** In-process catalog cache for the current request (null = not loaded). */
  private static ?array $catalogCache = null;

  /**
   * Retrieves the default model.
   *
   * @return string the model.
   */
  public static function defaultModel(): string
  {
    foreach (self::loadCatalog() as $m) {
      if (!empty($m['is_default'])) {
        return $m['id'];
      }
    }

    return self::getTechnicalFallbackModel();
  }

  /**
   * Minimal emergency fallback catalog — used ONLY when the DB catalog is empty/unreachable.
   *
   * The authoritative catalog lives in the `ai_models_*` tables (managed via the admin CRUD).
   * This is NOT that catalog: it is the last-resort safety net so that model/provider resolution
   * (`defaultModel`, `getModelProviderMap`) keeps working during a DB outage or before the seed
   * has run. It intentionally carries only the two rows that carry the net — the active default
   * and the active fallback — not the full model list (that is the DB's job).
   *
   * @return array<int,array<string,mixed>> rows: id,text,provider,context_window,status,is_default,is_fallback,ai_capable
   */
  private static function getStaticCatalog(): array
  {
    $array = [
      ['id' => 'gpt-4.1-mini',  'text' => 'OpenAI GPT-4.1 mini (64K context, embeddings, reasoning)',  'provider' => 'openai',   'context_window' => 64000,   'status' => 1, 'is_default' => 1, 'is_fallback' => 0, 'ai_capable' => 1],
      ['id' => 'gpt-4o-mini',   'text' => 'OpenAI GPT-4o mini (128K context, embeddings, reasoning)', 'provider' => 'openai',   'context_window' => 128000,  'status' => 1, 'is_default' => 0, 'is_fallback' => 1, 'ai_capable' => 1],
    ];

    return $array;
  }

  /**
   * Loads the model catalog (DB first, static list as fallback), cached in-process.
   *
   * @return array<int,array<string,mixed>> rows: id,text,provider,context_window,status,is_default,is_fallback,ai_capable
   */
  private static function loadCatalog(): array
  {
    if (self::$catalogCache !== null) {
      return self::$catalogCache;
    }

    $catalog = self::loadCatalogFromDb();

    if (empty($catalog)) {
      $catalog = self::getStaticCatalog();
    }

    self::$catalogCache = $catalog;

    return $catalog;
  }

  /**
   * Reads the ai_models_* catalog from the database, ordered by sort_order.
   * Returns [] on any failure (DB not registered, query error, empty table) so the
   * caller falls back to the static list. Never throws.
   *
   * @return array<int,array<string,mixed>>
   */
  private static function loadCatalogFromDb(): array
  {
    if (!Registry::exists('Db')) {
      return [];
    }

    $db = Registry::get('Db');

    if (!$db instanceof \ClicShopping\OM\Db) {
      return [];
    }

    try {
      $Q = $db->query("select n.model_technical_name,
                              n.model_display_name, 
			      n.ai_model_description,
                              n.ai_model_status, 
			      n.ai_model_status_default, 
			      n.ai_model_status_fallback,
                              n.ai_model_context_window, 
			      n.ai_model_ai_capable,
                              n.ai_model_token_input_price, 
			      n.ai_model_token_output_price,
                              p.ai_model_provider_code
                       from :table_ai_models_name n
                       inner join :table_ai_models_provider p on p.ai_model_provider_id = n.ai_model_provider_id
                       order by n.sort_order");

      if ($Q === false) {
        return [];
      }

      $rows = [];

      while ($r = $Q->fetch()) {
        $rows[] = [
          'id' => $r['model_technical_name'],
          'text' => $r['model_display_name'] . ' (' . $r['ai_model_description'] . ')',
          'provider' => $r['ai_model_provider_code'],
          'context_window' => (int)$r['ai_model_context_window'],
          'status' => (int)$r['ai_model_status'],
          'is_default' => (int)$r['ai_model_status_default'],
          'is_fallback' => (int)$r['ai_model_status_fallback'],
          'ai_capable' => (int)$r['ai_model_ai_capable'],
          'input_price' => (float)$r['ai_model_token_input_price'],
          'output_price' => (float)$r['ai_model_token_output_price'],
        ];
      }
    } catch (\Throwable $e) {
      return [];
    }

    return $rows;
  }

  /**
   * Clears the in-process catalog cache. Called by the admin CRUD (Phase 2) after any write.
   */
  public static function clearCatalogCache(): void
  {
    self::$catalogCache = null;
  }

  /**
   * Retrieves the list of models as [{id,text,provider}], DB-backed with static fallback.
   *
   * @return array An array of models, each an associative array with 'id', 'text', 'provider'.
   */
  public static function getGptModel(): array
  {
    $out = [];

    foreach (self::loadCatalog() as $m) {
      $out[] = ['id' => $m['id'], 'text' => $m['text'], 'provider' => $m['provider']];
    }

    return $out;
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
    foreach (self::loadCatalog() as $m) {
      if (!empty($m['is_fallback'])) {
        return $m['id'];
      }
    }

    return 'gpt-4.1-mini';
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
    return self::getTechnicalFallbackModel();
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
   * Valid provider names: openai, anthropic, ollama, lmstudio, mistral, gemini, deepseek
   *
   * @return array Associative array [model_id => provider_name]
   */
  public static function getModelProviderMap(): array
  {
    $validProviders = ['openai', 'anthropic', 'ollama', 'lmstudio', 'mistral', 'gemini', 'deepseek'];
    $mapping = [];

    foreach (self::loadCatalog() as $model) {
      $provider = $model['provider'] ?? 'openai';

      if (!in_array($provider, $validProviders, true)) {
        $provider = 'openai';
      }

      $mapping[$model['id']] = $provider;
    }

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
    foreach (self::loadCatalog() as $m) {
      if ($m['id'] === $model && !empty($m['context_window'])) {
        return (int)$m['context_window'];
      }
    }

    return 128000;
  }

  /**
   * Returns a model's token pricing from the DB catalog as [inputPricePerMillion, outputPricePerMillion]
   * (USD per 1,000,000 tokens), or null when the model is absent OR priced at 0 (local/unseeded) so the
   * caller can fall back to its own pricing table.
   *
   * @param string $model Technical model name (e.g. 'gpt-4.1-mini')
   * @return array{0:float,1:float}|null
   */
  public static function getModelPricing(string $model): ?array
  {
    foreach (self::loadCatalog() as $m) {
      if ($m['id'] === $model) {
        // Only DB-catalog rows carry price keys; the static fallback list does not.
        // When present, the catalog is authoritative — including a legitimate 0 for local models.
        // When absent (static fallback / model not in catalog), return null so the caller
        // falls back to its own pricing table.
        if (array_key_exists('input_price', $m)) {
          return [(float)$m['input_price'], (float)$m['output_price']];
        }

        return null;
      }
    }

    return null;
  }

  /**
   * Resolves a provider's API credential from the catalog (ai_models_api), decrypted for use.
   * Returns ['api_key'=>string, 'organisation'=>?string]; empty key when the provider/credential
   * is absent so callers keep the same "no key configured" behaviour as before.
   *
   * @return array{api_key:string,organisation:?string}
   */
  public static function getProviderApiKey(string $code): array
  {
    if (!Registry::exists('Db')) {
      return ['api_key' => '', 'organisation' => null];
    }

    $db = Registry::get('Db');

    if (!$db instanceof \ClicShopping\OM\Db) {
      return ['api_key' => '', 'organisation' => null];
    }

    try {
      $Q = $db->prepare("select a.ai_model_provider_api_key, a.ai_model_organisation
                         from :table_ai_models_api a
                         inner join :table_ai_models_provider p on p.ai_model_provider_id = a.ai_model_provider_id
                         where p.ai_model_provider_code = :code");
      $Q->bindValue(':code', $code);
      $Q->execute();
      $row = $Q->fetch();
    } catch (\Throwable $e) {
      return ['api_key' => '', 'organisation' => null];
    }

    if ($row === false) {
      return ['api_key' => '', 'organisation' => null];
    }

    return [
      'api_key' => Hash::displayDecryptedDataText($row['ai_model_provider_api_key']),
      'organisation' => $row['ai_model_organisation'],
    ];
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
