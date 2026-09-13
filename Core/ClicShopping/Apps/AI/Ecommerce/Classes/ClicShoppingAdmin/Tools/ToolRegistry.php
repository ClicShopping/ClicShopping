<?php
/**
 * ToolRegistry - Domain tool resolver for Ecommerce.
 *
 * Maps LLM action names to executable step types and metadata.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Tools;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\StockForecastService;

class ToolRegistry
{
  /**
   * Resolve an LLM-selected action into an execution step.
   *
   * @param string $action Action name from LLM (e.g., inventory_forecast)
   * @param array $params Action parameters from LLM
   * @param array $intent Full intent payload (optional context)
   * @param string $query Original user query
   * @return array|null Step descriptor or null if action not recognized
   */
  public static function resolveAction(string $action, array $params, array $intent, string $query): ?array
  {
    $action = strtolower(trim($action));

    switch ($action) {
      case 'inventory_forecast':
        return self::resolveInventoryForecast($params);
      case 'stockout_risk':
        return self::resolveStockoutRisk($params);
    }

    return null;
  }

  /**
   * Build metadata for inventory forecast action.
   */
  private static function resolveInventoryForecast(array $params): array
  {
    $meta = [];

    if (isset($params['entity_id']) && is_numeric($params['entity_id'])) {
      $meta['entity_id'] = (int)$params['entity_id'];
    } elseif (isset($params['product_id']) && is_numeric($params['product_id'])) {
      $meta['entity_id'] = (int)$params['product_id'];
    }

    if (isset($params['horizon_days']) && is_numeric($params['horizon_days'])) {
      $meta['horizon_days'] = max(1, (int)$params['horizon_days']);
    }

    if (isset($params['lead_time_days']) && is_numeric($params['lead_time_days'])) {
      $meta['lead_time_days'] = max(1, (int)$params['lead_time_days']);
    }

    if (isset($params['history_days']) && is_numeric($params['history_days'])) {
      $meta['history_days'] = max(1, (int)$params['history_days']);
    }

    if (isset($params['service_level']) && is_numeric($params['service_level'])) {
      $serviceLevel = (float)$params['service_level'];
      $meta['service_level'] = min(0.999, max(0.50, $serviceLevel));
    }

    $meta['action'] = 'inventory_forecast';
    $meta['action_params'] = $params;

    return [
      'step_type' => 'domain_tool',
      'meta' => $meta,
      'planner' => 'domain_tool_inventory_forecast',
    ];
  }

  /**
   * Build metadata for the stock-out risk list action ("products soon out of stock").
   */
  private static function resolveStockoutRisk(array $params): array
  {
    $meta = ['action' => 'stockout_risk', 'action_params' => $params];

    if (isset($params['limit']) && is_numeric($params['limit'])) {
      $meta['limit'] = max(1, (int)$params['limit']);
    }

    if (isset($params['horizon_days']) && is_numeric($params['horizon_days'])) {
      $meta['horizon_days'] = max(1, (int)$params['horizon_days']);
    }

    if (isset($params['lead_time_days']) && is_numeric($params['lead_time_days'])) {
      $meta['lead_time_days'] = max(1, (int)$params['lead_time_days']);
    }

    if (isset($params['history_days']) && is_numeric($params['history_days'])) {
      $meta['history_days'] = max(1, (int)$params['history_days']);
    }

    if (isset($params['service_level']) && is_numeric($params['service_level'])) {
      $meta['service_level'] = min(0.999, max(0.50, (float)$params['service_level']));
    }

    return [
      'step_type' => 'domain_tool',
      'meta' => $meta,
      'planner' => 'domain_tool_stockout_risk',
    ];
  }

  /**
   * Execute an action for the Ecommerce domain.
   */
  public static function executeAction(string $action, array $params, array $context = []): array
  {
    $action = strtolower(trim($action));

    switch ($action) {
      case 'inventory_forecast':
        return self::executeInventoryForecast($params);
      case 'stockout_risk':
        return self::executeStockoutRisk($params);
    }

    return [
      'type' => 'domain_tool',
      'success' => false,
      'error' => 'Unknown domain tool action',
      'text_response' => 'Unknown domain tool action.'
    ];
  }

  /**
   * Execute inventory forecast action.
   */
  private static function executeInventoryForecast(array $params): array
  {
    $entityId = (int)($params['entity_id'] ?? $params['product_id'] ?? 0);
    $horizonDays = (int)($params['horizon_days'] ?? 30);
    $leadTimeDays = isset($params['lead_time_days']) ? (int)$params['lead_time_days'] : null;
    $daysBack = isset($params['history_days']) ? (int)$params['history_days'] : null;
    $serviceLevel = isset($params['service_level']) ? (float)$params['service_level'] : null;

    if ($entityId <= 0) {
      return [
        'type' => 'inventory_forecast',
        'success' => false,
        'error' => 'Missing product id for inventory forecast',
        'text_response' => 'Missing product id for inventory forecast.'
      ];
    }

    $forecast = StockForecastService::forecastForProduct(
      $entityId,
      $horizonDays,
      $leadTimeDays,
      $daysBack,
      $serviceLevel
    );

    if (!($forecast['success'] ?? false)) {
      return [
        'type' => 'inventory_forecast',
        'success' => false,
        'error' => $forecast['error'] ?? 'Forecast failed',
        'text_response' => 'Stock forecast unavailable.'
      ];
    }

    return [
      'type' => 'inventory_forecast',
      'success' => true,
      'entity_id' => $forecast['products_id'],
      'entity_type' => 'products',
      'data' => $forecast,
      'text_response' => StockForecastService::buildSummary($forecast)
    ];
  }

  /**
   * Execute the stock-out risk list action: rank the products most at risk of rupture.
   * Returns the structured forecasts as data; the transparent per-product rendering is the
   * restitution layer's job (Phase 3), so the text_response stays a plain per-product join.
   */
  private static function executeStockoutRisk(array $params): array
  {
    $limit = (int)($params['limit'] ?? 10);
    $horizonDays = (int)($params['horizon_days'] ?? 30);
    $leadTimeDays = isset($params['lead_time_days']) ? (int)$params['lead_time_days'] : null;
    $daysBack = isset($params['history_days']) ? (int)$params['history_days'] : null;
    $serviceLevel = isset($params['service_level']) ? (float)$params['service_level'] : null;

    $risks = StockForecastService::forecastTopRiskProducts(
      $limit,
      $horizonDays,
      $leadTimeDays,
      $daysBack,
      $serviceLevel
    );

    $lines = array_map(
      static fn(array $forecast): string => StockForecastService::buildSummary($forecast),
      $risks
    );

    return [
      'type' => 'stockout_risk',
      'success' => true,
      'entity_type' => 'products',
      'data' => $risks,
      'count' => count($risks),
      'text_response' => implode("\n", $lines)
    ];
  }
}
