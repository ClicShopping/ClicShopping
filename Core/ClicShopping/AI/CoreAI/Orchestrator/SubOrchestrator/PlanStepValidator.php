<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * PlanStepValidator Class
 *
 * Proactively validates (and corrects) each analytics step of an execution plan before it runs.
 * Promoted from the OrchestratorAgent private helper validatePlanSteps() without behaviour change.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\CoreAI\Orchestrator\ValidationAgent;
use ClicShopping\AI\CoreAI\Orchestrator\CorrectionAgent;
use ClicShopping\AI\Security\SecurityLogger;

class PlanStepValidator
{
  private ValidationAgent $validationAgent;
  private CorrectionAgent $correctionAgent;
  private WorkingMemory $workingMemory;
  private SecurityLogger $securityLogger;
  private bool $debug;

  public function __construct(ValidationAgent $validationAgent, CorrectionAgent $correctionAgent, WorkingMemory $workingMemory, SecurityLogger $securityLogger, bool $debug = false)
  {
    $this->validationAgent = $validationAgent;
    $this->correctionAgent = $correctionAgent;
    $this->workingMemory = $workingMemory;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
  }

  /**
   * Proactively validate (and correct) each analytics step of a plan before execution.
   *
   * For every 'analytics_query' step, validates the sub-query with the ValidationAgent; on failure,
   * attempts a correction with the CorrectionAgent and, if successful, updates the step's sub_query
   * meta in place. Records all validations in working memory and debug-logs a summary. Returns the
   * per-step validation results (consumed later by memory storage). The plan object is mutated in
   * place exactly as before.
   *
   * @param object $plan ExecutionPlan whose steps are validated/corrected in place
   * @param string $queryToProcess Resolved query (correction context)
   * @param string $executionId Execution id used as plan id in validation metadata
   * @return array Per-step validation results keyed by step id
   */
  public function validate(object $plan, string $queryToProcess, string $executionId): array
  {
    $validationResults = [];

    foreach ($plan->getSteps() as $step) {
      if ($step->getType() === 'analytics_query') {
        $subQuery = $step->getMeta('sub_query', $step->getDescription());

        // VALIDATION PROACTIVE
        $validation = $this->validationAgent->validateBeforeExecution($subQuery, [
          'step_id' => $step->getId(),
          'plan_id' => $executionId,
        ]);

        $validationResults[$step->getId()] = $validation;

        // Si validation échoue, corriger immédiatement
        if (!$validation['can_execute']) {
          // Utiliser le CorrectionAgent
          $correction = $this->correctionAgent->attemptCorrection([
            'error_message' => implode(', ', $validation['errors']),
            'failed_query' => $subQuery,
            'original_query' => $queryToProcess,
          ]);

          if ($correction['success']) {
            // Update query in step
            $step->setMeta('sub_query', $correction['corrected_query']);
            $step->setMeta('was_corrected', true);
            $step->setMeta('correction_method', $correction['correction_method']);
          }
        }
      }
    }

    $this->workingMemory->set('validations', $validationResults);

    // Structured logging for validation results
    if ($this->debug && !empty($validationResults)) {
      $passedCount = count(array_filter($validationResults, fn($v) => $v['can_execute']));
      $failedCount = count($validationResults) - $passedCount;
      $this->securityLogger->logStructured(
        'info',
        'OrchestratorAgent',
        'validation',
        [
          'total_validations' => count($validationResults),
          'passed' => $passedCount,
          'failed' => $failedCount
        ]
      );
    }

    return $validationResults;
  }
}
