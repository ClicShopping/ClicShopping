<?php
/**
 * ApiCostCalculator
 *
 * Computes API costs from token usage, using the DB model catalog (ai_models_*) as the single
 * source of truth for pricing. LLM pricing evolves constantly, so no prices are hardcoded here:
 * an uncatalogued model yields null (price unavailable), never an estimate from a stale table.
 * Only a cost computed against a real catalog price may be 0.0.
 *
 * The result is an UPPER BOUND: every input token is priced at full rate, while the provider
 * already discounts replayed prefixes (prompt_tokens_details.cached_tokens, ~30% of the input on
 * the analytics path). That volume is not stored and the discount rate is published nowhere.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

class ApiCostCalculator
{
  /**
   * Calculates the total cost of an API call from the catalogued per-model price.
   *
   * The DB catalog is authoritative: prices are stored per 1,000,000 tokens (USD) and every model
   * in use is catalogued. Null and 0.0 are two different answers and must never be merged: null
   * means no price is available, 0.0 means the catalog prices this model at zero.
   *
   * @param string $model Model name (must match a catalogued model_technical_name)
   * @param int $promptTokens Prompt tokens
   * @param int $completionTokens Completion tokens
   * @return float|null Total cost in USD, or null when the model carries no catalog price
   */
  public static function calculateCost(string $model, int $promptTokens, int $completionTokens): ?float
  {
    $catalogPricing = Gpt::getModelPricing($model);

    if ($catalogPricing === null) {
      error_log("[ApiCostCalculator] No catalog pricing for model '{$model}' — cost left unavailable. Add the model to the ai_models catalog.");

      return null;
    }

    [$inputPerMillion, $outputPerMillion] = $catalogPricing;

    $promptCost = ($promptTokens / 1000000) * $inputPerMillion;
    $completionCost = ($completionTokens / 1000000) * $outputPerMillion;

    return round($promptCost + $completionCost, 6);
  }
}
