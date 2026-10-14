<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies;

/**
 * SyntaxErrorStrategy Class
 * Handles SQL syntax errors (missing commas, unbalanced parentheses, WHERE clause issues)
 * 
 * This strategy corrects common SQL syntax errors including:
 * - Double commas (,, -> ,)
 * - Unbalanced parentheses
 * - WHERE clause issues (WHERE AND/OR -> WHERE)
 */
class SyntaxErrorStrategy implements CorrectionStrategyInterface
{
  /**
   * Correct syntax error
   *
   * @param array $errorContext Error context containing failed_query
   * @param array $errorAnalysis Error analysis results
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence
   */
  public function correct(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    $query = $errorContext['failed_query'];
    $corrected = $query;
    $confidence = 0.6;

    // Fix double commas
    if (preg_match('/,\s*,/', $corrected)) {
      $corrected = preg_replace('/,\s*,/', ',', $corrected);
      $confidence += 0.1;
    }

    // Balance parentheses
    $openCount = substr_count($corrected, '(');
    $closeCount = substr_count($corrected, ')');

    if ($openCount > $closeCount) {
      // Add missing closing parentheses
      $corrected .= str_repeat(')', $openCount - $closeCount);
      $confidence += 0.1;
    } elseif ($closeCount > $openCount) {
      // Remove extra closing parentheses
      for ($i = 0; $i < $closeCount - $openCount; $i++) {
        $pos = strrpos($corrected, ')');
        if ($pos !== false) {
          $corrected = substr($corrected, 0, $pos) . substr($corrected, $pos + 1);
        }
      }
      $confidence += 0.1;
    }

    // Fix WHERE clause issues (WHERE AND/OR -> WHERE)
    $corrected = preg_replace('/\bWHERE\s+(AND|OR)\b/i', 'WHERE', $corrected);

    return [
      'query' => $corrected,
      'method' => 'syntax_correction',
      'confidence' => min($confidence, 0.9),
    ];
  }

  /**
   * Get error type this strategy handles
   *
   * @return string Error type identifier
   */
  public function getErrorType(): string
  {
    return 'syntax_error';
  }

  /**
   * Get confidence level of this strategy
   *
   * @return float Confidence level (0.0 to 1.0)
   */
  public function getConfidenceLevel(): float
  {
    return 0.8;
  }
}
