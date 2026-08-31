<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Config;

/**
 * Declared defaults of the technical RAG constants
 *
 * ONE literal per constant, here and nowhere else. `TechnicalConfig.php` turns this table into
 * `define()` calls, and application code reads it through get()/int()/float(): the constant wins
 * when it exists, the declared default answers otherwise. A consumer that repeats the value in a
 * `defined() ? … : <literal>` ternary reintroduces the divergence this class removes — measured
 * 2026-08-10, twelve such sites, one already out of sync with TechnicalConfig.
 *
 * This class is deliberately NOT `TechnicalConfig`: that file is a bare script guarded by
 * `RA_STATUS` in Core/config_clicshopping.php, and giving it a class body would let the autoloader
 * execute its `define()` calls behind the gate.
 *
 * @see \ClicShopping\AI\Config\TechnicalConfig
 */
class TechnicalDefaults
{
  /**
   * Constant name => declared default, types included ('True' strings, real booleans and nulls
   * are all deliberate: the consumers test them differently).
   */
  private const DEFAULTS = [
    // Active domain for the RAG BI system: 'Ecommerce', 'Hr', 'Finance', 'Trading'…
    'CLICSHOPPING_APP_CHATGPT_RA_ACTIVITIES' => 'Ecommerce',

    // Limits and thresholds
    'CLICSHOPPING_APP_CHATGPT_RA_MAX_PROMPT_LENGTH' => 100000,
    'CLICSHOPPING_APP_CHATGPT_RA_SQL_SAFETY_LIMIT' => 10000,
    'CLICSHOPPING_APP_CHATGPT_RA_MIN_SIMILARITY_SCORE' => 0.25,
    'CLICSHOPPING_APP_CHATGPT_RA_MEMORY_MIN_SCORE' => 0.85,
    'CLICSHOPPING_APP_CHATGPT_RA_MAX_RESULTS_PER_STORE' => 5,
    'CLICSHOPPING_APP_CHATGPT_RA_RERANKING_OUTPUT' => 5,

    // Output ceiling for the English normalisation of the user input. A CEILING, not a target:
    // it costs nothing unless consumed. A hit ceiling truncates silently; TranslationHandler reports it.
    'CLICSHOPPING_APP_CHATGPT_CH_TRANSLATION_MAX_TOKEN' => 500,

    // How many analytics steps one question may be split into — one schema window per step
    'CLICSHOPPING_APP_CHATGPT_RA_MAX_ANALYTICS_STEPS' => 3,

    // Cache TTL, in seconds. A null WARMUP_TTL means "follow CACHE_TTL".
    'CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL' => 3600,
    'CLICSHOPPING_APP_CHATGPT_RA_CACHE_WARMUP_TTL' => null,

    // Fallback
    'CLICSHOPPING_APP_CHATGPT_RA_ENABLE_WEB_FALLBACK' => 'True',
    'CLICSHOPPING_APP_CHATGPT_RA_ENABLE_LLM_FALLBACK' => 'True',

    // Schema RAG — MAX_TABLES is the schema window, and it is zero-sum (see Agents/DATABASE.md §5d)
    'CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG' => 'True',
    'CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_USE_EMBEDDINGS' => 'True',
    'CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_MAX_TABLES' => 5,

    // Reasoning agent
    'CLICSHOPPING_APP_CHATGPT_RA_MAX_REASONING_STEPS' => 10,
    'CLICSHOPPING_APP_CHATGPT_RA_CONSISTENCY_PATHS' => 3,
    'CLICSHOPPING_APP_CHATGPT_RA_TREE_PATHS' => 3,
    'CLICSHOPPING_APP_CHATGPT_RA_REASONING_MODE' => 'chain_of_thought',

    // Security, technical
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_THREAT_THRESHOLD' => 0.7,
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_PATTERN_FALLBACK' => false,
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_ALL_QUERIES' => false,
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_BLOCKED_ONLY' => true,
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_RESPONSE_VALIDATION' => true,

    // Char ceiling of the result handed to the critic; the original is logged on truncation.
    'CLICSHOPPING_APP_CHATGPT_RA_CRITIC_RESULT_MAX_CHARS' => 8000,

    // Char ceiling of the answer sent to the embedding model, so a runaway answer is not paid in full.
    'CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS' => 32000,

    // Security, alerting
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_THRESHOLD' => 10,
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_HIGH_THREAT_THRESHOLD' => 20,
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_COOLDOWN' => 60,
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_DIGEST_MODE' => true,
    'CLICSHOPPING_APP_CHATGPT_RA_SECURITY_FAILURE_ALERTS' => true,

    // Calculator tool — admin-level switch; its technical settings are class constants
    'CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED' => 'True',

    // Parallel LLM executor
    'CLICSHOPPING_APP_CHATGPT_RA_PARALLEL_TIMEOUT' => 30,
    'CLICSHOPPING_APP_CHATGPT_RA_PARALLEL_MAX_CONCURRENT' => 5,

    // Query execution timeout: must stay >= the HybridQueryProcessor cold-cache timeout
    'CLICSHOPPING_APP_CHATGPT_RA_MAX_EXECUTION_TIME' => 120,

    // Hybrid query decomposition — null means "use the default provider / the default debug flag"
    'CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_STATUS' => 'True',
    'CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_LLM_PROVIDER' => null,
    'CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_DEBUG' => null,
  ];

  /**
   * Every declared default, for the define() pass of TechnicalConfig.php
   *
   * @return array Constant name => declared default
   */
  public static function all(): array
  {
    return self::DEFAULTS;
  }

  /**
   * Effective value of a technical constant
   *
   * @param string $key Constant name
   * @return mixed The constant when defined, the declared default otherwise
   * @throws \InvalidArgumentException When the key is not declared here
   */
  public static function get(string $key): mixed
  {
    if (\defined($key)) {
      return \constant($key);
    }

    if (!\array_key_exists($key, self::DEFAULTS)) {
      throw new \InvalidArgumentException('Undeclared technical constant: ' . $key);
    }

    return self::DEFAULTS[$key];
  }

  /**
   * @param string $key Constant name
   * @return int
   */
  public static function int(string $key): int
  {
    return (int)self::get($key);
  }

  /**
   * @param string $key Constant name
   * @return float
   */
  public static function float(string $key): float
  {
    return (float)self::get($key);
  }
}
