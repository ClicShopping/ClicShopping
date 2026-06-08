<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * CreatePlanStage
 *
 * Eighth stage of the orchestration pipeline. Builds the execution plan for the resolved intent,
 * mirrors its summary onto working memory, and proactively validates (and corrects) each plan step
 * before execution.
 *
 * Carries sequencing only — it delegates to the already-injected {@see TaskPlanner} and
 * {@see PlanStepValidator}, and folds in the verbatim former {@see OrchestratorAgent::logPlanCreation()}.
 * Writes the plan and the per-step validation results onto the pipeline context.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\CoreAI\Planning\TaskPlanner;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class CreatePlanStage implements OrchestrationStageInterface
{
  public function __construct(
    private TaskPlanner $taskPlanner,
    private WorkingMemory $workingMemory,
    private PlanStepValidator $planStepValidator,
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'create_plan';
  }

  public function run(OrchestrationContext $context): ?array
  {
    $planStart = microtime(true);
    $plan = $this->taskPlanner->createPlan($context->intent, $context->queryToProcess, $context->enrichedContext);
    if ($this->debug) {
      error_log("[INFO : TIME] [PERF] createPlan took " . round((microtime(true) - $planStart), 2) . "s");
    }

    $context->plan = $plan;
    $this->workingMemory->set('execution_plan', $plan->getSummary());

    $this->logPlanCreation($plan);

    // 7. Valider chaque étape du plan AVANT exécution
    $context->validationResults = $this->planStepValidator->validate($plan, $context->queryToProcess, $context->executionId);

    return null;
  }

  /**
   * Debug-log a freshly created execution plan (step count and step types).
   *
   * @param object $plan ExecutionPlan produced by TaskPlanner::createPlan()
   * @return void
   */
  private function logPlanCreation(object $plan): void
  {
    if ($this->debug) {
      $steps = $plan->getSteps();
      $stepTypes = array_map(fn($step) => $step->getType(), $steps);
      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'createPlan',
        [
          'step_count' => count($steps),
          'step_types' => $stepTypes
        ]
      );
    }
  }
}
