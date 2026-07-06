<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies;

use ClicShopping\OM\Registry;

use ClicShopping\AI\CoreAI\Orchestrator\CorrectionAgent;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * ColumnErrorStrategy Class
 * Handles unknown column errors by finding similar column names in schema
 * 
 * This strategy attempts to correct column name typos by:
 * - Extracting the unknown column name from error details
 * - Finding similar column names in the database schema
 * - Replacing the unknown column with the most similar match
 */
class ColumnErrorStrategy implements CorrectionStrategyInterface
{
  /**
   * Correct column error
   *
   * @param array $errorContext Error context containing failed_query
   * @param array $errorAnalysis Error analysis with column_name in details
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence, suggestions
   */
  public function correct(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    $query = $errorContext['failed_query'];
    $errorMessage = $errorContext['error_message'] ?? '';
    $unknownColumn = $errorAnalysis['details']['column_name'] ?? '';

    if (empty($unknownColumn)) {
      // Cannot correct without knowing which column is unknown
      return [
        'query' => $query,
        'method' => 'column_correction_failed',
        'confidence' => 0.0,
        'suggestions' => ['Unable to identify unknown column name'],
      ];
    }

    // Check if this is an ORDER BY column reference error
    if ($this->isOrderByError($query, $errorMessage)) {
      return $this->correctOrderByError($query, $unknownColumn, $errorMessage);
    }

    // Find similar column in schema (alias-resolved, scoped to the aliased table)
    $similarColumn = $this->findSimilarColumnInSchema($unknownColumn, $query);

    if ($similarColumn && $similarColumn !== $unknownColumn) {
      $corrected = str_replace($unknownColumn, $similarColumn, $query);

      return [
        'query' => $corrected,
        'method' => 'column_name_correction',
        'confidence' => 0.8,
        'suggestions' => ["Column '$unknownColumn' replaced with '$similarColumn'"],
      ];
    }

    // No similar column found
    return [
      'query' => $query,
      'method' => 'column_correction_failed',
      'confidence' => 0.0,
      'suggestions' => [
        "Column '$unknownColumn' not found in schema",
        "Check column name spelling",
        "Verify table aliases are correct"
      ],
    ];
  }

  /**
   * Check if error is related to ORDER BY clause
   *
   * @param string $query SQL query
   * @param string $errorMessage Error message
   * @return bool True if ORDER BY error
   */
  private function isOrderByError(string $query, string $errorMessage): bool
  {
    // Check if query contains ORDER BY and error mentions ORDER BY or column reference
    return (stripos($query, 'ORDER BY') !== false) &&
           (stripos($errorMessage, 'ORDER BY') !== false || 
            stripos($errorMessage, 'order clause') !== false);
  }

  /**
   * Correct ORDER BY column reference error using LLM
   *
   * @param string $query Original SQL query
   * @param string $unknownColumn Unknown column name
   * @param string $errorMessage Error message
   * @return array Correction result
   */
  private function correctOrderByError(string $query, string $unknownColumn, string $errorMessage): array
  {
    try {
      // Get language instance
      $language = Registry::get('Language');
      
      // Load language definitions for SQL correction prompts using DomainConfig
      DomainConfig::loadAgnosticLanguageFile('rag_sql_correction');
      
      // Get ORDER BY correction prompt from language file
      $prompt = $language->getDef('llm_prompt_order_by_correction', [
        'query' => $query,
        'unknown_column' => $unknownColumn,
        'error_message' => $errorMessage
      ]);

      // Use LLM to generate corrected ORDER BY clause
      $response = Gpt::getGptResponse($prompt, 300);

      // Parse the response to extract corrected SQL
      $correctedQuery = $this->parseOrderByCorrectionResponse($response, $query);

      if ($correctedQuery && $correctedQuery !== $query) {
        return [
          'query' => $correctedQuery,
          'method' => 'llm_order_by_correction',
          'confidence' => 0.85,
          'suggestions' => [
            "ORDER BY clause corrected using LLM",
            "Column reference '$unknownColumn' fixed"
          ],
        ];
      }

      // LLM correction failed
      return [
        'query' => $query,
        'method' => 'order_by_correction_failed',
        'confidence' => 0.0,
        'suggestions' => [
          "Unable to correct ORDER BY clause",
          "Try using column position (ORDER BY 1, 2, etc.)",
          "Or use the exact function expression from SELECT"
        ],
      ];

    } catch (\Exception $e) {
      return [
        'query' => $query,
        'method' => 'order_by_correction_error',
        'confidence' => 0.0,
        'suggestions' => [
          "Error during ORDER BY correction: " . $e->getMessage()
        ],
      ];
    }
  }

