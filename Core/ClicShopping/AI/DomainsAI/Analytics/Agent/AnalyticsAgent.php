<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\AI\InterfacesAI\AgentInterface;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Cache as OMCache;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\AutonomousConfig;
use ClicShopping\AI\CoreAI\Orchestrator\CorrectionAgent;
use ClicShopping\AI\CoreAI\Orchestrator\SubAbstention\AgentAbstentionManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\FeedbackManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective;
use ClicShopping\AI\DomainsAI\Analytics\Executor\AnalyticsSqlExecutor;
use ClicShopping\AI\DomainsAI\Analytics\Executor\QueryExecutor;
use ClicShopping\AI\DomainsAI\Analytics\Executor\SqlQueryProcessor;
use ClicShopping\AI\DomainsAI\Analytics\Helper\AnalyticsErrorHandler;
use ClicShopping\AI\DomainsAI\Analytics\Helper\Formatter\AnalysisPlanAnnouncer;
use ClicShopping\AI\DomainsAI\Analytics\Helper\Detection\AmbiguousQueryDetector;
use ClicShopping\AI\DomainsAI\Analytics\Planning\AnalysisPlanner;
use ClicShopping\AI\DomainsAI\Analytics\Planning\DefaultAnalysisWindow;
use ClicShopping\AI\DomainsAI\DomainRegistry;
use ClicShopping\AI\DomainsAI\Shared\Helper\AgentResponseHelper;
use ClicShopping\AI\DomainsAI\Semantic\Processor\EnglishQueryNormalizer;
use ClicShopping\AI\Infrastructure\Cache\Cache;
use ClicShopping\AI\Infrastructure\Cache\QueryCache;
use ClicShopping\AI\Infrastructure\Cache\SubQueryCache\CacheFreshnessValidator;
use ClicShopping\AI\Infrastructure\Prompt\PromptBuilder;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * Class AnalyticsAgent
 * Handles database analytics and query processing with AI assistance
 * Manages table relationships, schema validation, and query optimization
 * Implements comprehensive security measures
 */

class AnalyticsAgent implements AgentInterface
{

  /** @inheritDoc */
  public function getAgentId(): string
  {
    return 'analytics';
  }

  /** @inheritDoc */
  public function getCapabilities(): array
  {
    return ['business_intelligence', 'nl_to_sql'];
  }

  /** @inheritDoc */
  public function getStats(): array
  {
    return $this->getQueryCacheStats();
  }

  private mixed $chat;
  private mixed $db;
  private mixed $language;
  private int $languageId;
  private ?string $sqlCacheKey = null;
  private bool $enablePromptCache;
  private bool $debug = false;
  private SecurityLogger $securityLogger;
  private string $userId;

  private mixed $maxRowsForInterpretation;

  // Delegated components
  private DatabaseSchemaManager $schemaManager;
  private SqlQueryProcessor $queryProcessor;
  private QueryExecutor $queryExecutor;
  private AnalyticsSqlExecutor $sqlExecutor;
  private QueryEnricher $queryEnricher;
  private AnalyticsQueryClassifier $queryClassifier;
  private CorrectionAgent $correctionAgent;
  private QueryCache $queryCache;
  private AmbiguousQueryDetector $ambiguityDetector;
  private PromptBuilder $promptBuilder;
  private ?AnalysisPlanner $analysisPlanner = null;
  private ?array $analysisPlan = null;
  private array $analysisPlanReserve = [];
  private bool $asksAction = false;

  private AnalysisPlanAnnouncer $planAnnouncer;
  private AnalyticsResultStage $resultStage;
  private AmbiguityHandler $ambiguityHandler;
  private AnalyticsErrorHandler $errorHandler;
  private AnalyticsObjectiveRunner $objectiveRunner;
  
  private mixed $conversationMemory = null;
  private ?AutonomousConfig $autonomousConfig = null;
  private ?AgentAbstentionManager $abstentionManager = null;
  private AnalyticsAbstentionEvaluator $abstentionEvaluator;

