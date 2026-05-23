<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * ReputationAnalyzer
 *
 * Uses pure LLM to analyze reputation patterns, detect trends, and identify anomalies.
 * This class provides intelligent reputation analysis without relying on pattern matching.
 *
 * Requirements: 6.1, 6.2, 6.3, 6.4
 *
 * @version 1.0
 * @since 2026-02-04
 */
class ReputationAnalyzer
{
  /**
   * @var ReputationStore Database store for reputation data
   */
  private ReputationStore $store;

  /**
   * @var SecurityLogger Logger for debugging and monitoring
   */
  private SecurityLogger $logger;

  /**
   * @var bool Debug mode flag
   */
  private bool $debug;

  /**
   * Constructor
   *
   * @param ReputationStore|null $store Optional reputation store (for testing)
   */
  public function __construct(?ReputationStore $store = null)
  {
    $this->store = $store ?? new ReputationStore();
    $this->logger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') &&
      CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Analyze reputation using LLM
   *
   * Uses LLM to analyze reputation history and identify:
   * - Trends (improving, declining, stable)
   * - Anomalies (unusual patterns or sudden changes)
   * - Gaming risk (signs of manipulation)
   * - Recommendations (specific actions to improve)
   *
   * Requirements: 6.1, 6.2, 6.3, 6.4
   *
   * @param string $criticId Critic agent ID
   * @param int $days Number of days of history to analyze (default: 30)
   * @return array Analysis result with trends, anomalies, gaming_risk, recommendations
   */
  public function analyzeReputation(string $criticId, int $days = 30): array
  {
    try {
      // Get reputation history
      $history = $this->store->getHistory($criticId, $days);

      // Get current reputation score
      $currentReputation = $this->store->getReputation($criticId);

      // Prepare context for LLM
      $context = $this->prepareAnalysisContext($history, $currentReputation);

      // Build LLM prompt
      $prompt = $this->buildAnalysisPrompt($context);

      if ($this->debug) {
        $this->logger->logStructured(
          'info',
          'ReputationAnalyzer',
          'analyze_reputation_start',
          [
            'critic_id' => $criticId,
            'days' => $days,
            'history_count' => count($history),
            'current_reputation' => $currentReputation ? $currentReputation->reputationScore : null
          ]
        );
      }

      // Call LLM for analysis
      $llmResponse = Gpt::getGptResponse($prompt, 500);

      // Parse LLM response
      $analysis = $this->parseLLMResponse($llmResponse);

      if ($this->debug) {
        $this->logger->logStructured(
          'info',
          'ReputationAnalyzer',
          'analyze_reputation_complete',
          [
            'critic_id' => $criticId,
            'trends' => $analysis['trends'] ?? 'unknown',
            'anomalies_detected' => !empty($analysis['anomalies']),
            'gaming_risk' => $analysis['gaming_risk'] ?? 'unknown'
          ]
        );
      }

      return $analysis;

    } catch (\Exception $e) {
      $this->logger->logStructured(
        'error',
        'ReputationAnalyzer',
        'analyze_reputation_error',
        [
          'critic_id' => $criticId,
          'error' => $e->getMessage()
        ]
      );

      // Return safe default analysis
      return [
        'trends' => 'unknown',
        'anomalies' => [],
        'gaming_risk' => 'unknown',
        'recommendations' => ['Unable to analyze reputation at this time.'],
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Build analysis prompt for LLM
   *
   * Creates a detailed prompt that instructs the LLM to analyze reputation data
   * and provide structured insights.
   *
   * Requirements: 6.1, 6.2, 6.3, 6.4
   *
   * @param array $context Prepared context with history and metrics
   * @return string LLM prompt
   */
  public function buildAnalysisPrompt(array $context): string
  {
    $historyJson = json_encode($context['history'], JSON_PRETTY_PRINT);
    $currentMetrics = json_encode($context['current_metrics'], JSON_PRETTY_PRINT);

    return <<<PROMPT
You are analyzing the reputation data for a critic agent in an AI evaluation system.

CRITIC INFORMATION:
- Critic ID: {$context['critic_id']}
- Current Reputation: {$context['current_reputation']}
- Status: {$context['status']}
- Total Evaluations: {$context['total_evaluations']}
- Analysis Period: Last {$context['days']} days

CURRENT METRICS:
{$currentMetrics}

REPUTATION HISTORY (Recent evaluations):
{$historyJson}

ANALYSIS TASKS:
1. TRENDS: Analyze the reputation trend over time
   - Is reputation improving, declining, or stable?
   - What is the rate of change?
   - Are there any inflection points?

2. ANOMALIES: Identify unusual patterns or sudden changes
   - Any sudden spikes or drops?
   - Unusual evaluation patterns?
   - Inconsistent performance?

3. GAMING RISK: Assess risk of reputation gaming or manipulation
   - Signs of strategic voting?
   - Suspicious consistency patterns?
   - Potential collusion indicators?

4. RECOMMENDATIONS: Provide specific, actionable recommendations
   - What should the critic focus on to improve?
   - Which metrics need attention?
   - Specific evaluation behaviors to adjust?

RESPONSE FORMAT:
Provide your analysis in JSON format with the following structure:
{
  "trends": "improving|declining|stable|volatile",
  "trend_description": "Detailed description of the trend",
  "trend_rate": "slow|moderate|fast",
  "anomalies": [
    {
      "type": "spike|drop|inconsistency|pattern",
      "description": "Description of the anomaly",
      "severity": "low|medium|high",
      "timestamp": "When it occurred"
    }
  ],
  "gaming_risk": "low|medium|high",
  "gaming_indicators": ["List of specific indicators if any"],
  "recommendations": [
    "Specific recommendation 1",
    "Specific recommendation 2",
    "Specific recommendation 3"
  ],
  "overall_assessment": "Brief overall assessment of critic performance"
}

Provide ONLY the JSON response, no additional text.
PROMPT;
  }

  /**
   * Prepare analysis context from history and current reputation
   *
   * @param array $history Reputation history records
   * @param object|null $currentReputation Current reputation score object
   * @return array Context for LLM prompt
   */
  private function prepareAnalysisContext(array $history, $currentReputation): array
  {
    // Extract key information from history
    $historyData = [];
    foreach ($history as $record) {
      $historyData[] = [
        'evaluation_id' => $record->evaluationId ?? 'unknown',
        'timestamp' => isset($record->recordedAt) ? $record->recordedAt->format('Y-m-d H:i:s') : 'unknown',
        'old_reputation' => $record->oldReputation ?? 0,
        'new_reputation' => $record->newReputation ?? 0,
        'reputation_change' => $record->reputationImpact ?? 0,
        'alignment_delta' => $record->alignmentDelta ?? 0,
        'critic_score' => $record->criticScore ?? 0,
        'consensus_score' => $record->consensusScore ?? 0
      ];
    }

    // Prepare current metrics
    $currentMetrics = [];
    if ($currentReputation) {
      $currentMetrics = [
        'reputation_score' => $currentReputation->reputationScore ?? 0,
        'consensus_alignment' => $currentReputation->consensusAlignment ?? 0,
        'feedback_quality' => $currentReputation->feedbackQuality ?? 0,
        'consistency_score' => $currentReputation->consistencyScore ?? 0,
        'expertise_accuracy' => $currentReputation->expertiseAccuracy ?? 0
      ];
    }

    return [
      'critic_id' => $currentReputation->criticId ?? 'unknown',
      'current_reputation' => $currentReputation->reputationScore ?? 0.75,
      'status' => $currentReputation->status ?? 'unknown',
      'total_evaluations' => $currentReputation->totalEvaluations ?? 0,
      'days' => count($history) > 0 ? 30 : 0,
      'history' => $historyData,
      'current_metrics' => $currentMetrics
    ];
  }

  /**
   * Parse LLM response into structured analysis result
   *
   * @param string $llmResponse Raw LLM response
   * @return array Parsed analysis result
   */
  private function parseLLMResponse(string $llmResponse): array
  {
    // Clean response: Remove markdown code blocks if present
    $cleanResponse = trim($llmResponse);
    $cleanResponse = preg_replace('/^```(?:json)?\s*/m', '', $cleanResponse);
    $cleanResponse = preg_replace('/\s*```$/m', '', $cleanResponse);
    $cleanResponse = trim($cleanResponse);

    // Try to parse JSON
    $result = json_decode($cleanResponse, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      if ($this->debug) {
        $this->logger->logStructured(
          'warning',
          'ReputationAnalyzer',
          'json_parse_error',
          [
            'error' => json_last_error_msg(),
            'response' => substr($cleanResponse, 0, 200)
          ]
        );
      }

      // Return safe default if parsing fails
      return [
        'trends' => 'unknown',
        'trend_description' => 'Unable to parse LLM response',
        'anomalies' => [],
        'gaming_risk' => 'unknown',
        'recommendations' => ['Unable to generate recommendations at this time.'],
        'parse_error' => json_last_error_msg()
      ];
    }

    // Validate required fields
    $validated = [
      'trends' => $result['trends'] ?? 'unknown',
      'trend_description' => $result['trend_description'] ?? '',
      'trend_rate' => $result['trend_rate'] ?? 'unknown',
      'anomalies' => $result['anomalies'] ?? [],
      'gaming_risk' => $result['gaming_risk'] ?? 'unknown',
      'gaming_indicators' => $result['gaming_indicators'] ?? [],
      'recommendations' => $result['recommendations'] ?? [],
      'overall_assessment' => $result['overall_assessment'] ?? ''
    ];

    return $validated;
  }
}
