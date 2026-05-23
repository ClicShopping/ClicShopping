<?php
/**
 * TemporalConflictPattern
 *
 * Pattern class for detecting temporal conflicts and granularity in queries.
 * Extracted from AmbiguityOptimizer to follow pattern separation principle.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * REFACTORING: Extracted from AmbiguityOptimizer (2026-01-09)
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class TemporalConflictPattern
{
  /**
   * Get temporal connector patterns
   *
   * Returns patterns for detecting temporal connectors in queries.
   * Used to detect if multiple temporal periods are clearly separated.
   *
   * @return string Regex pattern for temporal connectors
   */
  public static function getTemporalConnectorPattern(): string
  {
    return '/\b(then|and|puis|et|ensuite|after that|followed by)\b/i';
  }

  /**
   * Get single day patterns
   *
   * Returns patterns for detecting single-day time ranges.
   * Used to identify when a query specifies a single day.
   *
   * @return array Array of regex patterns for single day detection
   */
  public static function getSingleDayPatterns(): array
  {
    return [
      '/\btoday\b/',
      '/\byesterday\b/',
      '/\b\d{4}-\d{2}-\d{2}\b/',  // ISO date format
      '/\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{1,2}(st|nd|rd|th)?\b/',  // Month day
      '/\b\d{1,2}(st|nd|rd|th)?\s+(january|february|march|april|may|june|july|august|september|october|november|december)\b/',  // Day month
    ];
  }

  /**
   * Get week patterns
   *
   * Returns patterns for detecting week-level time ranges.
   *
   * @return string Regex pattern for week detection
   */
  public static function getWeekPattern(): string
  {
    return '/\b(this week|last week|week \d+)\b/';
  }

  /**
   * Get month patterns
   *
   * Returns patterns for detecting month-level time ranges.
   *
   * @return string Regex pattern for month detection
   */
  public static function getMonthPattern(): string
  {
    return '/\b(this month|last month|january|february|march|april|may|june|july|august|september|october|november|december)\b/';
  }

  /**
   * Get quarter patterns
   *
   * Returns patterns for detecting quarter-level time ranges.
   *
   * @return string Regex pattern for quarter detection
   */
  public static function getQuarterPattern(): string
  {
    return '/\b(q[1-4]|quarter [1-4]|this quarter|last quarter)\b/';
  }

  /**
   * Get semester patterns
   *
   * Returns patterns for detecting semester-level time ranges.
   *
   * @return string Regex pattern for semester detection
   */
  public static function getSemesterPattern(): string
  {
    return '/\b(semester [1-2]|first semester|second semester|h[1-2])\b/';
  }

  /**
   * Get year patterns
   *
   * Returns patterns for detecting year-level time ranges.
   *
   * @return string Regex pattern for year detection
   */
  public static function getYearPattern(): string
  {
    return '/\b(year \d{4}|\d{4}|this year|last year)\b/';
  }

  /**
   * Get period hierarchy
   *
   * Returns the hierarchy of temporal periods from smallest to largest.
   * Used for comparing granularity levels.
   *
   * @return array Period hierarchy with numeric levels
   */
  public static function getPeriodHierarchy(): array
  {
    return [
      'day' => 1,
      'week' => 2,
      'month' => 3,
      'quarter' => 4,
      'semester' => 5,
      'year' => 6,
    ];
  }

  /**
   * Detect time range granularity
   *
   * Determines the granularity level of a time range string.
   *
   * @param string $timeRange Time range string (lowercase)
   * @return string Granularity level (day, week, month, quarter, semester, year)
   */
  public static function detectTimeRangeGranularity(string $timeRange): string
  {
    $range = strtolower($timeRange);

    // Single day patterns
    foreach (self::getSingleDayPatterns() as $pattern) {
      if (preg_match($pattern, $range)) {
        return 'day';
      }
    }

    // Week patterns
    if (preg_match(self::getWeekPattern(), $range)) {
      return 'week';
    }

    // Month patterns (but not if it includes a day number)
    if (preg_match(self::getMonthPattern(), $range) && !preg_match('/\d+/', $range)) {
      return 'month';
    }

    // Quarter patterns
    if (preg_match(self::getQuarterPattern(), $range)) {
      return 'quarter';
    }

    // Semester patterns
    if (preg_match(self::getSemesterPattern(), $range)) {
      return 'semester';
    }

    // Year patterns
    if (preg_match(self::getYearPattern(), $range)) {
      return 'year';
    }

    // Default to year if unknown
    return 'year';
  }

  /**
   * Check if time range represents a single day
   *
   * @param string $timeRange Time range string
   * @return bool True if single day
   */
  public static function isSingleDayRange(string $timeRange): bool
  {
    $range = strtolower($timeRange);

    foreach (self::getSingleDayPatterns() as $pattern) {
      if (preg_match($pattern, $range)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Check if query has temporal connector
   *
   * Detects if a query has clear temporal connectors (and, then, etc.)
   * to separate multiple temporal periods.
   *
   * @param string $query Query string
   * @return bool True if temporal connector found
   */
  public static function hasTemporalConnector(string $query): bool
  {
    return preg_match(self::getTemporalConnectorPattern(), $query) === 1;
  }

  /**
   * Get coarse aggregation periods
   *
   * Returns list of temporal periods that are too coarse for single-day ranges.
   *
   * @return array List of coarse period names
   */
  public static function getCoarseAggregationPeriods(): array
  {
    return ['week', 'month', 'quarter', 'semester', 'year'];
  }

  /**
   * Get metadata about this pattern
   *
   * @return array Pattern metadata
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'Temporal Conflict Pattern',
      'description' => 'Detects temporal conflicts and granularity in queries for ambiguity resolution',
      'granularity_levels' => array_keys(self::getPeriodHierarchy()),
      'pattern_types' => [
        'single_day' => count(self::getSingleDayPatterns()),
        'week' => 1,
        'month' => 1,
        'quarter' => 1,
        'semester' => 1,
        'year' => 1,
        'connectors' => 1,
      ],
      'usage' => 'AmbiguityOptimizer::detectTemporalConflicts(), detectTimeRangeGranularity(), isSingleDayRange()',
      'examples' => [
        'today' => 'day',
        'this week' => 'week',
        'Q1' => 'quarter',
        'year 2025' => 'year',
      ]
    ];
  }
}