  /**
   * Constructor for AnalyticsAgent
   * Initializes database connection, language settings, and AI chat interface
   * Sets up schema caching, table relationships, and security components
   *
   * @param int|null $languageId Language ID for filtering results
   * @param bool $enablePromptCache Whether to enable local prompt caching
   * @param string $userId User identifier for rate limiting and auditing
   */
  public function __construct(?int $languageId = null, bool $enablePromptCache = true, string $userId = 'system')
  {
    $this->db = Registry::get('Db');
    $this->language = Registry::get('Language');
    $this->autonomousConfig = new AutonomousConfig($this->debug ?? false);
    $this->abstentionManager = new AgentAbstentionManager();

    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGpt());
    }


    // This replaces the duplicated model detection logic with a single, maintainable function
    $model = Gpt::defaultModel();
    
    try {
      $this->chat = Gpt::getChatForModel($model);
    } catch (\Exception $e) {
      // Log error and fall back to the centralized technical fallback model
      $this->debugLog("AnalyticsAgent: Error getting chat for model {$model}: " . $e->getMessage());
      $this->chat = Gpt::getChatForModel(Gpt::getTechnicalFallbackModel());
    }

    $this->userId = $userId;
    $this->languageId = $this->language->getId();

    // Initialize security components
    $this->securityLogger = new SecurityLogger();

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->enablePromptCache = $enablePromptCache;

    // Log initialization
    $this->securityLogger->logSecurityEvent("AnalyticsAgent initialized for user {$this->userId}", 'info');

    // Initialize PromptBuilder and set system message
    $this->promptBuilder = new PromptBuilder($this->language, $this->languageId, $this->debug);
    $this->chat->setSystemMessage($this->promptBuilder->getSystemMessage());

    // Bucket 3: user-facing labels, rendered verbatim in the interface language.
    $this->language->loadDefinitions('ClicShoppingAdmin/ai_response_labels');

    $this->maxRowsForInterpretation = defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_ROWS_FOR_LLM_INTERPRETATION') ? (int) CLICSHOPPING_APP_CHATGPT_RA_MAX_ROWS_FOR_LLM_INTERPRETATION : 150;

    // Initialize delegated components
    $this->schemaManager = new DatabaseSchemaManager(
      $this->db,
      $this->securityLogger,
      $this->debug
    );

    $this->queryProcessor = new SqlQueryProcessor(
      $this->securityLogger,
      $this->languageId,
      $this->debug
    );

    $this->queryExecutor = new QueryExecutor(
      $this->db,
      $this->securityLogger,
      $this->debug
    );

    $resultInterpreter = new ResultInterpreter(
      $this->getInterpreterChat(),
      new Cache($enablePromptCache),  // ResultInterpreter has its own cache instance
      $this->securityLogger,
      $this->maxRowsForInterpretation,
      $this->enablePromptCache,
      $this->debug
    );
    $this->queryEnricher = new QueryEnricher($this->promptBuilder, $this->language, $this->debug);
    $this->queryClassifier = new AnalyticsQueryClassifier($this->debug);
    $this->correctionAgent = new CorrectionAgent($userId, $languageId);
    
    // Initialize QueryCache
    $this->queryCache = new QueryCache();
    
    // Initialize AmbiguousQueryDetector with chat instance for LLM-based detection
    $this->ambiguityDetector = new AmbiguousQueryDetector($this->chat, $this->securityLogger, $this->debug);
    
    // Initialize AnalyticsErrorHandler for error recovery and messaging.
    // Built before AmbiguityHandler, which shares it to self-heal its interpretations.
    $this->errorHandler = new AnalyticsErrorHandler(
      $this->db,
      $this->correctionAgent,
      $this->queryExecutor
    );

    // Initialize AmbiguityHandler for handling ambiguous queries
    $this->ambiguityHandler = new AmbiguityHandler(
      $this->ambiguityDetector,
      $this->queryProcessor,
      $this->queryExecutor,
      $this->errorHandler,
      $this->debug
    );

    $this->sqlExecutor = new AnalyticsSqlExecutor(
      $this->queryProcessor,
      $this->queryExecutor,
      $this->errorHandler,
      $this->securityLogger,
      $this->debug
    );

    // Autonomous-agent concern extracted from this class (god-class decomposition);
    // kept for the live createLocalObjective() telemetry path (objective register).
    $this->objectiveRunner = new AnalyticsObjectiveRunner($this->autonomousConfig, $this->debug, $this->securityLogger);

    $this->planAnnouncer = new AnalysisPlanAnnouncer($this->debug);
    $this->resultStage = new AnalyticsResultStage($resultInterpreter, $this->errorHandler, $this->debug);

    // Pre-execution confidence/abstention concern extracted from this class (god-class decomposition).
    $this->abstentionEvaluator = new AnalyticsAbstentionEvaluator($this->abstentionManager, $this->debug);

    try {
      $this->schemaManager->buildDatabaseSchema();
    } catch (\Exception $e) {
      $this->securityLogger->logApplicationError("Error during AnalyticsAgent initialization: " . $e->getMessage());
    }
  }

  /**
   * Processes a complete business query including SQL generation, execution, and interpretation
   * Handles multiple query results and provides natural language interpretation
   * Includes error handling and recovery mechanisms
   *
   * @param string $question The business question in natural language
   * @param bool $includeSQL Whether to include SQL queries in the response (default: true)
   * @return array Response containing:
   *               - type: 'analytics_response' or 'error'
   *               - question: Original question
   *               - interpretation: Natural language interpretation of results
   *               - count: Number of results
   *               - sql_query: Executed SQL (if includeSQL is true)
   *               - results: Query results
   *               - corrections: Any applied corrections
   */
  public function processBusinessQuery(string $question, bool $includeSQL = true, array $feedbackContext = [], bool $skipClassification = false, bool $isSubQuery = false, string $widerRequest = ''): array
  {
    $this->debugLog("\n" . str_repeat("=", 100));
    $this->debugLog("DEBUG: AnalyticsAgent.processBusinessQuery() - START");
    $this->debugLog(str_repeat("=", 100));
    $this->debugLog("Question: '{$question}'");
    $this->debugLog("includeSQL: " . ($includeSQL ? 'true' : 'false'));
    $this->debugLog("feedbackContext items: " . count($feedbackContext));
    $this->debugLog("skipClassification: " . ($skipClassification ? 'true' : 'false'));
    
    try {
      // 0. 🆕 Detect if it's a modification and enrich with the last SQL query
      if ($this->queryClassifier->isModificationRequest($question) && $this->conversationMemory) {
        $lastSQL = $this->conversationMemory->getLastSQLQuery();
        if ($lastSQL) {
          $this->debugLog("\n--- STEP 0: Modification detected, enriching with last SQL ---");
          $question = $this->queryEnricher->enrichWithLastSQL($question, $lastSQL);
        }
      }

      // 1. Check if it's an analytics query (skip when called from PlanExecutor — already classified)
      $this->debugLog("\n--- STEP 1: Check if analytics query ---");
      $isAnalytics = $skipClassification ? true : $this->isAnalyticsQuery($question);

      $this->debugLog("isAnalyticsQuery() returned: " . ($isAnalytics ? 'TRUE' : 'FALSE') . ($skipClassification ? ' (SKIPPED - pre-classified by orchestrator)' : ''));
     
      if (!$isAnalytics) {
        $this->debugLog("NOT AN ANALYTICS QUERY - Returning early");
        return [
          'type' => 'not_analytics',
          'message' => 'This is not an analytics query',
          'question' => $question
        ];
      }

      // 2. Execute the query
      $this->debugLog("\n--- STEP 2: Execute query ---");
      $this->debugLog("Calling executeQuery()...");

      $results = $this->executeQuery($question, $feedbackContext, $skipClassification, $isSubQuery, $widerRequest);

      $this->debugLog("executeQuery() returned:");
      $this->debugLog("  type: " . ($results['type'] ?? 'unknown'));
      $this->debugLog("  has error: " . (isset($results['error']) ? 'YES' : 'NO'));
      $this->debugLog("  has results: " . (isset($results['results']) ? 'YES (' . count($results['results']) . ' rows)' : 'NO'));

      $results = $this->sqlExecutor->validateAndReexecuteSqlDates($results, $question);
      $results = $this->sqlExecutor->guardSensitiveOutput($results, $question, $this->userId);

      if (($results['type'] ?? 'unknown') === 'error') {
        $this->debugLog("ERROR in executeQuery: " . ($results['error'] ?? 'unknown'));
        return $results;
      }

      // Handle unknown or incomplete results (early returns)
      $earlyReturn = $this->resultStage->resolveEarlyResultReturn($results, $question);
      if ($earlyReturn !== null) {
        return $earlyReturn;
      }

      // 2.5. Let the active domain add columns to the rows (forecast, risk, ...)
      $results = $this->resultStage->enrichResultRows($results);

      // 2.75. Drop the lines whose margin has no cost basis, BEFORE interpretation: pruning after
      // it would leave the prose quoting the figure the guard withheld.
      $withheld = $this->planAnnouncer->withholdRowsWithoutCostBasis($results);
      $results = $withheld['results'];

      // 3. Interpret the results
      $this->debugLog("\n--- STEP 3: Interpret results ---");

      $interpretation = $this->resultStage->determineInterpretation($question, $results, $this->asksAction);

      // 3.5. 🆕 Update cache with interpretation
      if (!empty($results['sql_query']) && !($results['cached'] ?? false)) {
        $this->debugLog("\n--- STEP 3.5: Update cache with interpretation ---");
        try {
          $this->queryCache->set(
            $question,
            $results['sql_query'],
            $results['results'],
            [
              'entity_id' => $results['entity_id'] ?? null,
              'entity_type' => $results['entity_type'] ?? null,
              'interpretation' => $interpretation
            ]
          );
          $this->debugLog("✅ Cache updated with interpretation");
        } catch (\Exception $e) {
          $this->debugLog("⚠️ Failed to update cache with interpretation: " . $e->getMessage());
        }
      } elseif ($results['cached'] ?? false) {
        $this->debugLog("ℹ️ Skipping cache update (result was from cache)");
      }

      // 4. Construire la réponse
      $this->debugLog("\n--- STEP 4: Build response ---");
      $response = [
        'type' => 'analytics_response',
        'question' => $question,
        'interpretation' => $interpretation,
        'count' => $results['count'],
        'results' => $results['results'],
        'cached' => $results['cached'] ?? false,  // 🆕 Propagate cached flag
        'derived_columns' => $results['derived_columns'] ?? [],
        'sensitive_output' => $results['sensitive_output'] ?? null,
      ];

      $response += self::ambiguityKeysOf($results);

      // Add cache metadata if available
      if (isset($results['cache_age'])) {
        $response['cache_age'] = $results['cache_age'];
      }

      $this->persistAnalysisPlanContext($response, $isSubQuery);

      $this->planAnnouncer->announce($response, $this->analysisPlan, $this->analysisPlanReserve, $withheld['withheld'], $withheld['share'], $withheld['no_sale']);

      if ($includeSQL) {
        $response['sql_query'] = $results['sql_query'] ?? 'N/A';
        $response['original_sql_query'] = $results['original_sql_query'] ?? $results['sql_query'] ?? 'N/A';
        if (!empty($results['corrections'])) {
          $response['corrections'] = $results['corrections'];
        }
      }

      // Validation gate — closes the agentic critique loop (see AnalyticsResultStage::applyValidationGate()).
      $this->resultStage->applyValidationGate(
        $question,
        $includeSQL,
        $interpretation,
        $results,
        $response,
        $this->conversationMemory,
        fn(string $q, array $feedback): array => ['results' => $this->sqlExecutor->guardSensitiveOutput($this->executeQuery($q, $feedback), $q, $this->userId), 'asks_action' => $this->asksAction]
      );

      // 5. Extraire entity_id si présent
      $this->debugLog("\n--- STEP 5: Extract entity info ---");
      if (!empty($results['results'])) {
        $extracted = $this->queryExecutor->extractEntityIdFromResults($results['results']);
        if ($extracted['entity_id'] !== null) {
          $response['entity_id'] = $extracted['entity_id'];
          $response['entity_type'] = $extracted['entity_type'];
          $this->debugLog("Extracted entity_id: {$extracted['entity_id']}, type: {$extracted['entity_type']}");
        } else {
          $this->debugLog("No entity_id extracted from results");
        }
      }

      $this->debugLog("\n--- FINAL RESPONSE ---");
      $this->debugLog((string) json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      $this->debugLog(str_repeat("=", 100) . "\n");

      return $response;

    } catch (\Exception $e) {
      $this->debugLog("\n--- EXCEPTION ---");
      $this->debugLog("Error: " . $e->getMessage());
      $this->debugLog("Trace: " . $e->getTraceAsString());
      $this->debugLog(str_repeat("=", 100) . "\n");

      return [
        'type' => 'error',
        'message' => 'Error processing business query: ' . $e->getMessage(),
        'question' => $question,
      ];
    }
  }

  /**
   * Determines if a query is analytical in nature.
   * Public API kept for external callers (e.g. MultiDBRAGManager); delegates to AnalyticsQueryClassifier.
   *
   * @param string $query Query to analyze
   * @return bool True if query is analytical, false otherwise
   */
  public function isAnalyticsQuery(string $query): bool
  {
    return $this->queryClassifier->isAnalyticsQuery($query);
  }

  /**
   * Executes the generated SQL query and handles errors
   * Implements error recovery mechanisms
   * Logs errors when debug mode is enabled
   * Provides fallback responses on complete failure
   *
   * @param string $question The business question in natural language
   * @param array $feedbackContext Optional feedback context for query enrichment
   * @param bool $skipClassification When true, the orchestrator already classified this as analytics
   * @param bool $isSubQuery When true, the query is a decomposed fragment (from PlanExecutor) and the
   *                         ambiguity gate is skipped — a fragment has no period of its own to ask about
   * @param string $widerRequest The whole request the fragment was cut from, already English — where
   *                             that period was stated. Empty when the question stands alone.
   * @return array Results array containing:
   *               - type: 'success' or 'error'
   *               - message: Result message or error description
   *               - query: Original question
   *               - suggestion: Error fix suggestion if applicable
   *               - recovery_attempted: Boolean indicating if recovery was attempted
   */
  public function executeQuery(string $question, array $feedbackContext = [], bool $skipClassification = false, bool $isSubQuery = false, string $widerRequest = ''): array
  {
    $this->debugLog(str_repeat("-", 100));
    $this->debugLog("DEBUG: AnalyticsAgent.executeQuery() - START");
    $this->debugLog("-" . str_repeat("-", 99));
    $this->debugLog("Question: '{$question}'");
    $this->debugLog("Feedback context items: " . count($feedbackContext));

    $safeQuestion = InputValidator::validateParameter($question, 'string');
    if ($safeQuestion !== $question) {
      $this->debugLog("Question was sanitized");
      $question = $safeQuestion;
    }

    try {
      $this->debugLog("\nCalling processAnalyticsQuery()...");
      $result = $this->processAnalyticsQuery($question, $feedbackContext, $skipClassification, $isSubQuery, $widerRequest);

      $this->debugLog("processAnalyticsQuery() returned:");
      $this->debugLog("  type: " . ($result['type'] ?? 'unknown'));
      $this->debugLog("  sql_query: " . ($result['sql_query'] ?? 'N/A'));
      $this->debugLog("  count: " . ($result['count'] ?? 0));

      $this->debugLog("-" . str_repeat("-", 99) . "\n");
      return $result;

    } catch (\Exception $e) {
      $this->debugLog("EXCEPTION: " . $e->getMessage());
      $this->debugLog("-" . str_repeat("-", 99) . "\n");

      return [
        'type' => 'error',
        'message' => $e->getMessage(),
        'query' => $question,
      ];
    }
  }

  /**
   * Executes a query with error recovery mechanisms
   * Implements caching, query generation, validation, and error handling
   * Supports multiple query execution and result aggregation
   *
   * @param string $question The business question to process
   * @return array Results containing:
   *               - type: 'analytics_results'
   *               - query: Original question
   *               - sql_query: Executed SQL query
   *               - original_sql_query: Pre-correction SQL query
   *               - corrections: Array of applied corrections
   *               - results: Query results
   *               - count: Number of results
   * @param bool $skipClassification When true, the orchestrator already classified this as analytics
   * @param bool $isSubQuery When true (decomposed fragment from PlanExecutor), the ambiguity-detection
   *                         stage is skipped — see STEP 0/0.5 below
   * @param string $widerRequest The whole request this fragment was cut from, already English. Empty
   *                             when the question stands alone.
   *
   * A failure is RETURNED as a 'error' response carrying the ambiguity metadata, never thrown:
   * that metadata exists only in this scope.
   */
  private function processAnalyticsQuery(string $question, array $feedbackContext = [], bool $skipClassification = false, bool $isSubQuery = false, string $widerRequest = ''): array
  {
    $this->debugLog(str_repeat(".", 100));
    $this->debugLog("AnalyticsAgent.processAnalyticsQuery() - START", "QUERY");
    $this->debugLog("Feedback context items: " . count($feedbackContext), "QUERY");

    // The user's question is answered as asked; only the string SENT TO GENERATION may be resolved
    // by the ambiguity branch. Keeping them apart is what lets the response quote the real question.
    $questionForGeneration = $question;
    $ambiguityAnalysis = ['is_ambiguous' => false];

    // The agent outlives one question (sub-queries reuse it): a plan left over from the
    // previous one would silently key the SQL cache of this one.
    $this->analysisPlan = null;
    $this->analysisPlanReserve = [];
    $this->asksAction = false;

    try {

      $abstainResponse = $this->abstentionEvaluator->evaluate($question, $feedbackContext);
      if ($abstainResponse !== null) {
        return $abstainResponse;
      }

      // Ambiguity detection — the user-query clarification gate.
      if ($isSubQuery) {
        $ambiguityAnalysis = [
          'is_ambiguous' => false,
          'skipped' => true,
          'reason' => 'decomposed_subquery',
          'confidence' => 1.0,
        ];
      } else {
        $this->debugLog("--- STEP 0: Translate query for ambiguity detection ---", "TRANSLATION");

        // This ensures the LLM can properly detect explicit keywords in any language
        // Use a simple, fast translation that focuses on keywords
        $queryForAmbiguity = EnglishQueryNormalizer::normalize($question);
        $this->debugLog("Original query: {$question}", "TRANSLATION");
        $this->debugLog("Translated for ambiguity: {$queryForAmbiguity}", "TRANSLATION");

        $this->debugLog("--- STEP 0.5: Check for ambiguous query ---", "AMBIGUITY");

        $ambiguityAnalysis = $this->analyzeAmbiguity($question, $queryForAmbiguity);
      }

      if ($ambiguityAnalysis['is_ambiguous']) {
        $this->debugLog("AMBIGUOUS QUERY DETECTED!", "AMBIGUITY");
        $this->debugLog("Type: " . $ambiguityAnalysis['ambiguity_type'], "AMBIGUITY");
        $this->debugLog("Recommendation: " . $ambiguityAnalysis['recommendation'], "AMBIGUITY");
        $this->debugLog("Interpretations: " . json_encode(array_column($ambiguityAnalysis['interpretations'], 'type')), "AMBIGUITY");

        // Handle based on recommendation
        if ($ambiguityAnalysis['recommendation'] === 'generate_both') {
          $this->debugLog("→ Generating multiple interpretations", "AMBIGUITY");

          // Create SQL generator closure for AmbiguityHandler
          $sqlGenerator = function(string $modifiedQuery) use ($feedbackContext) {
            // Enrich question with feedback context
            $enrichedQuestion = $this->queryEnricher->enrichWithFeedback($modifiedQuery, $feedbackContext, $this->conversationMemory);

            // Generate SQL using LLM
            $rawResponse = $this->chat->generateText($enrichedQuestion);

            // Extract SQL
            $sqlQueries = $this->queryProcessor->extractSqlQueries($rawResponse);

            if (empty($sqlQueries)) {
              $sqlQueries = [$this->queryProcessor->cleanSqlResponse($rawResponse)];
            }

            return $sqlQueries[0] ?? '';
          };

          return $this->ambiguityHandler->handleAmbiguousQuery($question, $ambiguityAnalysis, $sqlGenerator);
        } elseif ($ambiguityAnalysis['recommendation'] === 'clarify') {
          $this->debugLog("→ Requesting clarification from user", "AMBIGUITY");
          return $this->ambiguityHandler->requestClarification($question, $ambiguityAnalysis);
        } else {
          $resolution = $this->ambiguityDetector->resolveDefaultInterpretation($questionForGeneration, $ambiguityAnalysis);
          $questionForGeneration = $resolution['query'];
          $ambiguityAnalysis['applied_interpretation'] = $resolution['type'];

          $this->debugLog("→ Applying default interpretation: " . var_export($resolution['type'], true), "AMBIGUITY");
        }
      } else {
        if (isset($ambiguityAnalysis['skipped']) && $ambiguityAnalysis['skipped']) {
          $this->debugLog("⚡ Ambiguity detection SKIPPED (reason: {$ambiguityAnalysis['reason']}, confidence: {$ambiguityAnalysis['confidence']})", "OPTIMIZATION");
        } else {
          $this->debugLog("No ambiguity detected - proceeding normally", "AMBIGUITY");
        }
      }

      $planner = $this->analysisPlanner();

      if ($planner !== null) {
        $this->debugLog("--- STEP 0.75: Build the analysis plan ---", "PLAN");

        $planResult = $planner->plan($this->translateForGeneration($questionForGeneration), $widerRequest);
        $this->analysisPlan = $planResult['plan'];
        $this->asksAction = $planResult['act'] ?? false;

        if ($this->analysisPlan === null) {
          if ($planResult['no_metric_proposed'] ?? false) {
            // Not every analytics question aggregates a metric: a stock level, a list of active
            // promotions or one product's price carry none. Same honest degradation as an empty
            // catalogue — run without a plan, exactly as the path did before the stage existed.
            $this->debugLog("PLAN SKIPPED: the question carries no catalogue metric", "PLAN");
          } else {
            // Refusing is not failing silently: the answer must name what could not be honoured.
            $this->debugLog("PLAN REFUSED: " . json_encode($planResult['errors']), "PLAN");

            return $this->analysisPlanRefusal($question, $planResult, $ambiguityAnalysis);
          }
        }

        if (($this->analysisPlan['periods']['period_missing'] ?? false) === true) {
          $this->debugLog("PLAN WITHOUT WINDOW: asking the user for the period", "PLAN");

          $clarification = AgentResponseHelper::buildClarificationRequest($question, 'time');
          // Distinguishable from the ambiguity stage, which asks the same question earlier.
          $clarification['clarification_source'] = 'analysis_plan';

          return $clarification;
        }

        if ($planResult['unsatisfiable'] !== []) {
          $this->debugLog("PLAN PARTIAL: " . json_encode($planResult['unsatisfiable']), "PLAN");

          if ($this->analysisPlan !== null) {
            $this->analysisPlanReserve = $planResult['unsatisfiable'];
          }
        }
      }

      $this->debugLog("--- STEP 1: Check QueryCache ---", "CACHE");

      $cachedResponse = $this->checkQueryCache($question, $ambiguityAnalysis);
      if ($cachedResponse !== null) {
        return $cachedResponse;
      }

      $this->debugLog("--- STEP 2: Generate SQL from question ---", "SQL");

      $sqlQueries = $this->generateSqlQueries($questionForGeneration, $feedbackContext);

      $this->debugLog("--- STEP 3: Execute SQL queries ---", "EXECUTION");
      return $this->sqlExecutor->executeSqlQueries($sqlQueries, $question, $this->ambiguityMetadata($ambiguityAnalysis), $this->analysisPlan, $this->sqlCacheKey);

    } catch (\Exception $e) {
      $this->debugLog("\nFINAL EXCEPTION: " . $e->getMessage());
      $this->debugLog("." . str_repeat(".", 99) . "\n");

      // Returned, not rethrown: the ambiguity metadata only exists in this scope, and a failure
      // rebuilt one frame higher answers an ambiguous question without ever saying it was one.
      return [
        'type' => 'error',
        'message' => $e->getMessage(),
        'query' => $question,
        ...$this->ambiguityMetadata($ambiguityAnalysis),
      ];
    }
  }

  /**
   * The analysis planner, built on FIRST USE and not in the constructor: the domain registry
   * is populated by whoever instantiates the domain App, which may happen after this agent.
   *
   * Returns null when the active domain declares no metric catalogue. Without one, every
   * metric comes back unsatisfiable and the stage would refuse EVERY question; the path then
   * runs exactly as it did before the stage existed, which is the honest degradation.
   *
   * @return AnalysisPlanner|null Planner, or null when the plan stage cannot apply
   */
  private function analysisPlanner(): ?AnalysisPlanner
  {
    if ($this->analysisPlanner !== null) {
      return $this->analysisPlanner;
    }

    $domainApp = DomainRegistry::getInstance()->getActiveApp();
    $catalog = ($domainApp !== null && method_exists($domainApp, 'getMetricCatalog'))
      ? $domainApp->getMetricCatalog()
      : [];

    if ($catalog === []) {
      $this->debugLog("PLAN STAGE OFF: the active domain declares no metric catalogue", "PLAN");

      return null;
    }

    $orderSideDimensions = method_exists($domainApp, 'getOrderSideDimensions') ? $domainApp->getOrderSideDimensions() : [];

    $terms = method_exists($domainApp, 'getMetricTerms') ? $domainApp->getMetricTerms() : [];

    $this->analysisPlanner = new AnalysisPlanner($catalog, $this->languageId, $orderSideDimensions, $terms);

    return $this->analysisPlanner;
  }

  /**
   * Attach the analysis plan to the response and record it as conversation context.
   *
   * Sink-safe invariant (CONC-1): only the final turn writes context. A sub-query step
   * still exposes its plan on its own response, but never writes the shared last-plan sink —
   * that write belongs to the single top-level turn, so concurrent sub-queries cannot race it.
   *
   * @param array $response Response being assembled, mutated in place
   * @param bool $isSubQuery Whether this call is a decomposed sub-query step
   * @return void
   */
  private function persistAnalysisPlanContext(array &$response, bool $isSubQuery): void
  {
    if ($this->analysisPlan === null) {
      return;
    }

    $response['analysis_plan'] = $this->analysisPlan;

    if (!$isSubQuery && $this->conversationMemory !== null && method_exists($this->conversationMemory, 'setLastAnalysisPlan')) {
      $this->conversationMemory->setLastAnalysisPlan($this->analysisPlan);
    }
  }

  /**
   * Build the response of a refused plan — a TERMINAL answer, not a technical incident.
   *
   * Rendered as `type => 'error'` with a `text_response`: that is the only shape the three
   * restitution gates (ResultValidator, ResultSynthesizer, ResultFormatter::determinePrimaryType)
   * let through unconditionally. Any other type is replaced downstream by a generic
   * "no results found", which is exactly the lie this stage exists to avoid.
   *
   * `analysis_plan_refused` stays as the discriminating marker, so the refusal remains
   * distinguishable from a failed query.
   *
   * @param string $question Question as the user asked it
   * @param array $planResult Planner verdict: plan, unsatisfiable, errors
   * @param array $ambiguityAnalysis Detector verdict for this request
   * @return array Terminal response
   */
  private function analysisPlanRefusal(string $question, array $planResult, array $ambiguityAnalysis): array
  {
    $elements = array_values(array_filter(array_map(
      static fn(array $entry): string => (string)($entry['label'] ?? '') !== ''
        ? (string)$entry['label']
        : (string)($entry['element'] ?? ''),
      $planResult['unsatisfiable']
    )));

    $message = $elements === []
      ? CLICSHOPPING::getDef('text_analysis_plan_refused')
      : CLICSHOPPING::getDef('text_analysis_plan_refused_details', ['elements' => implode(', ', $elements)]);

    return [
      'type' => 'error',
      'error' => 'analysis_plan_refused',
      'analysis_plan_refused' => true,
      'message' => $message,
      'text_response' => $message,
      'response' => $message,
      'question' => $question,
      'query' => $question,
      'unsatisfiable' => $planResult['unsatisfiable'],
      'errors' => $planResult['errors'],
      ...$this->ambiguityMetadata($ambiguityAnalysis),
    ];
  }

  /**
   * Apply the freshness rule of the result cache to the SQL cache, which had none.
   *
   * Both caches are keyed on the QUESTION, which does not move when the data behind the answer
   * does — nor when the merchant rewrites the meaning of a status, which changes what the cached
   * SQL means without changing a single table it references. The result cache has refused such
   * an entry since CacheFreshnessValidator; this one survived its full hour.
   *
   * Fails OPEN: an unreadable mtime or a validator error keeps the entry, as before.
   *
   * @param OMCache $sqlCache Cache entry already known to exist
   * @return bool True when the cached SQL may still be used
   */
  private function cachedSqlIsFresh(OMCache $sqlCache): bool
  {
    $builtAt = $sqlCache->getTime();

    if ($builtAt === false) {
      return true;
    }

    $sql = $sqlCache->get();

    if (!is_string($sql) || trim($sql) === '') {
      return true;
    }

    $fresh = (new CacheFreshnessValidator($this->debug))->isFreshSinceAge($sql, max(0, time() - (int)$builtAt));

    if (!$fresh) {
      $this->debugLog("SQL CACHE STALE - a source table moved since it was built", "CACHE");
    }

    return $fresh;
  }

  /**
   * Build the key of the SQL-generation cache ('Rag/SQL').
   *
   * Keyed on the ENGLISH form, like every other cache on this path (CacheKeyGenerator): the SQL
   * is generated from the English query, so two formulations that normalise to the same string
   * must share the entry instead of paying for the same generation twice. Costs no LLM call —
   * EnglishQueryNormalizer memoised the translation earlier in the request (abstention, STEP 1).
   *
   * The PLAN is part of the key too: the same question planned differently must not replay the
   * SQL of the previous plan — without it, a one-hour-old entry short-circuits the whole stage.
   *
   * @param string $englishQuestion Question already normalised by translateForGeneration()
   * @param array $feedbackContext Feedback context, part of the key: it changes the prompt
   * @return string Cache key
   */
  private function buildSqlCacheKey(string $englishQuestion, array $feedbackContext): string
  {
    return md5($englishQuestion . json_encode($feedbackContext) . json_encode($this->analysisPlan));
  }

  /**
   * STEP 2: produce the SQL queries for the question (SQL cache hit, else LLM generation +
   * extraction/cleaning, with fresh results cached). Extracted verbatim from processAnalyticsQuery.
   * Throws when no valid SQL can be extracted (caught by the caller's try).
   *
   * @param string $question
   * @param array $feedbackContext
   * @return array The extracted SQL queries (first element is the primary query)
   */
  private function generateSqlQueries(string $question, array $feedbackContext): array
  {
    $englishQuestion = $this->translateForGeneration($question);
    $originalWording = EnglishQueryNormalizer::originalOf($englishQuestion) ?? '';
    $cacheKey = $this->buildSqlCacheKey($englishQuestion . $originalWording, $feedbackContext);
    $this->sqlCacheKey = $cacheKey;
    $sqlCache = new OMCache($cacheKey, 'Rag/SQL');

    if ($sqlCache->exists(60) && $this->cachedSqlIsFresh($sqlCache)) { // 60 minutes = 1 hour
      $cachedSQL = $sqlCache->get();
      if ($cachedSQL !== null && !empty($cachedSQL)) {
        $this->debugLog("✅ SQL CACHE HIT - Duration: < 10ms", "CACHE");
        $this->securityLogger->logSecurityEvent(
          "SQL generation cache hit",
          'info',
          [
            'query' => substr($question, 0, 100),
            'cache_key' => $cacheKey,
            'time_saved_estimate' => '1-2 seconds'
          ]
        );

        // Use cached SQL directly
        $rawResponse = $cachedSQL;
        $sqlQueries = [$cachedSQL];
        $this->debugLog("Using cached SQL: " . substr($cachedSQL, 0, 200) . "...", "SQL");
      }
    }

    // Generate SQL via LLM only if not cached
    if (!isset($sqlQueries)) {
      $this->debugLog("❌ SQL CACHE MISS - Calling LLM", "CACHE");

      // Update system message with Schema RAG if enabled
      $this->updateSystemMessageForQuery($englishQuestion);

      // Enrich question with feedback context for learning
      $enrichedQuestion = $this->queryEnricher->enrichWithFeedback($englishQuestion, $feedbackContext, $this->conversationMemory);
      $enrichedQuestion = $this->promptBuilder->enrichWithOriginalQuestion($enrichedQuestion, $englishQuestion);

      $planBlock = $this->analysisPlanner?->describeForPrompt($this->analysisPlan) ?? '';

      if ($planBlock !== '') {
        $enrichedQuestion = $planBlock . "\n\n" . $enrichedQuestion;
      }

      $this->debugLog("Calling chat.generateText()...", "SQL");
      $startTime = microtime(true);
      $rawResponse = $this->chat->generateText($enrichedQuestion);
      $duration = (microtime(true) - $startTime) * 1000;
      $this->debugLog("Raw response from GPT (first 500 chars): " . substr($rawResponse, 0, 500), "SQL");
      $this->debugLog("LLM SQL generation took: " . round($duration, 2) . " ms", "PERFORMANCE");
    }

    // Extract SQL queries (skip if we already have template SQL)
    if (!isset($sqlQueries)) {
      $this->debugLog("Extracting SQL from response...", "SQL");
      $sqlQueries = $this->queryProcessor->extractSqlQueries($rawResponse);
      $this->debugLog("Extracted SQL queries count: " . count($sqlQueries), "SQL");
    }

    foreach ($sqlQueries as $idx => $sql) {
      $this->debugLog("SQL Query " . ($idx + 1) . ": " . substr($sql, 0, 200) . "...", "SQL");
    }

    if (empty($sqlQueries)) {
      $this->debugLog("NO SQL EXTRACTED - Trying to clean response", "SQL");
      $sqlQueries = [$this->queryProcessor->cleanSqlResponse($rawResponse)];
      $this->debugLog("After cleaning: " . substr($sqlQueries[0], 0, 200), "SQL");
    }

    if (empty($sqlQueries[0])) {
      $this->debugLog("ERROR: No valid SQL query extracted", "SQL");
      throw new \Exception('No valid SQL query could be extracted');
    }

    // The model is told to answer a fixed sentence when the injected schema cannot answer
    // (rag_analytics_agent). That sentence is not a query: it must never be run, and never cached.
    if (!$this->queryProcessor->looksLikeSqlStatement($sqlQueries[0])) {
      $this->debugLog("MODEL DECLINED - the generation is not a SQL statement: " . substr($sqlQueries[0], 0, 200), "SQL");
      throw new \Exception('The model declined to generate SQL for this question');
    }

    // Only cache if this was a fresh LLM generation (not from cache)
    if (!isset($cachedSQL)) {
      $this->debugLog("💾 Saving SQL to cache (TTL: 1 hour)", "CACHE");
      $sqlCache->save($sqlQueries[0]);
      $this->securityLogger->logSecurityEvent(
        "SQL generation cached",
        'info',
        [
          'query' => substr($question, 0, 100),
          'cache_key' => $cacheKey,
          'sql_length' => strlen($sqlQueries[0])
        ]
      );
    }

    return $sqlQueries;
  }

  /**
   * STEP 1: QueryCache lookup. Returns the cached analytics_results response on hit, or null
   * to proceed with SQL generation. Extracted verbatim from processAnalyticsQuery.
   *
   * @param string $question
   * @param array $ambiguityAnalysis Ambiguity metadata echoed into the cached response
   * @return array|null Cached response, or null on cache miss
   */
  private function checkQueryCache(string $question, array $ambiguityAnalysis): ?array
  {
    // Check QueryCache FIRST
    $cacheResult = $this->queryCache->get($question);
    if ($cacheResult !== null) {
      $this->debugLog("CACHE HIT! Returning cached results", "CACHE");
      $this->debugLog("Cache entry age: " . ($cacheResult['cache_age'] ?? 'unknown') . " seconds", "CACHE");

      $response = [
        'type' => 'analytics_results',
        'query' => $question,
        'sql_query' => $cacheResult['sql_query'],
        'original_sql_query' => $cacheResult['sql_query'],
        'corrections' => [],
        'results' => $cacheResult['results'],
        'count' => $cacheResult['result_count'],
        'entity_id' => $cacheResult['entity_id'] ?? null,
        'entity_type' => $cacheResult['entity_type'] ?? null,
        'interpretation' => $cacheResult['interpretation'] ?? null,  // 🆕 Return cached interpretation
        // A replayed answer settled the same ambiguity as the first one: same producer, same keys.
        ...$this->ambiguityMetadata($ambiguityAnalysis),
        'cached' => true,
        'cache_age' => $cacheResult['cache_age'] ?? null
      ];

      return $response;
    }
    $this->debugLog("CACHE MISS - Generating new query", "CACHE");

    return null;
  }

  /**
   * Ambiguity metadata echoed into every response built for this request.
   *
   * `applied_interpretation` is present ONLY when a reading was chosen on the user's behalf
   * (`use_default`): its presence is the signal that the answer settles an ambiguity the user
   * never settled. How that is shown to the user is not decided here.
   *
   * @param array $ambiguityAnalysis Detector verdict for this request
   * @return array<string, mixed> Metadata keys to merge into the response
   */
  private function ambiguityMetadata(array $ambiguityAnalysis): array
  {
    $isAmbiguous = $ambiguityAnalysis['is_ambiguous'] ?? false;

    $metadata = [
      'ambiguous' => $isAmbiguous,
      'ambiguity_type' => $ambiguityAnalysis['ambiguity_type'] ?? null,
      // Readings are a LIST of descriptors: their `type` names them, their offsets name nothing.
      'interpretations' => $isAmbiguous ? array_column($ambiguityAnalysis['interpretations'] ?? [], 'type') : [],
    ];

    if (isset($ambiguityAnalysis['applied_interpretation'])) {
      $metadata['applied_interpretation'] = $ambiguityAnalysis['applied_interpretation'];
    }

    return $metadata;
  }

  /**
   * The ambiguity metadata carried by an execution result, for the rebuilt success response.
   *
   * @param array $results Execution result, already holding ambiguityMetadata()
   * @return array<string, mixed> Only the ambiguity keys present in $results
   */
  private static function ambiguityKeysOf(array $results): array
  {
    return array_intersect_key($results, array_flip(['ambiguous', 'ambiguity_type', 'interpretations', 'applied_interpretation']));
  }

  /**
   * STEP 0.5 (compute): runs the ambiguity analysis for the query.
   *
   * The former high-confidence skip is gone: it treated "the classifier is sure this is
   * analytics" as "this query is unambiguous", which are different things. Measured 2026-07-28 —
   * "give me the evolution of revenue", "revenue by supplier", "seasonal coefficients of sales"
   * and "show me revenue" all classify analytics at exactly 0.90, so the detector was never
   * called for precisely the queries that must ask for their period.
   *
   * @param string $question Original question
   * @param string $queryForAmbiguity Query translated for ambiguity detection
   * @return array The ambiguity analysis result
   */
  private function analyzeAmbiguity(string $question, string $queryForAmbiguity): array
  {
    // Detector analyses are cached, so the cost is one call per distinct query, not per request.
    return DefaultAnalysisWindow::demoteTimeAmbiguity($this->ambiguityDetector->detectAmbiguity($queryForAmbiguity));
  }

  /**
   * Helper method for debug logging
   * Only logs when debug mode is enabled
   * Uses structured logging format with timestamp and context
   *
   * @param string $message Log message
   * @param string $context Optional context identifier (e.g., 'CACHE', 'SQL', 'VALIDATION')
   * @param array $data Optional structured data to log
   * @return void
   */
  private function debugLog(string $message, string $context = '', array $data = []): void
  {
    if (!$this->debug) {
      return;
    }

    $logMessage = $message;

    if (!empty($context)) {
      $logMessage = "[{$context}] {$message}";
    }

    if (!empty($data)) {
      $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    error_log($logMessage);
  }

  /**
   * Set the conversation memory instance
   * Allows AnalyticsExecutor to inject ConversationMemory
   * This is needed to access last_entity context for contextual query resolution.
   *
   * @param mixed $conversationMemory ConversationMemory instance
   * @return void
   */
  public function setConversationMemory($conversationMemory): void
  {
    $this->conversationMemory = $conversationMemory;
    
    $this->debugLog("[AnalyticsAgent] ConversationMemory set successfully");
  }

  /**
   * Normalise a query to English for the generation step.
   *
   * Schema retrieval (ColumnIndex) and the SQL system message (rules, examples) are both
   * English-only, so both must be driven by the English form. Delegates to the single
   * chokepoint so generation reads the same string as ambiguity, classification and abstention.
   *
   * @param string $query User query, in the interface language
   * @return string English query, or the original one on failure
   */
  private function translateForGeneration(string $query): string
  {
    return EnglishQueryNormalizer::normalize($query);
  }


  /**
   * Build the chat dedicated to result interpretation
   *
   * Falls back to the shared chat if the provider cannot give a second instance: a degraded
   * (verbose) prompt is preferable to losing the interpretation entirely.
   *
   * @return mixed Chat instance carrying the interpreter system message
   */
  private function getInterpreterChat(): mixed
  {
    try {
      $chat = Gpt::getChatForModel(Gpt::defaultModel());
      $chat->setSystemMessage($this->promptBuilder->getInterpreterSystemMessage());

      return $chat;
    } catch (\Exception $e) {
      $this->debugLog('AnalyticsAgent: interpreter chat failed, falling back to shared chat: ' . $e->getMessage());

      return $this->chat;
    }
  }

  /**
   * Update system message for query (Schema RAG)
   *
   * If Schema RAG is enabled, updates the system message with only relevant
   * table schemas based on the query, reducing context size for small models
   *
   * @param string $query Query already normalised to English by translateForGeneration()
   * @return void
   */
  private function updateSystemMessageForQuery(string $query): void
  {
    $useSchemaRAG = defined('CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG') && CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG == 'True';

    if (!$useSchemaRAG) {
      return; // Schema RAG disabled, use cached system message
    }

    try {
      $modelName = Gpt::defaultModel();
      // Get model name from chat instance

      // Try to get actual model name from chat config
      if (method_exists($this->chat, 'getModel')) {
        $modelName = $this->chat->getModel();
      }

      $this->debugLog("Updating system message with Schema RAG", "SCHEMA_RAG");
      $this->debugLog("Model: {$modelName}", "SCHEMA_RAG");

      // Get query-specific system message. The plan (built at STEP 0.75) drives the
      // schema join map: the window is traversed WITH the plan, never the raw question.
      $systemMessage = $this->promptBuilder->getSystemMessage('analytics', $query, $modelName, $this->analysisPlan);

      // Update chat system message
      $this->chat->setSystemMessage($systemMessage);

      $tokenCount = (int)ceil(strlen($systemMessage) / 4);
      $this->debugLog("System message updated: " . strlen($systemMessage) . " chars (~{$tokenCount} tokens)", "SCHEMA_RAG");

    } catch (\Exception $e) {
      // Log error but don't fail the query
      $this->debugLog("[AnalyticsAgent] Schema RAG update failed: " . $e->getMessage());

      // Fallback: system message remains unchanged (uses cached full schema)
      $this->debugLog("Schema RAG failed, using cached system message", "SCHEMA_RAG");
    }
  }

  /**
   * Get query cache statistics
   * Enriches statistics with calculated metrics for the dashboard
   */
  public function getQueryCacheStats(): array
  {
    $baseStats = $this->queryCache->getStats();

    // Calculate additional metrics for dashboard
    $totalRequests = ($baseStats['total_hits'] ?? 0) + ($baseStats['total_misses'] ?? 0);
    $hitRate = $totalRequests > 0 ? round(($baseStats['total_hits'] / $totalRequests) * 100, 1) : 0;

    // Estimate time saved (assuming ~10s saved per cache hit vs full query)
    $avgTimeSavedMs = 10000; // 10 seconds in ms
    $totalTimeSavedMs = ($baseStats['total_hits'] ?? 0) * $avgTimeSavedMs;

    // Estimate average result count (default to 1 if not available)
    $avgResultCount = $baseStats['avg_result_count'] ?? 1;

    return array_merge($baseStats, [
      'hit_rate' => $hitRate,
      'total_misses' => $totalRequests - ($baseStats['total_hits'] ?? 0),
      'total_time_saved_ms' => $totalTimeSavedMs,
      'avg_time_saved_ms' => $avgTimeSavedMs,
      'avg_result_count' => $avgResultCount,
      'total_requests' => $totalRequests
    ]);
  }
  
  /**
   * @return bool Flushes the SQL query cache
   */
  public function flushQueryCache(): bool
  {
    return $this->queryCache->flush();
  }

  // ========================================
  // AUTONOMOUS AGENT INTERFACE IMPLEMENTATION
  // ========================================

  /**
   * Create a local objective for analytics optimization
   *
   * AnalyticsAgent can create objectives for:
   * - Query performance optimization
   * - Schema analysis improvements
   * - Cache hit rate optimization
   * - Error rate reduction
   *
   * @param string $goalStatement Clear description of the goal
   * @param array $successCriteria Measurable success criteria
   * @param string $priority Priority level
   * @return \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective
   */
  public function createLocalObjective(
    string $goalStatement,
    array $successCriteria,
    string $priority
  ): LocalObjective {
    return $this->objectiveRunner->createLocalObjective($goalStatement, $successCriteria, $priority);
  }

  /**
   * Receive and process feedback from peer agents
   *
   * @param array $feedback Feedback from peer agent
   */
  public function receiveFeedback(array $feedback): void
  {
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "AnalyticsAgent received feedback from {$feedback['source_agent_id']}",
        'info'
      );
    }

    // Acknowledge feedback
    $feedbackManager = new FeedbackManager($this->db, $this->debug);
    $feedbackManager->acknowledgeFeedback(
      $feedback['feedback_id'],
      'AnalyticsAgent',
      null
    );

    // Learn from feedback (future enhancement)
    // Could adjust query generation strategies based on feedback patterns
  }
}
