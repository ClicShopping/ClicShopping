<?php
/**
 * WebSearchFacade - Agnostic facade for unified websearch engine
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch
 * @since 2026-05-08
 *
 * Architecture:
 * - Agnostic layer: Works with any domain (Ecommerce, HR, Finance, Trading, etc.)
 * - Domain-specific facades extend this class and add domain-specific methods
 * - Example: EcommerceWebSearchFacade extends WebSearchFacade and adds comparePrice()
 */

namespace ClicShopping\AI\DomainsAI\WebSearch;

use ClicShopping\AI\DomainsAI\WebSearch\Executor\WebSearchExecutor;
use ClicShopping\AI\DomainsAI\WebSearch\Planner\HybridQueryPlanner;
use ClicShopping\AI\DomainsAI\WebSearch\Planner\WebSearchPlan;
use ClicShopping\AI\DomainsAI\WebSearch\Processor\IntentRouter;
use ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter\ResultNormalizer;
use ClicShopping\AI\DomainsAI\WebSearch\Response\UserInputRequiredResponse;
use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * WebSearchFacade Class
 *
 * Provides a simplified interface to the unified websearch engine.
 * This is the agnostic layer facade that works with any domain.
 *
 * Key Features:
 * - Automatic mode selection (Mode A, B, C, D, Hybrid)
 * - Intent detection and routing
 * - Multi-engine execution and result normalization
 * - Configuration validation
 * - Comprehensive logging
 *
 * Domain-specific facades should extend this class and add domain-specific methods.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch
 */
class WebSearchFacade
{
  /**
   * @var IntentRouter Intent detection and routing component
   */
  protected IntentRouter $intentRouter;

  /**
   * @var WebSearchExecutor Multi-mode execution orchestrator
   */
  protected WebSearchExecutor $dispatcher;

  /**
   * @var ResultNormalizer Result normalization component
   */
  protected ResultNormalizer $normalizer;

  /**
   * @var HybridQueryPlanner Task planner for compound/multi-intent queries
   */
  protected HybridQueryPlanner $planner;

  /**
   * @var SecurityLogger Security and audit logger
   */
  protected SecurityLogger $logger;

  /**
   * @var string SerpAPI key for search operations
   */
  protected string $apiKey;

  /**
   * @var bool Debug mode flag
   */
  protected bool $debug;

  /**
   * Constructor
   *
   * Initializes the facade with all required components and validates configuration.
   *
   * @throws \RuntimeException If configuration is invalid
   */
  public function __construct()
  {
    // Load configuration
    $this->loadConfiguration();

    // Initialize components
    $this->intentRouter = new IntentRouter();
    $this->dispatcher = new WebSearchExecutor();
    $this->normalizer = new ResultNormalizer();
    $this->planner = new HybridQueryPlanner();
    $this->logger = new SecurityLogger('info');

    // Debug mode
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    // Log facade initialization
    $this->logger->logEvent('websearch_facade_initialized', [
      'component' => 'WebSearchFacade',
      'api_key_configured' => !empty($this->apiKey),
      'timestamp' => date('Y-m-d H:i:s')
    ]);
  }

