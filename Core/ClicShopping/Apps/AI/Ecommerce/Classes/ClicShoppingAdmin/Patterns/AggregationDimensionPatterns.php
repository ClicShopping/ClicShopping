<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns;

/**
 * AggregationDimensionPatterns Class - E-commerce Specific
 *
 * Provides pattern-based detection for temporal and non-temporal aggregation dimensions
 * in hybrid queries. This class centralizes all aggregation dimension patterns to support
 * mixed aggregation detection and query analysis.
 *
 * **E-COMMERCE SPECIFIC DIMENSIONS**:
 * - product, category, customer, supplier, manufacturer, brand
 * - store, sales_channel, order_status
 *
 * Temporal Dimensions (Generic):
 * - month, quarter, semester, year, week, day
 *
 * Non-Temporal Dimensions (E-commerce Specific):
 * - product, category, region, country, customer, supplier, manufacturer, brand
 * - channel, store, department, status, order_status
 *
 * Usage:
 * ```php
 * $patterns = new AggregationDimensionPatterns();
 * $temporalDims = $patterns->detectTemporalDimensions($query);
 * $nonTemporalDims = $patterns->detectNonTemporalDimensions($query);
 * ```
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns
 * @since 2026-01-14
 * @updated 2026-01-22 - Moved to Ecommerce App (e-commerce specific dimensions)
 * @version 1.0.0
 */
// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class AggregationDimensionPatterns
{
  /**
   * Temporal aggregation patterns (GENERIC - can be reused by other domains)
   * Maps dimension names to regex patterns
   *
   * @var array
   */
  private array $temporalPatterns = [
    'month' => '/\b(by\s+)?month(ly)?\b/i',
    'quarter' => '/\b(by\s+)?quarter(ly)?\b/i',
    'semester' => '/\b(by\s+)?semester\b/i',
    'year' => '/\b(by\s+)?year(ly)?\b/i',
    'week' => '/\b(by\s+)?week(ly)?\b/i',
    'day' => '/\b(by\s+)?day|daily\b/i',
  ];

  /**
   * Non-temporal aggregation patterns (E-COMMERCE SPECIFIC)
   * Maps dimension names to regex patterns
   *
   * **E-COMMERCE SPECIFIC DIMENSIONS**:
   * - product, category, customer, supplier, manufacturer, brand
   * - store, sales_channel, order_status
   *
   * @var array
   */
  private array $nonTemporalPatterns = [
    'product' => '/\b(by\s+)?product(s)?\b/i',                          // E-commerce
    'category' => '/\b(by\s+)?categor(y|ies)\b/i',                     // E-commerce
    'product_category' => '/\b(by\s+)?product\s+categor(y|ies)\b/i',   // E-commerce
    'region' => '/\b(by\s+)?region(s)?\b/i',                           // Generic
    'country' => '/\b(by\s+)?countr(y|ies)\b/i',                       // Generic
    'customer' => '/\b(by\s+)?customer(s)?\b/i',                       // E-commerce
    'customer_type' => '/\b(by\s+)?customer\s+type(s)?\b/i',           // E-commerce
    'supplier' => '/\b(by\s+)?supplier(s)?\b/i',                       // E-commerce
    'manufacturer' => '/\b(by\s+)?manufacturer(s)?\b/i',               // E-commerce
    'brand' => '/\b(by\s+)?brand(s)?\b/i',                             // E-commerce
    'channel' => '/\b(by\s+)?channel(s)?\b/i',                         // Generic
    'sales_channel' => '/\b(by\s+)?sales\s+channel(s)?\b/i',           // E-commerce
    'store' => '/\b(by\s+)?store(s)?\b/i',                             // E-commerce
    'department' => '/\b(by\s+)?department(s)?\b/i',                   // Could be HR or E-commerce
    'status' => '/\b(by\s+)?status\b/i',                               // Generic
    'order_status' => '/\b(by\s+)?order\s+status\b/i',                 // E-commerce
  ];

  /**
   * Detect temporal aggregation dimensions from query text
   *
   * Searches for temporal dimension patterns (month, quarter, year, etc.)
   * in the query string and returns all matched dimensions.
   *
   * Examples:
   * - "revenue by month" → ['month']
   * - "sales by quarter and year" → ['quarter', 'year']
   * - "monthly revenue" → ['month']
   *
   * @param string $query Query text (case-insensitive)
   * @return array List of detected temporal dimensions
   */
  public function detectTemporalDimensions(string $query): array
  {
    $detected = [];
    
    foreach ($this->temporalPatterns as $period => $pattern) {
      if (preg_match($pattern, $query)) {
        $detected[] = $period;
      }
    }

    return $detected;
  }

  /**
   * Detect non-temporal aggregation dimensions from query text
   *
   * Searches for non-temporal dimension patterns (product, category, region, etc.)
   * in the query string and returns all matched dimensions.
   *
   * **E-COMMERCE SPECIFIC**: Returns e-commerce dimensions (product, customer, supplier, etc.)
   *
   * Examples:
   * - "revenue by product" → ['product']
   * - "sales by category and region" → ['category', 'region']
   * - "orders by customer type" → ['customer_type']
   *
   * Note: Removes duplicates when more specific patterns match
   * (e.g., 'product_category' takes precedence over 'product')
   *
   * @param string $query Query text (case-insensitive)
   * @return array List of detected non-temporal dimensions (unique)
   */
  public function detectNonTemporalDimensions(string $query): array
  {
    $detected = [];
    
    foreach ($this->nonTemporalPatterns as $dimension => $pattern) {
      if (preg_match($pattern, $query)) {
        $detected[] = $dimension;
      }
    }

    // Remove duplicates (e.g., if both 'product' and 'product_category' match)
    return array_unique($detected);
  }

  /**
   * Get all temporal patterns
   *
   * @return array Temporal patterns map (dimension => regex)
   */
  public function getTemporalPatterns(): array
  {
    return $this->temporalPatterns;
  }

  /**
   * Get all non-temporal patterns
   *
   * @return array Non-temporal patterns map (dimension => regex)
   */
  public function getNonTemporalPatterns(): array
  {
    return $this->nonTemporalPatterns;
  }

  /**
   * Check if query contains temporal dimensions
   *
   * @param string $query Query text
   * @return bool True if temporal dimensions detected
   */
  public function hasTemporalDimensions(string $query): bool
  {
    return !empty($this->detectTemporalDimensions($query));
  }

  /**
   * Check if query contains non-temporal dimensions
   *
   * @param string $query Query text
   * @return bool True if non-temporal dimensions detected
   */
  public function hasNonTemporalDimensions(string $query): bool
  {
    return !empty($this->detectNonTemporalDimensions($query));
  }

  /**
   * Check if query contains mixed aggregations (both temporal and non-temporal)
   *
   * @param string $query Query text
   * @return bool True if both temporal and non-temporal dimensions detected
   */
  public function hasMixedAggregations(string $query): bool
  {
    return $this->hasTemporalDimensions($query) && $this->hasNonTemporalDimensions($query);
  }

  /**
   * Detect all aggregation dimensions (temporal + non-temporal)
   *
   * @param string $query Query text
   * @return array Array with keys 'temporal' and 'non_temporal'
   */
  public function detectAllDimensions(string $query): array
  {
    return [
      'temporal' => $this->detectTemporalDimensions($query),
      'non_temporal' => $this->detectNonTemporalDimensions($query),
      'is_mixed' => $this->hasMixedAggregations($query),
      'total_count' => count($this->detectTemporalDimensions($query)) + 
                       count($this->detectNonTemporalDimensions($query))
    ];
  }
}
