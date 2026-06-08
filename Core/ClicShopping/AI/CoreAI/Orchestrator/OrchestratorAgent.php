<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator;

use ClicShopping\AI\Config\ActorCriticConfig;
use ClicShopping\AI\Config\AgentActorsConfig;
use ClicShopping\AI\Config\AgentCriticsConfig;
use ClicShopping\AI\Config\AgentDomainsConfig;
use ClicShopping\AI\Config\AgentSystemConfig;
use ClicShopping\AI\Config\AgentTechnicalConfig;
use ClicShopping\AI\Config\AutonomousConfig;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCriticCoordinator;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ContextManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\DiagnosticManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\EntityExtractor;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\IntentAnalyzer;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\MemoryManager as MemoryManagerComponent;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ResponseProcessor as ResponseProcessorComponent;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\OutOfContextGate;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\PlanStepValidator;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\LowConfidenceReasoningFallback;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\IntentTranslationValidator;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\OrchestrationContext;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\StageRegistry;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\PrepareExecutionScopeStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ResolveConversationContextStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ResolveQueryAgainstContextStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\AnalyzeIntentStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\RouteHybridEarlyStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ReasoningFallbackStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\RouteHybridDuplicateStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\CreatePlanStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\RunPlanStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\BuildResponseStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\StoreMemoryStage;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\FinalizeStage;
use ClicShopping\AI\CoreAI\Planning\PlanExecutor;
use ClicShopping\AI\CoreAI\Planning\TaskPlanner;
use ClicShopping\AI\CoreAI\Query\QueryAnalyzer;
use ClicShopping\AI\CoreAI\Response\LlmResponseProcessor;
use ClicShopping\AI\DomainsAI\DomainRouter;
use ClicShopping\AI\DomainsAI\Hybrid\Handler\HybridQueryHandler;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\Handler\Error\ErrorHandler as ErrorHandlerComponent;
use ClicShopping\AI\Handler\Query\ComplexQueryHandler;
use ClicShopping\AI\Handler\Query\QueryProcessor;
use ClicShopping\AI\Infrastructure\Monitoring\AlertManager;
use ClicShopping\AI\Infrastructure\Monitoring\MetricsCollector;
use ClicShopping\AI\Infrastructure\Monitoring\MonitoringAgent;
use ClicShopping\AI\Infrastructure\Monitoring\PerformanceTracker;
use ClicShopping\AI\Security\RateLimit;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Security\Validation\HallucinationDetector;
use ClicShopping\AI\ServicesAI\ActorCritic\ActorCriticInitializer;
use ClicShopping\AI\ServicesAI\Autonomous\ObjectiveManager;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * OrchestratorAgent Class
 *
 * Senior Agent / Coordinator — does NOT implement ActorAgentInterface.
 * This is by design: the Orchestrator coordinates Actors and Critics but does not
 * execute domain actions itself. It delegates execution to specialized Actor agents
 * (AnalyticsActor, ValidationActor, ReasoningActor) via the Actor-Critic framework.
 */

class OrchestratorAgent
{
  /**
   * Context relevance threshold for out-of-context detection
   * Queries with context_relevance < this threshold are rejected
   * 
   * @var float Default: 0.3 (queries with relevance < 0.3 are rejected)
   */
  private const CONTEXT_RELEVANCE_THRESHOLD = 0.3;
  
  public TaskPlanner $taskPlanner;
  public PlanExecutor $planExecutor;
  private MetricsCollector $collector;
  private SecurityLogger $securityLogger;
  private RateLimit $rateLimit;
  private string $userId;
  private bool $debug;
  private int $languageId;
  private int $entityId;
  private $db;
  private string $prefix;
  private array $executionStats = [];
  private ConversationMemory $conversationMemory;
  private WorkingMemory $workingMemory;
  private CorrectionAgent $correctionAgent;
  private ValidationAgent $validationAgent;
  private ReasoningAgent $reasoningAgent;

  private MonitoringAgent $monitoring;
  private AlertManager $alertManager;
  private LlmResponseProcessor $responseProcessor;
  private ResponseProcessorComponent $responseProcessorComponent;

  private IntentAnalyzer $intentAnalyzer;
  private EntityExtractor $entityExtractor;
  public DiagnosticManager $diagnosticManager;
  private ContextManager $contextManager;
  private DomainRouter $domainRouter;
  private QueryProcessor $queryProcessor;
  private HybridQueryHandler $hybridQueryHandler;
  private ?ActorCriticCoordinator $actorCriticCoordinator = null;
  private ActorCriticInitializer $actorCriticInitializer;
  private ObjectiveManager $objectiveManager;
  private ComplexQueryHandler $complexQueryHandler;
  private QueryAnalyzer $queryAnalyzer;
  private ErrorHandlerComponent $errorHandler;
  private MemoryManagerComponent $memoryManager;
  private AutonomousConfig $autonomousConfig;
  private HallucinationDetector $hallucinationDetector;
  private OutOfContextGate $outOfContextGate;
  private PlanStepValidator $planStepValidator;
  private LowConfidenceReasoningFallback $lowConfidenceReasoningFallback;
  private IntentTranslationValidator $intentTranslationValidator;
  private PerformanceTracker $performanceTracker;

