<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies;

/**
 * TableErrorStrategy Class
 * Handles unknown table errors
 * 
 * This strategy attempts to correct table name errors by:
 * - Extracting the unknown table name from error details
 * - Validating table existence in the database
 * - Suggesting alternatives or corrections
 * 
 * Note: Currently delegates to LLM reasoning for complex table corrections
 */
class TableErrorStrategy implements CorrectionStrategyInterface
{
  /**
   * Correct table error
   *
   * @param array $errorContext Error context containing failed_query
   * @param array $errorAnalysis Error analysis with table_name in details
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence, suggestions
   */
  public function correct(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    $query = $errorContext['failed_query'];
    $unknownTable = $errorAnalysis['details']['table_name'] ?? '';

    if (empty($unknownTable)) {
      // Cannot correct without knowing which table is unknown
      return [
        'query' => $query,
        'method' => 'table_correction_failed',
        'confidence' => 0.0,
        'suggestions' => ['Unable to identify unknown table name'],
      ];
    }

    // For now, return suggestions without attempting automatic correction
    // Table name corrections are complex and often require LLM reasoning
    return [
      'query' => $query,
      'method' => 'table_correction_needs_llm',
      'confidence' => 0.3,
      'suggestions' => [
        "Table '$unknownTable' not found",
        "Verify table name spelling",
        "Check if table exists in the database",
        "Ensure proper table prefix if applicable"
      ],
    ];
  }

  /**
   * Get error type this strategy handles
   *
   * @return string Error type identifier
   */
  public function getErrorType(): string
  {
    return 'unknown_table';
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
