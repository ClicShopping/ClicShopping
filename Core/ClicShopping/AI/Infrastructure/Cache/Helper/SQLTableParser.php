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
  /**
   * A table reference: optional backtick/quote, optional db prefix, an identifier that starts with
   * a letter or underscore. Deliberately excludes `(` — a parenthesis after FROM/JOIN opens a
   * derived table — and anything starting with a digit, which is a literal, not a table.
   */
  private const IDENTIFIER = '[`"]?[A-Za-z_][A-Za-z0-9_$]*(?:\.[`"]?[A-Za-z_][A-Za-z0-9_$]*)?[`"]?';

  /** Words that follow a table reference but are never its alias. */
  private const NOT_AN_ALIAS = '(?:WHERE|INNER|LEFT|RIGHT|FULL|CROSS|OUTER|JOIN|GROUP|ORDER|HAVING|LIMIT|UNION|ON|SET|VALUES|USING)\b';

  /** One `FROM` item: a table, optionally followed by an alias (`t x`, `t AS x`). */
  private const FROM_ITEM = self::IDENTIFIER . '(?:\s+(?:AS\s+)?(?!' . self::NOT_AN_ALIAS . ')[A-Za-z_][A-Za-z0-9_$]*)?';

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

    // Clean BEFORE deduplicating: `clic_products p` and `clic_products` are two distinct raw
    // strings that name one table, and the old order let both through.
    $tables = array_map(self::cleanTableName(...), $tables);
    $tables = array_unique($tables);
    $tables = array_filter($tables); // Remove empty strings

    // A CTE name reads exactly like a table in `FROM cte_name`, but it exists only for the query.
    // Reported as a table it makes the execution guard abstain on a perfectly valid statement.
    $cteNames = self::extractCteNames($sqlQuery);

    if ($cteNames !== []) {
      $tables = array_filter($tables, static fn(string $t): bool => !in_array(strtolower($t), $cteNames, true));
    }

    return array_values($tables);
  }

  /**
   * Names bound by a WITH clause. They are query-local, never real tables.
   *
   * @param string $sqlQuery Query to scan
   * @return array<int, string> Lowercased CTE names
   */
  private static function extractCteNames(string $sqlQuery): array
  {
    if (!preg_match('/\bWITH\b/i', $sqlQuery)) {
      return [];
    }

    preg_match_all('/(?:\bWITH\s+(?:RECURSIVE\s+)?|,\s*)([`"]?[A-Za-z_][A-Za-z0-9_$]*[`"]?)\s*(?:\([^)]*\)\s*)?AS\s*\(/i', $sqlQuery, $matches);

    return array_map(
      static fn(string $name): string => strtolower(self::cleanTableName($name)),
      $matches[1] ?? []
    );
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

    // FROM table_name — never `FROM (`, which opens a derived table, not a table name.
    if (preg_match('/\bFROM\s+(' . self::IDENTIFIER . ')/i', $query, $matches)) {
      $tables[] = $matches[1];
    }

    // FROM a, b, c — comma-separated tables, ONE identifier each.
    // This used to capture everything up to the next SQL keyword and split it on commas
    preg_match_all('/\bFROM\s+((?:' . self::FROM_ITEM . ')(?:\s*,\s*(?:' . self::FROM_ITEM . '))*)/i', $query, $lists);

    foreach ($lists[1] ?? [] as $tableList) {
      foreach (explode(',', $tableList) as $part) {
        $tables[] = trim($part);
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

    // JOIN table_name — `JOIN (` opens a derived table and is deliberately not captured.
    preg_match_all('/\b(?:INNER\s+|LEFT\s+|RIGHT\s+|FULL\s+|CROSS\s+)?(?:OUTER\s+)?JOIN\s+(' . self::IDENTIFIER . ')/i', $query, $matches);
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
