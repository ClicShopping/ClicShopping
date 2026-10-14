<?php
/**
 * ApiCostCalculator
 *
 * Computes API costs from token usage, using the DB model catalog (ai_models_*) as the single
 * source of truth for pricing. LLM pricing evolves constantly, so no prices are hardcoded here:
 * a model that is not in the catalog is treated as an error condition (cost 0 + logged), never
 * estimated from a stale built-in table.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

class ApiCostCalculator
{
  /**
   * Calculates the total cost of an API call from the catalogued per-model price.
   *
   * The DB catalog is authoritative: prices are stored per 1,000,000 tokens (USD) and every model
   * in use is catalogued. If a model is absent from the catalog, its cost is recorded as 0 and an
   * error is logged (fail-loud) — we never guess with hardcoded prices that go stale as LLMs change.
   *
   * @param string $model Model name (must match a catalogued model_technical_name)
   * @param int $promptTokens Prompt tokens
   * @param int $completionTokens Completion tokens
   * @return float Total cost in USD (0.0 when the model is not catalogued)
   */
  public static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
  {
    $catalogPricing = Gpt::getModelPricing($model);

    if ($catalogPricing === null) {
      error_log("[ApiCostCalculator] No catalog pricing for model '{$model}' — cost recorded as 0. Add the model to the ai_models catalog.");

      return 0.0;
    }

    [$inputPerMillion, $outputPerMillion] = $catalogPricing;

    $promptCost = ($promptTokens / 1000000) * $inputPerMillion;
    $completionCost = ($completionTokens / 1000000) * $outputPerMillion;

    return round($promptCost + $completionCost, 6);
  }
}
