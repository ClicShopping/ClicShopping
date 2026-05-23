<?php
/**
 * Shop Guardrails Pattern Matcher
 *
 * Pattern matching class for table filtering in Shop GuardrailsConfig.
 * More restrictive than Admin patterns - focuses on public-facing data only.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Patterns;


/**
 * GuardrailsPattern - Shop Context
 *
 * Provides pattern matching logic for table filtering in Shop context.
 * This class is MORE RESTRICTIVE than Admin patterns, focusing only on
 * public-facing catalog data.
 *
 * Purpose:
 * - Filter database tables for public Shop access
 * - Support dynamic table discovery in Shop GuardrailsConfig
 * - Exclude sensitive customer, order, and admin data
 *
 * Key Differences from Admin Patterns:
 * - NO customer detail tables (customers_info, address_book)
 * - NO order detail tables (orders_total, orders_status_history)
 * - NO admin tables (admin_*, configuration)
 * - NO payment tables (paypal_*, stripe_*)
 * - ONLY public catalog data (products, categories, manufacturers)
 *
 * Note: This class is for TABLE FILTERING only, NOT for entity detection.
 * Entity detection uses Pure LLM Mode (not pattern matching).
 */


class GuardrailsPattern
{
  /**
   * Get Shop-specific table patterns for public-facing data
   *
   * Returns an array of regex patterns that match public-facing e-commerce tables.
   * These patterns are MORE RESTRICTIVE than Admin patterns.
   *
   * Pattern Categories (Shop - Public Only):
   * 1. Product Catalog: products, categories, manufacturers (public data)
   * 2. Basic Customer: customers (basic info only, NOT customers_info)
   * 3. Public Metadata: languages, currencies, tax_class
   * 4. Analytics: rag_statistics, rag_query_cache (aggregated, non-sensitive)
   *
   * Excluded Patterns (Sensitive Data):
   * - Order details: orders_total, orders_status_history
   * - Customer details: customers_info, address_book
   * - Admin tables: admin_*, configuration
   * - Payment tables: paypal_*, stripe_*
   * - Security logs: security_events, rag_security_events
   *
   * @return array Array of regex patterns
   */
  public static function getPatterns(): array
  {
    return [
      // Product catalog (public data)
      '/^products$/',                    // products (exact match)
      '/^products_description$/',        // products_description
      '/^products_images$/',             // products_images
      '/^products_attributes$/',         // products_attributes
      '/^products_options$/',            // products_options
      '/^products_options_values$/',     // products_options_values
      '/^products_options_values_to_products_options$/', // options mapping
      '/^specials$/',                    // special prices

      // Categories (public data)
      '/^categories$/',                  // categories (exact match)
      '/^categories_description$/',      // categories_description
      '/^products_to_categories$/',      // product-category mapping

      // Manufacturers (public data)
      '/^manufacturers$/',               // manufacturers (exact match)
      '/^manufacturers_info$/',          // manufacturers_info

      // Basic customer data (non-sensitive, for personalization)
      '/^customers$/',                   // customers (exact match, basic info only)

      // Public metadata
      '/^languages$/',                   // languages
      '/^currencies$/',                  // currencies
      '/^tax_class$/',                   // tax_class
      '/^tax_rates$/',                   // tax_rates

      // RAG analytics (aggregated, non-sensitive)
      '/^rag_statistics$/',              // rag_statistics (exact match)
      '/^rag_query_cache$/',             // rag_query_cache (exact match)
    ];
  }

