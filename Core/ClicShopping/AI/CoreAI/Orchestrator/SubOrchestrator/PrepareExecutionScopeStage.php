<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * PrepareExecutionScopeStage
 *
 * First stage of the orchestration pipeline. Opens a fresh working-memory scope for the request,
 * seeds it with the original query and start time, and marks the init point on the performance
 * tracker. Carries sequencing only — the behaviour is the verbatim former
 * {@see OrchestratorAgent::prepareExecutionScope()}; it just writes the generated executionId onto
 * the shared {@see OrchestrationContext} instead of returning it.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\Infrastructure\Monitoring\PerformanceTracker;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;

class PrepareExecutionScopeStage implements OrchestrationStageInterface
{
  public function __construct(
    private WorkingMemory $workingMemory,
    private PerformanceTracker $performanceTracker
  ) {
  }

  public function id(): string
  {
    return 'prepare_execution_scope';
  }

  public function run(OrchestrationContext $context): ?array
  {
    $executionId = 'exec_' . uniqid('', true);
    $this->workingMemory->enterScope($executionId);

    $this->workingMemory->set('original_query', $context->query);
    $this->workingMemory->set('start_time', $context->startTime);

    $this->performanceTracker->addMarker('after_init'); // Phase 5: Use PerformanceTracker

    $context->executionId = $executionId;

    return null;
  }
}
