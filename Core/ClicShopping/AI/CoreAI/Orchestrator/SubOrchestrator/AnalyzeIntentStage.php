<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * AnalyzeIntentStage
 *
 * Fourth stage of the orchestration pipeline. Produces a validated intent for the resolved query:
 * runs intent analysis, applies the anti-hallucination translation guard, detects query complexity,
 * and resolves the effective intent type. Intent and complexity are mirrored onto working memory.
 *
 * Carries sequencing only — it delegates to the already-injected {@see IntentAnalyzer},
 * {@see IntentTranslationValidator} and {@see ComplexQueryHandler}, and folds in the verbatim former
 * {@see OrchestratorAgent::logComplexityDetection()}. The validated intent and resolved intent type
 * are written onto the pipeline context for the downstream (still inline) steps.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\Handler\Query\ComplexQueryHandler;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class AnalyzeIntentStage implements OrchestrationStageInterface
{
  public function __construct(
    private IntentAnalyzer $intentAnalyzer,
    private WorkingMemory $workingMemory,
    private IntentTranslationValidator $intentTranslationValidator,
    private ComplexQueryHandler $complexQueryHandler,
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'analyze_intent';
  }

  public function run(OrchestrationContext $context): ?array
  {
    $intentStart = microtime(true);
    $intent = $this->intentAnalyzer->analyze($context->queryToProcess);
    $this->workingMemory->set('intent', $intent);

    // Anti-hallucination verification (PRIORITY 1): validate the intent's translated query
    // against the original; fall back to the resolved query if a hallucination is detected.
    $translationCheck = $this->intentTranslationValidator->validate($context->query, $context->queryToProcess, $intent);
    $intent = $translationCheck['intent'];
    $validationResult = $translationCheck['validation'];

    if ($this->debug) {
      error_log("[INFO : TIME]️ [PERF] analyzeIntent took " . round((microtime(true) - $intentStart), 2) . "s");
      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'PATH_DECISION.intent',
        [
          'translated_query' => $intent['translated_query'] ?? $context->queryToProcess,
          'intent_type' => $intent['type'] ?? 'unknown',
          'is_hybrid_flag' => $intent['is_hybrid'] ?? false,
          'confidence' => $intent['confidence'] ?? 0,
          'hallucination_detected' => $validationResult['hallucination_detected'],
          'hallucination_keywords' => $validationResult['hallucination_detected'] ? $validationResult['hallucination_keywords'] : null,
        ]
      );
    }

    // Use translated query from intent for detection
    $translatedQuery = $intent['translated_query'] ?? $context->queryToProcess;
    $complexityDetection = $this->complexQueryHandler->detectComplexQuery($translatedQuery);
    $this->workingMemory->set('complexity_detection', $complexityDetection);

    $this->logComplexityDetection($complexityDetection);


    //  Route hybrid queries BEFORE ReasoningAgent
    $context->intent = $intent;
    $context->intentType = $intent['type'] ?? $intent['query_type'] ?? 'semantic';

    return null;
  }

  /**
   * Debug-log the complexity-detection outcome for a complex query.
   *
   * Emits the detection details and the resulting hybrid-route decision. No-op unless debug is on
   * and the query was detected as complex. Logging side-effect only.
   *
   * @param array $complexityDetection Result of ComplexQueryHandler::detectComplexQuery()
   * @return void
   */
  private function logComplexityDetection(array $complexityDetection): void
  {
    if ($this->debug && $complexityDetection['is_complex']) {
      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'detectComplexQuery',
        [
          'query_type' => $complexityDetection['query_type'],
          'complexity_score' => $complexityDetection['complexity_score'],
          'detected_patterns' => $complexityDetection['detected_patterns'],
          'requires_web_search' => $complexityDetection['requires_web_search'],
          'estimated_sub_queries' => $complexityDetection['estimated_sub_queries']
        ]
      );

      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'PATH_DECISION.route',
        [
          'route' => 'hybrid',
          'reason' => 'complex_query_detected',
          'requires_web_search' => $complexityDetection['requires_web_search'],
        ]
      );
    }
  }
}
