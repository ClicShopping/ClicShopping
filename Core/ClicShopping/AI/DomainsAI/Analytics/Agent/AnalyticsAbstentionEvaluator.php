<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\AI\CoreAI\Orchestrator\SubAbstention\AgentAbstentionManager;
use ClicShopping\AI\CoreAI\Query\QueryClassifier;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

/**
 * AnalyticsAbstentionEvaluator — pre-execution confidence/abstention concern of AnalyticsAgent
 * (god-class decomposition). Decides, before running a query, whether the agent should
 * abstain (confidence too low → human review), delegate, or proceed. Behaviour unchanged:
 * moved verbatim from AnalyticsAgent::evaluateAbstention().
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Agent
 * @since 2026-06-23
 */
class AnalyticsAbstentionEvaluator
{
  public function __construct(
    private AgentAbstentionManager $abstentionManager,
    private ResultInterpreter $resultInterpreter,
    private bool $debug = false
  ) {
  }

  /**
   * Evaluate whether to abstain/delegate/proceed for this analytics query.
   *
   * @return array|null Non-null = early error response (abstain); null = proceed.
   */
  public function evaluate(string $question, array $feedbackContext): ?array
  {
    //  Use classification confidence instead of recalculating
    $this->debugLog("--- STEP -1: Evaluate confidence for abstention ---", "ABSTENTION");

    // FIX 2026-01-29: Configure lower thresholds for AnalyticsAgent
    // Abstention: 0.15 (was 0.3), Delegation: 0.5 (was 0.7)
    try {
      $this->abstentionManager->setThresholds('AnalyticsAgent', 0.15, 0.5);
      $this->debugLog("Thresholds configured: abstention=0.15, delegation=0.5", "ABSTENTION");
    } catch (\Exception $e) {
      $this->debugLog("Failed to set thresholds: " . $e->getMessage(), "ABSTENTION");
    }

    // Get classification confidence (already calculated in isAnalyticsQuery)
    $translatedForClassification = SemanticAgent::translateToEnglish($question, 80);
    $cleanTranslation = $this->resultInterpreter->extractCleanTranslation($translatedForClassification);
    $classifier = new QueryClassifier($this->debug);
    $classificationResult = $classifier->classify($cleanTranslation, $cleanTranslation);

    $classificationConfidence = $classificationResult['confidence'] ?? 0.0;
    $this->debugLog("Classification confidence: {$classificationConfidence}", "ABSTENTION");

    // Use classification confidence if high, otherwise calculate complexity-based confidence
    if ($classificationConfidence >= 0.7) {
      // High classification confidence - use it directly
      $confidence = $classificationConfidence;
      $this->debugLog("Using classification confidence: {$confidence}", "ABSTENTION");
    } else {
      // Low classification confidence - calculate based on complexity
      $complexity = AnalyticsQueryHeuristics::estimateQueryComplexity($question);
      $this->debugLog("Query complexity: {$complexity}", "ABSTENTION");

      $confidence = $this->abstentionManager->evaluateConfidence(
        'AnalyticsAgent',
        $question,
        [
          'task_type' => 'analytics_query',
          'description' => $question,
          'parameters' => $feedbackContext,
          'complexity' => $complexity
        ]
      );
      $this->debugLog("Calculated confidence: {$confidence}", "ABSTENTION");
    }

    $decision = $this->abstentionManager->getAbstentionDecision(
      'AnalyticsAgent',
      $confidence,
      'analytics_query'
    );

    $this->debugLog("Abstention decision: {$decision['action']}", "ABSTENTION");
    $this->debugLog("Reason: {$decision['reason']}", "ABSTENTION");

    if ($decision['action'] === 'abstain') {
      // Log abstention to database
      $this->abstentionManager->logAbstention(
        'AnalyticsAgent',
        md5($question),
        'analytics_query',
        $confidence,
        $decision['reason'],
        'escalate_human'
      );

      $this->debugLog("ABSTAINING - Confidence too low", "ABSTENTION");

      // Return error requiring human intervention
      return [
        'type' => 'error',
        'message' => 'Confidence too low for autonomous execution. Human review required.',
        'reason' => $decision['reason'],
        'confidence' => $confidence,
        'requires_human' => true,
        'query' => $question
      ];
    }

    if ($decision['action'] === 'delegate') {
      // Log delegation intent
      $this->abstentionManager->logAbstention(
        'AnalyticsAgent',
        md5($question),
        'analytics_query',
        $confidence,
        $decision['reason'],
        'delegate_peer',
        $decision['suggested_delegate']
      );

      $this->debugLog("DELEGATING - Medium confidence", "ABSTENTION");
      $this->debugLog("Suggested delegate: " . ($decision['suggested_delegate'] ?? 'none'), "ABSTENTION");

      // For now, proceed with execution but log the delegation intent
      // TODO: Implement actual delegation mechanism when peer agents are available
    }

    $this->debugLog("EXECUTING - Confidence sufficient ({$confidence})", "ABSTENTION");

    return null;
  }

  private function debugLog(string $message, string $context = '', array $data = []): void
  {
    if (!$this->debug) {
      return;
    }

    $logMessage = $message;

    if (!empty($context)) {
      $logMessage = "[{$context}] {$message}";
    }

    if (!empty($data)) {
      $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    error_log($logMessage);
  }
}
