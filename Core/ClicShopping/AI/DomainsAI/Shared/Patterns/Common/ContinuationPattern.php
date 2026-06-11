<?php
/**
 * ContinuationPattern
 *
 * Pattern class for detecting query continuation indicators.
 * Extracted from QueryAnalyzer to follow pattern separation principle.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * REFACTORING: Extracted from QueryAnalyzer (2026-01-05)
 * RESTRUCTURATION: Relocated to Common (2026-01-22)
 * 
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 */

namespace ClicShopping\AI\DomainsAI\Shared\Patterns\Common;




// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class ContinuationPattern
{
  /**
   * Get continuation detection patterns
   *
   * Returns regex patterns for detecting query continuation indicators
   * (e.g., "with their", "and also", "but with", etc.)
   *
   * @return array Continuation regex patterns
   */
  public static function getPatterns(): array
  {
    return [
      '/with\s+(their|its|the)/',            // "with their sku"
      '/and\s+(also|additionally|too)/',     // "and also"
      '/but\s+(with|without|including)/',    // "but with"
      '/more\s+(detailed|complete|specific)/',// "more detailed"
      '/add\s+(also|too)/',                  // "add also"
      '/include\s+(also|too)/',              // "include also"
    ];
  }

  /**
   * Check if query matches continuation patterns
   *
   * Determines if the query is a continuation of a previous query
   * based on continuation indicators.
   *
   * @param string $query Query text
   * @return bool True if query matches continuation patterns
   */
  public static function matches(string $query): bool
  {
    $patterns = self::getPatterns();
    $queryLower = strtolower($query);

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $queryLower)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Get metadata about this pattern
   *
   * @return array Pattern metadata
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'Continuation Pattern',
      'description' => 'Detects query continuation indicators (with their, and also, but with, etc.)',
      'pattern_count' => count(self::getPatterns()),
      'usage' => 'QueryAnalyzer::analyzeQueryContextRelation()',
      'domain' => 'Common',
      'examples' => [
        'with their sku',
        'and also show prices',
        'but with stock information',
        'more detailed results',
        'add also categories',
        'include also manufacturers'
      ]
    ];
  }
}
