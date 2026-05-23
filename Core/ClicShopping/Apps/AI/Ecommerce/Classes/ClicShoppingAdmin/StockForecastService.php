<?php
/**
 * StockForecastService
 *
 * Domain service for stock forecast and replenishment risk.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductStock;

class StockForecastService
{
  /**
   * Forecast stock renewal risk for a single product.
   */
  public static function forecastForProduct(int $productId, int $horizonDays = 30, int $leadTimeDays = 7, int $daysBack = 90, float $serviceLevel = 0.95): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                 p.products_quantity,
                                                 p.products_quantity_alert,
                                                 p.products_model,
                                                 pd.products_name
                                          from :table_products p
                                          left join :table_products_description pd
                                            on p.products_id = pd.products_id
                                          where p.products_id = :products_id
                                          limit 1
                                         ');
    $Qproduct->bindInt(':products_id', $productId);
    $Qproduct->execute();

    if (!$Qproduct->fetch()) {
      return [
        'success' => false,
        'error' => 'Product not found',
        'products_id' => $productId,
      ];
    }

    $dailyDemand = ProductStock::getDailyDemandSeriesByProducts($productId, $daysBack);
    $forecast = ProductStock::calculateDemandForecast($dailyDemand, $horizonDays);
    $safetyStock = ProductStock::calculateSafetyStockFromDailyDemand($dailyDemand, $leadTimeDays, $serviceLevel);

    $currentStock = (float)$Qproduct->value('products_quantity');
    $expectedDemand = (float)$forecast['mean_total'];
    $stdDevDemand = (float)$forecast['stddev_total'];

    $stockoutProbability = ProductStock::calculateStockoutProbability($currentStock, $expectedDemand, $stdDevDemand);
    $reorderQty = ProductStock::calculateReorderQuantity($currentStock, $expectedDemand, $safetyStock);

    return [
      'success' => true,
      'products_id' => (int)$Qproduct->value('products_id'),
      'products_name' => $Qproduct->value('products_name'),
      'products_model' => $Qproduct->value('products_model'),
      'current_stock' => $currentStock,
      'alert_stock' => (float)$Qproduct->value('products_quantity_alert'),
      'horizon_days' => $horizonDays,
      'lead_time_days' => $leadTimeDays,
      'history_days' => $daysBack,
      'service_level' => $serviceLevel,
      'forecast' => $forecast,
      'safety_stock' => round($safetyStock, 2),
      'stockout_probability' => round($stockoutProbability, 4),
      'reorder_quantity' => round($reorderQty, 2)
    ];
  }

  /**
   * Forecast stock renewal risk for a list of products (top risk).
   */
  public static function forecastTopRiskProducts(int $limit = 10, int $horizonDays = 30, int $leadTimeDays = 7, int $daysBack = 90, float $serviceLevel = 0.95): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $limit = max(1, (int)$limit);

    $Qproducts = $CLICSHOPPING_Db->prepare('select p.products_id
                                           from :table_products p
                                           where p.products_status = 1
                                           order by p.products_id desc
                                           limit :limit
                                          ');
    $Qproducts->bindInt(':limit', $limit);
    $Qproducts->execute();

    $results = [];
    while ($Qproducts->fetch()) {
      $forecast = self::forecastForProduct(
        $Qproducts->valueInt('products_id'),
        $horizonDays,
        $leadTimeDays,
        $daysBack,
        $serviceLevel
      );

      if ($forecast['success'] ?? false) {
        $results[] = $forecast;
      }
    }

    usort($results, function ($a, $b) {
      return ($b['stockout_probability'] <=> $a['stockout_probability']);
    });

    return $results;
  }

  /**
   * Build a concise human-readable summary for chat output.
   */
  public static function buildSummary(array $forecast): string
  {
    if (!($forecast['success'] ?? false)) {
      return 'Stock forecast unavailable.';
    }

    $name = $forecast['products_name'] ?: ('Product ' . $forecast['products_id']);
    $prob = round(($forecast['stockout_probability'] ?? 0) * 100, 1);
    $reorderQty = round($forecast['reorder_quantity'] ?? 0, 2);
    $safety = round($forecast['safety_stock'] ?? 0, 2);
    $horizon = (int)($forecast['horizon_days'] ?? 30);

    return sprintf(
      '%s: %s%% risk of stock-out in %d days. Suggested reorder: %s (safety stock %s).',
      $name,
      $prob,
      $horizon,
      $reorderQty,
      $safety
    );
  }
}
