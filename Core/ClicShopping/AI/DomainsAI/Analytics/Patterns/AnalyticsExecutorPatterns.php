<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

/**
 * AnalyticsExecutorPatterns - Regex patterns for analytics query execution
 *
 * NOTE: These patterns are for FUTURE USE only.
 * Current implementation uses Pure LLM mode (no pattern matching).
 * Patterns are preserved here for potential future hybrid approaches.
 *
 * Contains patterns for:
 * - Date column detection in SQL queries
 * - Table name extraction
 * - Temporal GROUP BY clause generation
 *
 * @package ClicShopping\AI\Domain\Patterns
 * @since 2026-01-08
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/
// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class AnalyticsExecutorPatterns
{
  /**
   * Common date column patterns for SQL queries
   * 
   * Used to detect the date column in SQL queries for temporal aggregations.
   * Patterns are ordered by specificity.
   *
   * @var array<string> Array of regex patterns
   */
  public const DATE_COLUMN_PATTERNS = [
    '/\b(orders_date)\b/i',
    '/\b(date_purchased)\b/i',
    '/\b(created_at)\b/i',
    '/\b(date_added)\b/i',
    '/\b(order_date)\b/i',
    '/\b(purchase_date)\b/i',
    '/\bYEAR\s*\(\s*([a-zA-Z_]+)\s*\)/i',
    '/\bMONTH\s*\(\s*([a-zA-Z_]+)\s*\)/i',
    '/\bDATE\s*\(\s*([a-zA-Z_]+)\s*\)/i',
  ];

  /**
   * Pattern for extracting table name from SQL FROM clause
   *
   * @var string
   */
  public const TABLE_NAME_PATTERN = '/FROM\s+([a-zA-Z0-9_]+)/i';

  /**
   * Default date column when none is detected
   *
   * @var string
   */
  public const DEFAULT_DATE_COLUMN = 'orders_date';

  /**
   * Detect date column from SQL query
   * 
   * This method attempts to identify the date column used in the SQL query
   * by looking for common date column patterns.
   * 
   * @param string $sql The SQL query
   * @return string|null The detected date column or null if not found
   */
  public static function detectDateColumn(string $sql): ?string
  {
    foreach (self::DATE_COLUMN_PATTERNS as $pattern) {
      if (preg_match($pattern, $sql, $matches)) {
        return $matches[1];
      }
    }
    
    return null;
  }

  /**
   * Detect date column from SQL with default fallback
   * 
   * @param string $sql The SQL query
   * @return string The detected date column or default
   */
  public static function detectDateColumnWithDefault(string $sql): string
  {
    return self::detectDateColumn($sql) ?? self::DEFAULT_DATE_COLUMN;
  }

  /**
   * Extract table name from SQL query
   * 
   * @param string|null $sql SQL query
   * @return string Table name or 'database'
   */
  public static function extractTableName(?string $sql): string
  {
    if (empty($sql)) {
      return 'database';
    }

    if (preg_match(self::TABLE_NAME_PATTERN, $sql, $matches)) {
      return $matches[1];
    }

    return 'database';
  }

  /**
   * Get all patterns as an array
   *
   * @return array<string, mixed> All patterns
   */
  public static function getAllPatterns(): array
  {
    return [
      'date_column_patterns' => self::DATE_COLUMN_PATTERNS,
      'table_name_pattern' => self::TABLE_NAME_PATTERN,
      'default_date_column' => self::DEFAULT_DATE_COLUMN,
    ];
  }
}
