<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Analytics\Agent\AnalyticsAgent;
use ClicShopping\AI\Infrastructure\Monitoring\MetricsCollector;
use ClicShopping\AI\CoreAI\Planning\SubPlanExecutor\AnalyticsExecutor;
use ClicShopping\AI\CoreAI\Planning\SubPlanExecutor\ResultSynthesizer;
use ClicShopping\AI\CoreAI\Planning\SubPlanExecutor\SemanticExecutor;
use ClicShopping\AI\CoreAI\Planning\SubPlanExecutor\StepExecutor;
use ClicShopping\AI\CoreAI\Planning\SubPlanExecutor\ToolExecutor;
use ClicShopping\AI\Infrastructure\Metrics\CalculatorTool;
use ClicShopping\AI\DomainsAI\WebSearch\Cache\SearchCacheManager;
use ClicShopping\AI\DomainsAI\WebSearch\WebSearchFacade;
use ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter\WebSearchFormatter;
use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;

/**
 * PlanExecutor Class
 * Executes plans with step-by-step execution, parallel processing, result transmission, error handling, and result synthesis
 */

class PlanExecutor
{
  private SecurityLogger $securityLogger;
  private TaskPlanner $planner;
  private ?AnalyticsAgent $analyticsAgent = null;
  private ?MultiDBRAGManager $ragManager = null;
  private bool $debug;
  private string $userId;
  private int $languageId;
  private mixed $language;
  private mixed $conversationMemory = null;

  // Configuration
  private int $maxRetries = 2;
  private bool $enableParallelExecution = false; // For future implementation

  private ?CalculatorTool $calculatorTool = null;
  private mixed $webSearchFacade;
  private mixed $cacheManager;
  private mixed $collector;

  // 🆕 Refactored components
  private StepExecutor $stepExecutor;
  private AnalyticsExecutor $analyticsExecutor;
  private SemanticExecutor $semanticExecutor;
  private ToolExecutor $toolExecutor;
  private ResultSynthesizer $resultSynthesizer;

  /**
   * Constructor
   *
   * @param TaskPlanner $planner Planner instance
   * @param string $userId User identifier
   * @param int $languageId Language ID
   */
  public function __construct(TaskPlanner $planner, string $userId = 'system', int $languageId = 1)
  {
    $this->language = Registry::get('Language');
    $this->languageId = $languageId;
    $this->planner = $planner;
    $this->userId = $userId;
    $this->securityLogger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    // Initialize CalculatorTool if enabled
    if (defined('CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED') && CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED === 'True') {
      if (!Registry::exists('CalculatorTool')) {
        Registry::set('CalculatorTool', new CalculatorTool());
      }
      
      $this->calculatorTool = Registry::get('CalculatorTool');

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("PlanExecutor initialized with CalculatorTool", 'info');
      }
    }


    // Direct SerpApi verification (without Gpt dependency)
    if ($this->debug) {
      error_log("[INFO] PlanExecutor: Direct SerpApi verification...");
    }

    $serpApiKey = "";

    // 1. Environment variable
    $envKey = getenv('SERP_API_KEY');
    if (!empty($envKey)) {
      $serpApiKey = $envKey;
      if ($this->debug) {
        error_log("[INFO] PlanExecutor: Key found in environment variable");
      }
    }
    // 2. ClicShopping constant
    elseif (defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI')) {
      $constKey = CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI;
      if (!empty($constKey)) {
        $serpApiKey = $constKey;
        if ($this->debug) {
          error_log("[INFO] PlanExecutor: Key found in constant");
        }
      }
    }

    if (!empty($serpApiKey)) {
      if ($this->debug) {
        error_log("[INFO] SERPAPI Key loaded: " . substr($serpApiKey, 0, 10) . "...");
      }
      // Set environment variable for WebSearchTool
      putenv('SERP_API_KEY=' . $serpApiKey);
      if ($this->debug) {
        error_log("[INFO] PlanExecutor: putenv('SERP_API_KEY') set");
      }

      $hasValidKey = true;
    } else {
      if ($this->debug) {
        error_log("[error] SERPAPI Key not loaded - no source found");
      }
      
      $hasValidKey = false;
    }

