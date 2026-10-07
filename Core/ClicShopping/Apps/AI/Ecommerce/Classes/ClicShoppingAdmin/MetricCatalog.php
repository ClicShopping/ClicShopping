<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\AI\DomainsAI\Analytics\Planning\MetricAggregation;
use ClicShopping\AI\DomainsAI\Analytics\Planning\MetricType;

/**
 * MetricCatalog
 *
 * The identity card of every metric this domain can plan: its GRAIN, its TYPE, how it rolls up
 * (AGGREGATION) and, where one formula defines it, its EXPRESSION — checked in the generated SQL.
 * The query shape around it (status weight, events, windows) stays in rag_analytics_agent.txt.
 *
 * Identity is a platform guarantee, not a merchant preference: a margin percentage IS a
 * rate. Shop CONVENTIONS (the cost basis of the margin) live in configuration instead.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin
 */
class MetricCatalog
{
  // Excluding tax and shipping: over the 'TO', 'TX' and 'SH' rows of one order.
  private const MERCHANDISE_AMOUNT = "CASE WHEN class = 'TO' THEN value ELSE -value END";

  /**
   * Row-level expressions a metric expression may name instead of repeating them: the SQL owes
   * each one its exact definition wherever it uses the name.
   *
   * @return array<string, string> Term => expression over columns, without table aliases
   */
  public static function terms(): array
  {
    return [
      'line_revenue' => 'final_price * products_quantity',
      // NULLIF keeps an uncosted line out of the margin instead of reading it as 100%.
      'line_cost' => '(NULLIF(products_cost, 0) + products_handling) * products_quantity',
    ];
  }

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
   * Key columns of a metric grain: a final GROUP BY on one of them breaks the result down by that
   * grain, which only a plan dimension, ranking or filter may ask for. Checked after generation.
   *
   * @return array<string, array<int, string>> Grain => key columns
   */
  public static function grainKeys(): array
  {
    return ['product' => ['products_id', 'products_name']];
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
   * `expression` is optional: the formula over columns or terms() (base), or over other metric
   * names (derived), checked after generation. Absent where no presence check can tell the metric
   * from its neighbour (revenue_ttc reads the same 'TO' rows as revenue_ht).
   *
   * @return array<string, array{grain: string, type: string, aggregation: string, definition: string, expression?: string, basis?: string, split?: string, line_alternative?: string, companions?: array<int, string>, weighted_by?: string}>
   */
  public static function all(): array
  {
    return [
      'revenue_ttc' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::ADDITIVE,
        'definition' => 'text_metric_revenue_ttc',
        'basis' => 'text_metric_basis_revenue_ttc',
        'split' => 'tax_convention',
        'weighted_by' => 'revenue_sign',
      ],
      'revenue_ht' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::ADDITIVE,
        'expression' => self::MERCHANDISE_AMOUNT,
        'definition' => 'text_metric_revenue_ht',
        'basis' => 'text_metric_basis_revenue_ht',
        'split' => 'tax_convention',
        'line_alternative' => 'line_revenue',
        'weighted_by' => 'revenue_sign',
      ],
      'line_revenue' => [
        'grain' => 'order_line',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::ADDITIVE,
        'expression' => 'final_price * products_quantity',
        'definition' => 'text_metric_line_revenue',
        'weighted_by' => 'revenue_sign',
      ],
      'revenue_per_customer' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::RATIO_OF_SUMS,
        'expression' => 'revenue_ht / COUNT(DISTINCT customers_id)',
        'definition' => 'text_metric_revenue_per_customer',
        'basis' => 'text_metric_basis_revenue_per_customer',
        'companions' => ['revenue_ht', 'customers_count'],
        'weighted_by' => 'revenue_sign',
      ],
      'average_cart' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::AVERAGE,
        'definition' => 'text_metric_average_cart',
        'weighted_by' => 'revenue_sign',
      ],
      'quantity_sold' => [
        'grain' => 'order_line',
        'type' => MetricType::COUNT,
        'aggregation' => MetricAggregation::ADDITIVE,
        'expression' => 'products_quantity',
        'definition' => 'text_metric_quantity_sold',
        'weighted_by' => 'revenue_sign',
      ],
      'orders_count' => [
        'grain' => 'order',
        'type' => MetricType::COUNT,
        'aggregation' => MetricAggregation::DISTINCT_COUNT,
        'expression' => 'orders_id',
        'definition' => 'text_metric_orders_count',
      ],
      'delivered_orders' => [
        'grain' => 'order',
        'type' => MetricType::COUNT,
        'aggregation' => MetricAggregation::DISTINCT_COUNT,
        'expression' => 'orders_id',
        'definition' => 'text_metric_delivered_orders',
      ],
      'cancelled_orders' => [
        'grain' => 'order',
        'type' => MetricType::COUNT,
        'aggregation' => MetricAggregation::DISTINCT_COUNT,
        'expression' => 'orders_id',
        'definition' => 'text_metric_cancelled_orders',
      ],
      'refunded_orders' => [
        'grain' => 'order',
        'type' => MetricType::COUNT,
        'aggregation' => MetricAggregation::DISTINCT_COUNT,
        'expression' => 'orders_id',
        'definition' => 'text_metric_refunded_orders',
      ],
      // The deduction leg alone: no weighted_by, its population carries weight -1 by definition.
      'refunded_amount' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::ADDITIVE,
        'expression' => self::MERCHANDISE_AMOUNT,
        'definition' => 'text_metric_refunded_amount',
        'basis' => 'text_metric_basis_refunded_amount',
      ],
      'gross_margin_amount' => [
        'grain' => 'product',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::ADDITIVE,
        'expression' => 'line_revenue - line_cost',
        'definition' => 'text_metric_gross_margin_amount',
        'basis' => 'text_metric_basis_cost_current',
        'companions' => ['line_revenue', 'revenue_without_cost', 'gross_margin_percent'],
        'weighted_by' => 'revenue_sign',
      ],
      'gross_margin_percent' => [
        'grain' => 'product',
        'type' => MetricType::RATE,
        'aggregation' => MetricAggregation::RATIO_OF_SUMS,
        'expression' => 'gross_margin_amount / line_revenue * 100',
        'definition' => 'text_metric_gross_margin_percent',
        'basis' => 'text_metric_basis_cost_current',
        'companions' => ['line_revenue', 'revenue_without_cost', 'gross_margin_amount'],
        'weighted_by' => 'revenue_sign',
      ],
      'avg_shipping_delay' => [
        'grain' => 'order',
        'type' => MetricType::DURATION,
        'aggregation' => MetricAggregation::AVERAGE,
        'definition' => 'text_metric_avg_shipping_delay',
      ],
      'discount_amount' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::ADDITIVE,
        'definition' => 'text_metric_discount_amount',
      ],
      'shipping_billed' => [
        'grain' => 'order',
        'type' => MetricType::AMOUNT,
        'aggregation' => MetricAggregation::ADDITIVE,
        'definition' => 'text_metric_shipping_billed',
      ],
    ];
  }
}
