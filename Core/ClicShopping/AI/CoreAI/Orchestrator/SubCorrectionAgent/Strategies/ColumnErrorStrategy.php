<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
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

    // Find similar column in schema
    $similarColumn = $this->findSimilarColumnInSchema($unknownColumn);

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
      DomainConfig::loadLanguageFile('rag_sql_correction');
      
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
  private function findSimilarColumnInSchema(string $columnName): ?string
  {
    // TODO: Implement schema lookup with similarity matching
    // This would require:
    // 1. Query INFORMATION_SCHEMA to get all column names
    // 2. Calculate similarity scores (Levenshtein distance)
    // 3. Return the most similar column above a threshold
    
    // For now, return null (no match found)
    return null;
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
