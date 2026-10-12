<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

/**
 * EventAxis
 *
 * Declares this domain's signed event axis for EventAxisSql: an order counts on its purchase date
 * at the weight of its status (orders_status.revenue_sign), and a refunded order is deducted on the
 * date it reached that status. Each projection is served to the prompt as {{event_axis_<name>}}.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin
 */
class EventAxis
{
  // Excluding tax and shipping, as MetricCatalog::MERCHANDISE_AMOUNT.
  private const MERCHANDISE_AMOUNT = "(SELECT SUM(CASE WHEN ot.class = 'TO' THEN ot.value ELSE -ot.value END) FROM {{table_prefix}}orders_total ot WHERE ot.orders_id = o.orders_id AND ot.class IN ('TO','TX','SH'))";
  private const ORDER_TOTAL = "(SELECT ot.value FROM {{table_prefix}}orders_total ot WHERE ot.orders_id = o.orders_id AND ot.class = 'TO')";
  private const PRODUCT_NAME_JOIN = 'LEFT JOIN {{table_prefix}}products_description pd ON pd.products_id = op.products_id AND pd.language_id = {{language_id}}';
  // The catalogue name when the product still exists, the name copied onto the line otherwise.
  private const PRODUCT_NAME = 'COALESCE(pd.products_name, op.products_name)';

  /**
   * @return array{fact: array{table: string, alias: string, key: string, date: string, state: string}, lines: array{table: string, alias: string, key: string}, weight: array{table: string, alias: string, key: string, column: string, language_scoped: bool}, history: array{table: string, alias: string, key: string, state: string, date: string}}
   */
  public static function axis(): array
  {
    return [
      'fact' => ['table' => 'orders', 'alias' => 'o', 'key' => 'orders_id', 'date' => 'date_purchased', 'state' => 'orders_status'],
      'lines' => ['table' => 'orders_products', 'alias' => 'op', 'key' => 'orders_id'],
      'weight' => ['table' => 'orders_status', 'alias' => 'os', 'key' => 'orders_status_id', 'column' => 'revenue_sign', 'language_scoped' => true],
      'history' => ['table' => 'orders_status_history', 'alias' => 'h', 'key' => 'orders_id', 'state' => 'orders_status_id', 'date' => 'date_added'],
    ];
  }

  /**
   * @return array<string, array{grain: string, columns: array<string, string>, joins?: array<int, string>}>
   */
  public static function projections(): array
  {
    return [
      'order_amount' => ['grain' => 'order', 'columns' => ['amount' => self::MERCHANDISE_AMOUNT]],
      'order_amount_ttc' => ['grain' => 'order', 'columns' => ['amount' => self::ORDER_TOTAL]],
      'order_customer' => ['grain' => 'order', 'columns' => ['customers_id' => 'o.customers_id', 'amount' => self::MERCHANDISE_AMOUNT]],
      'order_tax_convention' => ['grain' => 'order', 'columns' => ['tax_convention' => 'o.orders_prices_include_tax', 'amount' => self::MERCHANDISE_AMOUNT]],
      'order_tax_convention_ttc' => ['grain' => 'order', 'columns' => ['tax_convention' => 'o.orders_prices_include_tax', 'amount' => self::ORDER_TOTAL]],
      'line_category' => [
        'grain' => 'order_line',
        'joins' => [self::PRODUCT_NAME_JOIN],
        'columns' => [
          'products_id' => 'op.products_id',
          'products_name' => self::PRODUCT_NAME,
          'categories_name' => '(SELECT MIN(cd.categories_name) FROM {{table_prefix}}products_to_categories ptc JOIN {{table_prefix}}categories_description cd ON cd.categories_id = ptc.categories_id AND cd.language_id = {{language_id}} WHERE ptc.products_id = op.products_id)',
          'amount' => 'op.final_price * op.products_quantity',
        ],
      ],
      'line_quantity' => [
        'grain' => 'order_line',
        'joins' => [self::PRODUCT_NAME_JOIN],
        'columns' => ['products_id' => 'op.products_id', 'products_name' => self::PRODUCT_NAME, 'products_quantity' => 'op.products_quantity'],
      ],
      'line_orders' => [
        'grain' => 'order_line',
        'joins' => [self::PRODUCT_NAME_JOIN],
        'columns' => ['products_id' => 'op.products_id', 'products_name' => self::PRODUCT_NAME, 'orders_id' => 'op.orders_id', 'products_quantity' => 'op.products_quantity'],
      ],
      'line_cost' => [
        'grain' => 'order_line',
        'joins' => ['LEFT JOIN {{table_prefix}}products p ON p.products_id = op.products_id'],
        'columns' => [
          'line_revenue' => 'op.final_price * op.products_quantity',
          'line_cost' => '(NULLIF(p.products_cost, 0) + p.products_handling) * op.products_quantity',
        ],
      ],
    ];
  }
}
