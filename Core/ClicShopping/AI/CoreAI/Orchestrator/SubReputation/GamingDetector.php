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
use ClicShopping\OM\Registry;

/**
 * GamingDetector
 *
 * Uses pure LLM to detect reputation gaming attempts including:
 * - Collusion (always agreeing with specific critics)
 * - Score manipulation (artificially consistent scores)
 * - Strategic voting (patterns designed to maximize reputation)
 * - Sudden reputation spikes (unexplained improvements)
 *
 * Requirements: 12.1, 12.2, 12.3, 12.4
 *
 * @version 1.0
 * @since 2026-02-04
 */
class GamingDetector
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
   * @var \ClicShopping\OM\Db Database connection
   */
  private $db;

  /**
   * Constructor
   *
   * @param ReputationStore|null $store Optional reputation store (for testing)
   */
  public function __construct(?ReputationStore $store = null)
  {
    $this->store = $store ?? new ReputationStore();
    $this->logger = new SecurityLogger();
    $this->db = Registry::get('Db');
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') &&
      CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Detect gaming attempts using LLM
   *
   * Analyzes evaluation patterns to detect potential reputation gaming.
   * Uses LLM to identify complex patterns that might indicate manipulation.
   *
   * Requirements: 12.1, 12.2, 12.3, 12.4
   *
   * @param string $criticId Critic agent ID
   * @param int $days Number of days to analyze (default: 30)
   * @return array Gaming detection result with is_gaming, confidence, evidence, gaming_type
   */
  public function detectGaming(string $criticId, int $days = 30): array
  {
    try {
      // Get evaluation patterns
      $patterns = $this->getEvaluationPatterns($criticId, $days);

      // Build LLM prompt
      $prompt = $this->buildGamingDetectionPrompt($patterns);

      if ($this->debug) {
        $this->logger->logStructured(
          'info',
          'GamingDetector',
          'detect_gaming_start',
          [
            'critic_id' => $criticId,
            'days' => $days,
            'evaluations_analyzed' => count($patterns['evaluations'] ?? [])
          ]
        );
      }

      // Call LLM for gaming detection
      $llmResponse = Gpt::getGptResponse($prompt, 400);

      // Parse LLM response
      $detection = $this->parseLLMResponse($llmResponse);

      if ($this->debug) {
        $this->logger->logStructured(
          'info',
          'GamingDetector',
          'detect_gaming_complete',
          [
            'critic_id' => $criticId,
            'is_gaming' => $detection['is_gaming'] ?? false,
            'confidence' => $detection['confidence'] ?? 0,
            'gaming_type' => $detection['gaming_type'] ?? 'none'
          ]
        );
      }

      return $detection;

    } catch (\Exception $e) {
      $this->logger->logStructured(
        'error',
        'GamingDetector',
        'detect_gaming_error',
        [
          'critic_id' => $criticId,
          'error' => $e->getMessage()
        ]
      );

      // Return safe default
      return [
        'is_gaming' => false,
        'confidence' => 0.0,
        'evidence' => [],
        'gaming_type' => 'unknown',
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Build gaming detection prompt for LLM
   *
   * Creates a detailed prompt that instructs the LLM to analyze evaluation patterns
   * and detect signs of reputation gaming.
   *
   * Requirements: 12.1, 12.2, 12.3, 12.4
   *
   * @param array $patterns Evaluation patterns data
   * @return string LLM prompt
   */
  public function buildGamingDetectionPrompt(array $patterns): string
  {
    $evaluationsJson = json_encode($patterns['evaluations'] ?? [], JSON_PRETTY_PRINT);
    $agreementPatternsJson = json_encode($patterns['agreement_patterns'] ?? [], JSON_PRETTY_PRINT);
    $scoreDistributionJson = json_encode($patterns['score_distribution'] ?? [], JSON_PRETTY_PRINT);

    return <<<PROMPT
You are analyzing evaluation patterns to detect potential reputation gaming in an AI critic evaluation system.

CRITIC INFORMATION:
- Critic ID: {$patterns['critic_id']}
- Analysis Period: Last {$patterns['days']} days
- Total Evaluations: {$patterns['total_evaluations']}
- Current Reputation: {$patterns['current_reputation']}

EVALUATION PATTERNS:
{$evaluationsJson}

AGREEMENT PATTERNS (with other critics):
{$agreementPatternsJson}

SCORE DISTRIBUTION:
{$scoreDistributionJson}

GAMING DETECTION TASKS:

1. COLLUSION DETECTION:
   - Does this critic always agree with specific other critics?
   - Are there suspicious agreement patterns?
   - Is the agreement rate abnormally high with certain critics?

2. SCORE MANIPULATION DETECTION:
   - Are scores artificially consistent (too little variation)?
   - Are there patterns suggesting strategic score selection?
   - Does the score distribution look unnatural?

3. STRATEGIC VOTING DETECTION:
   - Are evaluation patterns designed to maximize reputation?
   - Does the critic adjust scores based on consensus predictions?
   - Are there timing patterns suggesting strategic behavior?

4. SUDDEN SPIKE DETECTION:
   - Are there unexplained sudden improvements in reputation?
   - Do reputation changes correlate with suspicious patterns?
   - Are there anomalies in the evaluation timeline?

RESPONSE FORMAT:
Provide your analysis in JSON format with the following structure:
{
  "is_gaming": true|false,
  "confidence": 0.0-1.0,
  "gaming_type": "collusion|score_manipulation|strategic_voting|sudden_spike|none",
  "evidence": [
    {
      "type": "collusion|manipulation|strategic|spike",
      "description": "Specific evidence description",
      "severity": "low|medium|high",
      "supporting_data": "Relevant data points"
    }
  ],
  "risk_level": "low|medium|high|critical",
  "recommended_action": "monitor|investigate|alert|restrict",
  "explanation": "Detailed explanation of findings"
}

IMPORTANT:
- Be conservative: Only flag as gaming if there is strong evidence
- Consider legitimate reasons for patterns before flagging
- Provide specific evidence, not just suspicions
- Confidence should reflect certainty of gaming detection

Provide ONLY the JSON response, no additional text.
PROMPT;
  }

  /**
   * Get evaluation patterns for gaming detection
   *
   * Retrieves and analyzes evaluation patterns including:
   * - Recent evaluations with scores and consensus
   * - Agreement patterns with other critics
   * - Score distribution statistics
   * - Reputation change timeline
   *
   * @param string $criticId Critic agent ID
   * @param int $days Number of days to analyze
   * @return array Evaluation patterns data
   */
  private function getEvaluationPatterns(string $criticId, int $days): array
  {
    // Get reputation history
    $history = $this->store->getHistory($criticId, $days);

    // Get current reputation
    $currentReputation = $this->store->getReputation($criticId);

    // Extract evaluations
    $evaluations = [];
    foreach ($history as $record) {
      $evaluations[] = [
        'evaluation_id' => $record->evaluationId ?? 'unknown',
        'timestamp' => isset($record->recordedAt) ? $record->recordedAt->format('Y-m-d H:i:s') : 'unknown',
        'critic_score' => $record->criticScore ?? 0,
        'consensus_score' => $record->consensusScore ?? 0,
        'alignment_delta' => $record->alignmentDelta ?? 0,
        'reputation_impact' => $record->reputationImpact ?? 0
      ];
    }

    // Calculate agreement patterns (simplified - would need evaluation details table)
    $agreementPatterns = $this->calculateAgreementPatterns($criticId, $days);

    // Calculate score distribution
    $scoreDistribution = $this->calculateScoreDistribution($evaluations);

    return [
      'critic_id' => $criticId,
      'days' => $days,
      'total_evaluations' => count($evaluations),
      'current_reputation' => $currentReputation ? $currentReputation->reputationScore : 0.75,
      'evaluations' => $evaluations,
      'agreement_patterns' => $agreementPatterns,
      'score_distribution' => $scoreDistribution
    ];
  }

  /**
   * Calculate agreement patterns with other critics
   *
   * Analyzes how often this critic agrees with other specific critics.
   * High agreement rates with specific critics may indicate collusion.
   *
   * @param string $criticId Critic agent ID
   * @param int $days Number of days to analyze
   * @return array Agreement patterns
   */
  private function calculateAgreementPatterns(string $criticId, int $days): array
  {
    // This would require a more detailed evaluation tracking table
    // For now, return placeholder structure
    // In production, this would query evaluation details to find co-evaluators

    return [
      'note' => 'Agreement pattern analysis requires detailed evaluation tracking',
      'average_agreement_rate' => 0.0,
      'high_agreement_critics' => []
    ];
  }

  /**
   * Calculate score distribution statistics
   *
   * Analyzes the distribution of scores to detect artificial consistency
   * or manipulation patterns.
   *
   * @param array $evaluations List of evaluations
   * @return array Score distribution statistics
   */
  private function calculateScoreDistribution(array $evaluations): array
  {
    if (empty($evaluations)) {
      return [
        'mean' => 0,
        'std_dev' => 0,
        'min' => 0,
        'max' => 0,
        'variance' => 0
      ];
    }

    $scores = array_column($evaluations, 'critic_score');

    $mean = array_sum($scores) / count($scores);

    $variance = 0;
    foreach ($scores as $score) {
      $variance += pow($score - $mean, 2);
    }
    $variance = $variance / count($scores);

    $stdDev = sqrt($variance);

    return [
      'mean' => round($mean, 3),
      'std_dev' => round($stdDev, 3),
      'min' => round(min($scores), 3),
      'max' => round(max($scores), 3),
      'variance' => round($variance, 3),
      'coefficient_of_variation' => $mean > 0 ? round($stdDev / $mean, 3) : 0
    ];
  }

  /**
   * Parse LLM response into structured gaming detection result
   *
   * @param string $llmResponse Raw LLM response
   * @return array Parsed gaming detection result
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
          'GamingDetector',
          'json_parse_error',
          [
            'error' => json_last_error_msg(),
            'response' => substr($cleanResponse, 0, 200)
          ]
        );
      }

      // Return safe default if parsing fails
      return [
        'is_gaming' => false,
        'confidence' => 0.0,
        'gaming_type' => 'unknown',
        'evidence' => [],
        'risk_level' => 'unknown',
        'recommended_action' => 'monitor',
        'parse_error' => json_last_error_msg()
      ];
    }

    // Validate and normalize fields
    $validated = [
      'is_gaming' => $result['is_gaming'] ?? false,
      'confidence' => max(0.0, min(1.0, (float)($result['confidence'] ?? 0.0))),
      'gaming_type' => $result['gaming_type'] ?? 'none',
      'evidence' => $result['evidence'] ?? [],
      'risk_level' => $result['risk_level'] ?? 'low',
      'recommended_action' => $result['recommended_action'] ?? 'monitor',
      'explanation' => $result['explanation'] ?? ''
    ];

    return $validated;
  }
}
