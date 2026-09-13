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
  public static function forecastForProduct(int $productId, int $horizonDays = 30, ?int $leadTimeDays = null, ?int $daysBack = null, ?float $serviceLevel = null): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $leadTimeDays = $leadTimeDays ?? ProductStock::configuredLeadTimeDays();
    $daysBack = $daysBack ?? self::configuredHistoryDays();
    $serviceLevel = $serviceLevel ?? self::configuredServiceLevel();

    $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                 p.products_quantity,
                                                 p.products_quantity_alert,
                                                 p.products_shipping_delay_out_of_stock,
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
    $productAlert = (float)$Qproduct->value('products_quantity_alert');
    $alertStock = ProductStock::effectiveAlertStock($productAlert);
    $expectedDemand = (float)$forecast['mean_total'];
    $stdDevDemand = (float)$forecast['stddev_total'];
    $meanDaily = (float)$forecast['mean_daily'];

    $stockoutProbability = ProductStock::calculateStockoutProbability($currentStock, $expectedDemand, $stdDevDemand);
    $reorderQty = ProductStock::calculateReorderQuantity($currentStock, $expectedDemand, $safetyStock);

    // Days until the stock hits zero at the current mean daily rate; null when there is no demand.
    $daysUntilStockout = ($meanDaily > 0.0 && $currentStock > 0.0) ? ($currentStock / $meanDaily) : null;

    $shippingDelayOutOfStock = (string)$Qproduct->value('products_shipping_delay_out_of_stock');
    if ($shippingDelayOutOfStock === '') {
      $shippingDelayOutOfStock = \defined('DISPLAY_SHIPPING_DELAY_OUT_OF_STOCK') ? (string)\constant('DISPLAY_SHIPPING_DELAY_OUT_OF_STOCK') : '';
    }

    return [
      'success' => true,
      'products_id' => (int)$Qproduct->value('products_id'),
      'products_name' => $Qproduct->value('products_name'),
      'products_model' => $Qproduct->value('products_model'),
      'current_stock' => $currentStock,
      'alert_stock' => $alertStock,
      // Which constant the threshold came from, so the answer can state it (spec: transparency).
      'threshold_source' => $productAlert > 0 ? 'products_quantity_alert' : ($alertStock > 0 ? 'stock_reorder_level' : 'none'),
      'below_alert' => $alertStock > 0 && $currentStock <= $alertStock,
      'horizon_days' => $horizonDays,
      'lead_time_days' => $leadTimeDays,
      'history_days' => $daysBack,
      'service_level' => $serviceLevel,
      'forecast' => $forecast,
      'safety_stock' => round($safetyStock, 2),
      'stockout_probability' => round($stockoutProbability, 4),
      'reorder_quantity' => round($reorderQty, 2),
      'days_until_stockout' => $daysUntilStockout !== null ? round($daysUntilStockout, 1) : null,
      'estimated_stockout_date' => $daysUntilStockout !== null ? date('Y-m-d', time() + (int)ceil($daysUntilStockout) * 86400) : null,
      'risk_status' => self::classifyStockStatus($currentStock, $alertStock, $daysUntilStockout, $leadTimeDays, $horizonDays),
      'risk_level' => self::classifyRiskLevel($stockoutProbability),
      'order_velocity' => round($meanDaily, 2),
      'order_velocity_level' => self::classifyVelocity($meanDaily),
      // Commercial rules, not stock thresholds: presented separately (spec). Read under defined().
      'stock_check' => \defined('STOCK_CHECK') && \constant('STOCK_CHECK') == 'true',
      'allow_checkout' => \defined('STOCK_ALLOW_CHECKOUT') && \constant('STOCK_ALLOW_CHECKOUT') == 'true',
      'shipping_delay_out_of_stock' => $shippingDelayOutOfStock,
    ];
  }

  /**
   * Classify a product's replenishment status against its thresholds and forecast. Order matters:
   * out-of-stock, then below-threshold, then a forecast rupture inside lead time / inside horizon.
   *
   * @return string out_of_stock | below_threshold | rupture_within_lead_time | rupture_within_horizon | ok
   */
  private static function classifyStockStatus(float $currentStock, float $threshold, ?float $daysUntilStockout, int $leadTimeDays, int $horizonDays): string
  {
    if ($currentStock <= 0.0) {
      return 'out_of_stock';
    }

    if ($threshold > 0.0 && $currentStock <= $threshold) {
      return 'below_threshold';
    }

    if ($daysUntilStockout !== null) {
      if ($daysUntilStockout <= $leadTimeDays) {
        return 'rupture_within_lead_time';
      }

      if ($daysUntilStockout <= $horizonDays) {
        return 'rupture_within_horizon';
      }
    }

    return 'ok';
  }

  /**
   * Risk level from the stock-out probability.
   *
   * @return string low | medium | high
   */
  private static function classifyRiskLevel(float $stockoutProbability): string
  {
    if ($stockoutProbability >= 0.7) {
      return 'high';
    }

    return $stockoutProbability >= 0.4 ? 'medium' : 'low';
  }

  /**
   * Order velocity band from the mean daily demand.
   * ponytail: absolute unit/day thresholds are a heuristic knob; tune per catalogue if a shop's
   * typical volumes make them misclassify.
   *
   * @return string none | low | medium | high | very_high
   */
  private static function classifyVelocity(float $meanDaily): string
  {
    if ($meanDaily <= 0.0) {
      return 'none';
    }

    if ($meanDaily < 0.5) {
      return 'low';
    }

    if ($meanDaily < 2.0) {
      return 'medium';
    }

    return $meanDaily < 5.0 ? 'high' : 'very_high';
  }

  /**
   * Forecast replenishment risk across the catalogue and return the products most at risk.
   *
   * The SQL only SELECTS candidates (active products, lowest stock first — the ones nearest a
   * rupture); ProductStock does the maths and the ranking, per the domain architecture.
   * ponytail: the candidate pool is capped for cost; a high-stock/high-velocity product outside
   * the pool can be missed — raise $candidatePool or add a velocity pre-filter if a shop needs it.
   *
   * @return array<int, array> Enriched forecasts, most at risk first, products not at risk removed
   */
  public static function forecastTopRiskProducts(int $limit = 10, int $horizonDays = 30, ?int $leadTimeDays = null, ?int $daysBack = null, ?float $serviceLevel = null, int $candidatePool = 100): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $limit = max(1, (int)$limit);
    $candidatePool = max($limit, (int)$candidatePool);

    $Qproducts = $CLICSHOPPING_Db->prepare('select p.products_id
                                           from :table_products p
                                           where p.products_status = 1
                                           order by p.products_quantity asc
                                           limit :limit
                                          ');
    $Qproducts->bindInt(':limit', $candidatePool);
    $Qproducts->execute();

    $forecasts = [];
    while ($Qproducts->fetch()) {
      $forecasts[] = self::forecastForProduct(
        $Qproducts->valueInt('products_id'),
        $horizonDays,
        $leadTimeDays,
        $daysBack,
        $serviceLevel
      );
    }

    return self::rankRiskCandidates($forecasts, $limit);
  }

  /**
   * Keep the products actually at risk and order them most-critical first: higher stock-out
   * probability, then the sooner rupture. Pure — the DB-free half of forecastTopRiskProducts.
   *
   * @param array<int, array> $forecasts forecastForProduct() results
   * @return array<int, array> At-risk forecasts, most critical first, capped at $limit
   */
  public static function rankRiskCandidates(array $forecasts, int $limit): array
  {
    $atRisk = array_values(array_filter(
      $forecasts,
      static fn(array $f): bool => ($f['success'] ?? false) && ($f['risk_status'] ?? 'ok') !== 'ok'
    ));

    usort($atRisk, static function (array $a, array $b): int {
      return ($b['stockout_probability'] <=> $a['stockout_probability'])
          ?: (($a['days_until_stockout'] ?? PHP_INT_MAX) <=> ($b['days_until_stockout'] ?? PHP_INT_MAX));
    });

    return array_slice($atRisk, 0, max(1, $limit));
  }

  /**
   * Service level of the safety-stock computation, from the CockpitAI configuration.
   * Guarded by defined(): the row does not exist until the CAI configuration is saved.
   * ONE literal for this default, here and nowhere else.
   *
   * @return float Probability in [0.50 .. 0.999]
   */
  public static function configuredServiceLevel(): float
  {
    $level = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STOCK_SERVICE_LEVEL')
      ? (float)CLICSHOPPING_APP_ECOMMERCE_CAI_STOCK_SERVICE_LEVEL
      : 0.95;

    return min(0.999, max(0.50, $level));
  }

  /**
   * Length of the sales history the demand series is built from, from the CockpitAI
   * configuration. ONE literal for this default, here and nowhere else.
   *
   * @return int Number of days, at least 1
   */
  public static function configuredHistoryDays(): int
  {
    $days = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STOCK_HISTORY_DAYS')
      ? (int)CLICSHOPPING_APP_ECOMMERCE_CAI_STOCK_HISTORY_DAYS
      : 90;

    return max(1, $days);
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

    $summary = sprintf(
      '%s: %s%% risk of stock-out in %d days. Suggested reorder: %s (safety stock %s).',
      $name,
      $prob,
      $horizon,
      $reorderQty,
      $safety
    );

    if ($forecast['below_alert'] ?? false) {
      $summary .= sprintf(
        ' Stock is at or below the alert threshold: %s in stock for a threshold of %s.',
        round($forecast['current_stock'] ?? 0, 2),
        round($forecast['alert_stock'] ?? 0, 2)
      );
    }

    return $summary;
  }
}
