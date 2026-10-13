<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\AI\DomainsAI\Analytics\Planning\HierarchySql;

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
  private const COST_JOIN = 'LEFT JOIN {{table_prefix}}products p ON p.products_id = op.products_id';
  private const LINE_REVENUE = 'op.final_price * op.products_quantity';
  // Cost frozen at sale first, today's catalogue cost for older lines; NULL = uncosted line.
  private const LINE_COST = 'COALESCE(op.products_unit_cost, NULLIF(p.products_cost, 0) + p.products_handling) * op.products_quantity';
  // Read by PlanSqlCompiler only: the margin's companion and its rate base, over the cost columns.
  private const COST_MEASURES = [
    'revenue_without_cost' => 'CASE WHEN line_cost IS NULL THEN line_revenue END',
    'costed_line_revenue' => 'CASE WHEN line_cost IS NOT NULL THEN line_revenue END',
  ];

  /**
   * @return array{fact: array{grain: string, table: string, alias: string, key: string, date: string, state: string}, lines: array{grain: string, table: string, alias: string, key: string, item: string}, weight: array{table: string, alias: string, key: string, column: string, language_scoped: bool}, history: array{table: string, alias: string, key: string, state: string, date: string}, fact_totals: array<int, array{table: string, column: string}>}
   */
  public static function axis(): array
  {
    return [
      'fact' => ['grain' => 'order', 'table' => 'orders', 'alias' => 'o', 'key' => 'orders_id', 'date' => 'date_purchased', 'state' => 'orders_status'],
      'lines' => ['grain' => 'order_line', 'table' => 'orders_products', 'alias' => 'op', 'key' => 'orders_id', 'item' => 'products_id'],
      'weight' => ['table' => 'orders_status', 'alias' => 'os', 'key' => 'orders_status_id', 'column' => 'revenue_sign', 'language_scoped' => true],
      'history' => ['table' => 'orders_status_history', 'alias' => 'h', 'key' => 'orders_id', 'state' => 'orders_status_id', 'date' => 'date_added'],
      // Amounts held once per order: summed across its lines they double-count (CoherenceGuard).
      'fact_totals' => [['table' => 'orders_total', 'column' => 'value']],
    ];
  }

  /**
   * The category tree read by HierarchySql: products filed on categories, nested by parent_id.
   * Plan dimension => level; output aliases follow MetricCatalog::leadColumns().
   *
   * @return array{cte: string, node: array, name: array, membership: array, dimensions: array<string, string>, aliases: array<string, string>, root_beside_path: string}
   */
  public static function hierarchy(): array
  {
    return [
      'cte' => 'category_tree',
      'node' => ['table' => 'categories', 'key' => 'categories_id', 'parent' => 'parent_id', 'root' => 0],
      'name' => ['table' => 'categories_description', 'key' => 'categories_id', 'column' => 'categories_name', 'language_scoped' => true],
      'membership' => ['table' => 'products_to_categories', 'node' => 'categories_id', 'item' => 'products_id'],
      'dimensions' => ['category' => HierarchySql::ROOT, 'subcategory' => HierarchySql::PATH],
      'aliases' => [HierarchySql::ROOT => 'categories_name', HierarchySql::PATH => 'category_path'],
      // Both levels asked: one row per path, its top-level category in a second column.
      'root_beside_path' => 'root_name',
    ];
  }

  /**
   * @return array<string, array{grain: string, columns: array<string, string>, joins?: array<int, string>, measures?: array<string, string>}>
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
        'joins' => [self::COST_JOIN],
        'columns' => ['line_revenue' => self::LINE_REVENUE, 'line_cost' => self::LINE_COST],
        'measures' => self::COST_MEASURES,
      ],
      // The same with the product, for the compiler: no prompt example reads it.
      'line_product_cost' => [
        'grain' => 'order_line',
        'joins' => [self::COST_JOIN, self::PRODUCT_NAME_JOIN],
        'columns' => ['products_id' => 'op.products_id', 'products_name' => self::PRODUCT_NAME, 'line_revenue' => self::LINE_REVENUE, 'line_cost' => self::LINE_COST],
        'measures' => self::COST_MEASURES,
      ],
    ];
  }
}
