<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\AI\DomainsAI\Analytics\Planning\MetricType;

/**
 * MetricCatalog
 *
 * The identity card of every metric this domain can plan: its GRAIN and its TYPE.
 * Not the SQL that computes it — that stays in rag_analytics_agent.txt, which is why
 * this catalogue can never disagree with the generator about how a value is built.
 *
 * Identity is a platform guarantee, not a merchant preference: a margin percentage IS a
 * rate. Shop CONVENTIONS (the cost basis of the margin) live in configuration instead.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin
 */
class MetricCatalog
{
  /**
   * @return array<string, array{grain: string, type: string, definition: string}>
   */
  public static function all(): array
  {
    return [
      'revenue_ttc' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_revenue_ttc',
      ],
      'revenue_ht' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_revenue_ht',
      ],
      'line_revenue' => [
        'grain' => 'order_line',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_line_revenue',
      ],
      'average_cart' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_average_cart',
      ],
      'quantity_sold' => [
        'grain' => 'order_line',
        'type' => MetricType::COUNT,
        'definition' => 'text_metric_quantity_sold',
      ],
      'orders_count' => [
        'grain' => 'order',
        'type' => MetricType::COUNT,
        'definition' => 'text_metric_orders_count',
      ],
      'delivered_orders' => [
        'grain' => 'order',
        'type' => MetricType::COUNT,
        'definition' => 'text_metric_delivered_orders',
      ],
      'cancelled_orders' => [
        'grain' => 'order',
        'type' => MetricType::COUNT,
        'definition' => 'text_metric_cancelled_orders',
      ],
      'refunded_orders' => [
        'grain' => 'order',
        'type' => MetricType::COUNT,
        'definition' => 'text_metric_refunded_orders',
      ],
      'gross_margin_amount' => [
        'grain' => 'product',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_gross_margin_amount',
      ],
      'gross_margin_percent' => [
        'grain' => 'product',
        'type' => MetricType::RATE,
        'definition' => 'text_metric_gross_margin_percent',
      ],
      'avg_shipping_delay' => [
        'grain' => 'order',
        'type' => MetricType::DURATION,
        'definition' => 'text_metric_avg_shipping_delay',
      ],
      'discount_amount' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_discount_amount',
      ],
      'shipping_billed' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_shipping_billed',
      ],
    ];
  }
}
