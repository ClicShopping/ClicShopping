<?php
/**
 * Guardrails Pattern Matcher
 *
 * Generic pattern matching class for table filtering in GuardrailsConfig.
 * Can be reused across different Apps (Ecommerce, HR, Finance, etc.).
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns;

/**
 * GuardrailsPattern
 *
 * Provides generic pattern matching logic for table filtering.
 * This class can be extended or reused by other domain apps (HR, Finance, Trading, etc.).
 *
 * Purpose:
 * - Filter database tables based on domain-specific patterns
 * - Support dynamic table discovery in GuardrailsConfig
 * - Reusable across multiple Apps
 *
 * Note: This class is for TABLE FILTERING only, NOT for entity detection.
 * Entity detection uses Pure LLM Mode (not pattern matching).
 */
class GuardrailsPattern
{
  /**
   * Get domain-specific table patterns for Ecommerce
   *
   * Returns an array of regex patterns that match e-commerce table names.
   * These patterns are used to filter tables discovered from the database.
   *
   * This method can be overridden or extended by other domain apps:
   * - HR App: Employee, department, payroll patterns
   * - Finance App: Transaction, account, invoice patterns
   * - Trading App: Order, position, portfolio patterns
   *
   * Pattern Categories (Ecommerce):
   * 1. Core Entities: products, orders, customers, categories, manufacturers, reviews
   * 2. Attributes: *_attributes, *_options, *_values
   * 3. Descriptions: *_description
   * 4. Analytics: rag_*, web_search*
   * 5. Relationships: *_to_*, notifications
   *
   * @return array Array of regex patterns
   */
  public static function getPatterns(): array
  {
    return [
      // Core e-commerce entities
      '/^products/',           // products, products_description, products_attributes, etc.
      '/^orders/',             // orders, orders_products, orders_status, etc.
      '/^customers$/',         // customers (exact match, NOT customers_info)
      '/^categories/',         // categories, categories_description
      '/^manufacturers/',      // manufacturers, manufacturers_info
      '/^reviews/',            // reviews, reviews_description
      '/^specials/',           // specials, special_prices

      // Attributes and options
      '/_attributes$/',        // products_attributes, orders_products_attributes
      '/_options$/',           // products_options, products_options_values
      '/_values$/',            // products_options_values
      '/_description$/',       // *_description tables

      // Analytics and RAG
      '/^rag_/',               // rag_statistics, rag_query_cache, rag_chat_interactions, etc.
      '/^web_search/',         // web_search_cache, web_search_results

      // Relationships
      '/_to_/',                // products_to_categories, etc.
      '/^notifications/',      // products_notifications
    ];
  }

  /**
   * Check if a table name matches domain patterns
   *
   * Tests a table name against all defined patterns to determine
   * if it's relevant for the domain (e.g., e-commerce, HR, finance).
   *
   * This method is generic and can be used by any domain app.
   *
   * @param string $tableName Table name without prefix
   * @return bool True if table matches domain patterns, false otherwise
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
   * Get pattern categories with descriptions
   *
   * Returns a structured array of pattern categories for documentation
   * and debugging purposes.
   *
   * @return array Associative array of pattern categories
   */
  public static function getPatternCategories(): array
  {
    return [
      'core_entities' => [
        'description' => 'Core e-commerce entities',
        'patterns' => [
          '/^products/' => 'Products and related tables',
          '/^orders/' => 'Orders and related tables',
          '/^customers$/' => 'Customers (exact match)',
          '/^categories/' => 'Categories and related tables',
          '/^manufacturers/' => 'Manufacturers and related tables',
          '/^reviews/' => 'Reviews and related tables',
          '/^specials/' => 'Special prices and promotions'
        ]
      ],
      'attributes' => [
        'description' => 'Product attributes and options',
        'patterns' => [
          '/_attributes$/' => 'Attribute tables',
          '/_options$/' => 'Option tables',
          '/_values$/' => 'Value tables'
        ]
      ],
      'descriptions' => [
        'description' => 'Multi-language descriptions',
        'patterns' => [
          '/_description$/' => 'Description tables'
        ]
      ],
      'analytics' => [
        'description' => 'Analytics and RAG tables',
        'patterns' => [
          '/^rag_/' => 'RAG system tables',
          '/^web_search/' => 'Web search tables'
        ]
      ],
      'relationships' => [
        'description' => 'Relationship and notification tables',
        'patterns' => [
          '/_to_/' => 'Relationship tables',
          '/^notifications/' => 'Notification tables'
        ]
      ]
    ];
  }
}
