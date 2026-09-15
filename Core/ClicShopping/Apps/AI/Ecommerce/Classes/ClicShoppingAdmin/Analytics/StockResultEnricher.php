<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Analytics;

use ClicShopping\AI\InterfacesAI\AnalyticsResultEnricherInterface;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\StockForecastService;
use ClicShopping\Apps\AI\Ecommerce\Config\EcommerceDefaults;

/**
 * StockResultEnricher
 *
 * Adds the predictive stock columns (days before rupture, probability, velocity) to the rows
 * of a stock question, so the analytical answer says WHEN a product runs out and not only
 * that it is below its threshold.
 *
 * The trigger is the SHAPE of the rows — a product id plus a stock quantity column — never a
 * pattern on the question (Pure LLM doctrine). Any other result set is returned untouched.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Analytics
 */
final class StockResultEnricher implements AnalyticsResultEnricherInterface
{
  /** One forecast costs several queries per product; a wide result set is not a stock question. */

  /**
   * Enrich stock rows with their forecast, or return the rows unchanged.
   *
   * @param array<int|string, mixed> $rows Executed query rows
   * @return array<int|string, mixed> Rows, with the forecast columns when they apply
   */
  public function enrich(array $rows): array
  {
    if (!$this->isStockResult($rows)) {
      return $rows;
    }

    foreach ($rows as $index => $row) {
      if (!\is_array($row)) {
        continue;
      }

      $productId = (int)($row['products_id'] ?? 0);

      if ($productId <= 0) {
        continue;
      }

      $forecast = StockForecastService::forecastForProduct($productId);

      if (($forecast['success'] ?? false) !== true) {
        continue;
      }

      // Union, not overwrite: a column the SQL already selected wins.
      $rows[$index] = $row + [
        'days_until_stockout' => $forecast['days_until_stockout'],
        'estimated_stockout_date' => $forecast['estimated_stockout_date'],
        'stockout_probability' => $forecast['stockout_probability'],
        'daily_sales_velocity' => $forecast['order_velocity'],
        'recommended_reorder_quantity' => $forecast['reorder_quantity'],
        'stock_risk_status' => $forecast['risk_status'],
        'stock_risk_level' => $forecast['risk_level'],
        'forecast_horizon_days' => $forecast['horizon_days'],
      ];
    }

    return $rows;
  }

  /**
   * A stock result: bounded, row-shaped, carrying a product id and a stock quantity column.
   *
   * @param array<int|string, mixed> $rows Executed query rows
   * @return bool True when the forecast applies to these rows
   */
  private function isStockResult(array $rows): bool
  {
    if ($rows === [] || \count($rows) > EcommerceDefaults::int('CLICSHOPPING_APP_ECOMMERCE_EC_STOCK_MAX_ROWS')) {
      return false;
    }

    $first = reset($rows);

    if (!\is_array($first) || !isset($first['products_id'])) {
      return false;
    }

    return \array_key_exists('products_quantity', $first)
      || \array_key_exists('products_quantity_alert', $first);
  }
}