  /**
   * Build ORDER BY correction prompt
   * Uses English for internal processing as per domain agnosticism requirement
   *
   * @param string $query Original SQL query
   * @param string $unknownColumn Unknown column name
   * @param string $errorMessage Error message
   * @return string Correction prompt
   */
  private function buildOrderByCorrectionPrompt(string $query, string $unknownColumn, string $errorMessage): string
  {
    $parts = [];
    
    $parts[] = "You are an SQL expert. Fix the ORDER BY column reference error in this query.";
    $parts[] = "";
    $parts[] = "## Error Details";
    $parts[] = "Error: {$errorMessage}";
    $parts[] = "Unknown Column: {$unknownColumn}";
    $parts[] = "";
    $parts[] = "## Failed SQL Query";
    $parts[] = "```sql";
    $parts[] = $query;
    $parts[] = "```";
    $parts[] = "";
    $parts[] = "## ORDER BY Rules";
    $parts[] = "When using aggregate functions (YEAR, QUARTER, MONTH, etc.) in GROUP BY:";
    $parts[] = "";
    $parts[] = "1. Use the SAME function expression in ORDER BY:";
    $parts[] = "   ✅ CORRECT: GROUP BY YEAR(date) ORDER BY YEAR(date)";
    $parts[] = "   ❌ WRONG: GROUP BY YEAR(date) ORDER BY year";
    $parts[] = "";
    $parts[] = "2. OR use column position numbers:";
    $parts[] = "   ✅ CORRECT: SELECT YEAR(date), SUM(value) ... ORDER BY 1";
    $parts[] = "";
    $parts[] = "3. OR use the alias if you created one:";
    $parts[] = "   ✅ CORRECT: SELECT YEAR(date) AS year_value ... ORDER BY year_value";
    $parts[] = "";
    $parts[] = "## Your Task";
    $parts[] = "Fix ONLY the ORDER BY clause. Preserve the SELECT and GROUP BY clauses exactly as they are.";
    $parts[] = "";
    $parts[] = "Return ONLY the corrected SQL query, nothing else.";
    
    return implode("\n", $parts);
  }

  /**
   * Parse ORDER BY correction response from LLM
   *
   * @param string $response LLM response
   * @param string $originalQuery Original query
   * @return string|null Corrected query or null if parsing failed
   */
  private function parseOrderByCorrectionResponse(string $response, string $originalQuery): ?string
  {
    // Try to extract SQL from code block
    if (preg_match('/```sql\s*(.+?)\s*```/is', $response, $matches)) {
      return trim($matches[1]);
    }
    
    // Try to extract SQL without code block
    if (preg_match('/SELECT\s+.+/is', $response, $matches)) {
      return trim($matches[0]);
    }
    
    // If response looks like a complete SQL query, return it
    $trimmed = trim($response);
    if (stripos($trimmed, 'SELECT') === 0 && stripos($trimmed, 'ORDER BY') !== false) {
      return $trimmed;
    }
    
    return null;
  }

  /**
   * Find similar column in schema
   * 
   * This method searches the database schema for columns with similar names
   * using string similarity algorithms (e.g., Levenshtein distance).
   * 
   * @param string $columnName Column name to search for
   * @return string|null Similar column name or null if not found
   */
  private function findSimilarColumnInSchema(string $columnName, string $query = ''): ?string
  {
    // Split an "alias.column" reference; the alias lets us scope the search to the
    // right table (e.g. s.date_added → alias "s", column "date_added").
    $alias = null;
    $bareColumn = $columnName;

    if (str_contains($columnName, '.')) {
      [$alias, $bareColumn] = explode('.', $columnName, 2);
    }

    // Resolve which real table(s) to inspect: the aliased table when we have an
    // alias, otherwise every table referenced by the query.
    $tables = $this->resolveQueryTables($query, $alias);

    if (empty($tables)) {
      return null;
    }

    $best = null;
    $bestScore = 0.0;

    foreach ($tables as $table) {
      foreach ($this->getTableColumns($table) as $column) {
        if ($column === $bareColumn) {
          continue; // Column exists as-is in this table: nothing to correct.
        }

        $score = $this->columnSimilarity($bareColumn, $column);

        if ($score > $bestScore) {
          $bestScore = $score;
          $best = $column;
        }
      }
    }

    // Require a strong match so we never swap in an unrelated column.
    if ($best === null || $bestScore < 0.6) {
      return null;
    }

    // Preserve the alias prefix so the caller's str_replace() targets the exact reference.
    return $alias !== null ? $alias . '.' . $best : $best;
  }