    if ($hasValidKey) {
      try {
        if (!Registry::exists('webSearchFacade')) {
          Registry::set('webSearchFacade', new WebSearchFacade());
        }
	
        $this->webSearchFacade = Registry::get('webSearchFacade');

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent("WebSearchFacade initialized successfully", 'info');
        }
      } catch (Exception $e) {
        error_log("[warning] WebSearchFacade initialization failed: " . $e->getMessage());
        $this->webSearchFacade = null;

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent("WebSearchFacade initialization failed: " . $e->getMessage(), 'warning');
        }
      }
    } else {
      if ($this->debug) {
        error_log("[INFO] SerpApi not configured - Web search disabled");
      }

      $this->webSearchFacade = null;

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("SerpApi not configured - Web search disabled", 'info');
      }
    }

    $this->collector = new MetricsCollector();
    $this->cacheManager = new SearchCacheManager();

    // 🆕 Initialize refactored components
    $this->stepExecutor = new StepExecutor($this->debug);
    $this->analyticsExecutor = new AnalyticsExecutor($this->userId, $this->languageId, $this->debug);
    $this->semanticExecutor = new SemanticExecutor($this->userId, $this->languageId, $this->debug);
    $this->toolExecutor = new ToolExecutor($this->debug);
    $this->resultSynthesizer = new ResultSynthesizer($this->debug);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("PlanExecutor initialized with SubPlanExecutor components", 'info');
    }
  }
  
  /**
   * Set the conversation memory instance
   * Allows OrchestratorAgent to inject ConversationMemory
   * This is needed to pass it down to AnalyticsExecutor and AnalyticsAgent for contextual query resolution.
   *
   * @param mixed $conversationMemory ConversationMemory instance
   * @return void
   */
  public function setConversationMemory($conversationMemory): void
  {
    // Store locally for use in executeWebSearch() and executeSemanticSearch()
    $this->conversationMemory = $conversationMemory;
    
    // Pass to AnalyticsExecutor which will pass it to AnalyticsAgent
    $this->analyticsExecutor->setConversationMemory($conversationMemory);
    
    // Also pass to SemanticExecutor if needed in the future
    // $this->semanticExecutor->setConversationMemory($conversationMemory);
    
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("ConversationMemory set on PlanExecutor and propagated to executors", 'info');
    }
  }

  /**
   * Exécute un plan d'exécution
   * 
   * - Continues execution even if some steps fail
   * - Collects successful results
   * - Returns partial results with failure information
   *
   * @param ExecutionPlan $plan Plan à exécuter
   * @return array Résultat de l'exécution
   */
  public function execute(ExecutionPlan $plan): array
  {
    // 🔍 TRACE: Log entry to verify this method is called
    $this->securityLogger->logSecurityEvent(
      "🚀 PlanExecutor.execute() CALLED - Plan has " . count($plan->getSteps()) . " steps",
      'info'
    );

    $startTime = microtime(true);
    $this->collector->startTimer('plan_execution');

    try {
      $plan->start();

      if ($this->debug) {
        $stepCount = count($plan->getSteps());
        $this->securityLogger->logSecurityEvent("Starting plan execution: {$stepCount} steps", 'info');
	
        if ($this->debug) {
          error_log("[INFO : TIME] [PERF] PlanExecutor: Starting execution of {$stepCount} steps");
        }
      }

      // Exécuter les étapes
      $retryCount = 0;
      $currentPlan = $plan;

      while ($retryCount <= $this->maxRetries) {
        try {
          // Déléguer l'exécution des étapes au StepExecutor
          $stepsStart = microtime(true);
          
          $this->stepExecutor->executeSteps($currentPlan, function ($step, $plan) {
            return $this->executeStepByType($step, $plan);
          });
          
          if ($this->debug) {
            error_log("[INFO : TIME]️ [PERF] PlanExecutor: executeSteps took " . round((microtime(true) - $stepsStart), 2) . "s");
          }

          $allResults = $currentPlan->getAllStepResults();
          $successfulResults = array_filter($allResults, function($result) {
            return !isset($result['failed']) || $result['failed'] !== true;
          });
          
          $failedResults = array_filter($allResults, function($result) {
            return isset($result['failed']) && $result['failed'] === true;
          });

          // If we have at least some successful results, synthesize them
          if (!empty($successfulResults) || $currentPlan->isComplete()) {
            $synthesizeStart = microtime(true);
            
            // Log step results before synthesis
            $allStepResults = $currentPlan->getAllStepResults();
	    
            if ($this->debug) {
              error_log("[PlanExecutor::execute] Step results count BEFORE synthesizeResults: " . count($allStepResults));
            }

            foreach ($allStepResults as $stepId => $stepResult) {
              if (is_array($stepResult)) {
                if ($this->debug) {
                  error_log("[PlanExecutor::execute] Step {$stepId}: type=" . ($stepResult['type'] ?? 'NO TYPE') . ", has text_response=" . (isset($stepResult['text_response']) ? 'YES' : 'NO'));
                }
              }
            }
            
            $finalResult = $this->synthesizeResults($currentPlan);

            if ($this->debug) {
              error_log("[INFO : TIME] [PERF] PlanExecutor: synthesizeResults took " . round((microtime(true) - $synthesizeStart), 2) . "s");
            }
            
            // synthesizeResults() returns an array, extract text_response for complete()
            $textResponse = is_array($finalResult) ? ($finalResult['text_response'] ?? json_encode($finalResult)) : $finalResult;
            $currentPlan->complete($textResponse);
            $this->planner->markPlanSuccess();

            $executionTime = microtime(true) - $startTime;
            $currentPlan->setExecutionTime($executionTime);

            $this->collector->recordHistogram('execution_time', microtime(true) - $startTime);

            $hasFailures = !empty($failedResults);
            
            $this->securityLogger->logSecurityEvent(
                "EXECUTION COMPLETE - Status: " . ($hasFailures ? 'PARTIAL SUCCESS' : 'SUCCESS') . 
                " | Steps: " . count($successfulResults) . "/" . count($allResults) . 
                " | Time: " . round($executionTime, 3) . "s",
                $hasFailures ? 'warning' : 'info',
                [
                    'execution_status' => $hasFailures ? 'partial_success' : 'success',
                    'total_steps' => count($allResults),
                    'successful_steps' => count($successfulResults),
                    'failed_steps' => count($failedResults),
                    'execution_time_seconds' => round($executionTime, 3),
                    'query' => $currentPlan->getQuery(),
                    'intent_type' => $currentPlan->getIntent()['type'] ?? 'unknown',
                    'is_hybrid' => isset($currentPlan->getIntent()['sub_queries']),
                    'has_partial_failures' => $hasFailures,
                    'failed_step_ids' => array_map(function($f) { return $f['step_id'] ?? 'unknown'; }, $failedResults),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            );
            
            $array_execute = [
              'success' => true,
              'result' => $finalResult,
              'plan' => $currentPlan,
              'execution_time' => $executionTime,
              'partial_failure' => $hasFailures, // Flag for partial failures
              'failed_steps' => $hasFailures ? array_values($failedResults) : [], //Failed step details
              'successful_steps' => count($successfulResults), // Count of successful steps
              'total_steps' => count($allResults), // Total step count
            ];

            if ($this->debug) {
              $this->securityLogger->logSecurityEvent('result', 'info', $array_execute);
              
              if ($hasFailures) {
                $this->securityLogger->logSecurityEvent(
                  "Plan completed with partial failures: " . count($successfulResults) . "/" . count($allResults) . " steps succeeded",
                  'warning',
                  [
                    'failed_steps' => array_map(function($f) { return $f['step_id'] ?? 'unknown'; }, $failedResults),
                  ]
                );
              }
              
              // 🆕 Debug: Check if source_attribution is in finalResult
              if ($this->debug) {
                error_log("[INFO] PlanExecutor: finalResult has source_attribution: " . (isset($finalResult['source_attribution']) ? 'YES' : 'NO'));
              }

              if (isset($finalResult['source_attribution'])) {
                if ($this->debug) {
                  error_log("   Source type: " . ($finalResult['source_attribution']['source_type'] ?? 'N/A'));
                }
              }
            }

            return $array_execute;
          }

          break;
        } catch (\Exception $e) {
          if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
              "Plan execution failed (attempt " . ($retryCount + 1) . "): " . $e->getMessage(),
              'warning'
            );
          }

          // Tenter une replanification
          if ($retryCount < $this->maxRetries) {
            $currentPlan = $this->planner->replan($currentPlan, [
              'error' => $e->getMessage(),
              'failed_step' => $this->getLastFailedStep($currentPlan),
            ]);
            $retryCount++;

            $this->collector->increment('executions_failed');
            throw $e;
          }
        } finally {
          $this->collector->stopTimer('plan_execution');
        }
      }

      // Si on arrive ici sans avoir complété, c'est un échec
      throw new \Exception("Plan execution incomplete after {$retryCount} retries");
    } catch (\Exception $e) {
      $plan->fail($e->getMessage());
      $this->planner->markPlanFailure();

      $executionTime = microtime(true) - $startTime;

      $this->securityLogger->logSecurityEvent(
        "EXECUTION FAILED - Error: " . $e->getMessage() . 
        " | Time: " . round($executionTime, 3) . "s",
        'error',
        [
          'execution_status' => 'failed',
          'error_message' => $e->getMessage(),
          'exception_type' => get_class($e),
          'execution_time_seconds' => round($executionTime, 3),
          'query' => $plan->getQuery(),
          'intent_type' => $plan->getIntent()['type'] ?? 'unknown',
          'is_hybrid' => isset($plan->getIntent()['sub_queries']),
          'total_steps' => count($plan->getSteps()),
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      $this->securityLogger->logSecurityEvent(
        "Plan execution failed: " . $e->getMessage(),
        'error'
      );

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'plan' => $plan,
        'execution_time' => $executionTime,
      ];
    }
  }

  /**
   * Exécute une étape selon son type
   * 
   * - Catches exceptions and marks step as failed
   * - Returns error result instead of throwing
   *
   * @param TaskStep $step Étape à exécuter
   * @param ExecutionPlan $plan Plan parent
   * @return mixed Résultat de l'exécution
   */
  private function executeStepByType(TaskStep $step, ExecutionPlan $plan)
  {
    try {
      $step->start();

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Executing step: {$step->getId()} ({$step->getType()})",
          'info'
        );
      }

      // Prepare context
      $context = [
        'plan_intent' => $plan->getIntent(),
        'previous_results' => $plan->getAllStepResults(),
        'query' => $plan->getQuery(),
      ];
      
      //  Log plan intent to understand what's being passed
      if ($this->debug) {
        error_log("[INFO PlanExecutor::executeStepByType] plan_intent: " . json_encode($plan->getIntent()));
      }

      // Exécuter selon le type
      $result = null;
      switch ($step->getType()) {
        case 'analytics_query':
          $result = $this->executeAnalyticsQuery($step, $context);
          break;

        case 'semantic_search':
          $result = $this->executeSemanticSearch($step, $context);
          break;

        case 'calculator':
          $result = $this->executeCalculator($step, $context);
          break;

        case 'web_search':
        case 'web': // Backward compatibility (QueryClassifier normalizes web_search → web)
          $result = $this->executeWebSearch($step, $context);
          break;

        case 'domain_tool':
          $result = $this->executeDomainTool($step, $context);
          break;

        default:
          throw new \Exception("Unknown step type: {$step->getType()}");
      }

      // Marquer comme complété
      $step->complete($result);
      $plan->setStepResult($step->getId(), $result);

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Step completed: {$step->getId()}",
          'info'
        );
      }

      return $result;

    } catch (\Exception $e) {
      $step->fail($e->getMessage());

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Step failed: {$step->getId()} - {$e->getMessage()}",
          'error',
          [
            'step_type' => $step->getType(),
            'description' => $step->getDescription(),
            'exception' => get_class($e),
          ]
        );
      }

      $errorResult = [
        'success' => false,
        'error' => $e->getMessage(),
        'step_id' => $step->getId(),
        'step_type' => $step->getType(),
        'failed' => true,
      ];
      
      $plan->setStepResult($step->getId(), $errorResult);
      
      return $errorResult;
    }
  }

  /**
   * Exécute une requête analytique
   * 🆕 REFACTORED: Délègue à AnalyticsExecutor
   * 🆕 NEW (2026-05-07): Make Analytics optional when target_site specified
   */
  private function executeAnalyticsQuery(TaskStep $step, array $context): array
  {
    if ($this->debug) {
      error_log(str_repeat("-", 100));
      error_log("[INFO] PlanExecutor.executeAnalyticsQuery() CALLED");
      error_log("[INFO] -" . str_repeat("-", 99));
      error_log("[INFO] Step ID: " . $step->getId());
      error_log("[INFO] Step Type: " . $step->getType());
      error_log("[INFO] Step Description: " . $step->getDescription());
    }  
    // Try to get sub_query from metadata
    $subQuery = $step->getMeta('sub_query', null);

    if ($this->debug) {
      error_log("[INFO] sub_query from metadata: " . ($subQuery ?? 'NULL'));
    }
    // Fallback to description
    $query = $step->getMeta('sub_query', $step->getDescription());
    if ($this->debug) {
      error_log("[INFO] Final query (after fallback): '{$query}'");
      error_log("[INFO] Query length: " . strlen($query));
      error_log("[INFO] Query is empty: " . (empty($query) ? 'YES' : 'NO'));
    
      if (empty($query)) {
      error_log("[ERROR] WARNING: Query is EMPTY in PlanExecutor!");
      error_log("[ERROR] This means either:");
      error_log("[ERROR]   1. sub_query metadata is not set");
      error_log("[ERROR]   2. step description is empty");
      error_log("[ERROR]  3. Both are empty");
      }
    }
    
    if ($this->debug) {
      error_log("[INFO] Calling AnalyticsExecutor.executeAnalyticsQuery()...");
      error_log("[INFO] -" . str_repeat("-", 99) . "\n");
    }
    
    $result = $this->analyticsExecutor->executeAnalyticsQuery($query, $context);

    // Check if Analytics returned empty results
    $analyticsEmpty = empty($result['data']) || 
                      empty($result['results']) ||
                      (isset($result['success']) && !$result['success']);
    
    // Check if target_site is specified in context
    $hasTargetSite = !empty($context['plan_intent']['target_site'] ?? null);

    if ($hasTargetSite && $analyticsEmpty) {
      // Mark Analytics as optional failure (don't block execution)
      $result['optional'] = true;
      $result['reason'] = 'product_not_found_but_target_site_specified';
      $result['continue_execution'] = true;
      
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Analytics returned no results, but target_site specified - marking as optional failure",
          'info',
          [
            'target_site' => $context['plan_intent']['target_site'],
            'query' => $context['query'] ?? 'unknown',
          ]
        );
      }
    }
    
    return $result;
  }

  /**
   * Execute a semantic search
   * Delegates to SemanticExecutor which handles the fallback chain: ConversationMemory → Documents → LLM → Web
   * 
   * @param TaskStep $step Step to execute
   * @param array $context Execution context
   * @return array Semantic search result
   */
  private function executeSemanticSearch(TaskStep $step, array $context): array
  {
    $query = $step->getMeta('sub_query', $step->getDescription());

    // Add last_entity to context for semantic query enrichment
    if ($this->conversationMemory !== null) {
      try {
        $lastEntity = $this->conversationMemory->getLastEntity();
        if ($lastEntity !== null) {
          $context['last_entity'] = $lastEntity;
          
          if ($this->debug) {
            $entityName = $lastEntity['name'] ?? ($lastEntity['id'] ?? 'unknown');
            $this->securityLogger->logSecurityEvent(
              "Added last_entity to semantic search context: {$entityName}",
              'info'
            );
          }
        }
      } catch (\Exception $e) {
        // Don't fail on context enrichment errors - just log and continue
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Error adding last_entity to semantic search context: " . $e->getMessage(),
            'warning'
          );
        }
      }
    }

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "🔄 PlanExecutor.executeSemanticSearch() - Delegating to SemanticExecutor",
        'info'
      );
    }

    // Use SemanticExecutor which has the fallback chain
    $result = $this->semanticExecutor->executeSemanticSearch($query, $context);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "✅ SemanticExecutor returned: " . json_encode([
          'success' => $result['success'] ?? false,
          'source' => $result['source'] ?? 'unknown',
          'has_response' => !empty($result['text_response'])
        ]),
        'info'
      );
    }

    return $result;
  }

  /**
   * Execute inventory forecast tool.
   */
  private function executeDomainTool(TaskStep $step, array $context): array
  {
    $meta = $step->getMeta();
    $action = (string)($meta['action'] ?? '');
    $params = is_array($meta['action_params'] ?? null) ? $meta['action_params'] : [];

    if ($action === '') {
      return [
        'type' => 'domain_tool',
        'success' => false,
        'error' => 'Missing action for domain tool',
        'text_response' => 'Missing action for domain tool.'
      ];
    }

    $domainApp = \ClicShopping\AI\DomainsAI\DomainRegistry::getInstance()->getActiveApp();
    if (!$domainApp || !method_exists($domainApp, 'getToolRegistryClass')) {
      return [
        'type' => 'domain_tool',
        'success' => false,
        'error' => 'Domain tool registry not available',
        'text_response' => 'Domain tool registry not available.'
      ];
    }

    $registryClass = $domainApp->getToolRegistryClass();
    if (!$registryClass || !class_exists($registryClass) || !method_exists($registryClass, 'executeAction')) {
      return [
        'type' => 'domain_tool',
        'success' => false,
        'error' => 'Domain tool registry missing executeAction',
        'text_response' => 'Domain tool registry missing executeAction.'
      ];
    }

    return $registryClass::executeAction($action, $params, $context);
  }

  /**
   * Execute a calculation
   * 
   * @param TaskStep $step Step to execute
   * @param array $context Execution context
   * @return array Calculation result
   */
  private function executeCalculator(TaskStep $step, array $context): array
  {
    if (!$this->calculatorTool) {
      throw new \Exception("Calculator tool not available");
    }

    $expression = $step->getMeta('expression', $step->getDescription());
    $result = $this->calculatorTool->calculate($expression);

    return [
      'type' => 'calculator_result',
      'expression' => $expression,
      'result' => $result,
    ];
  }

  /**
   * Exécute une recherche web via SERAPI
   * 
   * @param TaskStep $step Step to execute
   * @param array $context Execution context
   * @return array Web search results in standard format
   */
  private function executeWebSearch(TaskStep $step, array $context): array
  {
    if (!$this->webSearchFacade) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Web search facade not available - returning empty result",
          'warning'
        );
      }
      
      return [
        'type' => 'web_search_response',
        'success' => false,
        'error' => 'Web search facade not configured',
        'query' => $step->getDescription(),
        'results' => [],
        'text_response' => 'Web search is not available. Please configure SERAPI key.',
      ];
    }

    $query = $step->getMeta('search_query', $step->getDescription());
    
    // Skip query enrichment for price_comparison queries
    // For price_comparison, SubTaskPlannerWebSearch already extracts the clean product name
    // from intent, so we should NOT enrich it again with entity context to avoid duplication
    // Example: query="iPhone 17 Pro" should stay as-is, not become "iPhone 17 Pro iPhone 17 Pro"
    $skipEnrichment = false;
    
    // Check both 'intent' and 'intent_type' fields (different decomposers use different field names)
    $intentType = $context['plan_intent']['intent'] ?? $context['plan_intent']['intent_type'] ?? null;
    
    if ($intentType === 'price_comparison') {
      $skipEnrichment = true;
      
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Skipping query enrichment for price_comparison (query already contains clean product name)",
          'info'
        );
      }
    }
    
    //Enrich query with last_entity context for follow-up queries
    // This allows web search to use context from previous analytics queries
    // SKIP enrichment for price_comparison queries (they already have clean product names)
    
    if (!$skipEnrichment && $this->conversationMemory !== null) {
      try {
        $lastEntity = $this->conversationMemory->getLastEntity();
        
        if ($lastEntity !== null) {
          // Only use entity name, NOT entity ID
          // Using entity ID (e.g., "103") in web search queries causes Google to return no results
          // We MUST have the entity name (e.g., "iPhone 17 Pro") for meaningful web searches
          $entityName = $lastEntity['name'] ?? null;
          $entityType = $lastEntity['type'] ?? 'entity';
          
          // Only enrich if we have a valid entity NAME (not just ID)
          if ($entityName !== null && !empty(trim($entityName))) {
            // Enrich the query through the domain-agnostic registry: each
            // Apps/AI/{Domain} can register a QueryEnricher that injects the
            $enrichContext = [
              'entity_name' => $entityName,
              'entity_type' => $entityType,
              'intent_type' => $intentType,
            ];

            foreach (WebSearchEngineRegistry::getInstance()->getQueryEnrichers() as $enricher) {
              $query = $enricher->enrich($query, $enrichContext);
            }

            if ($this->debug) {
              $this->securityLogger->logSecurityEvent(
                "Enriched web search query with last_entity: {$entityName} ({$entityType})",
                'info'
              );
            }
          } else {
            // Log when enrichment is skipped due to missing entity name
            if ($this->debug) {
              $entityId = $lastEntity['id'] ?? 'unknown';
              $this->securityLogger->logSecurityEvent(
                "Skipped query enrichment: entity name not available (ID: {$entityId}, Type: {$entityType})",
                'info'
              );
            }
          }
        }
      } catch (\Exception $e) {
        // Don't fail on context enrichment errors - just log and continue
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Error enriching web search query with last_entity: " . $e->getMessage(),
            'warning'
          );
        }
      }
    }
    
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Executing web search for query: {$query}",
        'info'
      );
    }

    try {
      // Prepare options for WebSearchFacade
      $options = [
        'max_results' => $step->getMeta('max_results', 10),
        'language_id' => $this->languageId,
        'user_id' => $this->userId,
      ];
      
      // Call WebSearchFacade (unified engine)
      $searchResult = $this->webSearchFacade->search($query, $options);
      
      // CRITICAL LOGGING: Log what WebSearchFacade returned
      if ($this->debug) {
        error_log("[PlanExecutor::executeWebSearch] searchResult keys: " . implode(', ', array_keys($searchResult)));
      }

      if ($this->debug) {
        if (isset($searchResult['metadata']['mode'])) {
          error_log("[PlanExecutor::executeWebSearch] Mode returned by WebSearchFacade: " . $searchResult['metadata']['mode']);
        } else {
          error_log("[PlanExecutor::executeWebSearch] NO MODE in searchResult metadata");
        }
      }

      // Check if search was successful
      if (!isset($searchResult['success']) || $searchResult['success'] === false) {
        $errorMsg = $searchResult['metadata']['error'] ?? $searchResult['error'] ?? 'Unknown error';

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Web search failed: {$errorMsg}",
            'error'
          );
        }

        // Return success: true so ResultValidator accepts the step and the user sees the error message
        return [
          'type' => 'web_search_response',
          'success' => true,
          'error' => $errorMsg,
          'query' => $query,
          'results' => [],
          'text_response' => "<div class='alert alert-warning'>⚠️ " . htmlspecialchars($errorMsg) . "</div>",
          'source_attribution' => [
            'source_type' => 'web_search',
            'primary_source' => 'Web Search',
            'source_icon' => '🌐',
          ],
        ];
      }

      // WebSearchFacade returns shopping_results and organic_results, not items
      // Merge both into a single items array for backward compatibility
      $items = [];
      
      // Add shopping results first (higher priority)
      if (!empty($searchResult['shopping_results'])) {
        foreach ($searchResult['shopping_results'] as $result) {
          $items[] = [
            'title' => $result['title'] ?? '',
            'snippet' => $result['snippet'] ?? '',
            'link' => $result['product_link'] ?? $result['link'] ?? '',
            'source' => $result['source'] ?? '',
            'price' => $result['price'] ?? $result['extracted_price'] ?? null,
            'thumbnail' => $result['thumbnail'] ?? null,
            'type' => 'shopping'
          ];
        }
      }
      
      // Add organic results
      if (!empty($searchResult['organic_results'])) {
        foreach ($searchResult['organic_results'] as $result) {
          $items[] = [
            'title' => $result['title'] ?? '',
            'snippet' => $result['snippet'] ?? '',
            'link' => $result['link'] ?? '',
            'source' => $result['source'] ?? '',
            'price' => null,
            'type' => 'organic'
          ];
        }
      }
      
      // Format results for display
      $formattedResults = [];

      foreach ($items as $item) {
        $formattedResults[] = [
          'title' => $item['title'] ?? '',
          'snippet' => $item['snippet'] ?? '',
          'link' => $item['link'] ?? '',
          'source' => $item['source'] ?? '',
          'price' => $item['price'] ?? null,
        ];
      }

      // Extract AI Overview data from search result
      $aiOverview = $searchResult['ai_overview'] ?? null;
      $hasAiOverview = $searchResult['metadata']['has_ai_overview'] ?? false;
      
      // Determine mode from metadata
      // Use 'mode_type' instead of 'mode' (WebSearchExecutor uses 'mode_type')
      $mode = $searchResult['metadata']['mode_type'] ?? 'A';
      
      //Log mode detection**
      if ($this->debug) {
        error_log("[[INFO] PlanExecutor::executeWebSearch] Detected mode: " . $mode);
        error_log("[INFO PlanExecutor::executeWebSearch] searchResult keys: " . implode(', ', array_keys($searchResult)));
       
        if (isset($searchResult['metadata'])) {
          error_log("[INFO PlanExecutor::executeWebSearch] metadata keys: " . implode(', ', array_keys($searchResult['metadata'])));
        }
      }
      
      // Use original user query for display (not the enriched/translated internal query)
      $displayQuery = $context['plan_intent']['original_query'] ?? $query;

      // Prepare formatter data
      $formatterData = [
        'type' => 'web_search_response',
        'query' => $displayQuery,
        'ai_overview' => $aiOverview,
        'metadata' => $searchResult['metadata'] ?? [],
      ];

      // Forward fields injected by WebSearchResultEnhancers (registered by
      // any Apps/AI/{Domain}/). The formatter consumes them by key name —
      if (!empty($searchResult['market_analysis'])) {
        $formatterData['market_analysis'] = $searchResult['market_analysis'];
      }
      
      // Add shopping_results for Mode B and Mode D (use original shopping_results from WebSearchFacade)
      // Check for 'mode_b_google_shopping' instead of 'B'
      // Also pass shopping_results for Hybrid mode
      // Also pass shopping_results for Mode D (domain shopping engine)
      
      if (($mode === 'B' || $mode === 'mode_b_google_shopping' || $mode === 'mode_d_amazon_shopping' || $mode === 'D' || $mode === 'hybrid') && !empty($searchResult['shopping_results'])) {
        $formatterData['shopping_results'] = $searchResult['shopping_results'];

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Mode " . $mode . " detected - passing " . count($searchResult['shopping_results']) . " shopping_results to formatter",
            'info'
          );
        }
      } elseif ($mode === 'mode_e_google_trends' && !empty($searchResult['trends_data'])) {
        $formatterData['trends_data'] = $searchResult['trends_data'];

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Mode E (Google Trends) detected - passing trends_data (" . ($searchResult['trends_data']['point_count'] ?? 0) . " points) to formatter",
            'info'
          );
        }
      } else {
        // For non-shopping modes (Mode A, Mode C without shopping), include web results
        $formatterData['results'] = $formattedResults;
      }

      // Create text response using WebSearchFormatter
      $formatter = new WebSearchFormatter($this->debug);
      
      // Log formatterData keys before calling format()
      if ($this->debug) {
        error_log("[INFO PlanExecutor::executeWebSearch] formatterData keys BEFORE format(): " . implode(', ', array_keys($formatterData)));

        if (isset($formatterData['shopping_results'])) {
          error_log("[INFO PlanExecutor::executeWebSearch] shopping_results count: " . count($formatterData['shopping_results']));
        }
      }
      $formatted = $formatter->format($formatterData);
      
      // Log formatted result keys
      if ($this->debug) {
        error_log("[INFO PlanExecutor::executeWebSearch] formatted result keys: " . implode(', ', array_keys($formatted)));
      }

      $textResponse = $formatted['content'] ?? '';
      
      // Log textResponse status
      if ($this->debug) {
        error_log("[INFO PlanExecutor::executeWebSearch] textResponse is empty: " . (empty($textResponse) ? 'YES' : 'NO'));

        if (!empty($textResponse)) {
          error_log("[INFO PlanExecutor::executeWebSearch] textResponse length: " . strlen($textResponse));
          error_log("[INFO PlanExecutor::executeWebSearch] textResponse first 100 chars: " . substr($textResponse, 0, 100));
        }
      }
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Web search completed: " . count($formattedResults) . " results found" . 
          ($hasAiOverview ? " (with AI Overview)" : ""),
          'info'
        );
      }

      // Dynamic source attribution based on AI Overview presence
      $primarySource = $hasAiOverview ? 'Google AI Overview' : 'Web Search';
      $sourceIcon = $hasAiOverview ? '🤖' : '🌐';

      return [
        'type' => 'web_search_response',
        'success' => true,
        'query' => $query,
        'ai_overview' => $aiOverview,  // Include AI Overview in result structure
        'results' => $formattedResults,
        'total_results' => $searchResult['total_results'] ?? count($formattedResults),
        'text_response' => $textResponse,
        'metadata' => $searchResult['metadata'] ?? [],
        'cached' => $searchResult['cached'] ?? false,
        'cache_source' => $searchResult['cache_source'] ?? 'none',
        // Dynamic source_attribution based on AI Overview presence
        'source_attribution' => [
          'source_type' => 'web_search',
          'primary_source' => $primarySource,
          'source_icon' => $sourceIcon,
          'details' => [
            'has_ai_overview' => $hasAiOverview,  // Flag for AI Overview presence
            'url_count' => count($formattedResults),
            'cache_source' => $searchResult['cache_source'] ?? 'none',
            'cached' => $searchResult['cached'] ?? false,
          ],
          'confidence' => 0.7,
        ],
      ];

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Web search exception: " . $e->getMessage(),
        'error'
      );

      return [
        'type' => 'web_search_response',
        'success' => false,
        'error' => $e->getMessage(),
        'query' => $query,
        'results' => [],
        'text_response' => "Web search error: " . $e->getMessage(),
      ];
    }
  }


  /**
   * Synthesize final plan results
   * 🆕 REFACTORED: Delegates to ResultSynthesizer
   *
   * @param ExecutionPlan $plan Completed plan
   * @return array Synthesized final result
   */
  private function synthesizeResults(ExecutionPlan $plan): array
  {
    return $this->resultSynthesizer->synthesizeResults($plan);
  }

  /**
   * Get the last failed step
   * 
   * @param ExecutionPlan $plan Execution plan
   * @return TaskStep|null Last failed step, or null
   */
  public function getLastFailedStep(ExecutionPlan $plan): ?TaskStep
  {
    // Iterate through plan steps to find the last failed one
    $failedStep = null;

    foreach ($plan->getSteps() as $step) {
      if ($step->getStatus() === 'failed') {
        $failedStep = $step;
      }
    }

    return $failedStep;
  }

  /**
   * Enable/disable parallel execution
   * 
   * @param bool $enable Enable parallel execution
   * @return void
   */
  public function setEnableParallelExecution(bool $enable): void
  {
    $this->enableParallelExecution = $enable;
  }
}
