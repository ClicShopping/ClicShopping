<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Planning\ExecutionPlan;
use ClicShopping\AI\CoreAI\Planning\TaskStep;

/**
 * StepExecutor Class
 *
 * Responsible for executing individual steps in an execution plan.
 * Separated from PlanExecutor to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Execute individual steps
 * - Execute all steps in a plan
 * - Handle step failures
 * - Manage step dependencies
 * - Prepare step context
 */

class StepExecutor
{
  private SecurityLogger $logger;
  private bool $debug;
  private int $maxIterations;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->maxIterations = 100; // Safety limit

    if ($this->debug) {
      $this->logger->logSecurityEvent("StepExecutor initialized", 'info');
    }
  }

  /**
   * Execute all steps in a plan
   *
   * - Continue execution even if some steps fail
   * - Collect successful results
   * - Log failed steps with details
   * - Mark failed steps in results array
   *
   * @param ExecutionPlan $plan Plan to execute
   * @param callable $stepExecutor Callback to execute each step
   * @return void
   * @throws \Exception If ALL steps fail or execution deadlock occurs
   */
  public function executeSteps(ExecutionPlan $plan, callable $stepExecutor): void
  {
    $iteration = 0;
    $maxIterations = count($plan->getSteps()) * 2;
    $failedSteps = [];
    $successfulSteps = [];

    while (!$plan->isComplete() && !$plan->hasFailed() && $iteration < $maxIterations) {
      $iteration++;

      // Get ready steps
      $readySteps = $plan->getReadySteps();

      if (empty($readySteps)) {
        // Check if we have any successful steps before throwing deadlock error
        if (!empty($successfulSteps)) {
          // We have some successful results, consider plan partially complete
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Execution stopped with partial results: " . count($successfulSteps) . " successful, " . count($failedSteps) . " failed",
              'warning'
            );
          }
          break;
        }
        throw new \Exception("Execution deadlock: no steps ready but plan incomplete");
      }

      // Execute ready steps
      foreach ($readySteps as $step) {
        try {
          $stepExecutor($step, $plan);

          // Check if step failed
          if ($step->getStatus() === 'failed') {
            $failedSteps[] = [
              'step_id' => $step->getId(),
              'step_type' => $step->getType(),
              'error' => $step->getError(),
              'description' => $step->getDescription(),
            ];

            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Step {$step->getId()} failed: " . $step->getError() . " - Continuing with remaining steps",
                'warning',
                [
                  'step_type' => $step->getType(),
                  'description' => $step->getDescription(),
                ]
              );
            }
	    
            $plan->setStepResult($step->getId(), [
              'success' => false,
              'error' => $step->getError(),
              'step_id' => $step->getId(),
              'step_type' => $step->getType(),
              'failed' => true,
            ]);
	    
            continue;
          }

          // Track successful steps
          $successfulSteps[] = $step->getId();

        } catch (\Exception $e) {
          $failedSteps[] = [
            'step_id' => $step->getId(),
            'step_type' => $step->getType(),
            'error' => $e->getMessage(),
            'description' => $step->getDescription(),
          ];

          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Step {$step->getId()} threw exception: " . $e->getMessage() . " - Continuing with remaining steps",
              'error',
              [
                'step_type' => $step->getType(),
                'description' => $step->getDescription(),
                'exception' => get_class($e),
              ]
            );
          }

          // Mark step as failed
          $step->fail($e->getMessage());

          $plan->setStepResult($step->getId(), [
            'success' => false,
            'error' => $e->getMessage(),
            'step_id' => $step->getId(),
            'step_type' => $step->getType(),
            'failed' => true,
          ]);

          // Continue with next step
          continue;
        }
      }
    }

    if ($iteration >= $maxIterations) {
      throw new \Exception("Max iterations reached during plan execution");
    }

    if (!empty($failedSteps) && empty($successfulSteps)) {
      $errorSummary = "All steps failed:\n";
      foreach ($failedSteps as $failed) {
        $errorSummary .= "  - {$failed['step_id']} ({$failed['step_type']}): {$failed['error']}\n";
      }
      throw new \Exception($errorSummary);
    }

    if (!empty($failedSteps)) {
      $this->logger->logSecurityEvent(
        "Plan execution completed with partial failures",
        'warning',
        [
          'successful_steps' => count($successfulSteps),
          'failed_steps' => count($failedSteps),
          'failures' => $failedSteps,
        ]
      );
    }
  }

  /**
   * Execute a single step
   *
   * @param TaskStep $step Step to execute
   * @param ExecutionPlan $plan Parent plan
   * @param callable $typeExecutor Callback to execute by type
   * @return void
   * @throws \Exception If execution fails
   */
  public function executeStep(TaskStep $step, ExecutionPlan $plan, callable $typeExecutor): void
  {
    try {
      $step->start();

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Executing step: {$step->getId()} ({$step->getType()})",
          'info'
        );
      }

      // Prepare context
      $context = $this->prepareStepContext($step, $plan);

      // Execute by type
      $result = $typeExecutor($step, $context);

      // Store result
      $step->complete($result);
      $plan->setStepResult($step->getId(), $result);

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Step completed: {$step->getId()} in " . round($step->getExecutionTime(), 3) . "s",
          'info'
        );
      }
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Step failed: {$step->getId()} - " . $e->getMessage(),
        'error'
      );

      $step->fail($e->getMessage());
      throw $e;
    }
  }

  /**
   * Prepare context for a step
   *
   * @param TaskStep $step Step
   * @param ExecutionPlan $plan Parent plan
   * @return array Context
   */
  public function prepareStepContext(TaskStep $step, ExecutionPlan $plan): array
  {
    $context = [
      'step_id' => $step->getId(),
      'plan_query' => $plan->getQuery(),
      'plan_intent' => $plan->getIntent(),
      'metadata' => $step->getMetadata(),
      'dependency_results' => [],
    ];

    // Add dependency results
    $dependencies = $step->getDependencies();
    foreach ($dependencies as $depId) {
      $depResult = $plan->getStepResult($depId);
      if ($depResult !== null) {
        $context['dependency_results'][$depId] = $depResult;
      }
    }

    return $context;
  }

  /**
   * Handle step failure
   *
   * @param TaskStep $step Failed step
   * @param \Exception $e Exception
   * @return void
   */
  public function handleStepFailure(TaskStep $step, \Exception $e): void
  {
    $this->logger->logSecurityEvent(
      "Handling step failure: {$step->getId()} - " . $e->getMessage(),
      'error'
    );

    $step->fail($e->getMessage());
  }

  /**
   * Get ready steps from plan
   *
   * @param ExecutionPlan $plan Plan
   * @return array Array of ready steps
   */
  public function getReadySteps(ExecutionPlan $plan): array
  {
    return $plan->getReadySteps();
  }
}
