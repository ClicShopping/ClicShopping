<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Planning;

use ClicShopping\AI\DomainsAI\Analytics\Agent\AnalyticsAgent;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Infrastructure\Metrics\CalculatorTool;

/**
 * ExecutionPlan Class
 *
 * Represents complete execution plan with:
 * - Ordered list of steps
 * - Dependency graph
 * - Complexity metadata
 * - Execution state
 */

class ExecutionPlan
{
  private string $query;
  private array $intent;
  private array $steps;
  private array $dependencies;
  private array $complexity;
  private float $planningTime = 0.0;
  private bool $isReplan = false;
  private ?ExecutionPlan $originalPlan = null;
  private ?array $replanContext = null;

  // Execution state
  private string $status = 'pending'; // pending, in_progress, completed, failed
  private array $stepResults = [];
  private ?string $finalResult = null;
  private float $executionTime = 0.0;

  private ?AnalyticsAgent $analyticsAgent = null;
  private ?MultiDBRAGManager $ragManager = null;
  private bool $debug;
  private string $userId;
  private int $languageId;

  private ?CalculatorTool $calculatorTool = null;


  /**
   * Constructor
   *
   * @param string $query Original query
   * @param array $intent Analyzed intent
   * @param array $steps List of TaskStep objects
   * @param array $dependencies Dependency graph
   * @param array $complexity Complexity analysis
   */
  public function __construct(string $query, array $intent, array $steps, array $dependencies, array $complexity)
  {
    $this->query = $query;
    $this->intent = $intent;
    $this->steps = $steps;
    $this->dependencies = $dependencies;
    $this->complexity = $complexity;
  }

  /**
   * Marks this plan as a replan
   * 
   * @param ExecutionPlan $originalPlan Original plan that failed
   * @param array $context Replan context
   */
  public function markAsReplan(ExecutionPlan $originalPlan, array $context): void
  {
    $this->isReplan = true;
    $this->originalPlan = $originalPlan;
    $this->replanContext = $context;
  }

  /**
   * Gets list of steps
   * 
   * @return array Array of TaskStep objects
   */
  public function getSteps(): array
  {
    return $this->steps;
  }

  /**
   * Gets step by ID
   * 
   * @param string $id Step ID
   * @return TaskStep|null Step object or null if not found
   */
  public function getStepById(string $id): ?TaskStep
  {
    foreach ($this->steps as $step) {
      if ($step->getId() === $id) {
        return $step;
      }
    }
    return null;
  }

  /**
   * Gets steps that can execute now (all dependencies completed)
   * 
   * @return array Array of ready TaskStep objects
   */
  public function getReadySteps(): array
  {
    $ready = [];

    foreach ($this->steps as $step) {
      if ($step->getStatus() !== 'pending') {
        continue;
      }

      $stepId = $step->getId();
      $dependsOn = $this->dependencies[$stepId]['depends_on'] ?? [];

      // Check if all dependencies are completed
      $allDependenciesComplete = true;
      foreach ($dependsOn as $depId) {
        $depStep = $this->getStepById($depId);
        if (!$depStep || $depStep->getStatus() !== 'completed') {
          $allDependenciesComplete = false;
          break;
        }
      }

      if ($allDependenciesComplete) {
        $ready[] = $step;
      }
    }

    return $ready;
  }

  /**
   * Gets steps that can execute in parallel
   * 
   * @return array Array of parallel-capable TaskStep objects
   */
  public function getParallelSteps(): array
  {
    $ready = $this->getReadySteps();
    $parallel = [];

    foreach ($ready as $step) {
      if ($step->getMetadata()['can_run_parallel'] ?? false) {
        $parallel[] = $step;
      }
    }

    return $parallel;
  }

  /**
   * Stores step result
   * 
   * @param string $stepId Step ID
   * @param mixed $result Step result
   */
  public function setStepResult(string $stepId, $result): void
  {
    $this->stepResults[$stepId] = $result;

    $step = $this->getStepById($stepId);
    if ($step) {
      $step->setResult($result);
    }
  }

  /**
   * Gets step result
   * 
   * @param string $stepId Step ID
   * @return mixed Step result or null
   */
  public function getStepResult(string $stepId)
  {
    return $this->stepResults[$stepId] ?? null;
  }

