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

/**
 * TypeMismatchStrategy Class
 * Handles type mismatch errors
 * 
 * This strategy addresses data type conflicts including:
 * - String vs numeric comparisons
 * - Date format mismatches
 * - Type casting issues
 * 
 * Note: Type mismatch errors often require context-aware LLM reasoning
 */
class TypeMismatchStrategy implements CorrectionStrategyInterface
{
  /**
   * Correct type mismatch error
   *
   * @param array $errorContext Error context containing failed_query
   * @param array $errorAnalysis Error analysis with type mismatch details
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence, suggestions
   */
  public function correct(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    $query = $errorContext['failed_query'];

    // Type mismatch errors require understanding of data types and context
    // This strategy provides suggestions but delegates actual correction to LLM
    return [
      'query' => $query,
      'method' => 'type_mismatch_needs_llm',
      'confidence' => 0.3,
      'suggestions' => [
        "Check data types in comparisons and operations",
        "Ensure string values are properly quoted",
        "Verify numeric values are not quoted when used in calculations",
        "Review date/time format requirements",
        "Consider explicit type casting if needed (CAST, CONVERT)"
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
    return 'type_mismatch';
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
