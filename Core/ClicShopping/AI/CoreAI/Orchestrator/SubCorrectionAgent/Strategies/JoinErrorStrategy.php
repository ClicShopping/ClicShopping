<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies;

/**
 * JoinErrorStrategy Class
 * Handles JOIN errors (incorrect JOIN conditions, ambiguous columns)
 * 
 * This strategy addresses JOIN-related errors including:
 * - Ambiguous column references
 * - Missing JOIN conditions
 * - Incorrect table relationships
 * 
 * Note: JOIN errors are complex and often require LLM reasoning for proper correction
 */
class JoinErrorStrategy implements CorrectionStrategyInterface
{
  /**
   * Correct JOIN error
   *
   * @param array $errorContext Error context containing failed_query
   * @param array $errorAnalysis Error analysis with join-specific details
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence, suggestions
   */
  public function correct(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    $query = $errorContext['failed_query'];

    // JOIN errors are complex and typically require LLM reasoning
    // This strategy provides suggestions but delegates actual correction to LLM
    return [
      'query' => $query,
      'method' => 'join_correction_needs_llm',
      'confidence' => 0.3,
      'suggestions' => [
        "Check JOIN conditions for correctness",
        "Verify table aliases are used consistently",
        "Ensure ambiguous columns are qualified with table names",
        "Review table relationships and foreign keys"
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
    return 'join_error';
  }

  /**
   * Get confidence level of this strategy
   *
   * @return float Confidence level (0.0 to 1.0)
   */
  public function getConfidenceLevel(): float
  {
    return 0.7;
  }
}