  /**
   * Gets all step results
   * 
   * @return array All step results
   */
  public function getAllStepResults(): array
  {
    return $this->stepResults;
  }

  /**
   * Marks plan as in progress
   */
  public function start(): void
  {
    $this->status = 'in_progress';
  }

  /**
   * Marks plan as completed
   * 
   * @param string $finalResult Final result
   */
  public function complete(string $finalResult): void
  {
    $this->status = 'completed';
    $this->finalResult = $finalResult;
  }

  /**
   * Marks plan as failed
   * 
   * @param string $error Error message
   */
  public function fail(string $error): void
  {
    $this->status = 'failed';
    $this->finalResult = $error;
  }

  /**
   * Checks if all steps are completed
   * 
   * @return bool True if all steps completed
   */
  public function isComplete(): bool
  {
    foreach ($this->steps as $step) {
      if ($step->getStatus() !== 'completed') {
        return false;
      }
    }
    return true;
  }

  /**
   * Checks if plan has failed
   * 
   * @return bool True if plan failed
   */
  public function hasFailed(): bool
  {
    foreach ($this->steps as $step) {
      if ($step->getStatus() === 'failed') {
        return true;
      }
    }
    return $this->status === 'failed';
  }

  /**
   * Gets execution progress (0-100)
   * 
   * @return float Progress percentage
   */
  public function getProgress(): float
  {
    if (empty($this->steps)) {
      return 100.0;
    }

    $completed = 0;
    foreach ($this->steps as $step) {
      if ($step->getStatus() === 'completed') {
        $completed++;
      }
    }

    return ($completed / count($this->steps)) * 100;
  }

  /**
   * Gets executed step results
   * 
   * @return array Array of results by step
   */
  public function getResults(): array
  {
    return $this->stepResults;
  }

  /**
   * Generates plan summary
   * 
   * @return array Summary array
   */
  public function getSummary(): array
  {
    return [
      'query' => $this->query,
      'total_steps' => count($this->steps),
      'complexity_level' => $this->complexity['level'],
      'complexity_score' => $this->complexity['score'],
      'status' => $this->status,
      'progress' => round($this->getProgress(), 2) . '%',
      'is_replan' => $this->isReplan,
      'planning_time' => round($this->planningTime, 3) . 's',
      'execution_time' => round($this->executionTime, 3) . 's',
    ];
  }

  /**
   * Generates detailed plan representation
   * 
   * @return array Detailed view with steps and dependencies
   */
  public function getDetailedView(): array
  {
    $stepsView = [];

    foreach ($this->steps as $step) {
      $stepId = $step->getId();

      $stepsView[] = [
        'id' => $stepId,
        'type' => $step->getType(),
        'description' => $step->getDescription(),
        'status' => $step->getStatus(),
        'depends_on' => $this->dependencies[$stepId]['depends_on'] ?? [],
        'required_by' => $this->dependencies[$stepId]['required_by'] ?? [],
        'can_run_parallel' => $step->getMetadata()['can_run_parallel'] ?? false,
        'execution_time' => $step->getExecutionTime(),
        'has_result' => isset($this->stepResults[$stepId]),
      ];
    }

    return [
      'summary' => $this->getSummary(),
      'steps' => $stepsView,
      'dependencies' => $this->dependencies,
    ];
  }

  // Getters
  public function getQuery(): string {return $this->query;}
  public function getIntent(): array { return $this->intent; }
  public function getDependencies(): array { return $this->dependencies; }
  public function getComplexity(): array { return $this->complexity; }
  public function getStatus(): string { return $this->status; }
  public function getFinalResult(): ?string { return $this->finalResult; }
  public function isReplan(): bool { return $this->isReplan; }
  public function getOriginalPlan(): ?ExecutionPlan { return $this->originalPlan; }
  public function getReplanContext(): ?array { return $this->replanContext; }
  public function getPlanningTime(): float { return $this->planningTime; }
  public function getExecutionTime(): float { return $this->executionTime; }

  // Setters
  public function setPlanningTime(float $time): void { $this->planningTime = $time; }
  public function setExecutionTime(float $time): void { $this->executionTime = $time; }
}
