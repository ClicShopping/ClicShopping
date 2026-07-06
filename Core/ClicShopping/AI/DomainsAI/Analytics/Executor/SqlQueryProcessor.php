<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Executor;

use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\EncryptedColumnPattern;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Class SqlQueryProcessor
 * Handles SQL query extraction, cleaning, validation, and placeholder resolution
 * Implements comprehensive security measures for SQL processing
 */
class SqlQueryProcessor
{
  private SecurityLogger $securityLogger;
  private int $languageId;
  private bool $debug;
  private array $placeholderMap;

  private mixed $db;

  /**
   * Constructor
   *
   * @param SecurityLogger $securityLogger Security logger instance
   * @param int $languageId Language ID for placeholder resolution
   * @param bool $debug Enable debug mode
   */
  public function __construct(SecurityLogger $securityLogger, int $languageId, bool $debug = false)
  {
    $this->securityLogger = $securityLogger;
    $this->languageId = $languageId;
    $this->debug = $debug;
    $this->db = Registry::get('Db');

    // Initialize default placeholder map
    $this->placeholderMap = [
      'language_id' => $languageId,
    ];
  }

  /**
   * Extracts SQL queries from a response string
   * Uses regex patterns to identify and validate SQL queries
   * Handles potential security issues and logs suspicious patterns
   *
   * @param string $response The response string containing SQL queries
   * @param bool $allowAllPatterns Whether to allow all SQL patterns (default: false, only SELECT)
   * @return array Array of extracted SQL queries with table prefixes added
   */
  public function extractSqlQueries(string $response, bool $allowAllPatterns = false): array
  {
    $queries = [];

    $safeResponse = InputValidator::validateParameter($response, 'string');
    if ($safeResponse !== $response) {
      $this->securityLogger->logSecurityEvent("Response sanitized in extractSqlQueries", 'warning');
      $response = $safeResponse;
    }

    $response = $this->cleanSqlResponse($response);

    $allPatterns = [
      '/\\b(SELECT\\s+.*?)(;|\\Z)/is',
      '/\\b(INSERT\\s+.*?)(;|\\Z)/is',
      '/\\b(UPDATE\\s+.*?)(;|\\Z)/is',
      '/\\b(DELETE\\s+.*?)(;|\\Z)/is',
      '/\\b(CREATE\\s+.*?)(;|\\Z)/is',
      '/\\b(ALTER\\s+.*?)(;|\\Z)/is',
      '/\\b(DROP\\s+.*?)(;|\\Z)/is'
    ];

    $sqlPatterns = $allowAllPatterns === false ? ['/\\b(SELECT\\s+.*?)(;|\\Z)/is'] : $allPatterns;

    foreach ($sqlPatterns as $pattern) {
      if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          $query = trim($match[1]);

          // Check for suspicious patterns
          if (preg_match('/(--|#|\/\*|\bsleep\b|\bbenchmark\b|\bxp_|;)/i', $query)) {
            $this->securityLogger->logSecurityEvent(
              "Rejected query due to suspicious SQL pattern",
              'warning',
              ['query' => $query]
            );
            continue;
          }

          $validation = InputValidator::validateSqlQuery($query);
          if (!$validation['valid']) {
            $this->securityLogger->logSecurityEvent(
              "Potentially malicious SQL pattern detected in extracted query: " . implode(', ', $validation['issues']),
              'warning',
              ['query' => $query]
            );
            continue;
          }
 
          $query = $this->addTablePrefix($query);
     
          $query = $this->fixMultiWordLikePatterns($query);
          
          $queries[] = $query;
        }
      }
    }

    // If no queries found, try to match the entire response
    if (empty($queries)) {
      $fullPattern = $allowAllPatterns === false 
        ? '/^\s*(SELECT)\s+/i' 
        : '/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\s+/i';

      if (preg_match($fullPattern, trim($response))) {
        $query = trim($response);

        if (preg_match('/(--|#|\/\*|\bsleep\b|\bbenchmark\b|\bxp_|;)/i', $query)) {
          $this->securityLogger->logSecurityEvent(
            "Rejected full response due to suspicious SQL pattern",
            'warning',
            ['query' => $query]
          );
        } else {
          $validation = InputValidator::validateSqlQuery($query);
          if ($validation['valid']) {   
            $query = $this->addTablePrefix($query);     
            $query = $this->fixMultiWordLikePatterns($query);
            
            $queries[] = $query;
          } else {
            $this->securityLogger->logSecurityEvent(
              "Potentially malicious SQL pattern detected in full response: " . implode(', ', $validation['issues']),
              'warning',
              ['query' => $query]
            );
          }
        }
      }
    }

    return $queries;
  }

  /**
   * Cleans the SQL response by removing formatting tags
   * Removes SQL code block markers
   * Strips HTML tags
   * Trims whitespace
   *
   * @param string $response Raw response from the model
   * @return string Cleaned SQL query ready for execution
   */
  public function cleanSqlResponse(string $response): string
  {
    $cleaned = preg_replace('/```sql\s*|\s*```/', '', $response);
    $cleaned = strip_tags($cleaned);
    $cleaned = trim($cleaned);

    // CRITICAL FIX: Remove SQL comments BEFORE validation
    // LLM sometimes adds explanatory comments like "-- Corrected: YEAR instead of MONTH"
    // These comments are legitimate but trigger security validation
    $cleaned = $this->removeSqlComments($cleaned);

    $cleaned = $this->addTablePrefix($cleaned);

    $cleaned = $this->fixMultiWordLikePatterns($cleaned);

    return $cleaned;
  }

  /**
   * Removes SQL comments from query
   * =============================================================
   * This method is part of the error correction strategy for SQL generation.
   * LLM sometimes adds explanatory comments like "-- Corrected: YEAR instead of MONTH"
   * when regenerating SQL after errors. These comments are legitimate but trigger
   * security validation that blocks SQL execution.
   * 
   * ERROR CORRECTION FLOW:
   * 1. SQL generation fails with "Unknown column" error
   * 2. CorrectionAgent uses LLM to regenerate corrected SQL
   * 3. LLM adds helpful comments explaining the fix
   * 4. This method removes comments BEFORE security validation
   * 5. Clean SQL passes validation and executes successfully
   * 
   * This ensures that error correction works smoothly without triggering
   * false positive security alerts.
   * 
   * Handles both single-line (--) and multi-line  comments.
   * 
   * @param string $sql SQL query with potential comments
   * @return string SQL query without comments
   */
  private function removeSqlComments(string $sql): string
  {
    // Remove single-line comments (-- comment)
    // Match -- followed by anything until end of line
    $sql = preg_replace('/--[^\n\r]*/', '', $sql);
    
    // Remove multi-line comments (/* comment */)
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Clean up extra whitespace left by comment removal
    $sql = preg_replace('/\s+/', ' ', $sql);
    $sql = trim($sql);
    
    return $sql;
  }

  /**
   * Adds table prefix to SQL queries if missing
   * Dynamically retrieves table names from the database
   * Ensures all table references have the correct prefix
   *
   * @param string $sql SQL query to process
   * @return string SQL query with table prefixes added
   */
  private function addTablePrefix(string $sql): string
  {
    static $cachedTables = null;

    if ($cachedTables === null) {
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      $dbName = CLICSHOPPING::getConfig('db_database');

      try {
        // Get all database tables
        $stmt = $this->db->prepare("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = :dbName
            ");
        $stmt->execute([':dbName' => $dbName]);
        $cachedTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
      } catch (\Exception $e) {
        $cachedTables = [];
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Failed to retrieve table list: " . $e->getMessage(),
            'warning'
          );
        }
      }
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    foreach ($cachedTables as $table) {
      // Ajouter le préfixe uniquement si absent, pour FROM, JOIN, UPDATE, INSERT INTO, DELETE FROM
      $sql = preg_replace(
        '/\b(FROM|JOIN|UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+(?!' . preg_quote($prefix, '/') . ')(' . preg_quote($table, '/') . ')\b/i',
        '$1 ' . $prefix . '$2',
        $sql
      );
    }

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Table prefix dynamically added to SQL query",
        'info',
        ['sql_snippet' => substr($sql, 0, 200)]
      );
    }

    return $sql;
  }

  /**
   * Resolves placeholders in SQL queries with their actual values
   * Replaces [placeholder] syntax with corresponding values
   * Handles common placeholders like language_id
   *
   * @param string $sqlQuery SQL query with placeholders
   * @return string SQL query with resolved placeholders
   */
  public function resolvePlaceholders(string $sqlQuery): string
  {
    // Validate input
    $safeSqlQuery = InputValidator::validateParameter($sqlQuery, 'string');

    if ($safeSqlQuery !== $sqlQuery) {
      $this->securityLogger->logSecurityEvent("SQL query sanitized in resolvePlaceholders", 'warning');
      $sqlQuery = $safeSqlQuery;
    }

    // Detect placeholders in the format [placeholder_name]
    preg_match_all('/\[([^\]]+)\]/', $sqlQuery, $matches);

    if (empty($matches[1])) {
      return $sqlQuery;
    }

    $placeholders = array_unique($matches[1]);
    $resolvedQuery = $sqlQuery;

    foreach ($placeholders as $placeholder) {
      $value = $this->getPlaceholderValue($placeholder);

      if ($value === null) {
        $this->securityLogger->logSecurityEvent(
          "Unknown placeholder encountered: [{$placeholder}]",
          'warning'
        );
        $value = "'UNKNOWN_PLACEHOLDER_{$placeholder}'"; // Descriptive default value
      }

      $resolvedQuery = str_replace("[$placeholder]", $value, $resolvedQuery);
    }

    return $resolvedQuery;
  }

  /**
   * Gets the value for a specific placeholder
   * Maps common placeholders to their corresponding values
   * Provides fallback value for unknown placeholders
   * Logs unknown placeholders when debug mode is enabled
   *
   * @param string $placeholder Name of the placeholder to resolve
   * @return string|null Value to replace the placeholder, or null if not found
   */
  public function getPlaceholderValue(string $placeholder): ?string
  {
    if (isset($this->placeholderMap[$placeholder])) {
      return (string) $this->placeholderMap[$placeholder];
    }

    // Log unknown placeholders
    $this->securityLogger->logSecurityEvent(
      "Unknown placeholder encountered: [{$placeholder}]",
      'info'
    );

    if ($this->debug) {
      $this->securityLogger->logApplicationError("Placeholder unknown: [$placeholder]");
    }

    // Return default safe value
    return '1';
  }

  /**
   * Adds a custom placeholder to the placeholder map
   * Allows extending the placeholder system with custom values
   *
   * @param string $name Placeholder name
   * @param mixed $value Placeholder value
   * @return void
   */
  public function addPlaceholder(string $name, mixed $value): void
  {
    $this->placeholderMap[$name] = $value;
  }

  /**
   * Generates SQL query from natural language query
   * ================================================================
   * This method provides a standardized interface for SQL generation.
   * The actual generation is handled by AnalyticsAgent which uses LLM prompts
   * that have been enhanced to prevent common SQL errors:
   * 
   * CRITICAL RULES FOR ORDER BY (enforced in LLM prompts):
   * 1. If you use YEAR(column) in GROUP BY, you MUST use one of:
   *    - ORDER BY YEAR(column)  (repeat the function)
   *    - ORDER BY 1  (use column position)
   *    - Add alias: SELECT YEAR(column) AS year_value ... ORDER BY year_value
   * 
   * 2. If you use QUARTER(column) in GROUP BY, same rules apply
   * 3. NEVER reference a column name that doesn't exist as an alias
   * 
   * CORRECT EXAMPLES:
   * ✓ SELECT QUARTER(date) AS quarter ... GROUP BY QUARTER(date) ORDER BY QUARTER(date)
   * ✓ SELECT YEAR(date) AS year_val ... GROUP BY year_val ORDER BY year_val
   * ✓ SELECT MONTH(date) AS month ... GROUP BY 1 ORDER BY 1
   * 
   * INCORRECT EXAMPLES (will cause "Unknown column" errors):
   * ✗ SELECT QUARTER(date) ... GROUP BY QUARTER(date) ORDER BY quarter
   * ✗ SELECT YEAR(date) ... GROUP BY YEAR(date) ORDER BY year
   * 
   * These prompt enhancements ensure that hybrid mode queries generate valid SQL
   * that executes successfully and returns data for table display.
   * 
   * This method is designed to be called by HybridQueryProcessor and other
   * components that need a consistent interface for SQL generation.
   *
   * @param string $naturalLanguageQuery Natural language query to convert to SQL
   * @param array $context Context information (language_id, entity_id, etc.)
   * @return array Result array with structure:
   *   [
   *     'success' => bool,
   *     'sql' => string|null,
   *     'parameters' => array,
   *     'error' => string|null,
   *     'metadata' => array
   *   ]
   */
  public function generateSqlFromQuery(string $naturalLanguageQuery, array $context = []): array
  {
    try {
      // Validate input
      $safeQuery = InputValidator::validateParameter($naturalLanguageQuery, 'string');
      
      if ($safeQuery !== $naturalLanguageQuery) {
        $this->securityLogger->logSecurityEvent(
          "Natural language query sanitized in generateSqlFromQuery",
          'warning'
        );
        $naturalLanguageQuery = $safeQuery;
      }

      // Log the generation attempt
      $this->securityLogger->logSecurityEvent(
        "Generating SQL from natural language query: " . substr($naturalLanguageQuery, 0, 100),
        'info'
      );

      // Note: Actual SQL generation is handled by AnalyticsAgent which uses
      // LLM to convert natural language to SQL. This method provides a
      // standardized interface for that functionality.
      
      // For now, return a structure indicating that SQL generation should
      // be handled by AnalyticsAgent
      return [
        'success' => true,
        'sql' => null,
        'parameters' => [],
        'error' => null,
        'metadata' => [
          'note' => 'SQL generation is handled by AnalyticsAgent.processBusinessQuery()',
          'query' => $naturalLanguageQuery,
          'context' => $context,
        ],
      ];

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error in generateSqlFromQuery: " . $e->getMessage(),
        'error',
        ['query' => $naturalLanguageQuery]
      );

      return [
        'success' => false,
        'sql' => null,
        'parameters' => [],
        'error' => $e->getMessage(),
        'metadata' => [
          'query' => $naturalLanguageQuery,
        ],
      ];
    }
  }

  /**
   * Validates SQL syntax using InputValidator
   * Logs security events for invalid syntax
   *
   * @param array $validation Validation result from InputValidator
   * @param string $query SQL query being validated
   * @return bool True if valid, false otherwise
   */
  private function isSqlSyntaxValid(array $validation, string $query): bool
  {
    if (!$validation['valid']) {
      $this->securityLogger->logSecurityEvent(
        "Rejected query due to invalid SQL syntax (parse failure)",
        'warning',
        ['query' => $query]
      );
      return false;
    }
    return true;
  }

  /**
   * Fix date filters in SQL queries to include YEAR() when MONTH() is used
   * 
   * to avoid returning data from all years.
   * 
   * Examples:
   * - WRONG: WHERE MONTH(date) IN (1,2,3)
   * - CORRECT: WHERE YEAR(date) = 2025 AND MONTH(date) IN (1,2,3)
   *
   * @param string $sql SQL query to fix
   * @return string Fixed SQL query with proper date filtering
   */
  public function fixDateFilters(string $sql): string
  {
    try {
      // Check if SQL has MONTH() filter but no YEAR() filter
      $hasMonthFilter = preg_match('/MONTH\s*\(([^)]+)\)\s+(IN|=)/i', $sql, $monthMatches);
      $hasYearFilter = preg_match('/YEAR\s*\([^)]+\)\s*=\s*\d{4}/i', $sql);

      if ($hasMonthFilter && !$hasYearFilter) {
        $currentYear = date('Y');
        
        // Extract the column name from MONTH() function
        $dateColumn = isset($monthMatches[1]) ? trim($monthMatches[1]) : 'date_purchased';
        
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Fixing date filter: Adding YEAR({$currentYear}) to query with MONTH() filter (column: {$dateColumn})",
            'info'
          );
        }

        // Find the WHERE clause and add YEAR filter
        // Pattern 1: WHERE MONTH(...) - add YEAR before MONTH
        $sql = preg_replace(
          '/(WHERE\s+)(MONTH\s*\([^)]+\))/i',
          "$1YEAR({$dateColumn}) = {$currentYear} AND $2",
          $sql,
          1 // Only replace first occurrence
        );

        // Pattern 2: AND MONTH(...) without YEAR before it
        // Check if we still need to add YEAR (in case WHERE had other conditions)
        if (!preg_match('/YEAR\s*\([^)]+\)\s*=\s*\d{4}/i', $sql)) {
          $sql = preg_replace(
            '/(AND\s+)(MONTH\s*\([^)]+\))/i',
            "AND YEAR({$dateColumn}) = {$currentYear} AND $2",
            $sql,
            1
          );
        }

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Date filter fixed. New SQL: " . substr($sql, 0, 200),
            'info'
          );
        }
      }

      return $sql;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error fixing date filters: " . $e->getMessage(),
        'error'
      );
      
      // Return original SQL on error
      return $sql;
    }
  }

  /**
   * Removes GDPR-encrypted columns from GROUP BY before execution (schema-level guard).
   *
   * Thin wrapper: the detection/rewrite lives in
   * DomainsAI\Analytics\Patterns\EncryptedColumnPattern (schema-based, portable across LLMs —
   * not an LLM-output heuristic). See that class + rag_analytics_agent RULE -2 for the rationale.
   *
   * @param string $sql SQL query to fix
   * @return string SQL with encrypted columns removed from GROUP BY
   */
  public function fixEncryptedGroupBy(string $sql): string
  {
    try {
      $fixed = EncryptedColumnPattern::removeFromGroupBy($sql);

      if ($fixed !== $sql) {
        $this->securityLogger->logSecurityEvent(
          "Auto-corrected GROUP BY: removed GDPR-encrypted column(s) to prevent shattered aggregation (RULE -2)",
          'warning'
        );
      }

      return $fixed;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error in fixEncryptedGroupBy: " . $e->getMessage(),
        'error'
      );

      return $sql;
    }
  }

  /**
   * Fixes multi-word LIKE patterns by splitting them into multiple AND conditions
   * 
   * for better search results that don't require exact word order.
   * 
   * Examples:
   * - Input:  WHERE products_name LIKE '%Apple iPhone 17 Pro%'
   * - Output: WHERE products_name LIKE '%Apple%' AND products_name LIKE '%iPhone%' 
   *           AND products_name LIKE '%17%' AND products_name LIKE '%Pro%'
   * 
   * This method:
   * 1. Detects LIKE patterns with multiple words
   * 2. Extracts the column name and pattern
   * 3. Splits the pattern into individual words
   * 4. Generates multiple LIKE conditions joined with AND
   * 5. Preserves other SQL clauses (ORDER BY, LIMIT, etc.)
   *
   * @param string $sql SQL query to fix
   * @return string Fixed SQL query with multi-token LIKE patterns
   */
  public function fixMultiWordLikePatterns(string $sql): string
  {
    try {
      // Validate input
      $safeSql = InputValidator::validateParameter($sql, 'string');
      
      if ($safeSql !== $sql) {
        $this->securityLogger->logSecurityEvent(
          "SQL sanitized in fixMultiWordLikePatterns",
          'warning'
        );
        $sql = $safeSql;
      }

      // Pattern to match: column_name LIKE '%multi word pattern%'
      // Captures: (column_name) LIKE '(%pattern%)'
      $likePattern = '/(\w+\.\w+|\w+)\s+LIKE\s+\'%([^%\']+)%\'/i';
      
      $sql = preg_replace_callback($likePattern, function($matches) {
        $columnName = $matches[1];  // e.g., "pd.products_name" or "products_name"
        $pattern = $matches[2];      // e.g., "Apple iPhone 17 Pro"
        
        // Remove extra whitespace and trim
        $pattern = trim(preg_replace('/\s+/', ' ', $pattern));
        
        // Check if pattern contains multiple words
        $words = preg_split('/\s+/', $pattern);
        $wordCount = count($words);
        
        // If single word, keep original pattern
        if ($wordCount <= 1) {
          return $matches[0]; // Return original match
        }
        
        // Multi-word pattern detected - split into multiple LIKE conditions
        $conditions = [];
        foreach ($words as $word) {
          $word = trim($word);
          if (!empty($word)) {
            // Escape single quotes in word
            $escapedWord = str_replace("'", "''", $word);
            $conditions[] = "{$columnName} LIKE '%{$escapedWord}%'";
          }
        }
        
        // Join conditions with AND
        $result = '(' . implode(' AND ', $conditions) . ')';
        
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Fixed multi-word LIKE pattern: '{$pattern}' ({$wordCount} words) -> {$result}",
            'info'
          );
        }
        
        return $result;
        
      }, $sql);

      return $sql;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error in fixMultiWordLikePatterns: " . $e->getMessage(),
        'error',
        ['sql' => substr($sql, 0, 200)]
      );
      
      // Return original SQL on error
      return $sql;
    }
  }

  /**
   * Validates LIKE patterns in SQL queries to detect single-token multi-word patterns
   * 
   * This method analyzes LIKE conditions in SQL queries to identify patterns that should
   * be split into multiple LIKE conditions for better search results. For example:
   * - WRONG: LIKE '%iPhone 17 Pro%' (single-token multi-word pattern)
   * - CORRECT: LIKE '%iPhone%' AND LIKE '%17%' AND LIKE '%Pro%' (multi-token pattern)
   * 
   * The validation helps monitor and improve search quality by detecting overly restrictive
   * patterns that require exact word order matching.
   *
   * @param string $sql SQL query to validate
   * @return array Validation result with structure:
   *   [
   *     'valid' => bool,              // True if no issues detected
   *     'warnings' => array,          // Array of warning messages
   *     'like_count' => int,          // Total number of LIKE conditions found
   *     'patterns' => array,          // Array of extracted LIKE patterns
   *     'suggestions' => array        // Array of suggested improvements
   *   ]
   */
  public function validateLikePatterns(string $sql): array
  {
    $result = [
      'valid' => true,
      'warnings' => [],
      'like_count' => 0,
      'patterns' => [],
      'suggestions' => []
    ];

    try {
      // Validate input
      $safeSql = InputValidator::validateParameter($sql, 'string');
      
      if ($safeSql !== $sql) {
        $this->securityLogger->logSecurityEvent(
          "SQL sanitized in validateLikePatterns",
          'warning'
        );
        $sql = $safeSql;
      }

      // Extract all LIKE patterns using regex
      // Pattern matches: LIKE 'pattern' or LIKE "pattern"
      // Handles both single and double quotes
      $likePattern = '/LIKE\s+\'([^\']+)\'/i';
      $likePatternDouble = '/LIKE\s+"([^"]+)"/i';
      
      $matches = [];
      $allMatches = [];
      
      // Extract patterns with single quotes
      if (preg_match_all($likePattern, $sql, $matches)) {
        $allMatches = array_merge($allMatches, $matches[1]);
      }
      
      // Extract patterns with double quotes
      if (preg_match_all($likePatternDouble, $sql, $matches)) {
        $allMatches = array_merge($allMatches, $matches[1]);
      }

      // Handle escaped quotes (e.g., \' or \")
      // Look for patterns like LIKE '%iPhone\'s%'
      $escapedPattern = '/LIKE\s+\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/i';
      if (preg_match_all($escapedPattern, $sql, $matches)) {
        foreach ($matches[1] as $pattern) {
          if (!in_array($pattern, $allMatches, true)) {
            $allMatches[] = $pattern;
          }
        }
      }

      $result['like_count'] = count($allMatches);
      $result['patterns'] = $allMatches;

      // If no LIKE patterns found, return valid result
      if ($result['like_count'] === 0) {
        return $result;
      }

      // Analyze each pattern for multi-word issues
      foreach ($allMatches as $pattern) {
        // Remove wildcards (% and _) to analyze the actual search term
        $cleanPattern = str_replace(['%', '_'], '', $pattern);
        $cleanPattern = trim($cleanPattern);

        // Skip empty patterns
        if (empty($cleanPattern)) {
          continue;
        }

        // Detect multi-word patterns (patterns containing spaces)
        $wordCount = str_word_count($cleanPattern);
        
        if ($wordCount > 1) {
          // This is a single-token multi-word pattern - generate warning
          $result['valid'] = false;
          
          $warning = sprintf(
            "Single-token multi-word LIKE pattern detected: '%s' (contains %d words). " .
            "This requires exact word order matching and may miss relevant results.",
            $pattern,
            $wordCount
          );
          $result['warnings'][] = $warning;

          // Generate suggestion for multi-token pattern
          $words = preg_split('/\s+/', $cleanPattern);
          $multiTokenConditions = [];
          
          foreach ($words as $word) {
            if (!empty($word)) {
              $multiTokenConditions[] = "LIKE '%" . $word . "%'";
            }
          }
          
          $suggestion = sprintf(
            "Consider splitting '%s' into multiple conditions: %s",
            $pattern,
            implode(' AND ', $multiTokenConditions)
          );
          $result['suggestions'][] = $suggestion;
        }
      }

      // Log validation results if warnings were generated
      if (!empty($result['warnings'])) {
        $this->securityLogger->logSecurityEvent(
          "LIKE pattern validation warnings: " . count($result['warnings']) . " issue(s) detected",
          'info',
          [
            'like_count' => $result['like_count'],
            'warnings' => $result['warnings'],
            'sql_snippet' => substr($sql, 0, 200)
          ]
        );
      }

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error in validateLikePatterns: " . $e->getMessage(),
        'error',
        ['sql' => substr($sql, 0, 200)]
      );
      
      $result['valid'] = false;
      $result['warnings'][] = "Validation error: " . $e->getMessage();
    }

    return $result;
  }
}
