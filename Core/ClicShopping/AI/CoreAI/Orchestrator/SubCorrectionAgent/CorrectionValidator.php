<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * CorrectionValidator Class
 * Responsible for validating proposed corrections
 */
class CorrectionValidator
{
  private float $confidenceThreshold;
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param float $confidenceThreshold Minimum confidence threshold for corrections
   * @param SecurityLogger $logger Security logger instance
   * @param bool $debug Debug mode flag
   */
  public function __construct(float $confidenceThreshold, SecurityLogger $logger, bool $debug)
  {
    $this->confidenceThreshold = $confidenceThreshold;
    $this->logger = $logger;
    $this->debug = $debug;
  }

  /**
   * Validate proposed correction
   *
   * @param array $correction Correction to validate
   * @param string|null $failedQuery The query that failed, to reject no-op corrections
   * @return array Validation result with is_valid, issues, warnings
   */
  public function validateCorrection(array $correction, ?string $failedQuery = null): array
  {
    $validation = [
      'is_valid' => true,
      'issues' => [],
      'warnings' => [],
    ];

    $query = $correction['query'] ?? '';

    // Validation 1: Check if query is empty
    if (empty($query)) {
      $validation['is_valid'] = false;
      $validation['issues'][] = "Corrected query is empty";
      return $validation;
    }

    // Validation 2: a correction that returns the failed query unchanged repairs nothing.
    // Rejecting it here keeps it out of CorrectionMemory, which would otherwise re-serve it.
    if ($failedQuery !== null && QueryEquivalence::isUnchanged($failedQuery, $query)) {
      $validation['is_valid'] = false;
      $validation['issues'][] = "Correction is unchanged from the failed query (no-op)";
      return $validation;
    }

    if (preg_match('/`{3,}/', $query)) {
      $validation['is_valid'] = false;
      $validation['issues'][] = "Corrected query still carries a markdown fence";
      return $validation;
    }

    // Validation 3: Use InputValidator for SQL syntax validation
    $syntaxCheck = \ClicShopping\AI\Security\InputValidator::validateSqlQuery($query);
    if (!$syntaxCheck['valid']) {
      $validation['is_valid'] = false;
      $validation['issues'] = array_merge(
        $validation['issues'],
        $syntaxCheck['issues']
      );
    }

    // Validation 4: Check for balanced parentheses
    $openCount = substr_count($query, '(');
    $closeCount = substr_count($query, ')');
    if ($openCount !== $closeCount) {
      $validation['is_valid'] = false;
      $validation['issues'][] = "Unbalanced parentheses: $openCount open vs $closeCount close";
    }

    // Validation 5: Check SELECT queries have FROM clause
    if (stripos($query, 'SELECT') === 0 && stripos($query, 'FROM') === false) {
      $validation['is_valid'] = false;
      $validation['issues'][] = "SELECT query missing FROM clause";
    }

    // Validation 6: Check confidence threshold (semantic validation)
    if (isset($correction['confidence']) && $correction['confidence'] < $this->confidenceThreshold) {
      $validation['warnings'][] = "Low confidence correction: " . $correction['confidence'];
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "CorrectionValidator: Validation result - " . 
        ($validation['is_valid'] ? 'VALID' : 'INVALID') . 
        " (issues: " . count($validation['issues']) . ", warnings: " . count($validation['warnings']) . ")",
        'info'
      );
    }

    return $validation;
  }

  /**
   * Set confidence threshold
   *
   * @param float $threshold New threshold value
   * @return void
   */
  public function setConfidenceThreshold(float $threshold): void
  {
    $this->confidenceThreshold = max(0.0, min(1.0, $threshold));
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "CorrectionValidator: Confidence threshold set to {$this->confidenceThreshold}",
        'info'
      );
    }
  }
}