  /**
   * Execute unified websearch with automatic mode selection
   *
   * This method provides the main entry point for websearch operations.
   * It automatically detects query intent, selects appropriate search mode(s),
   * executes the search, and returns normalized results.
   *
   * Flow:
   * 1. IntentRouter analyzes query and selects mode(s)
   * 2. WebSearchExecutor executes selected engine(s)
   * 3. ResultNormalizer unifies results from multiple sources
   * 4. SecurityLogger logs operation for audit trail
   *
   * Supported Modes:
   * - Mode A: AI Overview (Google AI-generated synthesis)
   * - Mode B: Google Shopping (structured product results)
   * - Mode C: RAG WebSearch (site-specific search)
   * - Mode D-...: Domain-specific shopping engines registered by each
   *   Apps/AI/{Domain}/ (e.g. Ecommerce → its retail engines, future HR/CRM → ...)
   * - Hybrid: Multiple modes combined
   *
   * @param string $query Natural language search query
   * @param array $options Optional parameters:
   *                       - max_results: Maximum results per engine (default: engine-specific)
   *                       - mode_hint: Explicit mode override (mode_a|mode_b|mode_c|mode_d|hybrid)
   *                       - location: Geographic location for localized results
   *                       - target_site: Specific site to search (for Mode C)
   *                       - language_id: Language ID for localization
   *                       - user_id: User ID for personalization
   *
   * @return array Unified result structure:
   *               - success: bool - Operation success status
   *               - query: string - Original query
   *               - ai_overview: string|null - AI-generated synthesis (Mode A)
   *               - organic_results: array - Standard web results
   *               - shopping_results: array - Structured shopping results (Mode B/D)
   *               - metadata: array - Execution metadata (modes used, timing, etc.)
   *               - quality_score: float - Result quality score (0.0-1.0)
   *
   * @throws \RuntimeException If configuration is invalid or all engines fail
   */
  public function search(string $query, array $options = []): array
  {
    $startTime = microtime(true);

    try {
      // Log search operation start
      $this->logger->logStructured(
        'info',
        'WebSearchFacade',
        'search_start',
        [
          'query' => $query,
          'options' => $options,
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      // Step 1: Detect compound/multi-intent query before single-intent routing
      // Example: "What are smartphone trends AND compare iPhone price on a target site"
      // → decomposed into [market_research task, price_comparison task]
      $plan = $this->planner->analyze($query);
      if ($plan->isCompound()) {
        if ($this->debug) {
          error_log('[WebSearchFacade] Compound query detected, executing plan with ' . count($plan->getTasks()) . ' tasks');
        }
        return $this->executeCompoundPlan($plan, $query, $options);
      }

      // Step 2: Route query to appropriate mode(s)
      $routing = $this->intentRouter->route($query, $options);

      // CRITICAL: Check if user input is required (web/chat mode)
      // If IntentRouter returns UserInputRequiredResponse, return it directly
      // The caller (OrchestratorAgent or chat interface) will handle the prompt display
      if ($routing instanceof UserInputRequiredResponse) {
        if ($this->debug) {
          error_log("WebSearchFacade::search() - User input required, returning UserInputRequiredResponse");
        }
        
        // Return the UserInputRequiredResponse directly without executing search
        // The response will be formatted by the chat interface to display the prompt
        return [
          'type' => 'user_input_required',
          'response' => $routing,
          'context_id' => $routing->getContextId(),
          'prompt' => $routing->getPrompt(),
          'options' => $routing->getOptions(),
          'metadata' => $routing->getMetadata()
        ];
      }

      // Step 2: Execute search via dispatcher
      $results = $this->dispatcher->execute($routing, $query, $options);

      // Each enhancer (e.g. Apps/AI/Ecommerce/.../Enhancers/MarketAnalysisEnhancer)
      // can inject domain-specific fields into the result array — Core stays
      // brand-free; the rendering layer (WebSearchFormatter) picks up these
      // fields by key without knowing which domain produced them.
      $results = $this->applyResultEnhancers($results, $routing, $query, $options);

      // Step 3: Calculate execution time
      $executionTime = microtime(true) - $startTime;

      // Step 4: Log successful operation
      $this->logger->logStructured(
        'info',
        'WebSearchFacade',
        'search_success',
        [
          'query' => $query,
          'modes_used' => $routing->getSelectedModes(),
          'routing_method' => $routing->getRoutingMethod(),
          'is_hybrid' => $routing->isHybridMode(),
          'execution_time' => round($executionTime, 3),
          'quality_score' => $results['quality_score'] ?? 0.0,
          'result_count' => count($results['shopping_results'] ?? []) + count($results['organic_results'] ?? []),
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      return $results;

    } catch (\Exception $e) {
      // Calculate execution time even on failure
      $executionTime = microtime(true) - $startTime;

      // Log error
      $this->logger->logError(
        'WebSearchFacade search failed: ' . $e->getMessage(),
        [
          'query' => $query,
          'options' => $options,
          'execution_time' => round($executionTime, 3),
          'error_type' => get_class($e),
          'stack_trace' => $e->getTraceAsString(),
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      // Re-throw exception
      throw $e;
    }
  }

  /**
   * Execute a compound plan: route and run each task independently, then merge
   *
   * Each task in the plan is processed through the full IntentRouter → WebSearchExecutor
   * pipeline independently, ensuring that target_site, location, and mode selection
   * are correctly resolved per sub-query.
   *
   * @param WebSearchPlan $plan Decomposed plan with tasks
   * @param string $originalQuery Original user query
   * @param array $options Search options
   * @return array Merged result structure
   */
  private function executeCompoundPlan(WebSearchPlan $plan, string $originalQuery, array $options): array
  {
    $taskResults = [];

    foreach ($plan->getTasks() as $task) {
      try {
        $subQuery = $task['query'] ?? $originalQuery;

        // Each sub-query goes through full routing (IntentRouter + ModeSelector)
        $routing = $this->intentRouter->route($subQuery, $options);

        if ($routing instanceof UserInputRequiredResponse) {
          continue;
        }

        $result = $this->dispatcher->execute($routing, $subQuery, $options);
        $result['metadata']['plan_task'] = $task;
        $taskResults[] = $result;

        if ($this->debug) {
          error_log(sprintf(
            '[WebSearchFacade] Compound task "%s" (intent: %s) → %d shopping, ai_overview: %s',
            substr($subQuery, 0, 60),
            $task['intent'] ?? 'unknown',
            count($result['shopping_results'] ?? []),
            $result['ai_overview'] !== null ? 'yes' : 'no'
          ));
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log('[WebSearchFacade] Compound task failed: ' . $e->getMessage());
        }
      }
    }

    if (empty($taskResults)) {
      // All tasks failed — fall back to routing the original query
      if ($this->debug) {
        error_log('[WebSearchFacade] All compound tasks failed, falling back to single routing');
      }
      $routing = $this->intentRouter->route($originalQuery, $options);
      return $this->dispatcher->execute($routing, $originalQuery, $options);
    }

    return $this->mergePlanResults($taskResults, $originalQuery);
  }

  /**
   * Merge results from multiple plan tasks into a single unified response
   *
   * @param array $taskResults Array of result structures, one per task
   * @param string $originalQuery Original user query
   * @return array Merged result structure
   */
  private function mergePlanResults(array $taskResults, string $originalQuery): array
  {
    $merged = [
      'success' => true,
      'query' => $originalQuery,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'rag_results' => [],
      'metadata' => [
        'mode_type' => 'compound_hybrid',
        'is_hybrid_mode' => true,
        'plan_task_count' => count($taskResults),
        'plan_tasks' => [],
      ],
    ];

    foreach ($taskResults as $result) {
      if (!empty($result['ai_overview']) && $merged['ai_overview'] === null) {
        $merged['ai_overview'] = $result['ai_overview'];
      }
      if (!empty($result['organic_results'])) {
        $merged['organic_results'] = array_merge($merged['organic_results'], $result['organic_results']);
      }
      if (!empty($result['shopping_results'])) {
        $merged['shopping_results'] = array_merge($merged['shopping_results'], $result['shopping_results']);
      }
      if (!empty($result['rag_results'])) {
        $merged['rag_results'] = array_merge($merged['rag_results'], $result['rag_results']);
      }
      $merged['metadata']['plan_tasks'][] = [
        'task' => $result['metadata']['plan_task'] ?? [],
        'mode_type' => $result['metadata']['mode_type'] ?? 'unknown',
        'success' => $result['success'] ?? false,
      ];
    }

    return $merged;
  }

  /**
   * Load and validate configuration
   *
   * Loads the SerpAPI key from configuration constants and validates it.
   *
   * @throws \RuntimeException If configuration is invalid
   */
  protected function loadConfiguration(): void
  {
    // Load SerpAPI key
    if (defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI')
        && !empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI)) {
      $this->apiKey = trim(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI);
    } else {
      throw new \RuntimeException(
        'SerpAPI key not configured. ' .
        'Please set CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI in configuration.'
      );
    }

    // Validate API key format (basic validation)
    if (strlen($this->apiKey) < 10) {
      throw new \RuntimeException(
        'Invalid SerpAPI key format. ' .
        'Key must be at least 10 characters long.'
      );
    }
  }

  /**
   * Get available search engines
   *
   * Returns list of available search engines with their capabilities.
   *
   * @return array Array of engine metadata
   */
  public function getAvailableEngines(): array
  {
    return $this->dispatcher->getAvailableEngines();
  }

  /**
   * Get engine metadata
   *
   * Returns detailed metadata for all engines including cost, latency, and quality scores.
   *
   * @return array Array of engine metadata keyed by registered mode identifier.
   *               Built-in keys include mode_a_ai_overview, mode_b_google_shopping,
   *               mode_c_rag_websearch, mode_e_google_trends; additional keys are
   *               contributed by each Apps/AI/{Domain}/-registered provider.
   */
  public function getEngineMetadata(): array
  {
    return $this->dispatcher->getEngineMetadata();
  }

  /**
   * Validate configuration
   *
   * Checks if the facade is properly configured and all required components are available.
   *
   * @return array Validation result:
   *               - valid: bool - Overall validation status
   *               - api_key_configured: bool - SerpAPI key status
   *               - engines_available: array - Available engines
   *               - errors: array - Validation errors (if any)
   */
  public function validateConfiguration(): array
  {
    $errors = [];
    $valid = true;

    // Check API key
    $apiKeyConfigured = !empty($this->apiKey);
    if (!$apiKeyConfigured) {
      $errors[] = 'SerpAPI key not configured';
      $valid = false;
    }

    // Check available engines
    $enginesAvailable = $this->dispatcher->getAvailableEngines();
    if (empty($enginesAvailable)) {
      $errors[] = 'No search engines available';
      $valid = false;
    }

    return [
      'valid' => $valid,
      'api_key_configured' => $apiKeyConfigured,
      'engines_available' => $enginesAvailable,
      'errors' => $errors
    ];
  }

  /**
   * Run every registered WebSearchResultEnhancer on the search payload.
   *
   * Builds a small context (intent type, target site, language, original
   * query) so enhancers can decide whether they want to run without having
   * to walk the routing-decision tree themselves. Failures are swallowed
   * and logged — an enhancer must never break the search response.
   *
   * @param array          $results Raw result array from the dispatcher
   * @param mixed          $routing RoutingDecision (or any object with the
   *                                getters used below) from IntentRouter
   * @param string         $query   Original user query
   * @param array          $options Search options forwarded by the caller
   * @return array Possibly enhanced result array
   */
  protected function applyResultEnhancers($results, $routing, string $query, array $options): array
  {
    try {
      $enhancers = WebSearchEngineRegistry::getInstance()->getResultEnhancers();
    } catch (\Throwable $e) {
      // If the registry itself blows up, just return untouched results.
      return is_array($results) ? $results : [];
    }

    if (empty($enhancers) || !is_array($results)) {
      return is_array($results) ? $results : [];
    }

    // Extract a small, enhancer-friendly context from the routing decision.
    $intent = [];
    if (is_object($routing) && method_exists($routing, 'toArray')) {
      $intent = $routing->toArray()['intent'] ?? [];
    }

    $context = [
      'query'         => $query,
      'options'       => $options,
      'intent_type'   => $intent['intent']        ?? null,
      'product_query' => $intent['product']       ?? null,
      'target_site'   => $intent['target_site']   ?? null,
      'language'      => $intent['language']      ?? ($options['language'] ?? null),
      'language_id'   => $options['language_id']  ?? null,
      'confidence'    => $intent['confidence']    ?? null,
    ];

    foreach ($enhancers as $enhancer) {
      try {
        if (!$enhancer->shouldEnhance($results, $context)) {
          continue;
        }

        $before = $results;
        $results = $enhancer->enhance($results, $context);
        if (!is_array($results)) {
          // Defensive: rollback if an enhancer returns garbage.
          $results = $before;
        }

        if ($this->debug) {
          error_log(sprintf(
            '[WebSearchFacade] Result enhancer "%s" applied',
            $enhancer->getEnhancerId()
          ));
        }
      } catch (\Throwable $e) {
        $this->logger->logError(
          'WebSearchFacade enhancer "' . $enhancer->getEnhancerId() . '" failed: ' . $e->getMessage(),
          [
            'enhancer_id'   => $enhancer->getEnhancerId(),
            'enhancer_cls'  => $enhancer::class,
            'error_type'    => get_class($e),
            'stack_trace'   => $e->getTraceAsString(),
          ]
        );
        // Continue with the other enhancers — a single failure must not
        // poison the overall response.
      }
    }

    return $results;
  }
}
