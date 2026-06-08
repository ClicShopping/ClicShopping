<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * RouteHybridEarlyStage
 *
 * Fifth stage of the orchestration pipeline, and the first **short-circuit** stage: when the resolved
 * intent type is 'hybrid', it builds the enriched context and hands the query to the hybrid handler,
 * returning that result to halt the pipeline (hybrid queries must NOT reach the ReasoningAgent, which
 * would misclassify them as analytics — see the 2026-02-08 routing fix). For non-hybrid intents it
 * returns null and the pipeline continues.
 *
 * Carries sequencing only — it delegates to the already-injected {@see QueryProcessor} and
 * {@see HybridQueryHandler}; behaviour is the verbatim former
 * {@see OrchestratorAgent::routeHybridEarly()}.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\DomainsAI\Hybrid\Handler\HybridQueryHandler;
use ClicShopping\AI\Handler\Query\QueryProcessor;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class RouteHybridEarlyStage implements OrchestrationStageInterface
{
  public function __construct(
    private QueryProcessor $queryProcessor,
    private HybridQueryHandler $hybridQueryHandler,
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'route_hybrid_early';
  }

  public function run(OrchestrationContext $context): ?array
  {
    if ($context->intentType !== 'hybrid') {
      return null;
    }

    if ($this->debug) {
      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'HYBRID_ROUTING_EARLY',
        [
          'action' => 'routing_to_hybrid_processor_before_reasoning',
          'intent_type' => $context->intentType,
          'is_hybrid_flag' => $context->intent['is_hybrid'] ?? false,
          'confidence' => $context->intent['confidence'] ?? 0,
          'query' => substr($context->queryToProcess, 0, 100),
          'note' => 'Hybrid routing moved before ReasoningAgent to fix routing bug'
        ]
      );
    }

    // Get enriched context for hybrid processing
    $enrichedContext = $this->queryProcessor->buildEnrichedContext($context->context, $context->contextAnalysis);

    // Handle hybrid queries with Actor-Critic approach: returning a non-null result short-circuits
    // the pipeline so the query never reaches the ReasoningAgent.
    return $this->hybridQueryHandler->handleHybridQuery($context->queryToProcess, $context->intent, $enrichedContext, $context->startTime);
  }
}
