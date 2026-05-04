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
 * GroupByErrorStrategy Class
 * Handles GROUP BY errors (missing columns in GROUP BY clause)
 * 
 * This strategy corrects ONLY_FULL_GROUP_BY errors by:
 * - Identifying missing columns in the GROUP BY clause
 * - Adding the missing columns to the GROUP BY clause
 * - Maintaining proper SQL syntax
 */
class GroupByErrorStrategy implements CorrectionStrategyInterface
{
  /**
   * Correct GROUP BY error
   *
   * @param array $errorContext Error context containing failed_query
   * @param array $errorAnalysis Error analysis with missing_column in details
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence, suggestions
   */
  public function correct(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    $query = $errorContext['failed_query'];
    $missingColumn = $errorAnalysis['details']['missing_column'] ?? '';

    if (empty($missingColumn)) {
      // Cannot correct without knowing which column is missing
      return [
        'query' => $query,
        'method' => 'group_by_correction_failed',
        'confidence' => 0.0,
        'suggestions' => ['Unable to identify missing column in GROUP BY clause'],
      ];
    }

    // Find and update GROUP BY clause
    if (preg_match('/GROUP BY\s+(.+?)(?:\s+ORDER BY|\s+HAVING|\s*$)/i', $query, $matches)) {
      $currentGroupBy = trim($matches[1]);

      // Check if the missing column is already in GROUP BY
      if (stripos($currentGroupBy, $missingColumn) === false) {
        $newGroupBy = $currentGroupBy . ', ' . $missingColumn;
        $corrected = preg_replace(
          '/GROUP BY\s+' . preg_quote($currentGroupBy, '/') . '/i',
          'GROUP BY ' . $newGroupBy,
          $query,
          1
        );

        return [
          'query' => $corrected,
          'method' => 'group_by_correction',
          'confidence' => 0.9,
          'suggestions' => ["Added missing column '$missingColumn' to GROUP BY clause"],
        ];
      }
    }

    // GROUP BY clause not found or column already present
    return [
      'query' => $query,
      'method' => 'group_by_correction_failed',
      'confidence' => 0.0,
      'suggestions' => [
        "Could not locate GROUP BY clause in query",
        "Ensure all non-aggregated columns are in GROUP BY clause"
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
    return 'group_by_error';
  }

  /**
   * Get confidence level of this strategy
   *
   * @return float Confidence level (0.0 to 1.0)
   */
  public function getConfidenceLevel(): float
  {
    return 0.85;
  }
}
