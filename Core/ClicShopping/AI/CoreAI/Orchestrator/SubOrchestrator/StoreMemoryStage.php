<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * StoreMemoryStage
 *
 * Eleventh stage of the orchestration pipeline. Stores the orchestration outcome in conversation
 * memory (with cache-aware skipping), bracketed by the before/after memory performance markers.
 * On a warm-cache response, persistence is skipped but the last entity is still tracked so follow-up
 * contextual queries resolve; on a cache miss the full result is persisted via the MemoryManager.
 *
 * Carries sequencing only — it delegates to the already-injected {@see MemoryManager}; behaviour is
 * the verbatim former {@see OrchestratorAgent::storeOrchestrationMemory()} plus the surrounding
 * markers.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Query\QueryAnalyzer;
use ClicShopping\AI\Infrastructure\Monitoring\PerformanceTracker;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class StoreMemoryStage implements OrchestrationStageInterface
{
  public function __construct(
    private PerformanceTracker $performanceTracker,
    private MemoryManager $memoryManager,
    private QueryAnalyzer $queryAnalyzer,
    private ResponseProcessor $responseProcessorComponent,
    private string $userId,
    private int $languageId,
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'store_memory';
  }

  public function run(OrchestrationContext $context): ?array
  {
    $this->performanceTracker->addMarker('before_memory'); // Phase 5: Use PerformanceTracker

    $response = $context->response;
    $query = $context->query;
    $entityId = $context->entityId;
    $entityType = $context->entityType;

    // Check if query is already in QueryCache (warm cache scenario)
    // Check both 'from_cache' and 'cached' flags (different agents use different naming)
    $skipMemoryStorage = false;
    $isCached = (isset($response['from_cache']) && $response['from_cache'] === true) ||
                (isset($response['cached']) && $response['cached'] === true) ||
                (isset($response['metadata']['from_cache']) && $response['metadata']['from_cache'] === true);

    if ($isCached) {
      $skipMemoryStorage = true;

      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'memory_storage_skipped', [
          'reason' => 'query_already_cached',
          'cache_hit' => true,
          'latency_saved_ms' => '2000-3000 (estimated)',
          'query' => substr($query, 0, 100)
        ]);
      }

      // Entity tracking is lightweight (<1ms) and essential for follow-up queries
      // This ensures "What is its stock level" works after cached "What is the price of iPhone"
      if ($entityId !== null && $entityId !== 0) {
        if ($this->debug) {
          error_log("[INFO ENTITY_TRACKING] Setting last entity: ID={$entityId}, Type={$entityType}, Query=" . substr($query, 0, 50));
        }
        $this->memoryManager->setLastEntity($entityId, $entityType);

        if ($this->debug) {
          $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'entity_tracked_for_cached_query', [
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'reason' => 'contextual_reference_resolution',
            'overhead_ms' => '<1'
          ]);
        }
      } else {
        if ($this->debug) {
          error_log("[WARNING ENTITY_TRACKING] NOT setting last entity: ID={$entityId}, Type={$entityType}, Query=" . substr($query, 0, 50));
        }
      }
    }

    // Only store in memory for NEW queries (cache miss)
    if (!$skipMemoryStorage) {
      $this->memoryManager->storeOrchestrationResult(
        $query,
        $context->queryToProcess,
        $response,
        $context->intent,
        $context->contextAnalysis,
        $context->plan,
        $context->validationResults,
        $entityId,
        $entityType,
        $this->userId,
        $this->languageId,
        $this->queryAnalyzer,
        $this->responseProcessorComponent
      );

      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'memory_storage_completed', [
          'cache_miss' => true,
          'entity_id' => $entityId,
          'entity_type' => $entityType
        ]);
      }
    }

    $this->performanceTracker->addMarker('after_memory'); // Phase 5: Use PerformanceTracker

    return null;
  }
}
