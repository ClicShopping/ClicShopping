<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Handler;

use ClicShopping\AI\InterfacesAI\HybridQueryHandlerInterface;
use ClicShopping\AI\CoreAI\Planning\TaskPlanner;
use ClicShopping\AI\CoreAI\Planning\PlanExecutor;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Config\DomainKeywordsLoader;

/**
 * HybridQueryHandler Class
 *
 * Handles hybrid queries that require multiple processing modes (semantic + analytics,
 * semantic + web search, etc.) by coordinating TaskPlanner and PlanExecutor.
 *
 * IMPORTANT DISTINCTION:
 * - HybridQueryHandler: Orchestration-level handler for hybrid queries
 *   Coordinates TaskPlanner, PlanExecutor, and result synthesis
 *   Location: Core/ClicShopping/AI/DomainsAI/Hybrid/Handler/
 *
 * - HybridQueryProcessor components: Component-level processors
 *   Used by QueryClassifier, QuerySplitter, ResultSynthesizer, etc.
 *   Location: Core/ClicShopping/AI/DomainsAI/Hybrid/Processor/
 *
 * Purpose:
 * - Extract hybrid query handling logic from OrchestratorAgent
 * - Enable proper separation of concerns
 * - Facilitate query decomposition via TaskPlanner
 * - Support plan execution via PlanExecutor
 * - Enable result synthesis from multiple sources
 * - Support LLM-based web search detection (domain-agnostic)
 *
 * Architecture Flow:
 * User Query → IntentAnalyzer → OrchestratorAgent → HybridQueryHandler
 *   → TaskPlanner → PlanExecutor → ResultSynthesizer → Final Result
 *
 * Hybrid Query Processing Strategy:
 * 1. Receive hybrid query with intent and context
 * 2. Use TaskPlanner to decompose query into sub-tasks
 * 3. Execute each sub-task with appropriate domain agent
 * 4. Synthesize results from all sub-tasks
 * 5. Force result type to 'hybrid' for consistency
 * 6. Return the result; conversation-memory storage is handled by the Orchestrator/MemoryManager,
 *    not by this handler
 *
 * Web Search Detection:
 * - MUST use LLM-based classification via IntentAnalyzer
 * - MUST NOT use hardcoded keyword lists in Core AI
 * - Domain-specific keywords loaded dynamically from Apps/AI/{Domain}/
 * - Supports multiple business domains (Ecommerce, HR, Finance, Trading)
 *
 * CRITICAL ARCHITECTURE RULE:
 * - Core AI MUST be domain-agnostic
 * - NO e-commerce keywords in Core/ClicShopping/AI/
 * - E-commerce keywords go in Apps/AI/Ecommerce/
 * - Core AI loads keywords dynamically from domain configuration
 */
 
class HybridQueryHandler implements HybridQueryHandlerInterface
{
  private TaskPlanner $taskPlanner;
  private PlanExecutor $planExecutor;
  private ?ConversationMemory $conversationMemory;
  private SecurityLogger $securityLogger;
  private DomainKeywordsLoader $domainKeywordsLoader;
  private bool $debug;
  private string $currentDomain = 'Ecommerce'; // Default domain, can be configured

  // Statistics tracking
  private int $totalQueries = 0;
  private int $successfulQueries = 0;
  private int $failedQueries = 0;
  private float $totalExecutionTime = 0.0;
  private int $totalPlanSteps = 0;
  private int $webSearchQueries = 0;
  private array $subTypeDistribution = [
    'semantic' => 0,
    'analytics' => 0,
    'web_search' => 0,
    'other' => 0
  ];
  private ?string $lastExecution = null;

  /**
   * Constructor
   *
   * @param TaskPlanner $taskPlanner Task planner for query decomposition
   * @param PlanExecutor $planExecutor Plan executor for plan execution
   * @param ConversationMemory|null $conversationMemory Conversation memory for storage
   * @param DomainKeywordsLoader|null $domainKeywordsLoader Domain keywords loader for dynamic keyword loading
   * @param bool $debug Enable debug logging
   * @param string $domain Current business domain (default: 'Ecommerce')
   */
  public function __construct(
    TaskPlanner $taskPlanner,
    PlanExecutor $planExecutor,
    ?ConversationMemory $conversationMemory = null,
    ?DomainKeywordsLoader $domainKeywordsLoader = null,
    bool $debug = false,
    string $domain = 'Ecommerce'
  ) {
    $this->taskPlanner = $taskPlanner;
    $this->planExecutor = $planExecutor;
    $this->conversationMemory = $conversationMemory;
    $this->domainKeywordsLoader = $domainKeywordsLoader ?? new DomainKeywordsLoader($debug);
    $this->debug = $debug;
    $this->currentDomain = $domain;
    $this->securityLogger = new SecurityLogger();
  }

