<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * RouteHybridDuplicateStage
 *
 * Seventh stage of the orchestration pipeline. Re-resolves the effective intent type (the reasoning
 * fallback may have changed the intent), performs the transitional domain-routing lookup (kept for
 * its logging/future use), then runs the safety-net duplicate hybrid check: if a hybrid intent
 * slipped past {@see RouteHybridEarlyStage}, it forwards to the hybrid handler and returns that
 * result to short-circuit the pipeline. Normally returns null.
 *
 * Carries sequencing only — delegates to the already-injected {@see DomainRouter} and
 * {@see HybridQueryHandler}; folds in the verbatim former {@see OrchestratorAgent::resolveIntentType()}
 * and {@see OrchestratorAgent::routeHybridDuplicate()}.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\DomainsAI\DomainRouter;
use ClicShopping\AI\DomainsAI\Hybrid\Handler\HybridQueryHandler;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class RouteHybridDuplicateStage implements OrchestrationStageInterface
{
  public function __construct(
    private DomainRouter $domainRouter,
    private HybridQueryHandler $hybridQueryHandler,
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'route_hybrid_duplicate';
  }

  public function run(OrchestrationContext $context): ?array
  {
    // Hybrid queries need to be split into sub-queries and executed by multiple agents.
    // Check both 'type' and 'query_type' fields, default to 'semantic' (safer than 'analytics').
    $intentType = $this->resolveIntentType($context->intent, $context->queryToProcess);
    $context->intentType = $intentType;

    // Domain-based routing (transitional). The lookup is kept for its logging/future use;
    // current routing still happens directly downstream.
    $this->domainRouter->getDomainForIntent($intentType, []);

    // Safety net: hybrid queries are routed earlier; this duplicate check should never trigger.
    return $this->routeHybridDuplicate(
      $context->queryToProcess,
      $context->intent,
      $intentType,
      $context->enrichedContext,
      $context->startTime
    );
  }

  /**
   * Resolve the effective intent type, defaulting to a safe 'semantic' classification.
   *
   * @param array $intent Intent produced upstream
   * @param string $queryToProcess Resolved query (for log context)
   * @return string The resolved intent type
   */
  private function resolveIntentType(array $intent, string $queryToProcess): string
  {
    $intentType = $intent['type'] ?? $intent['query_type'] ?? 'semantic';

    if (!isset($intent['type']) && !isset($intent['query_type'])) {
      $this->securityLogger->logStructured(
        'warning',
        'OrchestratorAgent',
        'intent_type_fallback',
        [
          'fallback_value' => 'semantic',
          'reason' => 'Neither type nor query_type found in intent',
          'intent_keys' => array_keys($intent),
          'query' => $queryToProcess
        ]
      );
    }

    return $intentType;
  }

  /**
   * Safety-net duplicate hybrid routing (should never trigger). Returns the hybrid result to
   * short-circuit the pipeline, or null for non-hybrid intents.
   *
   * @return array|null Hybrid orchestration result, or null when the query is not hybrid
   */
  private function routeHybridDuplicate(string $queryToProcess, array $intent, string $intentType, array $enrichedContext, float $startTime): ?array
  {
    if ($intentType !== 'hybrid') {
      return null;
    }

    if ($this->debug) {
      $this->securityLogger->logStructured(
        'warning',
        'OrchestratorAgent',
        'HYBRID_ROUTING_DUPLICATE',
        [
          'action' => 'unexpected_hybrid_routing_fallback',
          'intent_type' => $intentType,
          'is_hybrid_flag' => $intent['is_hybrid'] ?? false,
          'confidence' => $intent['confidence'] ?? 0,
          'query' => substr($queryToProcess, 0, 100),
          'note' => 'This should not happen - hybrid queries should be routed earlier'
        ]
      );
    }

    return $this->hybridQueryHandler->handleHybridQuery($queryToProcess, $intent, $enrichedContext, $startTime);
  }
}