  /**
   * Resolve the real table name(s) referenced by the query. With $alias, returns only
   * the table bound to that alias in a FROM/JOIN clause; otherwise returns every table.
   * Table/alias tokens are limited to [A-Za-z0-9_].
   *
   * @param string $query SQL query
   * @param string|null $alias Table alias to resolve (null = all tables)
   * @return array<string> Real table names
   */
  private function resolveQueryTables(string $query, ?string $alias): array
  {
    if (!preg_match_all('/\b(?:FROM|JOIN)\s+([A-Za-z0-9_]+)(?:\s+(?:AS\s+)?([A-Za-z0-9_]+))?/i', $query, $matches, PREG_SET_ORDER)) {
      return [];
    }

    // Words that are not real aliases when they follow the table name.
    $reserved = ['on', 'where', 'group', 'order', 'limit', 'having', 'join', 'inner', 'left', 'right', 'outer', 'as'];
    $tables = [];

    foreach ($matches as $m) {
      $table = $m[1];
      $tableAlias = (isset($m[2]) && !in_array(strtolower($m[2]), $reserved, true)) ? $m[2] : null;

      if ($alias !== null) {
        if ($tableAlias !== null && strcasecmp($tableAlias, $alias) === 0) {
          return [$table];
        }
      } else {
        $tables[] = $table;
      }
    }

    return $alias !== null ? [] : array_values(array_unique($tables));
  }

  /**
   * Return the real column names of a table via SHOW COLUMNS (cached per run).
   *
   * @param string $table Real table name (already limited to [A-Za-z0-9_])
   * @return array<string> Column names
   */
  private function getTableColumns(string $table): array
  {
    static $cache = [];

    if (isset($cache[$table])) {
      return $cache[$table];
    }

    $columns = [];

    try {
      if (Registry::exists('Db')) {
        $stmt = Registry::get('Db')->query('SHOW COLUMNS FROM ' . $table);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
          if (isset($row['Field'])) {
            $columns[] = $row['Field'];
          }
        }
      }
    } catch (\Throwable $e) {
      $columns = []; // Unknown/inaccessible table: correction simply fails.
    }

    $cache[$table] = $columns;

    return $columns;
  }

  /**
   * Similarity score in [0,1] between an unknown column and a candidate. A candidate
   * that is "<prefix>_<unknown>" or "<unknown>_<suffix>" (the common "<table>_<column>"
   * naming, e.g. date_added → specials_date_added) scores highest; otherwise a
   * Levenshtein-based ratio is used.
   *
   * @param string $unknown Unknown (bare) column name
   * @param string $candidate Candidate real column name
   * @return float Similarity score (0.0 – 1.0)
   */
  private function columnSimilarity(string $unknown, string $candidate): float
  {
    $u = strtolower($unknown);
    $c = strtolower($candidate);

    if ($u === '' || $c === '') {
      return 0.0;
    }

    if (str_ends_with($c, '_' . $u) || str_starts_with($c, $u . '_')) {
      return 0.95;
    }

    if (str_contains($c, $u)) {
      return 0.8;
    }

    $distance = levenshtein($u, $c);
    $maxLen = max(strlen($u), strlen($c));

    return $maxLen === 0 ? 0.0 : 1.0 - ($distance / $maxLen);
  }

  /**
   * Get error type this strategy handles
   *
   * @return string Error type identifier
   */
  public function getErrorType(): string
  {
    return 'unknown_column';
  }

  /**
   * Get confidence level of this strategy
   *
   * @return float Confidence level (0.0 to 1.0)
   */
  public function getConfidenceLevel(): float
  {
    return 0.75;
  }
}
