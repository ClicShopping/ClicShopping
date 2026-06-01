<?php
/**
 * ClicShopping AI - SQL Table Parser
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache\Helper;

/**
 * SQLTableParser - Parses SQL queries to extract table names
 *
 * This class provides functionality to parse SQL queries and extract
 * all table names referenced in the query. This is used for intelligent
 * cache invalidation - when a table is updated, all cached queries
 * that reference that table can be invalidated.
 *
 * Supports:
 * - SELECT queries (FROM, JOIN clauses)
 * - INSERT queries
 * - UPDATE queries
 * - DELETE queries
 * - Subqueries
 * - Table aliases
 * - Multiple tables in JOIN operations
 */
class SQLTableParser
{
  /**
   * Extract all table names from a SQL query
   *
   * @param string $sqlQuery The SQL query to parse
   * @return array Array of unique table names found in the query
   */
  public static function extractTables(string $sqlQuery): array
  {
    $tables = [];

    // Normalize the query: remove extra whitespace, convert to uppercase for parsing
    $normalizedQuery = preg_replace('/\s+/', ' ', trim($sqlQuery));
    $upperQuery = strtoupper($normalizedQuery);

    // Extract tables from FROM clause
    $tables = array_merge($tables, self::extractFromClause($normalizedQuery, $upperQuery));

    // Extract tables from JOIN clauses
    $tables = array_merge($tables, self::extractJoinClauses($normalizedQuery, $upperQuery));

    // Extract tables from INSERT INTO
    $tables = array_merge($tables, self::extractInsertTable($normalizedQuery, $upperQuery));

    // Extract tables from UPDATE
    $tables = array_merge($tables, self::extractUpdateTable($normalizedQuery, $upperQuery));

    // Extract tables from DELETE FROM
    $tables = array_merge($tables, self::extractDeleteTable($normalizedQuery, $upperQuery));

    // Remove duplicates and clean table names
    $tables = array_unique($tables);
    $tables = array_map(self::cleanTableName(...), $tables);
    $tables = array_filter($tables); // Remove empty strings

    return array_values($tables);
  }

  /**
   * Extract tables from FROM clause
   *
   * @param string $query Original query (preserves case)
   * @param string $upperQuery Uppercase version for pattern matching
   * @return array Array of table names
   */
  private static function extractFromClause(string $query, string $upperQuery): array
  {
    $tables = [];

    // Pattern: FROM table_name or FROM table_name alias
    if (preg_match('/\bFROM\s+([^\s,;(]+)/i', $query, $matches)) {
      $tables[] = $matches[1];
    }

    // Pattern: FROM table1, table2, table3
    if (preg_match('/\bFROM\s+([^WHERE|JOIN|GROUP|ORDER|LIMIT|;]+)/i', $query, $matches)) {
      $tableList = $matches[1];
      // Split by comma and extract table names
      $parts = explode(',', $tableList);
      foreach ($parts as $part) {
        $part = trim($part);
        // Extract table name (before alias if present)
        if (preg_match('/^([^\s]+)/', $part, $tableMatch)) {
          $tables[] = $tableMatch[1];
        }
      }
    }

    return $tables;
  }

  /**
   * Extract tables from JOIN clauses
   *
   * @param string $query Original query (preserves case)
   * @param string $upperQuery Uppercase version for pattern matching
   * @return array Array of table names
   */
  private static function extractJoinClauses(string $query, string $upperQuery): array
  {
    $tables = [];

    // Pattern: JOIN table_name or LEFT JOIN table_name, etc.
    preg_match_all('/\b(?:INNER\s+|LEFT\s+|RIGHT\s+|FULL\s+|CROSS\s+)?JOIN\s+([^\s,;(]+)/i', $query, $matches);
    if (!empty($matches[1])) {
      $tables = array_merge($tables, $matches[1]);
    }

    return $tables;
  }

  /**
   * Extract table from INSERT INTO clause
   *
   * @param string $query Original query (preserves case)
   * @param string $upperQuery Uppercase version for pattern matching
   * @return array Array of table names
   */
  private static function extractInsertTable(string $query, string $upperQuery): array
  {
    $tables = [];

    // Pattern: INSERT INTO table_name
    if (preg_match('/\bINSERT\s+INTO\s+([^\s(]+)/i', $query, $matches)) {
      $tables[] = $matches[1];
    }

    return $tables;
  }

