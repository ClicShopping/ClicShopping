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
 * CorrectionStrategyInterface
 * Interface for all correction strategy implementations
 */
interface CorrectionStrategyInterface
{
  /**
   * Correct the error
   *
   * @param array $errorContext Error context containing error_message, failed_query, etc.
   * @param array $errorAnalysis Error analysis results
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence, reasoning
   */
  public function correct(array $errorContext, array $errorAnalysis, array $similarCases): array;

  /**
   * Get the error type this strategy handles
   *
   * @return string Error type identifier
   */
  public function getErrorType(): string;

  /**
   * Get the confidence level of this strategy
   *
   * @return float Confidence level (0.0 to 1.0)
   */
  public function getConfidenceLevel(): float;
}
