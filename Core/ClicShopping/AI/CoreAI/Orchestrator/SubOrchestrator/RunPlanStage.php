<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * RunPlanStage
 *
 * Ninth stage of the orchestration pipeline. Drives plan execution: runs the validated plan through
 * the Planning subsystem's {@see PlanExecutor}, extracts the resulting entity identity (patching a
 * null/empty/'ABSENT' id to a neutral 0/'general'), mirrors the success flag onto working memory, and
 * raises on execution failure.
 *
 * Named to distinguish it from Planning's {@see PlanExecutor}: this is the orchestration **stage**
 * that drives the executor, not the executor itself. It carries sequencing only — it delegates to the
 * already-injected {@see PlanExecutor} and {@see EntityExtractor}; behaviour is the verbatim former
 * {@see OrchestratorAgent::executePlanAndExtractEntities()} plus the inline success check. Writes the
 * execution result and the resolved entity id/type onto the pipeline context.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\CoreAI\Planning\PlanExecutor;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class RunPlanStage implements OrchestrationStageInterface
{
  public function __construct(
    private PlanExecutor $planExecutor,
    private EntityExtractor $entityExtractor,
    private WorkingMemory $workingMemory,
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'run_plan';
  }

  public function run(OrchestrationContext $context): ?array
  {
    $executionResult = $this->planExecutor->execute($context->plan);

    // Extract entity information
    $entityId = $this->entityExtractor->extractEntityId($executionResult, $context->intent, $context->plan);
    $entityType = $this->entityExtractor->extractEntityType($executionResult, $context->intent, $context->plan) ?? 'unknown';

    // Patch: Ensure entity_id is never null for database
    if ($entityId === null || $entityId === '' || $entityId === 'ABSENT') {
      $entityId = 0;
      $entityType = 'general';
    }

    // Debug logging
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'execution_complete', [
        'success' => $executionResult['success'] ?? false,
        'entity_id' => $entityId,
        'entity_type' => $entityType
      ]);
    }

    $context->executionResult = $executionResult;
    $context->entityId = $entityId;
    $context->entityType = $entityType;

    $this->workingMemory->set('execution_result', $executionResult['success']);

    if (!$executionResult['success']) {
      throw new \Exception($executionResult['error'] ?? 'Execution failed');
    }

    return null;
  }
}