  /**
   * Handle hybrid query using Actor-Critic approach
   *
   * Processes queries with multiple intents by:
   * 1. Using TaskPlanner to decompose the query into sub-tasks
   * 2. Executing each sub-task with appropriate domain agent
   * 3. Synthesizing results from all sub-tasks
   * 4. Forcing result type to 'hybrid' for consistency
   * 5. Returning the result (conversation-memory storage is handled by the
   *    Orchestrator/MemoryManager, not by this handler)
   *
   * This method replaces the deprecated HybridQueryProcessor and provides
   * a cleaner separation of concerns between orchestration and execution.
   *
   * @param string $queryToProcess Original user query
   * @param array $intent Intent analysis from IntentAnalyzer
   * @param array $context Query context (user, language, conversation, etc.)
   * @param float $startTime Query start time (microtime(true))
   * @return array Synthesized result with type forced to 'hybrid'
   * @throws \Exception If plan creation or execution fails
   */
  public function handleHybridQuery(
    string $queryToProcess,
    array $intent,
    array $context,
    float $startTime
  ): array {
    // Increment total queries counter
    $this->totalQueries++;

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'hybrid_query_start', [
        'query' => substr($queryToProcess, 0, 100),
        'intent_type' => $intent['type'] ?? 'unknown',
        'confidence' => $intent['confidence'] ?? 0,
        'sub_types' => $intent['sub_types'] ?? []
      ]);
    }

    try {
      // Step 1: Create execution plan using TaskPlanner
      $planStart = microtime(true);
      $plan = $this->taskPlanner->createPlan($intent, $queryToProcess, $context);

      $steps = $plan->getSteps();
      $stepCount = count($steps);
      $this->totalPlanSteps += $stepCount;

      // Track sub-type distribution
      foreach ($steps as $step) {
        $stepType = $step->getType();
        if (isset($this->subTypeDistribution[$stepType])) {
          $this->subTypeDistribution[$stepType]++;
        } else {
          $this->subTypeDistribution['other']++;
        }
      }

      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'hybrid_plan_created', [
          'step_count' => $stepCount,
          'step_types' => array_map(fn($step) => $step->getType(), $steps),
          'plan_time' => round((microtime(true) - $planStart) * 1000, 2) . 'ms'
        ]);
      }

      // Step 2: Execute plan using PlanExecutor
      $executeStart = microtime(true);
      $executionResult = $this->planExecutor->execute($plan);

      $executionTime = microtime(true) - $startTime;
      $this->totalExecutionTime += $executionTime;

      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'hybrid_plan_executed', [
          'execution_time' => round((microtime(true) - $executeStart) * 1000, 2) . 'ms',
          'total_time' => round($executionTime * 1000, 2) . 'ms',
          'success' => $executionResult['success'] ?? false,
          'result_type' => $executionResult['result']['type'] ?? 'unknown'
        ]);
      }

      // Check if execution was successful
      if (!$executionResult['success']) {
        throw new \Exception($executionResult['error'] ?? 'Plan execution failed');
      }

      // Extract the actual result
      $result = $executionResult['result'] ?? $executionResult;

      // CRITICAL: Force type to 'hybrid' for hybrid queries
      // The result may have type 'semantic_results' or 'analytics_response' from synthesis
      // but for hybrid queries, the type MUST be 'hybrid'
      $result['type'] = 'hybrid';

      // Ensure result has success key for QueryProcessor
      if (!isset($result['success'])) {
        $result['success'] = true;
      }

      // Ensure result has required keys
      if (!isset($result['intent'])) {
        $result['intent'] = $intent;
      }

      if (!isset($result['agent_used'])) {
        $result['agent_used'] = 'hybrid_orchestrator';
      }

      // Log the final type for debugging
      if ($this->debug) {
        error_log("[info] HybridQueryHandler: Forcing type to 'hybrid' (was: " . ($executionResult['result']['type'] ?? 'unknown') . ")");
      }

      // Update statistics
      $this->successfulQueries++;
      $this->lastExecution = date('c'); // ISO 8601 format

      // Track web search queries
      if ($this->requiresWebSearch($queryToProcess, $intent)) {
        $this->webSearchQueries++;
      }

      if ($this->debug) {
        error_log(sprintf(
          "[PERF] HybridQueryHandler: handle() returning after %.3fs",
          microtime(true) - $startTime
        ));
      }

      return $result;

    } catch (\Exception $e) {
      // Update failure statistics
      $this->failedQueries++;
      $this->lastExecution = date('c');

      // Error handling with detailed logging
      $this->securityLogger->logStructured('error', 'HybridQueryHandler', 'hybrid_query_failed', [
        'query' => substr($queryToProcess, 0, 100),
        'error' => $e->getMessage(),
        'trace' => substr($e->getTraceAsString(), 0, 500)
      ]);

      // Return error response
      return [
        'type' => 'error',
        'response' => 'Une erreur est survenue lors du traitement de votre requête hybride.',
        'error' => $e->getMessage(),
        'query' => $queryToProcess,
        'intent_type' => 'hybrid',
        'success' => false
      ];
    }
  }

  /**
   * Check if query requires web search
   *
   * Determines whether a query requires external web search based on
   * a multi-layered detection strategy:
   * 1. Primary: LLM-based intent classification via IntentAnalyzer
   * 2. Secondary: Dynamic keyword matching from domain configuration
   * 3. Fallback: Return false (no web search needed)
   *
   * CRITICAL ARCHITECTURE RULE:
   * - Core AI MUST be domain-agnostic
   * - NO hardcoded keyword lists in Core/ClicShopping/AI/
   * - Domain-specific keywords loaded dynamically from Apps/AI/{Domain}/
   * - LLM classification is PRIMARY detection method
   * - Keyword matching is SECONDARY (for performance optimization)
   *
   * Detection strategy:
   * 1. Primary: Check intent['requires_web_search'] from IntentAnalyzer (LLM-based)
   * 2. Secondary: Check sub_types for 'web_search'
   * 3. Tertiary: Check query against dynamic keywords from domain
   * 4. Fallback: Return false (no web search needed)
   *
   * Dynamic keyword loading:
   * - Keywords loaded from Apps/AI/{Domain}/Patterns/HybridPreFilter.php
   * - Cached for performance
   * - Graceful degradation if domain not found
   * - Logs which domain keywords are being used
   *
   * @param string $query User query to evaluate
   * @param array $intent Intent analysis from IntentAnalyzer
   * @return bool True if web search is required, false otherwise
   */
  public function requiresWebSearch(string $query, array $intent): bool
  {
    // Primary detection: Check intent['requires_web_search'] from IntentAnalyzer (LLM-based)
    if (isset($intent['requires_web_search']) && $intent['requires_web_search'] === true) {
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'web_search_required', [
          'query' => substr($query, 0, 100),
          'detection_method' => 'llm_intent_flag',
          'reason' => $intent['web_search_reason'] ?? 'Not specified',
          'priority' => 'primary'
        ]);
      }
      return true;
    }

    // Secondary detection: Check sub_types for 'web_search'
    if (isset($intent['sub_types']) && is_array($intent['sub_types'])) {
      if (in_array('web_search', $intent['sub_types'], true)) {
        if ($this->debug) {
          $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'web_search_required', [
            'query' => substr($query, 0, 100),
            'detection_method' => 'sub_types',
            'sub_types' => $intent['sub_types'],
            'priority' => 'secondary'
          ]);
        }
        return true;
      }
    }

    // Tertiary detection: Check query against dynamic keywords from domain
    // This is a performance optimization to avoid LLM calls for obvious web search queries
    $keywords = $this->domainKeywordsLoader->loadWebSearchKeywords($this->currentDomain);
    
    if (!empty($keywords)) {
      $queryLower = mb_strtolower($query, 'UTF-8');
      
      foreach ($keywords as $keyword) {
        $keywordLower = mb_strtolower($keyword, 'UTF-8');
        
        if (mb_strpos($queryLower, $keywordLower) !== false) {
          if ($this->debug) {
            $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'web_search_required', [
              'query' => substr($query, 0, 100),
              'detection_method' => 'dynamic_keyword_match',
              'matched_keyword' => $keyword,
              'domain' => $this->currentDomain,
              'priority' => 'tertiary',
              'note' => 'Performance optimization - keyword match before LLM call'
            ]);
          }
          return true;
        }
      }
      
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'keywords_checked', [
          'query' => substr($query, 0, 100),
          'domain' => $this->currentDomain,
          'keyword_count' => count($keywords),
          'matched' => false
        ]);
      }
    } else {
      // No keywords loaded - log warning
      if ($this->debug) {
        $this->securityLogger->logStructured('warning', 'HybridQueryHandler', 'no_keywords_loaded', [
          'domain' => $this->currentDomain,
          'note' => 'Falling back to LLM-based detection only'
        ]);
      }
    }

    // Fallback: No web search needed
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'web_search_not_required', [
        'query' => substr($query, 0, 100),
        'intent_type' => $intent['type'] ?? 'unknown',
        'detection_methods_tried' => ['llm_intent_flag', 'sub_types', 'dynamic_keywords']
      ]);
    }

    return false;
  }

  /**
   * Get hybrid query handler statistics
   *
   * Returns performance and usage statistics for the hybrid query handler.
   * Used for monitoring, optimization, and capacity planning.
   *
   * @return array Hybrid query handler statistics
   */
  public function getStats(): array
  {
    $avgExecutionTime = $this->totalQueries > 0
      ? $this->totalExecutionTime / $this->totalQueries
      : 0.0;

    $avgPlanSteps = $this->totalQueries > 0
      ? $this->totalPlanSteps / $this->totalQueries
      : 0.0;

    $errorRate = $this->totalQueries > 0
      ? $this->failedQueries / $this->totalQueries
      : 0.0;

    $cacheHitRate = 0.0; // TODO: Implement cache hit tracking

    return [
      'total_queries' => $this->totalQueries,
      'successful_queries' => $this->successfulQueries,
      'failed_queries' => $this->failedQueries,
      'avg_execution_time' => round($avgExecutionTime, 3),
      'avg_plan_steps' => round($avgPlanSteps, 2),
      'web_search_queries' => $this->webSearchQueries,
      'cache_hit_rate' => $cacheHitRate,
      'sub_type_distribution' => $this->subTypeDistribution,
      'error_rate' => round($errorRate, 3),
      'last_execution' => $this->lastExecution,
      'current_domain' => $this->currentDomain,
      'domain_keywords_loaded' => !empty($this->domainKeywordsLoader->loadWebSearchKeywords($this->currentDomain))
    ];
  }

  /**
   * Set current business domain
   *
   * Changes the current business domain for keyword loading.
   * Useful for multi-domain applications.
   *
   * @param string $domain Domain name (e.g., 'Ecommerce', 'HR', 'Finance', 'Trading')
   * @return void
   */
  public function setDomain(string $domain): void
  {
    $this->currentDomain = $domain;
    
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'HybridQueryHandler', 'domain_changed', [
        'new_domain' => $domain,
        'note' => 'Domain changed - keywords will be loaded from new domain'
      ]);
    }
  }

  /**
   * Get current business domain
   *
   * Returns the current business domain being used for keyword loading.
   *
   * @return string Current domain name
   */
  public function getCurrentDomain(): string
  {
    return $this->currentDomain;
  }

  /**
   * Get loaded keywords for current domain
   *
   * Returns the web search keywords loaded for the current domain.
   * Useful for debugging and monitoring.
   *
   * @return array Web search keywords
   */
  public function getLoadedKeywords(): array
  {
    return $this->domainKeywordsLoader->loadWebSearchKeywords($this->currentDomain);
  }

  /**
   * Get domain keywords loader
   *
   * Returns the DomainKeywordsLoader instance.
   * Useful for advanced configuration and testing.
   *
   * @return DomainKeywordsLoader Domain keywords loader instance
   */
  public function getDomainKeywordsLoader(): DomainKeywordsLoader
  {
    return $this->domainKeywordsLoader;
  }
}
