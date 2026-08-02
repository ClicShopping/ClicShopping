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
   * Tokens added on top of the caller's budget so hidden reasoning does not eat the answer.
   * Measured 2026-08-02 on the ambiguity prompt: reasoning alone reaches 501 and, at a budget of
   * 600, consumes all 600 (finish_reason=length, empty content). A larger cap costs nothing —
   * unused tokens are not billed and latency stayed flat from 600 to 4096.
   */
  public const REASONING_TOKEN_HEADROOM = 1024;

  /**
   * gpt-5 members whose OWN default is not to reason: with no reasoning_effort sent they spend 0
   * reasoning tokens and accept a temperature, like a standard chat model.
   */
  private const NON_REASONING_GPT5_MODELS = ['gpt-5.4-mini'];

  /** Models the API enumerates as taking 'none' (and 'xhigh'), but NOT 'minimal'. */
  private const REASONING_EFFORT_NONE_MODELS = ['gpt-5.4-mini', 'gpt-5.6-luna'];

  /** Models the API enumerates as taking 'minimal', but NOT 'none'. */
  private const REASONING_EFFORT_MINIMAL_MODELS = ['gpt-5-mini', 'gpt-5'];

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
   * - max_completion_tokens: GPT-4.1 series, GPT-5 series (they reject max_tokens with HTTP 400)
   * - max_tokens: GPT-4, GPT-4o, Anthropic, Mistral, LM Studio models
   *
   * Reasoning headroom is applied here too, so a caller going through this helper gets the same
   * budget as one going through normalizeGenerationOptions().
   *
   * @param string $model The model name (e.g., 'gpt-4o', 'gpt-4.1-mini', 'gpt-5')
   * @param int $maxtoken The maximum number of tokens
   * @return array The model-specific parameters
   */
  public static function getModelApiParameters(string $model, int $maxtoken): array
  {
    if (self::usesCompletionTokenBudget($model)) {
      return ['max_completion_tokens' => self::applyReasoningHeadroom($model, $maxtoken)];
    }

    return ['max_tokens' => $maxtoken];
  }

  /**
   * Does this model spell its output budget max_completion_tokens?
   *
   * Measured 2026-08-02: gpt-5* and gpt-4.1* reject max_tokens with HTTP 400; every other model
   * accepts it. See unit_test/2026_08_02/gpt5_api_parameter_probe.php.
   *
   * @param string $model Model name
   * @return bool True when max_tokens must be renamed max_completion_tokens
   */
  public static function usesCompletionTokenBudget(string $model): bool
  {
    return str_starts_with($model, 'gpt-4.1') || str_starts_with($model, 'gpt-5');
  }

  /**
   * Can this model reason before answering (whether or not it will on a given call)?
   *
   * @param string $model Model name
   * @return bool True for the gpt-5 and o-series families
   */
  public static function isReasoningCapableModel(string $model): bool
  {
    return str_starts_with($model, 'gpt-5')
        || str_starts_with($model, 'o1')
        || str_starts_with($model, 'o3')
        || str_starts_with($model, 'o4');
  }

  /**
   * Reasoning-effort values this model accepts, as enumerated BY THE API itself (an invalid value
   * makes it list them). The families disagree on the cheapest tier: gpt-5.4/5.6 offer 'none' and
   * refuse 'minimal', gpt-5-mini the exact opposite. An unmeasured model gets only the tiers all
   * of them share, so an unknown addition degrades instead of failing.
   *
   * @param string $model Model name
   * @return array<int, string> Accepted values, empty when the model takes no reasoning effort
   */
  public static function supportedReasoningEfforts(string $model): array
  {
    if (!self::isReasoningCapableModel($model)) {
      return [];
    }

    return match (true) {
      in_array($model, self::REASONING_EFFORT_NONE_MODELS, true) => ['none', 'low', 'medium', 'high', 'xhigh'],
      in_array($model, self::REASONING_EFFORT_MINIMAL_MODELS, true) => ['minimal', 'low', 'medium', 'high'],
      default => ['low', 'medium', 'high'],
    };
  }

  /**
   * The configured reasoning effort (CLICSHOPPING_APP_CHATGPT_CH_REASONING_EFFORT), translated to
   * something this model accepts. 'none' and 'minimal' are the same intent — do not think — so one
   * stands in for the other; anything else unsupported is dropped rather than guessed.
   *
   * @param string $model Model name
   * @return string|null Value to send, or null to leave the model on its own default
   */
  public static function resolveReasoningEffort(string $model): ?string
  {
    $supported = self::supportedReasoningEfforts($model);
    $configured = defined('CLICSHOPPING_APP_CHATGPT_CH_REASONING_EFFORT')
      ? strtolower(trim((string)CLICSHOPPING_APP_CHATGPT_CH_REASONING_EFFORT))
      : '';

    // 'text' is the "not set" placeholder of the admin select.
    if ($supported === [] || $configured === '' || $configured === 'text') {
      return null;
    }

    if (in_array($configured, $supported, true)) {
      return $configured;
    }

    $equivalent = match ($configured) {
      'minimal' => 'none',
      'none' => 'minimal',
      'xhigh' => 'high',
      default => null,
    };

    return in_array($equivalent, $supported, true) ? $equivalent : null;
  }

  /**
   * The configured verbosity (CLICSHOPPING_APP_CHATGPT_CH_VERBOSITY), or null when the model would
   * reject it. Measured: gpt-5* takes low|medium|high, gpt-4o and gpt-4.1-mini reject the parameter.
   *
   * @param string $model Model name
   * @return string|null Value to send, or null to omit the parameter
   */
  public static function resolveVerbosity(string $model): ?string
  {
    $configured = defined('CLICSHOPPING_APP_CHATGPT_CH_VERBOSITY')
      ? strtolower(trim((string)CLICSHOPPING_APP_CHATGPT_CH_VERBOSITY))
      : '';

    if (!str_starts_with($model, 'gpt-5') || !in_array($configured, ['low', 'medium', 'high'], true)) {
      return null;
    }

    return $configured;
  }

  /**
   * Will THIS call actually spend hidden reasoning tokens?
   *
   * Not a fixed model property: it follows the effective effort. Measured 2026-08-02 —
   * gpt-5.6-luna reasons on its own default but spends 0 at effort 'none', and gpt-5.4-mini
   * defaults to no reasoning at all. Drives both the token headroom and the temperature rule.
   *
   * @param string $model Model name
   * @return bool True when the call reasons before answering
   */
  public static function willReason(string $model): bool
  {
    if (!self::isReasoningCapableModel($model)) {
      return false;
    }

    $effort = self::resolveReasoningEffort($model);

    if ($effort !== null) {
      return $effort !== 'none';
    }

    // No effort sent: the model falls back to its own default.
    return !in_array($model, self::NON_REASONING_GPT5_MODELS, true);
  }

  /**
   * Widen an output budget sized for a non-reasoning model so hidden reasoning is paid on top of
   * the answer, not out of it.
   *
   * Every caller budget in the pipeline (15 to 2300) counts VISIBLE tokens, because that is all a
   * gpt-4* model spends. A reasoning model bills its thinking to the same budget and thinks first:
   * the call then returns HTTP 200 with an EMPTY string, which the caller reads as "the model
   * answered nothing" rather than as an error. Measured on gpt-5.6-luna with the ambiguity prompt
   * (gpt5_budget_floor_probe.php and the campaign of 2026-08-02): 600 requested => 600 spent on
   * reasoning, nothing left; the same call at 1624 answers in the same 5-6 s.
   *
   * @param string $model Model name
   * @param int $maxtoken Budget the caller asked for, in visible tokens
   * @return int Budget to send
   */
  public static function applyReasoningHeadroom(string $model, int $maxtoken): int
  {
    if (!self::willReason($model)) {
      return $maxtoken;
    }

    return $maxtoken + self::REASONING_TOKEN_HEADROOM;
  }

  /**
   * Rewrite generation options into what the target model actually accepts.
   *
   * Single chokepoint for the OpenAI wire format: renames the token budget, carries the configured
   * reasoning effort and verbosity to the models that take them, grants reasoning its own token
   * headroom, and drops a temperature the call would reject. Anything else is passed through.
   *
   * @param string $model Model name
   * @param array<string, mixed> $options Generation options in OpenAI wire format
   * @return array<string, mixed> Options safe to send for this model
   */
  public static function normalizeGenerationOptions(string $model, array $options): array
  {
    if (isset($options['max_tokens']) && self::usesCompletionTokenBudget($model)) {
      $options['max_completion_tokens'] = (int)$options['max_tokens'];
      unset($options['max_tokens']);
    }

    $effort = self::resolveReasoningEffort($model);

    if ($effort !== null) {
      $options['reasoning_effort'] = $effort;
    }

    $verbosity = self::resolveVerbosity($model);

    if ($verbosity !== null) {
      $options['verbosity'] = $verbosity;
    }

    if (isset($options['max_completion_tokens'])) {
      $options['max_completion_tokens'] = self::applyReasoningHeadroom($model, (int)$options['max_completion_tokens']);
    }

    // A call that reasons accepts only the default temperature (1); any value is a 400.
    if (self::willReason($model)) {
      unset($options['temperature']);
    }

    return $options;
  }

  /**
   * Check if model uses reasoning API approach (GPT-5 style)
   *
   * Kept for the public Gpt facade; delegates so there is ONE definition. A third rule living here
   * is what let gpt-5 reach the API with gpt-4 parameters.
   *
   * @param string $model Model name
   * @return bool True if model uses reasoning API approach
   */
  public static function isReasoningApiModel(string $model): bool
  {
    return self::isReasoningCapableModel($model);
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
