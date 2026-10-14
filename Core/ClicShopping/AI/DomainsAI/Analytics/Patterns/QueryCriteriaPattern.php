<?php
/**
 * QueryCriteriaPattern
 *
 * Pattern class for defining allowed fields in query criteria extraction.
 * Extracted from QueryAnalyzer to follow pattern separation principle.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * REFACTORING: Extracted from QueryAnalyzer (2026-01-09)
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class QueryCriteriaPattern
{
  /**
   * Get allowed fields for query criteria extraction
   *
   * Returns a list of database fields that can be used in query filters,
   * ranges, and boolean conditions. These fields are commonly used in
   * e-commerce analytics queries.
   *
   * @return array List of allowed field names
   */
  public static function getAllowedFields(): array
  {
    return [
      'price',
      'stock',
      'quantity',
      'sku',
      'model',
      'weight',
      'status',
      'date',
      'name',
      'description',
      'category',
      'manufacturer',
      'rating'
    ];
  }

  /**
   * Get field pattern for regex matching
   *
   * Returns a regex pattern that matches all allowed fields.
   * Used for extracting field references from queries.
   *
   * @return string Regex pattern for field matching (e.g., "price|stock|quantity|...")
   */
  public static function getFieldPattern(): string
  {
    return implode('|', self::getAllowedFields());
  }

  /**
   * Check if a field is allowed
   *
   * Validates whether a field name is in the allowed list.
   *
   * @param string $field Field name to check
   * @return bool True if field is allowed, false otherwise
   */
  public static function isAllowedField(string $field): bool
  {
    return in_array(strtolower($field), self::getAllowedFields(), true);
  }

  /**
   * Get field categories
   *
   * Groups fields by their semantic category for better organization.
   *
   * @return array Field categories with field lists
   */
  public static function getFieldCategories(): array
  {
    return [
      'product_attributes' => ['name', 'description', 'sku', 'model'],
      'pricing' => ['price'],
      'inventory' => ['stock', 'quantity'],
      'physical' => ['weight'],
      'metadata' => ['status', 'date', 'rating'],
      'relationships' => ['category', 'manufacturer']
    ];
  }

  /**
   * Get metadata about this pattern
   *
   * @return array Pattern metadata
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'Query Criteria Pattern',
      'description' => 'Defines allowed fields for query criteria extraction (filters, ranges, boolean conditions)',
      'field_count' => count(self::getAllowedFields()),
      'categories' => array_keys(self::getFieldCategories()),
      'usage' => 'QueryAnalyzer::extractQueryCriteria()',
      'examples' => [
        'price greater than 100',
        'with stock',
        'quantity between 10 and 50',
        'without description'
      ]
    ];
  }
}
