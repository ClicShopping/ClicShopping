<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * Refactored TaskPlanner
 * Main orchestrator that delegates planning to specialized SubTaskPlanners
 * Selects appropriate SubTaskPlanner and coordinates execution
 */

namespace ClicShopping\AI\CoreAI\Planning;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Monitoring\MetricsCollector;
use ClicShopping\AI\CoreAI\Planning\SubTaskPlanning\SubTaskPlannerCompetitorAnalysis;
use ClicShopping\AI\CoreAI\Planning\SubTaskPlanning\SubTaskPlannerPatternAnalysis;
use ClicShopping\AI\CoreAI\Planning\SubTaskPlanning\SubTaskPlannerPriceAnalytics;
use ClicShopping\AI\CoreAI\Planning\SubTaskPlanning\SubTaskPlannerAnalytics;
use ClicShopping\AI\CoreAI\Planning\SubTaskPlanning\SubTaskPlannerSemanticSearch;
use ClicShopping\AI\CoreAI\Planning\SubTaskPlanning\SubTaskPlannerWebSearch;
use ClicShopping\AI\CoreAI\Planning\SubTaskPlanning\SubTaskPlannerStandard;
use ClicShopping\AI\CoreAI\Planning\TaskPlannerPrompts;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;


/**
 * Refactored TaskPlanner - Modular architecture with SubTaskPlanners
 */

class TaskPlanner
{
    private SecurityLogger $securityLogger;
    private mixed $chat;
    private bool $debug;
    private int $languageId;
    private MetricsCollector $collector;

    // Specialized SubTaskPlanners
    private array $subTaskPlanners = [];

    // Planning statistics
    private array $planningStats = [
        'total_plans_created' => 0,
        'total_steps_planned' => 0,
        'replans_triggered' => 0,
        'successful_executions' => 0,
        'failed_executions' => 0,
        'planner_usage' => [],
    ];

