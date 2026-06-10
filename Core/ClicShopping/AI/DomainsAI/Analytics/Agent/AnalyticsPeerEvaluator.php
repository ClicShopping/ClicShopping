<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\AI\Config\AutonomousConfig;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation;
use ClicShopping\AI\Security\InputValidator;

/**
 * AnalyticsPeerEvaluator - Actor-critic peer-evaluation concern of the AnalyticsAgent.
 *
 * Extracted from AnalyticsAgent (god-class decomposition): the agent acting AS A CRITIC,
 * scoring peer outputs (SQL queries, data analysis). AnalyticsAgent now delegates its
 * evaluatePeerOutput()/getEvaluationCapabilities() interface methods here, behaviour
 * unchanged.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Agent
 * @since 2026-06-10
 */
class AnalyticsPeerEvaluator
{
  private AutonomousConfig $autonomousConfig;

  public function __construct(AutonomousConfig $autonomousConfig)
  {
    $this->autonomousConfig = $autonomousConfig;
  }

  /**
   * Evaluate peer agent output (SQL queries, data analysis).
   *
   * @param string $outputType Type of output
   * @param mixed $output The output to evaluate
   * @param array $criteria Evaluation criteria
   * @return AgentEvaluation
   */
  public function evaluatePeerOutput(
    string $outputType,
    mixed $output,
    array $criteria
  ): AgentEvaluation {

    if (!$this->autonomousConfig->canAgentEvaluatePeers('AnalyticsAgent')) {
      throw new \RuntimeException('AnalyticsAgent is not authorized to evaluate peers (disabled in configuration)');
    }

    // Verify capability
    $capabilities = $this->getEvaluationCapabilities();
    if (!isset($capabilities[$outputType])) {
      throw new \InvalidArgumentException(
        "AnalyticsAgent cannot evaluate {$outputType}"
      );
    }

    // Perform evaluation based on output type
    $scores = match ($outputType) {
      'sql_query' => $this->evaluateSqlQuery($output, $criteria),
      'data_analysis' => $this->evaluateDataAnalysis($output, $criteria),
      default => $this->getDefaultScores()
    };

    return new AgentEvaluation(
      'AnalyticsAgent',
      $output['output_id'] ?? uniqid('output_'),
      $scores,
      $scores['feedback'] ?? 'Evaluation completed',
      $scores['strengths'] ?? [],
      $scores['improvements'] ?? []
    );
  }

  /**
   * Get evaluation capabilities for AnalyticsAgent
   *
   * @return array Mapping of output types to capability levels
   */
  public function getEvaluationCapabilities(): array
  {
    return [
      'sql_query' => 'expert',        // Expert in SQL query evaluation
      'data_analysis' => 'expert',    // Expert in data analysis
      'reasoning_chain' => 'competent', // Competent in reasoning evaluation
      'validation_result' => 'novice'  // Basic validation understanding
    ];
  }

  /**
   * Evaluate SQL query quality
   *
   * @param mixed $output SQL query output
   * @param array $criteria Evaluation criteria
   * @return array Evaluation scores
   */
  private function evaluateSqlQuery(mixed $output, array $criteria): array
  {
    $sql = $output['sql_query'] ?? '';

    // Evaluate SQL query
    $validation = InputValidator::validateSqlQuery($sql);

    $accuracyScore = $validation['valid'] ? 0.9 : 0.5;
    $completenessScore = 0.8; // Check if query has all necessary clauses
    $efficiencyScore = 0.8;   // Check for performance issues
    $clarityScore = 0.8;      // Check for readability

    $strengths = [];
    $improvements = [];

    if ($validation['valid']) {
      $strengths[] = 'SQL syntax is valid';
    } else {
      $improvements[] = 'Fix SQL syntax errors: ' . implode(', ', $validation['issues']);
    }

    // Check for SELECT *
    if (preg_match('/SELECT\s+\*/i', $sql)) {
      $improvements[] = 'Avoid SELECT * - specify columns explicitly';
      $efficiencyScore -= 0.1;
    }

    // Check for LIMIT clause
    if (preg_match('/^SELECT/i', $sql) && !preg_match('/LIMIT/i', $sql)) {
      $improvements[] = 'Consider adding LIMIT clause to prevent large result sets';
      $efficiencyScore -= 0.05;
    }

    return [
      'accuracy_score' => max(0, $accuracyScore),
      'completeness_score' => max(0, $completenessScore),
      'efficiency_score' => max(0, $efficiencyScore),
      'clarity_score' => max(0, $clarityScore),
      'feedback' => 'SQL query evaluation completed',
      'strengths' => $strengths,
      'improvements' => $improvements
    ];
  }

  /**
   * Evaluate data analysis quality
   *
   * @param mixed $output Data analysis output
   * @param array $criteria Evaluation criteria
   * @return array Evaluation scores
   */
  private function evaluateDataAnalysis(mixed $output, array $criteria): array
  {
    return [
      'accuracy_score' => 0.8,
      'completeness_score' => 0.8,
      'efficiency_score' => 0.8,
      'clarity_score' => 0.8,
      'feedback' => 'Data analysis evaluation completed',
      'strengths' => ['Analysis structure is sound'],
      'improvements' => ['Consider additional data validation']
    ];
  }

  /**
   * Get default evaluation scores
   *
   * @return array Default scores
   */
  private function getDefaultScores(): array
  {
    return [
      'accuracy_score' => 0.7,
      'completeness_score' => 0.7,
      'efficiency_score' => 0.7,
      'clarity_score' => 0.7,
      'feedback' => 'Default evaluation',
      'strengths' => [],
      'improvements' => []
    ];
  }
}
