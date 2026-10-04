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
   * Dimensions carried by the order itself (one value per order): breaking an order-grain metric
   * down by one of them needs no join to the order lines, so it is not a fan-out.
   *
   * ponytail: only the dimensions a question needed are declared; add another the day a
   * question breaks down by it — until then it is refused honestly, never miscounted.
   *
   * @return array<int, string> Dimension names as the analysis plan writes them
   */
  public static function orderSideDimensions(): array
  {
    return ['customer', 'order'];
  }

  /**
   * Rows no total or average may read: the 'ST' subtotal carries tax on tax-inclusive orders
   * and not on the others, so summing it mixes two conventions. Checked after generation.
   *
   * @return array<string, array<int, string>> Column => forbidden values
   */
  public static function forbiddenAggregateSources(): array
  {
    return ['class' => ['ST']];
  }

  /**
   * Columns that select an order population by value: when the question names a status and the
   * SQL pins it here, the accounting weight (`weighted_by`) no longer has to be read.
   *
   * @return array<int, string>
   */
  public static function populationPinColumns(): array
  {
    return ['orders_status', 'orders_status_name'];
  }

  /**
   * `basis` is optional and carries a USER-FACING label key: declare it on a metric whose
   * figure means nothing without its convention, so the restitution can name it.
   *
   * `split` is optional and names the DIMENSION the answer is broken down by when the question
   * asks for a bare total: two populations the merchant must see apart, never silently merged.
   *
   * `line_alternative` is optional and names the ORDER_LINE-grain metric that answers the same
   * intent when an order-grain figure is broken down by a product dimension: revenue per category
   * IS the sum of line revenues, so the plan swaps to it rather than fanning the order total out.
   *
   * `companions` is optional and names the columns always selected WITH the metric, as its own
   * prompt rule defines them: the binding plan lists them, so they are never read as an added metric.
   *
   * `weighted_by` is optional and names the column carrying the accounting weight of the row
   * (orders_status.revenue_sign): the SQL must read it keeping weight 1, checked after generation.
   *
   * @return array<string, array{grain: string, type: string, definition: string, basis?: string, split?: string, line_alternative?: string, companions?: array<int, string>, weighted_by?: string}>
   */
  public static function all(): array
  {
    return [
      'revenue_ttc' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_revenue_ttc',
        'basis' => 'text_metric_basis_revenue_ttc',
        'split' => 'tax_convention',
        'weighted_by' => 'revenue_sign',
      ],
      'revenue_ht' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_revenue_ht',
        'basis' => 'text_metric_basis_revenue_ht',
        'split' => 'tax_convention',
        'line_alternative' => 'line_revenue',
        'weighted_by' => 'revenue_sign',
      ],
      'line_revenue' => [
        'grain' => 'order_line',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_line_revenue',
        'weighted_by' => 'revenue_sign',
      ],
      'revenue_per_customer' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_revenue_per_customer',
        'basis' => 'text_metric_basis_revenue_per_customer',
        'companions' => ['revenue_ht', 'customers_count'],
        'weighted_by' => 'revenue_sign',
      ],
      'average_cart' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_average_cart',
        'weighted_by' => 'revenue_sign',
      ],
      'quantity_sold' => [
        'grain' => 'order_line',
        'type' => MetricType::COUNT,
        'definition' => 'text_metric_quantity_sold',
        'weighted_by' => 'revenue_sign',
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
      // The deduction leg alone: no weighted_by, its population carries weight -1 by definition.
      'refunded_amount' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_refunded_amount',
        'basis' => 'text_metric_basis_refunded_amount',
      ],
      'gross_margin_amount' => [
        'grain' => 'product',
        'type' => MetricType::AMOUNT,
        'definition' => 'text_metric_gross_margin_amount',
        'basis' => 'text_metric_basis_cost_current',
        'companions' => ['revenue_ht', 'revenue_without_cost'],
        'weighted_by' => 'revenue_sign',
      ],
      'gross_margin_percent' => [
        'grain' => 'product',
        'type' => MetricType::RATE,
        'definition' => 'text_metric_gross_margin_percent',
        'basis' => 'text_metric_basis_cost_current',
        'companions' => ['revenue_ht', 'revenue_without_cost'],
        'weighted_by' => 'revenue_sign',
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
