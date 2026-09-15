<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Config;

/**
 * Declared defaults of the Ecommerce domain's tunable constants
 *
 * ONE literal per constant, here and nowhere else; consumers read it through get()/int()/float()
 * and the defined constant wins when it exists. A consumer that repeats the value in a
 * `defined() ? … : <literal>` ternary reintroduces the divergence this class removes.
 *
 * Deliberately NOT in Core/ClicShopping/AI/Config: those defaults are domain-agnostic, these
 * are Ecommerce. A constant that is already the declared `$default` of a Params file stays on
 * its class - the Params file references it, so there is no duplicated literal to remove.
 *
 * Identity constants - actor/critic ids, action and output types, prompt tokens, table names,
 * format versions - are contracts, not settings: they stay next to the code that implements them.
 *
 * @see \ClicShopping\AI\Config\TechnicalDefaults The agnostic counterpart this mirrors
 */
class EcommerceDefaults
{
  /**
   * Constant name => declared default.
   */
  private const DEFAULTS = [
    // Review sentiment (RS)
    'CLICSHOPPING_APP_ECOMMERCE_EC_RS_MIN_SUPPORTED' => 0.90,
    'CLICSHOPPING_APP_ECOMMERCE_EC_RS_MIN_AI_SUMMARY_VOTES' => 3,

    // SEO services (SEO)
    'CLICSHOPPING_APP_ECOMMERCE_EC_SEO_LLM_CACHE_TTL' => 3600,
    'CLICSHOPPING_APP_ECOMMERCE_EC_SEO_LLM_MAX_RETRIES' => 3,
    'CLICSHOPPING_APP_ECOMMERCE_EC_SEO_LLM_BACKOFF_MS' => 1000,
    'CLICSHOPPING_APP_ECOMMERCE_EC_SEO_TRANSLATION_CACHE_TTL' => 604800,

    // FAQ generation and grounding (FAQ)
    'CLICSHOPPING_APP_ECOMMERCE_EC_FAQ_MAX_QUESTION_LENGTH' => 500,
    'CLICSHOPPING_APP_ECOMMERCE_EC_FAQ_MAX_ANSWER_LENGTH' => 2000,
    'CLICSHOPPING_APP_ECOMMERCE_EC_FAQ_GROUNDING_THRESHOLD' => 0.45,
    'CLICSHOPPING_APP_ECOMMERCE_EC_FAQ_MIN_GROUNDED_ITEMS' => 2,
    'CLICSHOPPING_APP_ECOMMERCE_EC_FAQ_MAX_RETRIES' => 2,
    'CLICSHOPPING_APP_ECOMMERCE_EC_FAQ_MIN_SUPPORT' => 1.0,

    // CockpitAI scoring, promotion and rules (CAI)
    'CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MIN_SAMPLE_SIZE' => 10,
    'CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MIN_THRESHOLD_GAP' => 10.0,
    'CLICSHOPPING_APP_ECOMMERCE_EC_CAI_CATALOG_MEDIAN_TTL' => '60',
    'CLICSHOPPING_APP_ECOMMERCE_EC_CAI_THRESHOLD_CACHE_TTL' => '60',
    'CLICSHOPPING_APP_ECOMMERCE_EC_CAI_HIGH_INTENT_THRESHOLD' => 0.7,
    'CLICSHOPPING_APP_ECOMMERCE_EC_CAI_MAX_ADJUSTMENT_PCT' => 0.20,
    'CLICSHOPPING_APP_ECOMMERCE_EC_CAI_VELOCITY_THRESHOLD' => 2.0,

    // Analytics, web search, prompts and recommendations
    'CLICSHOPPING_APP_ECOMMERCE_EC_STOCK_MAX_ROWS' => 25,
    'CLICSHOPPING_APP_ECOMMERCE_EC_WEB_MAX_PROMPT_TOKENS' => 500,
    'CLICSHOPPING_APP_ECOMMERCE_EC_HALLUCINATION_FUTURE_YEAR_HORIZON' => 9,
    'CLICSHOPPING_APP_ECOMMERCE_EC_SEO_THIN_CONTENT_WORDS' => 50,
    'CLICSHOPPING_APP_ECOMMERCE_EC_SEO_MIN_PRESERVATION' => 0.95,
    'CLICSHOPPING_APP_ECOMMERCE_EC_PROMPT_MAX_PRODUCTS' => 10,
    'CLICSHOPPING_APP_ECOMMERCE_EC_RECO_MIN_ORDERS_PERSONAL' => 3,
    'CLICSHOPPING_APP_ECOMMERCE_EC_RECO_MIN_COSINE_DISTANCE' => 0.15,
    'CLICSHOPPING_APP_ECOMMERCE_EC_RECO_MIN_RELEVANCE_SCORE' => 2,

    // Fallback defaults for constants a Params file installs in the database
    'CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P1' => '5',
    'CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P2' => '8',
    'CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P3' => '12',
    'CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P4' => '15',
    'CLICSHOPPING_APP_ECOMMERCE_CAI_MARGIN_RATE' => '15',
    'CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH' => '70',
    'CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW' => '30',
    'CLICSHOPPING_APP_ECOMMERCE_UCP_RATE_LIMIT' => '100',
  ];

  /**
   * @return array<string, mixed> Every declared default, constant name => value
   */
  public static function all(): array
  {
    return self::DEFAULTS;
  }

  /**
   * @param string $key Constant name
   * @return mixed The defined constant when it exists, the declared default otherwise
   * @throws \InvalidArgumentException When the key was never declared here
   */
  public static function get(string $key): mixed
  {
    if (\defined($key)) {
      return \constant($key);
    }

    if (!\array_key_exists($key, self::DEFAULTS)) {
      throw new \InvalidArgumentException('Undeclared Ecommerce constant: ' . $key);
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