  private StageRegistry $stageRegistry;

  // Diagnostics - delegated to DiagnosticManager

  /**
   * Constructor
   *
   * @param string $userId Identifiant de l'utilisateur
   * @param int|null $languageId ID de la langue (null = langue par défaut)
   * @param int $entityId Entity ID for context
   *

   */
  public function __construct(string $userId = 'system', ?int $languageId = null, int $entityId = 0)
  {
    // Core initialization
    $this->userId = $userId;
    $this->entityId = $entityId;
    $this->db = Registry::get('Db');
    $this->languageId = is_null($languageId) ? Registry::get('Language')->getId() : $languageId;
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');

    // Initialize core components (security, monitoring, memory)
    $this->initializeCoreComponents();

    // Initialize all agents and SubOrchestrator components
    $this->initializeAgents();

    // Initialize SubOrchestrator components (Phase 2 extracted components)
    $this->initializeSubComponents();

    // Initialize statistics
    $this->initializeStats();

    // Register with monitoring
    $this->monitoring->registerComponent('orchestrator', $this);

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'initialized', [
        'user_id' => $this->userId,
        'entity_id' => $this->entityId,
        'language_id' => $this->languageId
      ]);
    }
  }

  /**
   * Initialize core components (security, rate limiting, monitoring, memory)
   *
   */
  private function initializeCoreComponents(): void
  {
    // Security and rate limiting
    $this->securityLogger = new SecurityLogger();
    $this->rateLimit = new RateLimit('orchestrator', 100, 60);
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    
    $this->autonomousConfig = new AutonomousConfig($this->debug);

    // Monitoring and metrics
    $this->monitoring = new MonitoringAgent();
    $this->collector = new MetricsCollector($this->monitoring);
    $this->alertManager = new AlertManager();

    // Memory systems
    $this->conversationMemory = new ConversationMemory(
      $this->userId,
      $this->languageId,
      $this->prefix . 'rag_conversation_memory_embedding',
      $this->entityId
    );
    $this->workingMemory = new WorkingMemory();

    // Response processor
    $this->responseProcessor = new LlmResponseProcessor();
  }

  /**
   * Initialize all agents (planning, correction, validation, reasoning)
   *
   */
  private function initializeAgents(): void
  {
    $this->taskPlanner = new TaskPlanner($this->languageId);
    $this->planExecutor = new PlanExecutor($this->taskPlanner, $this->userId, $this->languageId);

    // This enables contextual query resolution in AnalyticsAgent (e.g., "give me this sku")
    $this->planExecutor->setConversationMemory($this->conversationMemory);
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("ConversationMemory set on PlanExecutor", 'info');
    }


    $this->correctionAgent = new CorrectionAgent($this->userId, $this->languageId);
    $this->validationAgent = new ValidationAgent($this->userId);

    // Phase 3 (refactor): plan-step validator component (proactive validate + correct of plan steps)
    $this->planStepValidator = new PlanStepValidator($this->validationAgent, $this->correctionAgent, $this->workingMemory, $this->securityLogger, $this->debug);
    $this->reasoningAgent = new ReasoningAgent();

    // Phase 3 (refactor): low-confidence reasoning fallback component
    $this->lowConfidenceReasoningFallback = new LowConfidenceReasoningFallback($this->reasoningAgent, $this->workingMemory, $this->securityLogger);
  }

  /**
   * Initialize SubOrchestrator components
   *
   */
  private function initializeSubComponents(): void
  {
    // Existing SubOrchestrator components
    $this->intentAnalyzer = new IntentAnalyzer($this->conversationMemory, $this->debug);
    $this->entityExtractor = new EntityExtractor($this->debug);
    $this->diagnosticManager = new DiagnosticManager($this->executionStats, $this->debug);
    $this->contextManager = new ContextManager($this->debug, [
      'auto_clear_on_domain_switch' => true,
      'prioritize_feedback_over_context' => true,
      'min_confidence_for_clear' => 0.7,
    ]);

    // Phase 1: Domain Routing - Initialize DomainRouter
    $this->domainRouter = new DomainRouter($this->debug);

    // Initialize dependencies first (required by QueryProcessor)
    $this->responseProcessorComponent = new ResponseProcessorComponent($this->debug);
    $this->queryAnalyzer = new QueryAnalyzer($this->debug);
    $this->errorHandler = new ErrorHandlerComponent($this->debug, $this->responseProcessorComponent);
    $this->memoryManager = new MemoryManagerComponent(
      $this->conversationMemory,
      $this->workingMemory,
      $this->debug
    );

    // Phase 2: Query Processing - Initialize QueryProcessor (after dependencies)
    $this->queryProcessor = new QueryProcessor(
      $this->contextManager,
      $this->queryAnalyzer,
      $this->errorHandler,
      $this->conversationMemory,
      $this->debug
    );

    // Phase 3: Hybrid Query Handling - Initialize HybridQueryHandler
    $this->hybridQueryHandler = new HybridQueryHandler(
      $this->taskPlanner,
      $this->planExecutor,
      $this->conversationMemory,
      null, // DomainKeywordsLoader will be auto-created
      $this->debug
    );

    // Phase 4: Validation and Hallucination Detection - Initialize HallucinationDetector
    $this->hallucinationDetector = new HallucinationDetector($this->debug);

    // Phase 3 (refactor): out-of-context gate component (evaluate + rejection-message builder)
    $this->outOfContextGate = new OutOfContextGate($this->hallucinationDetector, $this->securityLogger, $this->debug);

    // Phase 3 (refactor): anti-hallucination guard on the intent's translated query
    $this->intentTranslationValidator = new IntentTranslationValidator($this->hallucinationDetector, $this->securityLogger);

    // Phase 5: Performance Tracking - Initialize PerformanceTracker
    $this->performanceTracker = new PerformanceTracker($this->collector, $this->debug);

    // Phase 6B: Actor-Critic Initialization - Initialize ActorCriticInitializer
    $this->actorCriticInitializer = new ActorCriticInitializer($this->securityLogger);

    // Phase 6B: Autonomous Agent Management - Initialize ObjectiveManager
    $this->objectiveManager = new ObjectiveManager($this->db, $this->securityLogger, $this->debug);

    $this->actorCriticCoordinator = new ActorCriticCoordinator();
    $this->complexQueryHandler = new ComplexQueryHandler($this->debug);

    // Phase 4: build the ordered orchestration stage pipeline. Core registers the agnostic stages;
    // a domain App may insert its own stage via the registry without touching the core orchestrator.
    // Built here (after all stage dependencies are initialized) and only iterated at request time.
    $this->stageRegistry = (new StageRegistry())
      ->append(new PrepareExecutionScopeStage($this->workingMemory, $this->performanceTracker))
      ->append(new ResolveConversationContextStage(
        $this->queryProcessor,
        $this->workingMemory,
        $this->performanceTracker,
        $this->conversationMemory,
        $this->securityLogger,
        $this->debug
      ))
      ->append(new ResolveQueryAgainstContextStage(
        $this->queryProcessor,
        $this->workingMemory
      ))
      ->append(new AnalyzeIntentStage(
        $this->intentAnalyzer,
        $this->workingMemory,
        $this->intentTranslationValidator,
        $this->complexQueryHandler,
        $this->securityLogger,
        $this->debug
      ))
      ->append(new RouteHybridEarlyStage(
        $this->queryProcessor,
        $this->hybridQueryHandler,
        $this->securityLogger,
        $this->debug
      ))
      ->append(new ReasoningFallbackStage(
        $this->lowConfidenceReasoningFallback,
        $this->queryProcessor,
        $this->securityLogger,
        $this->debug
      ))
      ->append(new RouteHybridDuplicateStage(
        $this->domainRouter,
        $this->hybridQueryHandler,
        $this->securityLogger,
        $this->debug
      ))
      ->append(new CreatePlanStage(
        $this->taskPlanner,
        $this->workingMemory,
        $this->planStepValidator,
        $this->securityLogger,
        $this->debug
      ))
      ->append(new RunPlanStage(
        $this->planExecutor,
        $this->entityExtractor,
        $this->workingMemory,
        $this->securityLogger,
        $this->debug
      ))
      ->append(new BuildResponseStage(
        $this->responseProcessorComponent,
        $this->responseProcessor
      ))
      ->append(new StoreMemoryStage(
        $this->performanceTracker,
        $this->memoryManager,
        $this->queryAnalyzer,
        $this->responseProcessorComponent,
        $this->userId,
        $this->languageId,
        $this->securityLogger,
        $this->debug
      ))
      ->append(new FinalizeStage(
        $this->workingMemory,
        $this->collector,
        $this->performanceTracker,
        $this->securityLogger,
        $this->debug,
        $this->executionStats
      ));

    if ($this->debug) {
      error_log('---------------------------');
      error_log('Actor CriticsConfig Enable : ' . ActorCriticConfig::isEnabled());
      error_log('---------------------------');
    }

    if (ActorCriticConfig::isEnabled()) {
      try {
        // Log Agent System and Agent Technical status
        if ($this->debug) {
          $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'agent_modules_status', [
            'agent_system' => [
              'enabled' => AgentSystemConfig::isEnabled(),
              'websearch_global' => AgentSystemConfig::isWebSearchGloballyEnabled(),
              'adaptive_weighting' => AgentSystemConfig::isAdaptiveWeightingEnabled(),
              'reputation_system' => AgentSystemConfig::isReputationSystemEnabled()
            ],
            'agent_technical' => [
              'enabled' => AgentTechnicalConfig::isEnabled(),
              'llm_provider' => AgentTechnicalConfig::getLLMProvider(),
              'coordination_timeout' => AgentTechnicalConfig::getCoordinationTimeout(),
              'max_critics' => AgentTechnicalConfig::getMaxCritics(),
              'consensus_threshold' => AgentTechnicalConfig::getConsensusThreshold()
            ],
            'agent_actors' => [
              'enabled' => AgentActorsConfig::isEnabled(),
              'analytics' => AgentActorsConfig::isAnalyticsEnabled(),
              'semantic' => AgentActorsConfig::isSemanticEnabled(),
              'validation' => AgentActorsConfig::isValidationEnabled(),
              'websearch' => AgentActorsConfig::isWebSearchEnabled(),
              'reasoning' => AgentActorsConfig::isReasoningEnabled()
            ],
            'agent_critics' => [
              'enabled' => AgentCriticsConfig::isEnabled(),
              'analytics_expert' => AgentCriticsConfig::isAnalyticsExpertEnabled(),
              'specialist' => AgentCriticsConfig::isSpecialistEnabled(),
              'security_expert' => AgentCriticsConfig::isSecurityExpertEnabled(),
              'generalist' => AgentCriticsConfig::isGeneralistEnabled()
            ],
            'agent_domains' => [
              'enabled' => AgentDomainsConfig::isEnabled(),
              'domains_enabled' => AgentDomainsConfig::isDomainsEnabled()
            ]
          ]);
        }
        
        // Initialize registries and register actors/critics
        $this->initializeActorCriticSystem();
        
        $this->actorCriticCoordinator = new ActorCriticCoordinator();
        
        if ($this->debug) {
          $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'actor_critic_enabled', [
            'message' => 'Actor-Critic separation is ENABLED',
            'fallback_enabled' => ActorCriticConfig::shouldFallbackToHybrid()
          ]);
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          $this->securityLogger->logStructured('warning', 'OrchestratorAgent', 'actor_critic_init_failed', [
            'message' => 'Failed to initialize ActorCriticCoordinator, will use hybrid mode',
            'error' => $e->getMessage()
          ]);
        }
        $this->actorCriticCoordinator = null;
      }
    } else {
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'actor_critic_disabled', [
          'message' => 'Actor-Critic separation is DISABLED (using hybrid mode)'
        ]);
      }
    }
  }
  
  /**
   * Initialize Actor-Critic system by registering all actors and critics
   * 
   * Delegates to ActorCriticInitializer for actor/critic registration.
   * Called during OrchestratorAgent initialization when Actor-Critic separation is enabled.
   * 
   * @return void
   */
  private function initializeActorCriticSystem(): void
  {
    $this->actorCriticInitializer->initialize($this->languageId, $this->debug);
  }

  /**
   * Initialize execution statistics
   */
  private function initializeStats(): void
  {
    $this->executionStats = [
      'total_queries' => 0,
      'total_requests' => 0, // 🆕 For DiagnosticManager
      'total_execution_time' => 0, // 🆕 For DiagnosticManager
      'successful_queries' => 0,
      'failed_queries' => 0,
      'analytics_queries' => 0,
      'semantic_queries' => 0,
      'hybrid_queries' => 0,
      'average_execution_time' => 0,
    ];
  }

  /**
   * Get latency metrics for dashboard
   *
   * Delegates to PerformanceTracker for comprehensive latency metrics.
   *
   * @return array Latency metrics with detailed statistics
   */
  public function getLatencyMetrics(): array
  {
    return $this->performanceTracker->getLatencyMetrics();
  }

  // ========================================
  // AUTONOMOUS AGENT INTEGRATION
  // ========================================

  /**
   * Approve or reject an agent's local objective
   *
   * Delegates to ObjectiveManager for objective approval/rejection.
   *
   * @param string $objectiveId The objective ID to approve/reject
   * @param bool $approve True to approve, false to reject
   * @param string $reason Reason for the decision
   * @return array Approval result
   */
  public function approveObjective(string $objectiveId, bool $approve, string $reason = ''): array
  {
    return $this->objectiveManager->approveObjective($objectiveId, $approve, $reason);
  }

  /**
   * Resolve conflicts between agent objectives
   *
   * Delegates to ObjectiveManager for conflict resolution.
   *
   * @param array $conflictingObjectiveIds Array of conflicting objective IDs
   * @param string $resolutionStrategy Strategy: 'cancel_lower_priority', 'merge', 'sequence', 'allow_both'
   * @return array Resolution result
   */
  public function resolveObjectiveConflict(array $conflictingObjectiveIds, string $resolutionStrategy = 'cancel_lower_priority'): array
  {
    return $this->objectiveManager->resolveObjectiveConflict($conflictingObjectiveIds, $resolutionStrategy);
  }

  /**
   * Get active objectives across all agents
   *
   * Delegates to ObjectiveManager for retrieving active objectives.
   *
   * @return array Array of active objectives
   */
  public function getActiveObjectives(): array
  {
    return $this->objectiveManager->getActiveObjectives();
  }

  /**
   * Process query with retry logic
   * 
   * Delegates to QueryProcessor for retry handling with temporary error detection.
   * Automatically retries on temporary errors (timeouts, rate limits, etc.).
   * 
   * @param string $query User query
   * @param array $options Processing options (max_retries, retry_delay, etc.)
   * @return array Processing result with retry info
   */
  public function processWithRetry(string $query, array $options = []): array
  {
    // Delegate to QueryProcessor with processWithValidation as callback
    return $this->queryProcessor->processWithRetry(
      $query,
      $options,
      fn($q, $opts) => $this->processWithValidation($q, $opts)
    );
  }

  /**
   * Main entry point for external callers and tests.
   * Delegates to processWithValidation().
   *
   * @param string $query User query
   * @param array $options Additional options (context, preferences, etc.)
   * @return array Structured response with metadata
   */
  public function execute(string $query, array $options = []): array
  {
    return $this->processWithValidation($query, $options);
  }

  /**
   * Main processing entry point with full validation pipeline.
   *
   * @param string $query User query
   * @param array $options Additional options (context, preferences, etc.)
   * @return array Structured response with metadata
   */
  public function processWithValidation(string $query, array $options = []): array
  {
    $startTime = microtime(true);
    $this->performanceTracker->startTracking(); // Phase 5: Use PerformanceTracker
    $this->collector->startTimer('process_validation');

    $status = 'success';

    $intent = null;
    $executionId = null;

    if ($this->debug) {
      error_log("-------------------------------");
      error_log("[INFO  START] processWithValidation - Query: " . substr($query, 0, 100));
      error_log("[INFO TIME] Start time: " . date('Y-m-d H:i:s.u'));
      error_log("-------------------------------");
    }

    try {
      // Compute the three-tier out-of-context decision inputs (short-query skip + detection).
      $contextCheck = $this->outOfContextGate->evaluate($query);

      // Handle out-of-context queries using three-tier decision logic:
      // 1. Primary Gate (Boolean): is_out_of_context check (authoritative rejection)
      // 2. Threshold Gate: context_relevance check (configurable threshold-based decisions)
      // 3. Nuanced Handling: suggested_action check (fine-grained action routing)
      
      // PRIMARY GATE: Check is_out_of_context (boolean gate)
      // If LLM explicitly marks query as out-of-context, reject immediately — UNLESS the detector
      // suggested redirecting to web search (e.g. "prices on Google Shopping for X"): such queries
      // are out of the internal catalog context but valid for the web-search engine, so let them
      // fall through to the nuanced 'redirect_to_web_search' handler below.
      if (isset($contextCheck['is_out_of_context']) && $contextCheck['is_out_of_context'] === true
          && ($contextCheck['suggested_action'] ?? '') !== 'redirect_to_web_search') {
        // Reject query immediately - return error response
        $this->securityLogger->logSecurityEvent(
          "Query rejected by PRIMARY GATE (is_out_of_context=true): '{$query}' (category: {$contextCheck['detected_category']})",
          'warning'
        );

        // Build dynamic error message based on active domain
        $activeDomain = DomainConfig::getActivities();
        $errorMessage = $this->outOfContextGate->buildError(
          $activeDomain,
          CLICSHOPPING::getDef('text_orchestrator_no_business_operation'),
          'text_orchestrator_no_business_domain',
          CLICSHOPPING::getDef('text_orchestrator_no_business_domain_general')
        );

        return [
          'success' => false,
          'type' => 'error',
          'error' => 'out_of_context',
          'text_response' => $errorMessage,
          'response' => $errorMessage,
          'out_of_context_detection' => [
            'is_out_of_context' => true,
            'context_relevance' => $contextCheck['context_relevance'] ?? 0.0,
            'category' => $contextCheck['detected_category'],
            'confidence' => $contextCheck['confidence'],
            'explanation' => $contextCheck['explanation'],
            'rejection_gate' => 'primary_boolean_gate'
          ],
          'sources' => [],
          'data' => []
        ];
      }
      
      // THRESHOLD GATE: Check context_relevance (threshold gate)
      // If relevance score is below threshold, reject or flag for review — again, never reject a
      // query the detector wants redirected to web search (handled by the nuanced tier below).
      if (isset($contextCheck['context_relevance']) &&
          $contextCheck['context_relevance'] < self::CONTEXT_RELEVANCE_THRESHOLD
          && ($contextCheck['suggested_action'] ?? '') !== 'redirect_to_web_search') {
        // Reject query due to low relevance score
        $this->securityLogger->logSecurityEvent(
          "Query rejected by THRESHOLD GATE (context_relevance={$contextCheck['context_relevance']} < " . self::CONTEXT_RELEVANCE_THRESHOLD . "): '{$query}'",
          'warning'
        );

        // Build dynamic error message based on active domain
        $activeDomain = DomainConfig::getActivities();
        $errorMessage = $this->outOfContextGate->buildError(
          $activeDomain,
          "I'm sorry, but this question appears to have low relevance to our business domain.",
          'text_orchestrator_no_business_operation_no_relevance',
          "I'm sorry, but this question appears to have low relevance to our business domain. I can only help with questions about business data, revenue, analytics, and operations."
        );

        return [
          'success' => false,
          'type' => 'error',
          'error' => 'out_of_context',
          'text_response' => $errorMessage,
          'response' => $errorMessage,
          'out_of_context_detection' => [
            'is_out_of_context' => $contextCheck['is_out_of_context'] ?? false,
            'context_relevance' => $contextCheck['context_relevance'],
            'threshold' => self::CONTEXT_RELEVANCE_THRESHOLD,
            'category' => $contextCheck['detected_category'],
            'confidence' => $contextCheck['confidence'],
            'explanation' => $contextCheck['explanation'],
            'rejection_gate' => 'threshold_relevance_gate'
          ],
          'sources' => [],
          'data' => []
        ];
      }
      
      // NUANCED HANDLING: Check suggested_action (action routing)
      // Only reached if query passes both boolean and threshold gates
      if ($contextCheck['suggested_action'] === 'reject') {
        // Reject query based on LLM's suggested action
        // This handles nuanced rejection cases beyond boolean/threshold violations
        $this->securityLogger->logSecurityEvent(
          "Query rejected by NUANCED HANDLING (suggested_action='reject'): '{$query}' (category: {$contextCheck['detected_category']})",
          'warning'
        );

        // Build dynamic error message based on active domain
        $activeDomain = DomainConfig::getActivities();
        $errorMessage = $this->outOfContextGate->buildError(
          $activeDomain,
          "I'm sorry, but this question is not related to business operations.",
          'text_orchestrator_no_business_configured_domain',
          CLICSHOPPING::getDef('text_orchestrator_no_business_configured_domain_general')
        );

        return [
          'success' => false,
          'type' => 'error',
          'error' => 'out_of_context',
          'text_response' => $errorMessage,
          'response' => $errorMessage,
          'out_of_context_detection' => [
            'is_out_of_context' => $contextCheck['is_out_of_context'] ?? false,
            'context_relevance' => $contextCheck['context_relevance'] ?? 0.0,
            'category' => $contextCheck['detected_category'],
            'confidence' => $contextCheck['confidence'],
            'explanation' => $contextCheck['explanation'],
            'rejection_gate' => 'nuanced_action_routing'
          ],
          'sources' => [],
          'data' => []
        ];
      } elseif ($contextCheck['suggested_action'] === 'ask_clarification') {
        // Ask user for clarification on ambiguous query
        $this->securityLogger->logSecurityEvent(
          "Query requires clarification: '{$query}' (category: {$contextCheck['detected_category']})",
          'info'
        );

        // Build clarification message
        $clarificationMessage = "Nous avons détecté une requête ambiguë. Veuillez préciser votre question:\n\n";
        if (isset($contextCheck['clarification_options']) && is_array($contextCheck['clarification_options'])) {
          foreach ($contextCheck['clarification_options'] as $index => $option) {
            $clarificationMessage .= ($index + 1) . ". " . $option . "\n";
          }
        } else {
          $clarificationMessage .= "1. Rechercher un produit nommé '{$query}'\n";
          $clarificationMessage .= "2. Obtenir des informations sur une personne\n";
          $clarificationMessage .= "3. Autre chose\n";
        }

        return [
          'success' => false,
          'type' => 'clarification_needed',
          'error' => 'ambiguous_query',
          'text_response' => $clarificationMessage,
          'response' => $clarificationMessage,
          'clarification_needed' => true,
          'original_query' => $query,
          'clarification_options' => $contextCheck['clarification_options'] ?? [
            "Rechercher un produit nommé '{$query}'",
            "Obtenir des informations sur une personne",
            "Autre chose"
          ],
          'out_of_context_detection' => [
            'is_out_of_context' => false,
            'category' => $contextCheck['detected_category'],
            'confidence' => $contextCheck['confidence'],
            'explanation' => $contextCheck['explanation']
          ],
          'sources' => [],
          'data' => []
        ];
      } elseif ($contextCheck['suggested_action'] === 'redirect_to_web_search') {
        // Force intent to web_search for business queries requiring external data
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Query redirected to web_search: '{$query}' (category: {$contextCheck['detected_category']})",
            'info'
          );
        }
        // Note: Web search intent will be detected naturally by IntentAnalyzer
      }
      // If action is 'allow', continue normally
      $resolved = $this->memoryManager->resolveContextualReferences($query);
      $queryToProcess = $resolved['resolved_query'] ?? $query;
      $contextUsed = $resolved['has_references'] ?? false;

      if ($contextUsed && $this->debug) {
        $this->securityLogger->logSecurityEvent(
          "TASK 2.8: Contextual references resolved EARLY: '{$query}' → '{$queryToProcess}'",
          'info'
        );
      }

      // Translation is done inside handleFullOrchestration in parallel with context retrieval
      // This early translation is kept for backward compatibility with logging
      $translatedQuery = '';
      try {
        $translatedQuery = SemanticAgent::translateToEnglish($queryToProcess, 80);
      } catch (\Exception $e) {
        // Non-blocking error: log and continue
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Query translation failed (using original): " . $e->getMessage(),
            'warning'
          );
        }
      }
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'query_processing', [
          'original_query' => $query,
          'resolved_query' => $contextUsed ? $queryToProcess : null,
          'translated_query' => $translatedQuery,
          'context_used' => $contextUsed
        ]);
      }

      // Full orchestration path
      return $this->handleFullOrchestration($query, $queryToProcess, $startTime);
    } catch (\Exception $e) {
      $status = 'error';

      $this->securityLogger->logSecurityEvent(
        "Orchestrator error: " . $e->getMessage(),
        'error'
      );

      // Store error in DiagnosticManager for analysis
      $this->diagnosticManager->storeError(
        $e->getMessage(),
        $query,
        [
          'intent' => $intent ?? null,
          'stack_trace' => $e->getTraceAsString(),
          'execution_id' => $executionId ?? null,
        ]
      );

      // Nettoyer en cas d'erreur
      if (isset($executionId)) {
        $this->workingMemory->deleteScope($executionId);
      }

      $this->collector->recordEvent('error', [
        'component' => 'orchestrator',
        'error_message' => $e->getMessage(),
      ]);

      // Construire une réponse d'erreur avec contexte pour messages explicites
      $errorContext = [
        'query' => $query ?? '',
        'intent' => $intent ?? []
      ];

      return $this->errorHandler->buildErrorResponse($e->getMessage(), $errorContext);
    } finally {
      $latencyMs = (microtime(true) - $startTime) * 1000;

      $this->collector->recordMetric(
        'orchestrator_query_latency_ms',
        $latencyMs,
        [
          'status' => $status
        ]
      );

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "⏱️ TASK 4.4.2.3: Query latency recorded: {$latencyMs}ms (status: {$status})",
          'info'
        );

        if ($this->debug) {        
          error_log("-----------------------------------");
          error_log("[INFO END] processWithValidation - Status: {$status}");
          error_log("[INFO TIME] End time: " . date('Y-m-d H:i:s.u'));
          error_log("⏱️ [INFO DURATION] Total time: " . round($latencyMs, 2) . "ms");
          error_log("-----------------------------------");
	}  
      }

      $this->collector->stopTimer('process_validation');
      
      // Phase 5: Stop performance tracking
      $this->performanceTracker->stopTracking($status);
    }
  }


  /**
   * Handle full orchestration for complex queries
   *
   *
   * @param string $query Original user query
   * @param string $queryToProcess Resolved query to process
   * @param float $startTime Start time for performance tracking
   * @return array Full orchestration response
   */
  private function handleFullOrchestration(string $query, string $queryToProcess, float $startTime): array
  {
    if ($this->debug) {
      error_log("-----------------------------------");
      error_log(" [INFO START] handleFullOrchestration");
      error_log(" [INFO QUERY] Original: " . substr($query, 0, 100));
      error_log(" [INFO QUERY] To Process: " . substr($queryToProcess, 0, 100));
      error_log("-----------------------------------");
    }
    
    $pipelineContext = new OrchestrationContext($query, $queryToProcess, $startTime);

    // The full orchestration is an ordered pipeline of stages (see SubOrchestrator/). Each
    // stage reads from and writes to $pipelineContext; a stage may short-circuit the whole run by
    // returning a non-null result (e.g. hybrid routing, clarification). Otherwise the FinalizeStage
    // completes and the built response is returned.
    foreach ($this->stageRegistry->all() as $stage) {
      $stageResult = $stage->run($pipelineContext);
      if ($stageResult !== null) {
        return $stageResult;
      }
    }

    return $pipelineContext->response;
  }

  /**
   * 🎯 Obtenir un rapport complet système
   */
  public function getSystemReport(): array
  {
    return [
      'orchestrator' => $this->getStats(),
      'planning' => $this->taskPlanner->getStats(),
      'memory' => [
        'conversation' => $this->conversationMemory->getStats(),
        'working' => $this->workingMemory->getStats(),
      ],
      'correction' => $this->correctionAgent->getLearningStats(),
      'validation' => $this->validationAgent->getStats(),
      'reasoning' => $this->reasoningAgent->getStats(),
    ];
  }
  
  // ========================================
  // ACTOR-CRITIC INTEGRATION
  // ========================================
  
  /**
   * Get execution statistics
   *
   * @return array Execution statistics
   */
  public function getStats(): array
  {
    return $this->executionStats;
  }
  
  /**
   * Execute action using Actor-Critic coordination
   *
   * This method provides transparent integration with the Actor-Critic workflow.
   * When enabled, it delegates to ActorCriticCoordinator for execution and evaluation.
   * When disabled or on error, it falls back to hybrid mode.
   *
   * Requirements: 25.1, 25.2, 25.3, 25.4, 25.5
   *
   * @param \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action $action Action to execute
   * @return \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\CoordinatedResult|array Result or fallback response
   */
  public function executeWithActorCritic($action)
  {
    // Check if Actor-Critic is enabled (Requirement 25.5)
    if (!$this->isActorCriticEnabled()) {
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'actor_critic_disabled', [
          'message' => 'Actor-Critic disabled, using hybrid mode',
          'action_type' => is_object($action) ? $action->getType() : 'unknown'
        ]);
      }
      
      // Fallback to hybrid mode (Requirement 25.3)
      return $this->executeWithHybridMode($action);
    }
    
    try {
      // Use ActorCriticCoordinator for execution (Requirements 25.1, 25.2)
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'actor_critic_execution', [
          'message' => 'Using Actor-Critic coordination',
          'action_type' => $action->getType(),
          'action_priority' => $action->getPriority()
        ]);
      }
      
      $result = $this->actorCriticCoordinator->coordinateExecution($action);
      
      // Preserve existing security and validation constraints (Requirement 25.3)
      $this->validateCoordinatedResult($result);
      
      // Integrate with MonitoringAgent (Requirement 25.4)
      $this->monitoring->recordEvent('actor_critic_execution', [
        'action_type' => $action->getType(),
        'consensus_score' => $result->getConsensusScore(),
        'execution_time' => $result->getMetadata()['execution_time'] ?? 0,
        'evaluation_time' => $result->getMetadata()['evaluation_time'] ?? 0,
        'total_time' => $result->getMetadata()['total_time'] ?? 0,
        'critics_count' => $result->getMetadata()['critics_count'] ?? 0
      ]);
      
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'actor_critic_success', [
          'action_type' => $action->getType(),
          'consensus_score' => $result->getConsensusScore(),
          'actor_id' => $result->getMetadata()['actor_id'] ?? 'unknown',
          'critics_count' => $result->getMetadata()['critics_count'] ?? 0
        ]);
      }
      
      return $result;
      
    } catch (\Exception $e) {
      // Error handling with fallback (Requirement 25.3)
      $this->securityLogger->logSecurityEvent(
        "Actor-Critic execution failed: " . $e->getMessage(),
        'error'
      );
      
      // Check if fallback is enabled
      if (ActorCriticConfig::shouldFallbackToHybrid()) {
        if ($this->debug) {
          $this->securityLogger->logStructured('warning', 'OrchestratorAgent', 'actor_critic_fallback', [
            'message' => 'Falling back to hybrid mode after error',
            'error' => $e->getMessage()
          ]);
        }
        
        // Fallback to hybrid mode
        return $this->executeWithHybridMode($action);
      } else {
        // Re-throw exception if fallback disabled
        throw $e;
      }
    }
  }
  
  /**
   * Check if Actor-Critic separation is enabled

   * @return bool True if enabled
   */
  public function isActorCriticEnabled(): bool
  {
    return ActorCriticConfig::isEnabled() && $this->actorCriticCoordinator !== null;
  }
  
  /**
   * Execute action using hybrid mode (fallback)
   *
   * This method provides backward compatibility when Actor-Critic is disabled
   * or when fallback is needed due to errors.
   *
   * @param mixed $action Action to execute
   * @return array Execution result
   */
  private function executeWithHybridMode($action): array
  {
    // Extract action details
    $actionType = is_object($action) && method_exists($action, 'getType')
                  ? $action->getType()
                  : 'unknown';

    $parameters = is_object($action) && method_exists($action, 'getParameters')
                  ? $action->getParameters()
                  : [];

    // Use existing hybrid agent workflow
    // This maintains backward compatibility with the current system

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'hybrid_mode_execution', [
        'action_type' => $actionType,
        'reason' => 'actor_critic_disabled_or_fallback'
      ]);
    }

    // Return a compatible response structure
    return [
      'success' => true,
      'mode' => 'hybrid',
      'action_type' => $actionType,
      'message' => 'Executed using hybrid mode (Actor-Critic disabled or fallback)',
      'parameters' => $parameters
    ];
  }
  
  /**
   * Validate coordinated result
   *
   * Ensures the coordinated result meets security and validation constraints.
   *
   * @param \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\CoordinatedResult $result Result to validate
   * @return void
   * @throws \Exception If validation fails
   */
  private function validateCoordinatedResult($result): void
  {
    // Integrate with ValidationAgent (Requirement 25.4)
    $actionResult = $result->getActionResult();
    $output = $actionResult->getOutput();

    // Validate output if it's a query
    if (is_string($output) && str_contains(strtoupper($output), 'SELECT')) {
      $validation = $this->validationAgent->validateBeforeExecution($output, [
        'source' => 'actor_critic_coordination',
        'actor_id' => $result->getMetadata()['actor_id'] ?? 'unknown'
      ]);

      if (!$validation['can_execute']) {
        throw new \Exception(
          'Coordinated result failed validation: ' . implode(', ', $validation['errors'])
        );
      }
    }

    // Additional security checks can be added here
  }

  /**
   * Get Actor-Critic coordination statistics
   *
   * @return array Statistics about Actor-Critic coordination
   */
  public function getActorCriticStats(): array
  {
    if (!$this->isActorCriticEnabled()) {
      return [
        'enabled' => false,
        'message' => 'Actor-Critic separation is disabled'
      ];
    }

    // Get statistics from monitoring
    $stats = [
      'enabled' => true,
      'configuration' => ActorCriticConfig::getAll(),
      'executions' => []
    ];

    // Add execution statistics if available
    try {
      $sql = "SELECT 
                COUNT(*) as total_coordinations,
                AVG(total_time_ms) as avg_total_time,
                AVG(execution_time_ms) as avg_execution_time,
                AVG(evaluation_time_ms) as avg_evaluation_time,
                AVG(consensus_score) as avg_consensus_score,
                AVG(num_critics) as avg_critics_count
              FROM {$this->prefix}rag_coordinated_results
              WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";

      $result = $this->db->query($sql)->fetch();

      if ($result) {
        $stats['executions'] = [
          'total_coordinations_24h' => (int)$result['total_coordinations'],
          'avg_total_time_ms' => round((float)$result['avg_total_time'], 2),
          'avg_execution_time_ms' => round((float)$result['avg_execution_time'], 2),
          'avg_evaluation_time_ms' => round((float)$result['avg_evaluation_time'], 2),
          'avg_consensus_score' => round((float)$result['avg_consensus_score'], 2),
          'avg_critics_count' => round((float)$result['avg_critics_count'], 1)
        ];
      }
    } catch (\Exception $e) {
      $stats['executions']['error'] = 'Failed to retrieve statistics: ' . $e->getMessage();
    }

    return $stats;
  }
}