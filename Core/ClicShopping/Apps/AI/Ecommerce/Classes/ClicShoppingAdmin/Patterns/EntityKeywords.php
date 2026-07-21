<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns;

/**
 * EntityKeywords
 *
 * Ecommerce-domain entity vocabulary. Owns the commerce nouns and financial-metric
 * keywords that used to be hardcoded in the agnostic Core (§Q-quater). Core's
 * EntityKeywordsPattern reads this class through the active domain, so the vocabulary
 * lives with the domain that defines it.
 */
class EntityKeywords
{
  /** @var array<string> Flat commerce entity keywords */
  public static array $entityKeywords = [
    'product', 'products', 'item', 'items', 'article', 'articles',
    'order', 'orders', 'sale', 'sales', 'purchase', 'purchases',
    'customer', 'customers', 'client', 'clients', 'user', 'users',
    'supplier', 'suppliers', 'vendor', 'vendors', 'manufacturer', 'manufacturers',
    'invoice', 'invoices', 'payment', 'payments', 'transaction', 'transactions',
    'category', 'categories', 'section', 'sections',
    'review', 'reviews', 'rating', 'ratings', 'comment', 'comments',
    'brand', 'brands',
  ];

  /** @var array<string, array<string>> Commerce entity keywords by type */
  public static array $entityPatterns = [
    'product' => ['product', 'products', 'item', 'items', 'article', 'articles'],
    'category' => ['category', 'categories', 'section', 'sections'],
    'customer' => ['customer', 'customers', 'client', 'clients', 'user', 'users'],
    'order' => ['order', 'orders', 'purchase', 'purchases', 'sale', 'sales'],
    'manufacturer' => ['manufacturer', 'manufacturers', 'brand', 'brands', 'supplier', 'suppliers', 'vendor', 'vendors'],
    'review' => ['review', 'reviews', 'rating', 'ratings', 'comment', 'comments'],
    'financial' => ['invoice', 'invoices', 'payment', 'payments', 'transaction', 'transactions'],
  ];

  /**
   * Financial metric keywords that mark a database analytics query (not web search).
   * Moved verbatim from WebSearchPostFilter's inline list.
   *
   * @var array<string>
   */
  public static array $financialMetricKeywords = [
    'revenue', 'turnover', 'sales', 'profit', 'margin', 'income',
    'cost', 'expense', 'spending', 'budget', 'forecast',
    'average', 'total', 'sum', 'count', 'number of', 'how many',
    'stock', 'inventory', 'quantity', 'units',
    'pending', 'delivered', 'cancelled', 'processing', 'shipped',
    'orders', 'customers', 'products', 'categories',
  ];

  /** @return array<string> */
  public static function getKeywords(): array
  {
    return self::$entityKeywords;
  }

  /** @return array<string, array<string>> */
  public static function getPatterns(): array
  {
    return self::$entityPatterns;
  }

  /** @return array<string> */
  public static function getFinancialMetricKeywords(): array
  {
    return self::$financialMetricKeywords;
  }
}