  /**
   * Check if a table name matches Shop patterns
   *
   * Tests a table name against Shop-specific patterns to determine
   * if it's accessible for public Shop context.
   *
   * This method is MORE RESTRICTIVE than Admin - only public-facing
   * catalog data is allowed.
   *
   * @param string $tableName Table name without prefix
   * @return bool True if table matches Shop patterns, false otherwise
   */
  public static function matches(string $tableName): bool
  {
    $patterns = self::getPatterns();

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $tableName)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Get pattern categories with descriptions for Shop context
   *
   * Returns a structured array of pattern categories for documentation
   * and debugging purposes.
   *
   * @return array Associative array of pattern categories
   */
  public static function getPatternCategories(): array
  {
    return [
      'product_catalog' => [
        'description' => 'Public product catalog data',
        'patterns' => [
          '/^products$/' => 'Products (basic info)',
          '/^products_description$/' => 'Product descriptions',
          '/^products_images$/' => 'Product images',
          '/^products_attributes$/' => 'Product attributes',
          '/^products_options$/' => 'Product options',
          '/^products_options_values$/' => 'Product option values',
          '/^specials$/' => 'Special prices'
        ]
      ],
      'categories' => [
        'description' => 'Public category data',
        'patterns' => [
          '/^categories$/' => 'Categories',
          '/^categories_description$/' => 'Category descriptions',
          '/^products_to_categories$/' => 'Product-category mapping'
        ]
      ],
      'manufacturers' => [
        'description' => 'Public manufacturer data',
        'patterns' => [
          '/^manufacturers$/' => 'Manufacturers',
          '/^manufacturers_info$/' => 'Manufacturer information'
        ]
      ],
      'basic_customer' => [
        'description' => 'Basic customer data (non-sensitive)',
        'patterns' => [
          '/^customers$/' => 'Customers (basic info only)'
        ]
      ],
      'public_metadata' => [
        'description' => 'Public metadata',
        'patterns' => [
          '/^languages$/' => 'Languages',
          '/^currencies$/' => 'Currencies',
          '/^tax_class$/' => 'Tax classes',
          '/^tax_rates$/' => 'Tax rates'
        ]
      ],
      'analytics' => [
        'description' => 'RAG analytics (aggregated, non-sensitive)',
        'patterns' => [
          '/^rag_statistics$/' => 'RAG statistics',
          '/^rag_query_cache$/' => 'RAG query cache'
        ]
      ]
    ];
  }

  /**
   * Get excluded patterns (sensitive data not accessible in Shop)
   *
   * Returns patterns for tables that are explicitly excluded from Shop access.
   * These tables contain sensitive data and should never be accessible in Shop context.
   *
   * @return array Array of excluded patterns with descriptions
   */
  public static function getExcludedPatterns(): array
  {
    return [
      'customer_details' => [
        'description' => 'Sensitive customer information',
        'patterns' => [
          '/^customers_info$/' => 'Customer detailed information',
          '/^customers_basket/' => 'Customer shopping cart',
          '/^address_book$/' => 'Customer addresses',
          '/^whos_online$/' => 'Online customer tracking',
          '/^action_recorder$/' => 'Customer action tracking'
        ]
      ],
      'order_details' => [
        'description' => 'Order financial and status details',
        'patterns' => [
          '/^orders_total$/' => 'Order financial totals',
          '/^orders_status_history$/' => 'Order status history',
          '/^orders_products_attributes$/' => 'Order product attributes'
        ]
      ],
      'admin_tables' => [
        'description' => 'Admin and system tables',
        'patterns' => [
          '/^admin_/' => 'All admin tables',
          '/^configuration$/' => 'System configuration',
          '/^security_events$/' => 'Security audit logs',
          '/^rag_security_events$/' => 'RAG security logs'
        ]
      ],
      'payment_tables' => [
        'description' => 'Payment and financial data',
        'patterns' => [
          '/^paypal_/' => 'PayPal transactions',
          '/^stripe_/' => 'Stripe transactions',
          '/^payment_methods$/' => 'Payment method details'
        ]
      ],
      'reviews' => [
        'description' => 'Customer reviews (may contain personal info)',
        'patterns' => [
          '/^reviews$/' => 'Customer reviews',
          '/^reviews_description$/' => 'Review text content'
        ]
      ]
    ];
  }

  /**
   * Check if a table is explicitly excluded from Shop access
   *
   * Tests a table name against excluded patterns to determine
   * if it contains sensitive data that should not be accessible in Shop.
   *
   * @param string $tableName Table name without prefix
   * @return bool True if table is excluded, false otherwise
   */
  public static function isExcluded(string $tableName): bool
  {
    $excludedCategories = self::getExcludedPatterns();

    foreach ($excludedCategories as $category) {
      foreach ($category['patterns'] as $pattern => $description) {
        if (preg_match($pattern, $tableName)) {
          return true;
        }
      }
    }

    return false;
  }
}
