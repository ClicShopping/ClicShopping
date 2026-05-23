<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

/**
 * TimeRangePattern
 *
 * Provides patterns for detecting time ranges in queries.
 * Extracted from UnifiedQueryAnalyzer for reusability.
 *
 * @package ClicShopping\AI\Domain\Patterns\Analytics
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/
// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class TimeRangePattern
{
  /**
   * Get relative time patterns
   *
   * @return array Associative array of pattern => range
   */
  public static function getRelativeTimePatterns(): array
  {
    return [
      'this year' => 'this year',
      'last year' => 'last year',
      'current year' => 'current year',
      'this month' => 'this month',
      'last month' => 'last month',
      'this quarter' => 'this quarter',
      'last quarter' => 'last quarter',
      'this week' => 'this week',
      'last week' => 'last week',
      'today' => 'today',
      'yesterday' => 'yesterday',
    ];
  }

  /**
   * Get month names
   *
   * @return array List of month names (lowercase)
   */
  public static function getMonthNames(): array
  {
    return [
      'january', 'february', 'march', 'april', 'may', 'june',
      'july', 'august', 'september', 'october', 'november', 'december'
    ];
  }

  /**
   * Extract time range from query
   *
   * Identifies the time range being queried (year 2025, this year, last month, etc.)
   *
   * @param string $query The query (should be in English and lowercase)
   * @return string|null The time range or null if not found
   */
  public static function extractTimeRange(string $query): ?string
  {
    $query = strtolower($query);
    
    // Check for specific year patterns
    if (preg_match('/\b(year\s+)?(\d{4})\b/i', $query, $matches)) {
      return 'year ' . $matches[2];
    }
    
    // Check for relative time patterns
    $relativePatterns = self::getRelativeTimePatterns();
    foreach ($relativePatterns as $pattern => $range) {
      if (str_contains($query, $pattern)) {
        return $range;
      }
    }
    
    // Check for date range patterns (e.g., "from January to March")
    if (preg_match('/from\s+(\w+)\s+to\s+(\w+)/i', $query, $matches)) {
      return 'from ' . $matches[1] . ' to ' . $matches[2];
    }
    
    // Check for specific month patterns
    $months = self::getMonthNames();
    foreach ($months as $month) {
      if (preg_match('/\b' . $month . '\s*(\d{4})?\b/i', $query, $matches)) {
        return isset($matches[1]) ? $month . ' ' . $matches[1] : $month;
      }
    }
    
    return null;
  }

  /**
   * Check if query contains a time range
   *
   * @param string $query The query to check
   * @return bool True if a time range is detected
   */
  public static function hasTimeRange(string $query): bool
  {
    return self::extractTimeRange($query) !== null;
  }

  /**
   * Get year pattern regex
   *
   * @return string Regex pattern for year detection
   */
  public static function getYearPattern(): string
  {
    return '/\b(year\s+)?(\d{4})\b/i';
  }

  /**
   * Get date range pattern regex
   *
   * @return string Regex pattern for date range detection
   */
  public static function getDateRangePattern(): string
  {
    return '/from\s+(\w+)\s+to\s+(\w+)/i';
  }

  /**
   * Get month pattern regex
   *
   * @param string $month Month name
   * @return string Regex pattern for month detection
   */
  public static function getMonthPattern(string $month): string
  {
    return '/\b' . preg_quote($month, '/') . '\s*(\d{4})?\b/i';
  }

  /**
   * Get metadata about time range patterns
   *
   * @return array Metadata about the pattern
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'TimeRangePattern',
      'description' => 'Detects time ranges in queries',
      'domain' => 'Analytics',
      'relative_patterns_count' => count(self::getRelativeTimePatterns()),
      'months_count' => count(self::getMonthNames()),
    ];
  }
}