    /**
     * Constructor - Initializes SubTaskPlanners
     */
    public function __construct(?int $languageId = null)
    {
        $this->securityLogger = new SecurityLogger();
        $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
        $this->collector = new MetricsCollector();

        if (is_null($languageId)) {
            $this->languageId = Registry::get('Language')->getId();
        } else {
            $this->languageId = $languageId;
        }

        // Initialize chat for planning
        $this->initializeChat();

        // Initialize specialized SubTaskPlanners
        $this->initializeSubTaskPlanners();

        if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
                "TaskPlannerRefactored initialized with " . count($this->subTaskPlanners) . " SubTaskPlanners",
                'info'
            );
        }
    }

    /**
     * Initializes all specialized SubTaskPlanners
     */
    private function initializeSubTaskPlanners(): void
    {
        // Evaluation order: from most specific to most general
        $this->subTaskPlanners = [
            'competitor_analysis' => new SubTaskPlannerCompetitorAnalysis($this->debug, $this->securityLogger),
            'pattern_analysis' => new SubTaskPlannerPatternAnalysis($this->debug, $this->securityLogger),
            'analytics' => new SubTaskPlannerAnalytics($this->debug, $this->securityLogger), // Basic analytics catch-all
            'semantic_search' => new SubTaskPlannerSemanticSearch($this->debug, $this->securityLogger),
            'web_search' => new SubTaskPlannerWebSearch($this->debug, $this->securityLogger),
            'standard' => new SubTaskPlannerStandard($this->debug, $this->securityLogger), // Fallback
        ];

        if ($this->debug) {
            $plannerNames = array_keys($this->subTaskPlanners);
            $this->securityLogger->logSecurityEvent(
                "SubTaskPlanners initialized: " . implode(', ', $plannerNames),
                'info'
            );
        }
    }

    /**
     * Initializes chat for planning reasoning
     */
    private function initializeChat(): void
    {
        $model = defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL') ? CLICSHOPPING_APP_CHATGPT_CH_MODEL : 'gpt-5-mini';

        // Use Gpt::getChatForModel which automatically handles all model types
        $this->chat = Gpt::getChatForModel($model);

        // Verify chat was initialized correctly
        if ($this->chat === false || $this->chat === null) {
            // Degraded mode: use predefined plans instead of AI
            $this->chat = null;
	    
            if($this->debug) {
              error_log('TaskPlannerRefactored: Using fallback mode due to missing API key');
            }
	    
            return;
        }

        $systemPrompt = TaskPlannerPrompts::getTaskPlannerSystemPrompt();
        $this->chat->setSystemMessage($systemPrompt);
    }

    /**
     * Main method: Creates execution plan for a query
     * 
     * Refactored architecture:
     * 1. Selects appropriate SubTaskPlanner
     * 2. Delegates plan creation
     * 3. Analyzes dependencies and optimizes
     * 
     * @param array $intent Intent classification result
     * @param string $query User query
     * @param array $context Additional context
     * @return ExecutionPlan Execution plan with steps
     */
    public function createPlan(array $intent, string $query, array $context = []): ExecutionPlan
    {
      $startTime = microtime(true);
      $this->collector->startTimer('plan_creation');

      if($this->debug) {
        error_log(str_repeat("+", 100));
        error_log("TASK 4.3.4.3: TaskPlanner.createPlan() CALLED");
        error_log("+" . str_repeat("+", 99));
        error_log("Query received: '{$query}'");
        error_log("Query length: " . strlen($query));
        error_log("Intent type: " . ($intent['type'] ?? 'unknown'));
        error_log("Intent has translated_query: " . (isset($intent['translated_query']) ? 'YES' : 'NO'));
      }

        if (isset($intent['translated_query'])) {
            error_log("Translated query: " . $intent['translated_query']);
        }

        try {
            if ($this->debug) {
                $this->securityLogger->logSecurityEvent(
                    "Creating plan for intent type: " . ($intent['type'] ?? 'unknown') .
                    ", query: " . substr($query, 0, 100),
                    'info'
                );
            }

            // 1. Select appropriate SubTaskPlanner (pass intent by reference to preserve modifications)
            $selectedPlanner = $this->selectSubTaskPlanner($intent, $query);

            if ($this->debug) {
                $plannerName = $this->getSubTaskPlannerName($selectedPlanner);
                $this->securityLogger->logSecurityEvent(
                    "Selected SubTaskPlanner: $plannerName",
                    'info'
                );
                
                // Log if sub_queries were added
                if (isset($intent['sub_queries'])) {
                    $this->securityLogger->logSecurityEvent(
                        "Intent contains " . count($intent['sub_queries']) . " sub-queries",
                        'info'
                    );
                }
            }

            // 2. Delegate plan creation to SubTaskPlanner
            $steps = $selectedPlanner->createPlan($intent, $query);

            // 2.1 If LLM selected an explicit action, resolve via active domain tool registry
            if (!empty($intent['action'])) {
              $domainApp = \ClicShopping\AI\DomainsAI\DomainRegistry::getInstance()->getActiveApp();

              if ($domainApp && method_exists($domainApp, 'getToolRegistryClass')) {
                $registryClass = $domainApp->getToolRegistryClass();

                if ($registryClass && class_exists($registryClass) && method_exists($registryClass, 'resolveAction')) {
                  $toolStep = $registryClass::resolveAction(
                    (string)$intent['action'],
                    is_array($intent['action_params'] ?? null) ? $intent['action_params'] : [],
                    $intent,
                    $query
                  );

                  if (is_array($toolStep) && !empty($toolStep['step_type'])) {
                    $meta = $toolStep['meta'] ?? [];
                    $meta['intent'] = $intent;
                    $meta['planner'] = $toolStep['planner'] ?? 'llm_action_domain_tool';
                    $meta['data_source'] = $meta['data_source'] ?? 'internal_database';
                    $meta['depends_on'] = $meta['depends_on'] ?? [];
                    $meta['can_run_parallel'] = $meta['can_run_parallel'] ?? false;
                    $meta['is_final'] = $meta['is_final'] ?? true;

                    $steps = [new TaskStep('step_1', $toolStep['step_type'], $query, $meta)];
                  }
                }
              }
            }

          if($this->debug) {
            error_log("Steps created by SubTaskPlanner:");
          }

          foreach ($steps as $step) {
              error_log("  Step: " . $step->getId());
              error_log("    Type: " . $step->getType());
              error_log("    Description: " . $step->getDescription());
              $subQuery = $step->getMeta('sub_query', null);
              error_log("    sub_query metadata: " . ($subQuery ?? 'NULL'));
          }

          // 3. Analyze dependencies
          $dependencies = $this->analyzeDependencies($steps);

          // 4. Optimize execution order
          $optimizedSteps = $this->optimizeExecutionOrder($steps, $dependencies);

          // 5. Analyze complexity (for compatibility)
          $complexity = $this->analyzeComplexity($intent, $query);

          // 6. Create execution plan
          $plan = new ExecutionPlan($query, $intent, $optimizedSteps, $dependencies, $complexity);

          // Statistics
          $this->updatePlanningStats($selectedPlanner, count($optimizedSteps));

          $this->collector->stopTimer('plan_creation');
          $plan->setPlanningTime(microtime(true) - $startTime);

          $stepTypes = array_map(function($step) { return $step->getType(); }, $optimizedSteps);
          $stepIds = array_map(function($step) { return $step->getId(); }, $optimizedSteps);
          
          $this->securityLogger->logSecurityEvent(
              "PLAN CREATED - Steps: " . count($optimizedSteps) . 
              " | Types: " . json_encode(array_unique($stepTypes)) . 
              " | Time: " . round($plan->getPlanningTime(), 3) . "s",
              'info',
              [
                  'total_steps' => count($optimizedSteps),
                  'step_ids' => $stepIds,
                  'step_types' => $stepTypes,
                  'unique_step_types' => array_unique($stepTypes),
                  'planning_time_seconds' => round($plan->getPlanningTime(), 3),
                  'complexity_level' => $complexity['level'] ?? 'unknown',
                  'complexity_score' => $complexity['score'] ?? 0,
                  'planner_used' => $this->getSubTaskPlannerName($selectedPlanner),
                  'has_dependencies' => !empty($dependencies),
                  'query' => substr($query, 0, 100),
                  'intent_type' => $intent['type'] ?? 'unknown',
                  'is_hybrid' => isset($intent['sub_queries']),
                  'timestamp' => date('Y-m-d H:i:s')
              ]
          );

          if ($this->debug) {
              $this->securityLogger->logSecurityEvent(
                  "Plan created with " . count($optimizedSteps) . " steps in " .
                  round($plan->getPlanningTime(), 3) . "s",
                  'info'
              );
          }

          return $plan;

        } catch (\Exception $e) {
            $this->securityLogger->logSecurityEvent(
                "Error creating plan: " . $e->getMessage(),
                'error'
            );

            // Fallback: simple plan with standard planner
            return $this->createFallbackPlan($intent, $query);
        }
    }

    /**
     * SubTaskPlanner selector: Chooses appropriate planner
     * 
     * @param array &$intent Intent classification result (passed by reference to preserve modifications)
     * @param string $query User query
     * @return object Selected SubTaskPlanner instance
     */
    private function selectSubTaskPlanner(array &$intent, string $query): object
    {
        $intentType = $intent['type'] ?? 'analytics';
        $confidence = $intent['confidence'] ?? 0.5;

        // Handle hybrid queries with decomposition
        if ($intentType === 'hybrid' || ($intent['is_hybrid'] ?? false)) {
            $subTypes = $intent['sub_types'] ?? [];
            $this->securityLogger->logSecurityEvent(
                "HYBRID QUERY DETECTED - Query: " . substr($query, 0, 100) . 
                " | Sub-types: " . json_encode($subTypes),
                'info',
                [
                    'query' => $query,
                    'intent_type' => $intentType,
                    'sub_types' => $subTypes,
                    'is_hybrid' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            );
            
            // Decompose query into sub-queries using Pure LLM approach
            $decomposer = new \ClicShopping\AI\CoreAI\Planning\SubTaskPlanning\HybridQueryDecomposer(
                $this->debug,
                $this->securityLogger
            );
            
            $context = [
                'translated_query' => $intent['translated_query'] ?? null,
                'resolved_query' => $intent['resolved_query'] ?? null,
            ];
	    
            $subQueries = $decomposer->decompose($query, $intent, $context);
            
            // Store sub-queries in intent for plan creation
            $intent['sub_queries'] = $subQueries;
            
            
            $this->securityLogger->logSecurityEvent(
                "Hybrid query decomposed into " . count($subQueries) . " sub-queries",
                'info',
                [
                    'total_sub_queries' => count($subQueries),
                    'sub_queries' => $subQueries,
                    'original_query' => $query
                ]
            );
            
            // Log each sub-query individually for detailed tracking
            foreach ($subQueries as $index => $subQuery) {
                $this->securityLogger->logSecurityEvent(
                    "Sub-query " . ($index + 1) . "/" . count($subQueries) . 
                    " - Type: " . ($subQuery['type'] ?? 'unknown') . 
                    " | Text: " . substr($subQuery['text'] ?? '', 0, 100),
                    'info',
                    [
                        'sub_query_index' => $index,
                        'sub_query_type' => $subQuery['type'] ?? 'unknown',
                        'sub_query_text' => $subQuery['text'] ?? '',
                        'confidence' => $subQuery['confidence'] ?? null,
                        'is_fallback' => $subQuery['is_fallback'] ?? false
                    ]
                );
            }
            
            // Return standard planner (it will create steps from sub_queries)
            return $this->subTaskPlanners['standard'];
        }

        // For semantic queries, use semantic planner directly
        // Note: Intent type can be 'semantic' or 'semantic_search'
        if ($intentType === 'semantic' || $intentType === 'semantic_search') {
            if ($this->debug) {
                $this->securityLogger->logSecurityEvent(
                    "Routing to semantic_search planner for intent type: {$intentType}",
                    'info'
                );
            }
            return $this->subTaskPlanners['semantic_search'];
        }

        // For web_search queries, use web search planner
        if ($intentType === 'web_search' || $intentType === 'web') {
            if ($this->debug) {
                $this->securityLogger->logSecurityEvent(
                    "Routing to web_search planner for intent type: {$intentType}",
                    'info'
                );
            }
            return $this->subTaskPlanners['web_search'];
        }

        // For analytics queries, test specialized planners
        if ($intentType === 'analytics') {
            // Test in order of specificity
            $plannersToTest = [
                'competitor_analysis',  // Most specific
                'pattern_analysis',     // Specific
                'analytics'             // Basic analytics catch-all (handles COUNT, SUM, AVG, etc.)
            ];

            foreach ($plannersToTest as $plannerKey) {
                $planner = $this->subTaskPlanners[$plannerKey] ?? null;
                if ($planner === null) {
                    continue;
                }
                if ($planner->canHandle($query)) {
                    if ($this->debug) {
                        $this->securityLogger->logSecurityEvent(
                            "Selected analytics planner: {$plannerKey} for query: " . substr($query, 0, 100),
                            'info'
                        );
                    }
                    return $planner;
                }
            }
        }

        // Fallback: standard planner
        return $this->subTaskPlanners['standard'];
    }

    /**
     * Gets SubTaskPlanner name for logging
     *
     * @param object $planner SubTaskPlanner instance
     * @return string Planner name
     */
    private function getSubTaskPlannerName(object $planner): string
    {
        foreach ($this->subTaskPlanners as $name => $instance) {
            if ($instance === $planner) {
                return $name;
            }
        }
        return 'unknown';
    }

    /**
     * Updates planning statistics
     * 
     * @param object $planner SubTaskPlanner used
     * @param int $stepsCount Number of steps created
     */
    private function updatePlanningStats(object $planner, int $stepsCount): void
    {
        $plannerName = $this->getSubTaskPlannerName($planner);

        $this->planningStats['total_plans_created']++;
        $this->planningStats['total_steps_planned'] += $stepsCount;

        if (!isset($this->planningStats['planner_usage'][$plannerName])) {
            $this->planningStats['planner_usage'][$plannerName] = 0;
        }
        $this->planningStats['planner_usage'][$plannerName]++;

        // Metrics for monitoring
        $avgSteps = $this->planningStats['total_plans_created'] > 0 ?
            round($this->planningStats['total_steps_planned'] / $this->planningStats['total_plans_created'], 2) : 0;

        $this->collector->gauge('plans_created', $this->planningStats['total_plans_created']);
        $this->collector->gauge('avg_steps', $avgSteps);
    }

    /**
     * Analyzes query complexity (method kept for compatibility)
     * 
     * @param array $intent Intent classification result
     * @param string $query User query
     * @return array Complexity analysis with score and level
     */
    private function analyzeComplexity(array $intent, string $query): array
    {
        $score = 0;
        $factors = [];

        // Translate query to English for multilingual analysis
        // not used : Delete ?
        $translatedQuery = SemanticAgent::translateToEnglish($query, 80);

        // Factor 1: Hybrid query
        if ($intent['is_hybrid'] ?? false) {
            $score += 3;
            $factors[] = 'hybrid_query';
        }

        // Factor 2: Conversational context required
        if ($intent['requires_context'] ?? false) {
            $score += 2;
            $factors[] = 'requires_context';
        }

        // Factor 3: Multiple entities
        $entityCount = count($intent['metadata']['entities'] ?? []);
        if ($entityCount > 1) {
            $score += $entityCount;
            $factors[] = "multiple_entities:{$entityCount}";
        }

        // Determine level
        $level = 'simple';
        if ($score >= 8) {
            $level = 'very_complex';
        } elseif ($score >= 5) {
            $level = 'complex';
        } elseif ($score >= 3) {
            $level = 'medium';
        }

        return [
            'score' => $score,
            'level' => $level,
            'factors' => $factors,
        ];
    }

    /**
     * Analyzes dependencies between steps (kept from original)
     * 
     * @param array $steps Array of TaskStep objects
     * @return array Dependency graph
     */
    private function analyzeDependencies(array $steps): array
    {
        $dependencies = [];

        foreach ($steps as $step) {
            $stepId = $step->getId();
            $dependsOn = $step->getMetadata()['depends_on'] ?? [];

            $dependencies[$stepId] = [
                'depends_on' => $dependsOn,
                'required_by' => [],
            ];
        }

        // Build inverse graph (required_by)
        foreach ($dependencies as $stepId => $data) {
            foreach ($data['depends_on'] as $dependencyId) {
                if (isset($dependencies[$dependencyId])) {
                    $dependencies[$dependencyId]['required_by'][] = $stepId;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Optimizes execution order of steps (kept from original)
     * 
     * @param array $steps Array of TaskStep objects
     * @param array $dependencies Dependency graph
     * @return array Optimized steps array
     */
    private function optimizeExecutionOrder(array $steps, array $dependencies): array
    {
        // Topological sort to respect dependencies
        $sorted = [];
        $visited = [];
        $temp = [];

        foreach ($steps as $step) {
            if (!isset($visited[$step->getId()])) {
                $this->topologicalSort($step, $steps, $dependencies, $visited, $temp, $sorted);
            }
        }

        return array_reverse($sorted);
    }

    /**
     * Recursive topological sort (kept from original)
     * 
     * @param TaskStep $step Current step
     * @param array $allSteps All steps
     * @param array $dependencies Dependency graph
     * @param array $visited Visited steps
     * @param array $temp Temporary markers
     * @param array $sorted Sorted result
     */
    private function topologicalSort(
        TaskStep $step,
        array $allSteps,
        array $dependencies,
        array &$visited,
        array &$temp,
        array &$sorted
    ): void {
        $stepId = $step->getId();

        if (isset($temp[$stepId])) {
            // Cycle detected - ignore
            return;
        }

        if (isset($visited[$stepId])) {
            return;
        }

        $temp[$stepId] = true;

        // Visit dependencies first
        $dependsOn = $dependencies[$stepId]['depends_on'] ?? [];
        foreach ($dependsOn as $depId) {
            $depStep = $this->findStepById($allSteps, $depId);
            if ($depStep) {
                $this->topologicalSort($depStep, $allSteps, $dependencies, $visited, $temp, $sorted);
            }
        }

        unset($temp[$stepId]);
        $visited[$stepId] = true;
        $sorted[] = $step;
    }

    /**
     * Finds step by ID (kept from original)
     * 
     * @param array $steps Array of TaskStep objects
     * @param string $id Step ID to find
     * @return TaskStep|null Found step or null
     */
    private function findStepById(array $steps, string $id): ?TaskStep
    {
        foreach ($steps as $step) {
            if ($step->getId() === $id) {
                return $step;
            }
        }
        return null;
    }

    /**
     * Replans execution on failure
     * 
     * @param ExecutionPlan $failedPlan Failed plan
     * @param array $context Failure context
     * @return ExecutionPlan New plan
     */
    public function replan(ExecutionPlan $failedPlan, array $context): ExecutionPlan
    {
        $this->planningStats['replans_triggered']++;
        
        if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
                "Replan triggered for: " . $failedPlan->getQuery(),
                'info'
            );
        }
        
        // Create new plan based on failure context
        $query = $failedPlan->getQuery();
        $intent = $failedPlan->getIntent();
        
        // Create simplified plan on failure
        // Signature: createPlan(array $intent, string $query, array $context = [])
        $newPlan = $this->createPlan($intent, $query, $context);
        $newPlan->markAsReplan($failedPlan, $context);
        
        return $newPlan;
    }

    /**
     * Creates fallback plan on error
     * 
     * @param array $intent Intent classification result
     * @param string $query User query
     * @return ExecutionPlan Fallback plan
     */
    private function createFallbackPlan(array $intent, string $query): ExecutionPlan
    {
        $step = new TaskStep(
            'fallback_step',
            'fallback',
            $query,
            [
                'intent' => $intent,
                'is_fallback' => true,
                'is_final' => true,
            ]
        );

        $plan = new ExecutionPlan(
            $query,
            $intent,
            [$step],
            [],
            ['level' => 'simple', 'score' => 0, 'factors' => ['fallback']]
        );

        return $plan;
    }

    /**
     * Gets planning statistics with SubTaskPlanner details
     * 
     * @return array Statistics array
     */
    public function getStats(): array
    {
        $totalExecutions = $this->planningStats['successful_executions'] +
            $this->planningStats['failed_executions'];

        $successRate = $totalExecutions > 0 ? ($this->planningStats['successful_executions'] / $totalExecutions) * 100 : 0;

        return array_merge($this->planningStats, [
            'success_rate' => round($successRate, 2) . '%',
            'avg_steps_per_plan' => $this->planningStats['total_plans_created'] > 0 ?
                round($this->planningStats['total_steps_planned'] / $this->planningStats['total_plans_created'], 2) : 0,
            'subtask_planners' => array_map(function ($planner) {
                return $planner->getMetadata();
            }, $this->subTaskPlanners)
        ]);
    }

    /**
     * Marks plan as successful
     */
    public function markPlanSuccess(): void
    {
        $this->planningStats['successful_executions']++;
    }

    /**
     * Marks plan as failed
     */
    public function markPlanFailure(): void
    {
        $this->planningStats['failed_executions']++;
    }
}
