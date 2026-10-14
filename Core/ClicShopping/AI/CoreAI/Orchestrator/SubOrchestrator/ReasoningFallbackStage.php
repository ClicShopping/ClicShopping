<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * ReasoningFallbackStage
 *
 * Sixth stage of the orchestration pipeline (runs only for non-hybrid queries, which short-circuit
 * earlier). Clarifies low-confidence intents via the ReasoningAgent fallback and builds the enriched
 * context used by planning. The resolved route is debug-logged.
 *
 * Carries sequencing only — it delegates to the already-injected {@see LowConfidenceReasoningFallback}
 * and {@see QueryProcessor}. Writes the (possibly updated) intent and the enriched context onto the
 * pipeline context.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\Handler\Query\QueryProcessor;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class ReasoningFallbackStage implements OrchestrationStageInterface
{
  public function __construct(
    private LowConfidenceReasoningFallback $lowConfidenceReasoningFallback,
    private QueryProcessor $queryProcessor,
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'reasoning_fallback';
  }

  public function run(OrchestrationContext $context): ?array
  {
    // 5. Low-confidence queries: clarify via ReasoningAgent, default unknown types to semantic.
    $context->intent = $this->lowConfidenceReasoningFallback->apply(
      $context->intent,
      $context->context,
      $context->contextAnalysis,
      $context->queryToProcess
    );

    $context->enrichedContext = $this->queryProcessor->buildEnrichedContext($context->context, $context->contextAnalysis);

    if ($this->debug) {
      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'PATH_DECISION.intent_route',
        [
          'route' => $context->intent['type'] ?? 'unknown',
          'is_hybrid_flag' => $context->intent['is_hybrid'] ?? false,
        ]
      );
    }

    return null;
  }
}