  /**
   * Extract table from UPDATE clause
   *
   * @param string $query Original query (preserves case)
   * @param string $upperQuery Uppercase version for pattern matching
   * @return array Array of table names
   */
  private static function extractUpdateTable(string $query, string $upperQuery): array
  {
    $tables = [];

    // Pattern: UPDATE table_name
    if (preg_match('/\bUPDATE\s+([^\s,;]+)/i', $query, $matches)) {
      $tables[] = $matches[1];
    }

    return $tables;
  }

  /**
   * Extract table from DELETE FROM clause
   *
   * @param string $query Original query (preserves case)
   * @param string $upperQuery Uppercase version for pattern matching
   * @return array Array of table names
   */
  private static function extractDeleteTable(string $query, string $upperQuery): array
  {
    $tables = [];

    // Pattern: DELETE FROM table_name
    if (preg_match('/\bDELETE\s+FROM\s+([^\s,;]+)/i', $query, $matches)) {
      $tables[] = $matches[1];
    }

    return $tables;
  }

  /**
   * Clean table name by removing aliases, backticks, quotes, and database prefixes
   *
   * @param string $tableName Raw table name from query
   * @return string Cleaned table name
   */
  public static function cleanTableName(string $tableName): string
  {
    // Remove backticks and quotes
    $tableName = str_replace(['`', '"', "'"], '', $tableName);

    // Remove database prefix (e.g., database.table -> table)
    if (str_contains($tableName, '.')) {
      $parts = explode('.', $tableName);
      $tableName = end($parts);
    }

    // Remove alias (take only the first word)
    $parts = preg_split('/\s+/', $tableName);
    $tableName = $parts[0];

    // Remove any remaining special characters
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

    return trim($tableName);
  }

  /**
   * Check if a SQL query references a specific table
   *
   * @param string $sqlQuery The SQL query to check
   * @param string $tableName The table name to look for
   * @return bool True if the query references the table
   */
  public static function referencesTable(string $sqlQuery, string $tableName): bool
  {
    $tables = self::extractTables($sqlQuery);
    $cleanTableName = self::cleanTableName($tableName);

    return in_array($cleanTableName, $tables, true);
  }

  /**
   * Get a summary of tables used in a query with their usage context
   *
   * @param string $sqlQuery The SQL query to analyze
   * @return array Array with table names as keys and usage context as values
   */
  public static function getTableUsageSummary(string $sqlQuery): array
  {
    $summary = [];
    $tables = self::extractTables($sqlQuery);

    foreach ($tables as $table) {
      $summary[$table] = [
        'table' => $table,
        'in_from' => self::isInFromClause($sqlQuery, $table),
        'in_join' => self::isInJoinClause($sqlQuery, $table),
        'in_insert' => self::isInInsertClause($sqlQuery, $table),
        'in_update' => self::isInUpdateClause($sqlQuery, $table),
        'in_delete' => self::isInDeleteClause($sqlQuery, $table)
      ];
    }

    return $summary;
  }

  /**
   * Check if table is in FROM clause
   */
  private static function isInFromClause(string $query, string $table): bool
  {
    return preg_match('/\bFROM\s+[^;]*\b' . preg_quote($table, '/') . '\b/i', $query) === 1;
  }

  /**
   * Check if table is in JOIN clause
   */
  private static function isInJoinClause(string $query, string $table): bool
  {
    return preg_match('/\bJOIN\s+[^;]*\b' . preg_quote($table, '/') . '\b/i', $query) === 1;
  }

  /**
   * Check if table is in INSERT clause
   */
  private static function isInInsertClause(string $query, string $table): bool
  {
    return preg_match('/\bINSERT\s+INTO\s+' . preg_quote($table, '/') . '\b/i', $query) === 1;
  }

  /**
   * Check if table is in UPDATE clause
   */
  private static function isInUpdateClause(string $query, string $table): bool
  {
    return preg_match('/\bUPDATE\s+' . preg_quote($table, '/') . '\b/i', $query) === 1;
  }

  /**
   * Check if table is in DELETE clause
   */
  private static function isInDeleteClause(string $query, string $table): bool
  {
    return preg_match('/\bDELETE\s+FROM\s+' . preg_quote($table, '/') . '\b/i', $query) === 1;
  }
}
