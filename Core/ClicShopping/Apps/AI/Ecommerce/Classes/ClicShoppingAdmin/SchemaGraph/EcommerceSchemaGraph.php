<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SchemaGraph;

use ClicShopping\AI\InterfacesAI\SchemaGraphProviderInterface;

/**
 * EcommerceSchemaGraph
 *
 * The Ecommerce entities as a graph. Node ids match the grain values the metric
 * catalogue declares (`order`, `order_line`, `product`), so a plan's metric grain
 * resolves straight to a table and its join keys. Columns are the platform's real
 * join columns (no foreign keys are declared on this schema, by design — see
 * `Agents/DATABASE.md`), so the graph, not a constraint, carries the join truth.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SchemaGraph
 */
final class EcommerceSchemaGraph implements SchemaGraphProviderInterface
{
  /**
   * {@inheritDoc}
   */
  public function getNodes(): array
  {
    return [
      'order' => 'orders',
      'order_line' => 'orders_products',
      'order_total' => 'orders_total',
      'product' => 'products',
      'product_description' => 'products_description',
      'customer' => 'customers',
      'category' => 'categories',
      'manufacturer' => 'manufacturers',
      // Bridge node for the products <-> categories N-N link.
      'product_category' => 'products_to_categories',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getEdges(): array
  {
    return [
      ['from' => 'order', 'to' => 'order_line', 'from_col' => 'orders_id', 'to_col' => 'orders_id', 'cardinality' => '1-N'],
      ['from' => 'order', 'to' => 'order_total', 'from_col' => 'orders_id', 'to_col' => 'orders_id', 'cardinality' => '1-N'],
      ['from' => 'order', 'to' => 'customer', 'from_col' => 'customers_id', 'to_col' => 'customers_id', 'cardinality' => 'N-1'],
      ['from' => 'order_line', 'to' => 'product', 'from_col' => 'products_id', 'to_col' => 'products_id', 'cardinality' => 'N-1'],
      ['from' => 'product', 'to' => 'product_description', 'from_col' => 'products_id', 'to_col' => 'products_id', 'cardinality' => '1-N'],
      ['from' => 'product', 'to' => 'manufacturer', 'from_col' => 'manufacturers_id', 'to_col' => 'manufacturers_id', 'cardinality' => 'N-1'],
      ['from' => 'product', 'to' => 'product_category', 'from_col' => 'products_id', 'to_col' => 'products_id', 'cardinality' => '1-N'],
      ['from' => 'category', 'to' => 'product_category', 'from_col' => 'categories_id', 'to_col' => 'categories_id', 'cardinality' => '1-N'],
    ];
  }
}
