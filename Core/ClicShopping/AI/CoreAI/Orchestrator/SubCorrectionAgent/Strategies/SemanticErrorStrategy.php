<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies;

/**
 * SemanticErrorStrategy Class
 * Handles semantic/logical errors
 * 
 * This strategy addresses logical and semantic issues in SQL queries including:
 * - Incorrect query logic
 * - Misaligned business logic
 * - Logical contradictions in WHERE clauses
 * - Semantic misunderstandings of the data model
 * 
 * Note: Semantic errors are inherently complex and require LLM reasoning
 */
class SemanticErrorStrategy implements CorrectionStrategyInterface
{
  /**
   * Correct semantic error
   *
   * @param array $errorContext Error context containing failed_query and original_query
   * @param array $errorAnalysis Error analysis with semantic error details
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence, suggestions
   */
  public function correct(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    $query = $errorContext['failed_query'];
    $originalQuery = $errorContext['original_query'] ?? '';

    // Semantic errors require deep understanding of intent and context
    // This strategy always delegates to LLM reasoning
    $suggestions = [
      "Review the query logic against the intended business requirement",
      "Verify the query aligns with the original question",
      "Check for logical contradictions in conditions",
      "Ensure the data model is correctly understood"
    ];

    // Add context-specific suggestions if original query is available
    if (!empty($originalQuery)) {
      $suggestions[] = "Original question: " . substr($originalQuery, 0, 100);
    }

    return [
      'query' => $query,
      'method' => 'semantic_correction_needs_llm',
      'confidence' => 0.2,
      'suggestions' => $suggestions,
    ];
  }

  /**
   * Get error type this strategy handles
   *
   * @return string Error type identifier
   */
  public function getErrorType(): string
  {
    return 'semantic_error';
  }

  /**
   * Get confidence level of this strategy
   *
   * @return float Confidence level (0.0 to 1.0)
   */
  public function getConfidenceLevel(): float
  {
    return 0.6;
  }
}
