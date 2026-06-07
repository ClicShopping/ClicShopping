<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * LowConfidenceReasoningFallback Class
 *
 * Applies the low-confidence reasoning fallback to an intent: for non-hybrid intents with
 * confidence below 0.6 it runs the ReasoningAgent to clarify and defaults unknown types to a safe
 * 'semantic' classification. Promoted from the OrchestratorAgent private helper
 * applyLowConfidenceReasoningFallback() without behaviour change.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\CoreAI\Orchestrator\ReasoningAgent;
use ClicShopping\AI\Security\SecurityLogger;

class LowConfidenceReasoningFallback
{
  private ReasoningAgent $reasoningAgent;
  private WorkingMemory $workingMemory;
  private SecurityLogger $securityLogger;

  public function __construct(ReasoningAgent $reasoningAgent, WorkingMemory $workingMemory, SecurityLogger $securityLogger)
  {
    $this->reasoningAgent = $reasoningAgent;
    $this->workingMemory = $workingMemory;
    $this->securityLogger = $securityLogger;
  }

  /**
   * Apply the low-confidence reasoning fallback to an intent.
   *
   * For non-hybrid intents with confidence below 0.6, runs the ReasoningAgent to clarify (storing
   * its result in working memory) and, when the intent type is unknown, defaults it to a safe
   * 'semantic' classification (confidence 0.5). Higher-confidence or hybrid intents are returned
   * unchanged.
   *
   * @param array $intent Intent produced by analyzeIntent()
   * @param array $context Conversation context
   * @param array $contextAnalysis Query-context relation analysis
   * @param string $queryToProcess Resolved query to process
   * @return array The (possibly adjusted) intent
   */
  public function apply(array $intent, array $context, array $contextAnalysis, string $queryToProcess): array
  {
    // Fix: Safely check is_hybrid with default value
    $isHybrid = $intent['is_hybrid'] ?? false;

    // 🔧 Don't send hybrid queries to ReasoningAgent
    // Hybrid queries are already routed above
    if ($intent['confidence'] < 0.6 && !$isHybrid) {
      // Log fallback decision
      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'fallback_decision',
        [
          'reason' => $intent['confidence'] < 0.6 ? 'low_confidence' : 'hybrid_query',
          'confidence' => $intent['confidence'],
          'original_type' => $intent['type'],
          'is_hybrid' => $intent['is_hybrid'],
          'query' => $queryToProcess
        ]
      );

      // Utiliser le ReasoningAgent pour clarifier
      $reasoning = $this->reasoningAgent->reason($queryToProcess, [
        'intent' => $intent,
        'context' => $context,
        'context_analysis' => $contextAnalysis,
      ]);

      $this->workingMemory->set('reasoning_result', $reasoning);

      // default to semantic (safer fallback than analytics)
      if ($intent['confidence'] < 0.6 && !in_array($intent['type'], ['analytics', 'semantic', 'web_search', 'hybrid'], true)) {
        $this->securityLogger->logStructured(
          'warning',
          'OrchestratorAgent',
          'fallback_to_semantic',
          [
            'original_type' => $intent['type'],
            'confidence' => $intent['confidence'],
            'reason' => 'Unknown type with low confidence, defaulting to semantic (safer)'
          ]
        );

        $intent['type'] = 'semantic';
        $intent['confidence'] = 0.5; // Reset to default semantic confidence
      }
    }

    return $intent;
  }
}
