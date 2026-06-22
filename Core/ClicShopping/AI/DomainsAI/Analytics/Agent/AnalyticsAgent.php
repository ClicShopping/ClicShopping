<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\OM\Cache as OMCache;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\AutonomousConfig;
use ClicShopping\AI\Config\AgentSystemConfig;
use ClicShopping\AI\CoreAI\Orchestrator\CorrectionAgent;
use ClicShopping\AI\CoreAI\Orchestrator\SubAbstention\AgentAbstentionManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\FeedbackManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective;
use ClicShopping\AI\CoreAI\Query\QueryClassifier;
use ClicShopping\AI\CoreAI\Orchestrator\SubValidation\ValidationGate;
use ClicShopping\AI\DomainsAI\Analytics\Executor\QueryExecutor;
use ClicShopping\AI\DomainsAI\Analytics\Executor\SqlQueryProcessor;
use ClicShopping\AI\DomainsAI\Analytics\Helper\AnalyticsErrorHandler;
use ClicShopping\AI\DomainsAI\Analytics\Helper\Detection\AmbiguousQueryDetector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\Infrastructure\Cache\Cache;
use ClicShopping\AI\Infrastructure\Cache\QueryCache;
use ClicShopping\AI\Infrastructure\Prompt\PromptBuilder;
use ClicShopping\AI\Security\DbSecurity;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Security\RateLimit;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\AI\Helper\TypeSafetyGuard;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * Class AnalyticsAgent
 * Handles database analytics and query processing with AI assistance
 * Manages table relationships, schema validation, and query optimization
 * Implements comprehensive security measures
 */

class AnalyticsAgent
{
  private mixed $chat;
  private mixed $db;
  private mixed $language;
  private int $languageId;
  private array $correctionLog = [];
  private bool $enablePromptCache;
  private bool $debug = false;
  private SecurityLogger $securityLogger;
  private RateLimit $rateLimit;
  private string $userId;
  private DbSecurity $dbSecurity;

  private mixed $maxRowsForInterpretation;

  // Delegated components
  private DatabaseSchemaManager $schemaManager;
  private SqlQueryProcessor $queryProcessor;
  private QueryExecutor $queryExecutor;
  private ResultInterpreter $resultInterpreter;
  private AmbiguityTranslator $ambiguityTranslator;
  private QueryEnricher $queryEnricher;
  private AnalyticsQueryClassifier $queryClassifier;
  private CorrectionAgent $correctionAgent;
  private QueryCache $queryCache;
  private AmbiguousQueryDetector $ambiguityDetector;
  private PromptBuilder $promptBuilder;
  private AmbiguityHandler $ambiguityHandler;
  private CompoundQueryHandler $compoundQueryHandler;
  private AnalyticsErrorHandler $errorHandler;
  private AnalyticsObjectiveRunner $objectiveRunner;
  private mixed $app;
  
  private mixed $conversationMemory = null;
  private string $Usecache;
  private ?AutonomousConfig $autonomousConfig = null;
  private ?AgentAbstentionManager $abstentionManager = null;

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

    $this->app = Registry::get('ChatGpt');

    // This replaces the duplicated model detection logic with a single, maintainable function
    $model = CLICSHOPPING_APP_CHATGPT_CH_MODEL;
    
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
    $this->rateLimit = new RateLimit('analytics_agent', 50, 60); // 50 requests per minute
    $this->dbSecurity = new DbSecurity();

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->Usecache = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';

    $this->enablePromptCache = $enablePromptCache;

    // Log initialization
    $this->securityLogger->logSecurityEvent("AnalyticsAgent initialized for user {$this->userId}", 'info');

    // Initialize PromptBuilder and set system message
    $this->promptBuilder = new PromptBuilder($this->language, $this->languageId, $this->debug);
    $this->chat->setSystemMessage($this->promptBuilder->getSystemMessage());

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
      $this->dbSecurity,
      $this->debug
    );

    $this->resultInterpreter = new ResultInterpreter(
      $this->chat,
      new Cache($enablePromptCache),  // ResultInterpreter has its own cache instance
      $this->securityLogger,
      $this->app,
      $this->maxRowsForInterpretation,
      $this->enablePromptCache,
      $this->debug
    );
    $this->ambiguityTranslator = new AmbiguityTranslator($this->resultInterpreter, $this->debug);
    $this->queryEnricher = new QueryEnricher($this->promptBuilder, $this->language, $this->debug);
    $this->queryClassifier = new AnalyticsQueryClassifier($this->resultInterpreter, $this->debug);
    $this->correctionAgent = new CorrectionAgent($userId, $languageId);
    
    // Initialize QueryCache
    $this->queryCache = new QueryCache();
    
    // Initialize AmbiguousQueryDetector with chat instance for LLM-based detection
    $this->ambiguityDetector = new AmbiguousQueryDetector($this->chat, $this->securityLogger, $this->debug);
    
    // Initialize AmbiguityHandler for handling ambiguous queries
    $this->ambiguityHandler = new AmbiguityHandler(
      $this->ambiguityDetector,
      $this->queryProcessor,
      $this->queryExecutor,
      $this->debug
    );
    
    // Initialize CompoundQueryHandler for handling compound queries (multiple questions)
    $this->compoundQueryHandler = new CompoundQueryHandler(
      $this->chat,
      $this->securityLogger,
      $this->debug
    );
    
    // Initialize AnalyticsErrorHandler for error recovery and messaging
    $this->errorHandler = new AnalyticsErrorHandler(
      $this->db,
      $this->correctionAgent,
      $this->queryExecutor,
      $this->debug
    );

    // Autonomous-agent concern extracted from this class (god-class decomposition);
    // kept for the live createLocalObjective() telemetry path (objective register).
    $this->objectiveRunner = new AnalyticsObjectiveRunner($this->autonomousConfig, $this->debug, $this->securityLogger);

    try {
      $this->schemaManager->initializeTableRelationships();
      $this->schemaManager->buildDatabaseSchema();
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent("Error during AnalyticsAgent initialization: " . $e->getMessage(), 'error');

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("Error during AnalyticsAgent initialization: " . $e->getMessage(), 'error');
      }
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
  public function processBusinessQuery(string $question, bool $includeSQL = true, array $feedbackContext = [], bool $skipClassification = false): array
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

      $results = $this->executeQuery($question, $feedbackContext);

      $this->debugLog("executeQuery() returned:");
      $this->debugLog("  type: " . ($results['type'] ?? 'unknown'));
      $this->debugLog("  has error: " . (isset($results['error']) ? 'YES' : 'NO'));
      $this->debugLog("  has results: " . (isset($results['results']) ? 'YES (' . count($results['results']) . ' rows)' : 'NO'));

      $results = $this->validateAndReexecuteSqlDates($results, $question);

      if (($results['type'] ?? 'unknown') === 'error') {
        $this->debugLog("ERROR in executeQuery: " . ($results['error'] ?? 'unknown'));
        return $results;
      }

      // Handle unknown or incomplete results
      // ✅ FIX: Allow ambiguous results which use 'interpretation_results' instead of 'results'
      $isAmbiguous = isset($results['type']) && $results['type'] === 'analytics_results_ambiguous';
      $isClarification = isset($results['type']) && $results['type'] === 'clarification_needed';
      $hasResults = isset($results['results']) && $results['results'] !== null;
      $hasInterpretationResults = isset($results['interpretation_results']) && !empty($results['interpretation_results']);

      // ✅ FIX: For clarification requests, return them directly
      if ($isClarification) {
        $this->debugLog("✅ Clarification needed - returning directly");
        return $results;
      }

      // ✅ FIX: For ambiguous results, return them directly without interpretation
      if ($isAmbiguous && $hasInterpretationResults) {
        $this->debugLog("✅ Ambiguous results detected - returning directly");
        return $results;
      }

      if (!$hasResults && !$hasInterpretationResults && !$isAmbiguous && !$isClarification) {
        $this->debugLog("WARNING: No results array in executeQuery response");
        return [
          'type' => 'error',
          'error' => 'Query execution failed to return results',
          'question' => $question,
          'details' => $results
        ];
      }

      // 3. Interpret the results
      $this->debugLog("\n--- STEP 3: Interpret results ---");

      $interpretation = $this->determineInterpretation($question, $results);

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
      ];

      // Add cache metadata if available
      if (isset($results['cache_age'])) {
        $response['cache_age'] = $results['cache_age'];
      }

      if ($includeSQL) {
        $response['sql_query'] = $results['sql_query'] ?? 'N/A';
        $response['original_sql_query'] = $results['original_sql_query'] ?? $results['sql_query'] ?? 'N/A';
        if (!empty($results['corrections'])) {
          $response['corrections'] = $results['corrections'];
        }
      }

      // Validation gate — closes the agentic critique loop (see applyValidationGate()).
      $this->applyValidationGate($question, $includeSQL, $interpretation, $results, $response);

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
   * Determine the interpretation for an executed analytics result.
   *
   * Extracted verbatim from processBusinessQuery (STEP 3). Reuses a cached
   * interpretation when present, otherwise generates an empty-results message or a
   * fresh interpretation from the result rows.
   *
   * @param string $question Original business question
   * @param array $results Executed query results
   * @return mixed Interpretation (normally a string; may be array on upstream quirks)
   */
  private function determineInterpretation(string $question, array $results): mixed
  {
    // 🆕 Check if interpretation is already in cache
    if (isset($results['interpretation']) && !empty($results['interpretation'])) {
      $interpretation = $results['interpretation'];
      $this->debugLog("✅ Using cached interpretation");

      // Type-safe logging with TypeSafetyGuard
      if (is_array($interpretation)) {
        $this->debugLog(" WARNING: Cached interpretation is an array, not a string");
      }

      $logSnippet = TypeSafetyGuard::safeSubstr($interpretation, 0, 200);
      $this->debugLog("Interpretation: " . $logSnippet . "...");
    } else {
      if (empty($results['results'])) {
        $this->debugLog("⚠️  WARNING: No results to interpret, generating empty results message");
        $interpretation = $this->errorHandler->generateEmptyResultsMessage($question, $results, $this->debug);
        $this->debugLog(" Empty results message: " . $interpretation);
      } else {
        // Generate new interpretation only if we have data
        $interpretation = $this->resultInterpreter->interpretResults($question, $results['results']);
        $this->debugLog(" Generated new interpretation");

        // Type-safe logging with TypeSafetyGuard
        if (is_array($interpretation)) {
          $this->debugLog(" WARNING: interpretResults() returned an array, not a string");
        }

        $logSnippet = TypeSafetyGuard::safeSubstr($interpretation, 0, 200);
        $this->debugLog("Interpretation: " . $logSnippet . "...");
      }
    }

    return $interpretation;
  }

  /**
   * Apply the optional LLM validation gate to a built analytics response.
   *
   * OFF by default (flag undefined) -> no behaviour change. When enabled, an LLM
   * evaluation (model-agnostic, no regex) scores the answer; ValidationGate turns the
   * score into a decision. On 'regenerate' it re-runs generation ONCE with the critique
   * as feedback, keeping the new answer ONLY if it scores strictly better (never a
   * regression). The computed evaluation is attached to the response so the formatter
   * reuses it (no double LLM call). Mutates $results and $response by reference.
   *
   * @param string $question Original business question
   * @param bool $includeSQL Whether SQL fields are exposed in the response
   * @param mixed $interpretation Generated interpretation (string when gate runs)
   * @param array $results Query results, mutated by reference on regeneration
   * @param array $response Built response, mutated by reference
   * @return void
   */
  private function applyValidationGate(string $question, bool $includeSQL, mixed $interpretation, array &$results, array &$response): void
  {
    if (AgentSystemConfig::isValidationGateEnabled()
        && is_string($interpretation) && $interpretation !== '') {
      try {
        $evaluation = LlmGuardrails::checkGuardrails($question, $interpretation);

        if (is_array($evaluation)) {
          $score = isset($evaluation['overall_score']) ? (float) $evaluation['overall_score'] : null;
          $issues = $evaluation['llm_evaluation']['detected_issues'] ?? [];
          $decision = ValidationGate::decide($score, $issues);

          // Bounded regeneration (one attempt), non-regressive (keep only if strictly better).
          if ($decision['action'] === 'regenerate' && $score !== null && !empty($results['results'])) {
            $feedback = [[
              'feedback_type' => 'correction',
              'original_query' => $question,
              'sql_query' => $results['sql_query'] ?? '',
              'corrected_response' => '',
              'correction_comment' => 'The previous SQL was judged low quality (score ' . round($score, 2)
                . '). Do NOT reproduce it. Issues: ' . implode('; ', array_slice($issues, 0, 5))
                . '. Regenerate a corrected SQL that preserves ALL constraints of the question.',
              'interaction_id' => 'validation_gate_' . uniqid(),
            ]];

            $regen = $this->executeQuery($question, $feedback);

            if (($regen['type'] ?? 'error') !== 'error' && !empty($regen['results'])) {
              $regenInterp = $this->resultInterpreter->interpretResults($question, $regen['results']);

              if (is_string($regenInterp) && $regenInterp !== '') {
                $regenEval = LlmGuardrails::checkGuardrails($question, $regenInterp);
                $regenScore = is_array($regenEval) && isset($regenEval['overall_score']) ? (float) $regenEval['overall_score'] : null;

                if ($regenScore !== null && $regenScore > $score) {
                  // Adopt the strictly-better regenerated answer.
                  $interpretation = $regenInterp;
                  $results = $regen;
                  $evaluation = $regenEval;
                  $score = $regenScore;
                  $issues = $regenEval['llm_evaluation']['detected_issues'] ?? [];
                  $decision = ValidationGate::decide($score, $issues);

                  $response['interpretation'] = $regenInterp;
                  $response['results'] = $regen['results'];
                  $response['count'] = $regen['count'] ?? count($regen['results']);
                  if ($includeSQL) {
                    $response['sql_query'] = $regen['sql_query'] ?? ($response['sql_query'] ?? 'N/A');
                  }
                  $this->debugLog("Validation gate: regenerated (improved to " . round($regenScore, 2) . ")");
                } else {
                  $this->debugLog("Validation gate: regeneration not better, kept original");
                }
              }
            }
          }

          $response['validation'] = [
            'action' => $decision['action'],
            'reason' => $decision['reason'],
            'score' => $decision['score'],
          ];
          // Pass the computed evaluation to the formatter to avoid a second LLM call.
          $response['validation_evaluation'] = $evaluation;
          $this->debugLog("Validation gate: {$decision['action']} ({$decision['reason']})");
        }
      } catch (\Exception $e) {
        $this->debugLog("Validation gate error: " . $e->getMessage());
      }
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
   * @return array Results array containing:
   *               - type: 'success' or 'error'
   *               - message: Result message or error description
   *               - query: Original question
   *               - suggestion: Error fix suggestion if applicable
   *               - recovery_attempted: Boolean indicating if recovery was attempted
   */
  public function executeQuery(string $question, array $feedbackContext = []): array
  {
    $this->debugLog(str_repeat("-", 100));
    $this->debugLog("DEBUG: AnalyticsAgent.executeQuery() - START");
    $this->debugLog("-" . str_repeat("-", 99));
    $this->debugLog("Question: '{$question}'");
    $this->debugLog("Feedback context items: " . count($feedbackContext));

    if (!$this->rateLimit->checkLimit($this->userId)) {
      $this->debugLog("RATE LIMIT EXCEEDED");
      return [
        'type' => 'error',
        'message' => 'Rate limit exceeded',
        'query' => $question,
      ];
    }

    $safeQuestion = InputValidator::validateParameter($question, 'string');
    if ($safeQuestion !== $question) {
      $this->debugLog("Question was sanitized");
      $question = $safeQuestion;
    }

    try {
      $this->debugLog("\nCalling processAnalyticsQuery()...");
      $result = $this->processAnalyticsQuery($question, $feedbackContext);

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
   * @throws \Exception When query execution fails after recovery attempts
   */
  private function processAnalyticsQuery(string $question, array $feedbackContext = []): array
  {
    $this->debugLog(str_repeat(".", 100));
    $this->debugLog("AnalyticsAgent.processAnalyticsQuery() - START", "QUERY");
    $this->debugLog("Feedback context items: " . count($feedbackContext), "QUERY");

    try {

      $abstainResponse = $this->evaluateAbstention($question, $feedbackContext);
      if ($abstainResponse !== null) {
        return $abstainResponse;
      }

      $this->debugLog("--- STEP 0: Translate query for ambiguity detection ---", "TRANSLATION");

      // This ensures the LLM can properly detect explicit keywords in any language
      // Use a simple, fast translation that focuses on keywords
      $queryForAmbiguity = $this->ambiguityTranslator->translate($question);
      $this->debugLog("Original query: {$question}", "TRANSLATION");
      $this->debugLog("Translated for ambiguity: {$queryForAmbiguity}", "TRANSLATION");

      // STEP 0.25: DISABLED - Compound query detection
      // Compound queries (e.g., "pending orders and revenue") are now classified as 'hybrid'
      // and routed to HybridQueryProcessor which has proper handling and formatting.
      // The CompoundQueryHandler produced incorrect output format not compatible with formatters.
      // See: HybridQueryProcessor.splitHybridQuery() and HybridQueryProcessor.handleComplexQuery()
      $this->debugLog("--- STEP 0.25: Compound query detection DISABLED ---", "COMPOUND");
      $this->debugLog("Hybrid queries are handled by HybridQueryProcessor", "COMPOUND");

      $this->debugLog("--- STEP 0.5: Check for ambiguous query ---", "AMBIGUITY");

      $ambiguityAnalysis = $this->analyzeAmbiguity($question, $queryForAmbiguity);

      if ($ambiguityAnalysis['is_ambiguous']) {
        $this->debugLog("AMBIGUOUS QUERY DETECTED!", "AMBIGUITY");
        $this->debugLog("Type: " . $ambiguityAnalysis['ambiguity_type'], "AMBIGUITY");
        $this->debugLog("Recommendation: " . $ambiguityAnalysis['recommendation'], "AMBIGUITY");
        $this->debugLog("Interpretations: " . json_encode(array_keys($ambiguityAnalysis['interpretations'])), "AMBIGUITY");

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
          $this->debugLog("→ Using default interpretation: " . $ambiguityAnalysis['default_interpretation'], "AMBIGUITY");
          // Continue with default interpretation
        }
      } else {
        if (isset($ambiguityAnalysis['skipped']) && $ambiguityAnalysis['skipped']) {
          $this->debugLog("⚡ Ambiguity detection SKIPPED (reason: {$ambiguityAnalysis['reason']}, confidence: {$ambiguityAnalysis['confidence']})", "OPTIMIZATION");
        } else {
          $this->debugLog("No ambiguity detected - proceeding normally", "AMBIGUITY");
        }
      }

      $this->debugLog("--- STEP 1: Check QueryCache ---", "CACHE");

      $cachedResponse = $this->checkQueryCache($question, $ambiguityAnalysis);
      if ($cachedResponse !== null) {
        return $cachedResponse;
      }

      $this->debugLog("--- STEP 2: Generate SQL from question ---", "SQL");

      $sqlQueries = $this->generateSqlQueries($question, $feedbackContext);

      $this->debugLog("--- STEP 3: Execute SQL queries ---", "EXECUTION");
      return $this->executeSqlQueries($sqlQueries, $question, $ambiguityAnalysis);

    } catch (\Exception $e) {
      $this->debugLog("\nFINAL EXCEPTION: " . $e->getMessage());
      $this->debugLog("." . str_repeat(".", 99) . "\n");
      throw $e;
    }
  }

  /**
   * STEP 3: execute each generated SQL query (with validation, intelligent correction on
   * failure, and result caching), interpret and assemble the analytics response. Extracted
   * verbatim from processAnalyticsQuery. Throws on unrecoverable execution failure.
   *
   * @param array $sqlQueries Generated SQL queries (STEP 2)
   * @param string $question Original question
   * @param array $ambiguityAnalysis Ambiguity metadata echoed into the response
   * @return array The assembled analytics_results response
   */
  private function executeSqlQueries(array $sqlQueries, string $question, array $ambiguityAnalysis): array
  {
    $results = [];
    $this->correctionLog = [];

    foreach ($sqlQueries as $idx => $sqlQuery) {
      $this->debugLog("Processing SQL query " . ($idx + 1), "EXECUTION");
      $this->debugLog("Original: " . substr($sqlQuery, 0, 150) . "...", "EXECUTION");

      $resolvedQuery = $this->queryProcessor->resolvePlaceholders($sqlQuery);
      $this->debugLog("After placeholder resolution: " . substr($resolvedQuery, 0, 150) . "...", "EXECUTION");

      $likeValidation = $this->queryProcessor->validateLikePatterns($resolvedQuery);
      if (!empty($likeValidation['warnings'])) {
        $this->debugLog("LIKE pattern warnings: " . count($likeValidation['warnings']), "VALIDATION");

        // Log warnings using security logger
        foreach ($likeValidation['warnings'] as $warning) {
          $this->securityLogger->logSecurityEvent(
            "LIKE pattern validation warning: " . $warning,
            'warning',
            [
              'sql_snippet' => substr($resolvedQuery, 0, 200),
              'like_count' => $likeValidation['like_count'],
              'patterns' => $likeValidation['patterns']
            ]
          );
        }

        // Log suggestions if available
        if (!empty($likeValidation['suggestions'])) {
          $this->debugLog("Suggestions: " . implode('; ', $likeValidation['suggestions']), "VALIDATION");
        }
      } else {
        $this->debugLog("LIKE pattern validation: PASSED (" . $likeValidation['like_count'] . " patterns checked)", "VALIDATION");
      }

      $validation = InputValidator::validateSqlQuery($resolvedQuery);
      $this->debugLog("SQL validation: " . ($validation['valid'] ? 'VALID' : 'INVALID'), "VALIDATION");

      if (!$validation['valid']) {
        $this->debugLog("  Validation issues: " . implode(', ', $validation['issues']));
        continue;
      }

      $finalQuery = $validation['valid'] ? $resolvedQuery : $sqlQuery;
      $finalQuery = $this->queryProcessor->fixDateFilters($finalQuery);
      // Schema-level guard: never GROUP BY a GDPR-encrypted column (shatters aggregation).
      $finalQuery = $this->queryProcessor->fixEncryptedGroupBy($finalQuery);

      $this->debugLog("  Final query to execute: " . substr($finalQuery, 0, 150) . "...");

      try {
        $this->debugLog("  Executing query...");
        $executionResult = $this->queryExecutor->execute($finalQuery);

        if (!$executionResult['success']) {
          throw new \Exception($executionResult['error'] ?? 'Query execution failed');
        }

        $queryResults = $executionResult['data'];

        $this->debugLog("  Query executed successfully!");
        $this->debugLog("  Rows returned: " . count($queryResults));

        if (!empty($queryResults)) {
          $this->debugLog("  First row keys: " . implode(', ', array_keys($queryResults[0])));
          $this->debugLog("  First row preview: " . json_encode(array_slice($queryResults[0], 0, 3)));
        }

        // Extract entity_id using QueryExecutor
        $entityInfo = $this->queryExecutor->extractEntityIdFromResults($queryResults);
        $entityId = $entityInfo['entity_id'];
        $entityType = $entityInfo['entity_type'];

        if ($entityId !== null) {
          $this->debugLog("  Entity extracted: ID={$entityId}, Type={$entityType}");
        }

        $results = [
          'type' => 'analytics_results',
          'query' => $question,
          'sql_query' => $finalQuery,
          'original_sql_query' => $sqlQuery,
          'corrections' => $this->correctionLog,
          'results' => $queryResults,
          'count' => count($queryResults),
          'entity_id' => $entityId,
          'entity_type' => $entityType,
          'ambiguous' => $ambiguityAnalysis['is_ambiguous'] ?? false,  //Add ambiguity metadata
          'ambiguity_type' => $ambiguityAnalysis['ambiguity_type'] ?? null,
          'interpretations' => $ambiguityAnalysis['is_ambiguous'] ? array_keys($ambiguityAnalysis['interpretations']) : [],
        ];

        // 🆕 CACHE THE SUCCESSFUL RESULT
        $this->debugLog("   Caching successful query result in QueryCache");
        $this->queryCache->set(
          $question,
          $finalQuery,
          $queryResults,
          [
            'entity_id' => $entityId,
            'entity_type' => $entityType
          ]
        );

      } catch (\Exception $e) {
        $this->debugLog("  QUERY EXECUTION FAILED: " . $e->getMessage());
        $this->debugLog("  Attempting intelligent correction...");

        $correctionResult = $this->errorHandler->attemptIntelligentCorrection($e, $finalQuery, $sqlQuery, $question);

        if ($correctionResult['success']) {
          $this->debugLog("  Correction successful!");

          // Use the corrected data as the main result (not append to array)
          $correctedData = $correctionResult['data'];

          // Extract entity info from corrected results
          $entityInfo = $this->queryExecutor->extractEntityIdFromResults($correctedData['results']);

          $results = [
            'type' => 'analytics_results',
            'query' => $question,
            'sql_query' => $correctedData['executed_query'],
            'original_sql_query' => $sqlQuery,
            'corrections' => $correctedData['corrections'] ?? [],
            'results' => $correctedData['results'],
            'count' => count($correctedData['results']),
            'entity_id' => $entityInfo['entity_id'],
            'entity_type' => $entityInfo['entity_type'],
            'ambiguous' => $ambiguityAnalysis['is_ambiguous'] ?? false,  // Add ambiguity metadata
            'ambiguity_type' => $ambiguityAnalysis['ambiguity_type'] ?? null,
            'interpretations' => $ambiguityAnalysis['is_ambiguous'] ? array_keys($ambiguityAnalysis['interpretations']) : [],
          ];

          // 🆕 CACHE THE CORRECTED RESULT
          if (!empty($correctedData['results'])) {
            $this->debugLog("  Caching corrected query result");
            $this->queryCache->set(
              $question,
              $correctedData['executed_query'],
              $correctedData['results'],
              [
                'entity_id' => $entityInfo['entity_id'],
                'entity_type' => $entityInfo['entity_type']
              ]
            );
          }
        } else {
          $this->debugLog("  Correction failed");
          throw new \Exception("Execution failed after intelligent correction attempt: " . $e->getMessage());
        }
      }
    }

    $this->debugLog("\n" . "." . str_repeat(".", 99) . "\n");
    return $results;
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
    $cacheKey = md5($question . json_encode($feedbackContext));
    $sqlCache = new OMCache($cacheKey, 'Rag/SQL');

    if ($sqlCache->exists(60)) { // 60 minutes = 1 hour
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
      $this->updateSystemMessageForQuery($question);

      // Enrich question with feedback context for learning
      $enrichedQuestion = $this->queryEnricher->enrichWithFeedback($question, $feedbackContext, $this->conversationMemory);

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
      $this->debugLog("Cache entry age: " . (time() - strtotime($cacheResult['created_at'])) . " seconds", "CACHE");

      return [
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
        'ambiguous' => $ambiguityAnalysis['is_ambiguous'],  // Add ambiguity metadata
        'ambiguity_type' => $ambiguityAnalysis['ambiguity_type'] ?? null,
        'cached' => true,
        'cache_age' => time() - strtotime($cacheResult['created_at'])
      ];
    }
    $this->debugLog("CACHE MISS - Generating new query", "CACHE");

    return null;
  }

  /**
   * STEP 0.5 (compute): runs the ambiguity analysis for the query (with the high-confidence
   * skip-optimization). Extracted verbatim from processAnalyticsQuery (core-method decomposition).
   *
   * @param string $question Original question
   * @param string $queryForAmbiguity Query translated for ambiguity detection
   * @return array The ambiguity analysis result
   */
  private function analyzeAmbiguity(string $question, string $queryForAmbiguity): array
  {
    // 🚀 OPTIMIZATION: Skip ambiguity detection for high-confidence analytics queries
    // Get classification confidence from isAnalyticsQuery (already called in processBusinessQuery)
    // If confidence >= 0.9, skip ambiguity detection to save 1-2 seconds
    $skipAmbiguity = false;
    $classificationConfidence = 0.0;

    // Re-classify to get confidence (cached, so very fast)
    $translatedForClassification = SemanticAgent::translateToEnglish($question, 80);
    $cleanTranslation = $this->resultInterpreter->extractCleanTranslation($translatedForClassification);
    $classifier = new QueryClassifier($this->debug);
    $classificationResult = $classifier->classify($cleanTranslation, $cleanTranslation);

    $classificationConfidence = $classificationResult['confidence'] ?? 0.0;

    if ($classificationResult['type'] === 'analytics' && $classificationConfidence >= 0.9) {
      $skipAmbiguity = true;
      $this->debugLog("⚡ SKIPPING ambiguity detection (high confidence: {$classificationConfidence})", "OPTIMIZATION");
      $this->securityLogger->logSecurityEvent(
        "Ambiguity detection skipped for high-confidence analytics query",
        'info',
        [
          'query' => substr($question, 0, 100),
          'confidence' => $classificationConfidence,
          'time_saved_estimate' => '1-2 seconds'
        ]
      );
    }

    $ambiguityAnalysis = $skipAmbiguity
      ? ['is_ambiguous' => false, 'skipped' => true, 'reason' => 'high_confidence_analytics', 'confidence' => $classificationConfidence]
      : $this->ambiguityDetector->detectAmbiguity($queryForAmbiguity);

    return $ambiguityAnalysis;
  }

  /**
   * STEP -1: confidence / abstention evaluation.
   *
   * Extracted verbatim from processAnalyticsQuery (core-method decomposition). Returns the
   * abstain error response when the agent must not execute autonomously, or null to proceed
   * (the 'delegate' case also proceeds, after logging the delegation intent).
   *
   * @param string $question
   * @param array $feedbackContext
   * @return array|null Abstain response array, or null to continue execution
   */
  private function evaluateAbstention(string $question, array $feedbackContext): ?array
  {
    //  Use classification confidence instead of recalculating
    $this->debugLog("--- STEP -1: Evaluate confidence for abstention ---", "ABSTENTION");

    // FIX 2026-01-29: Configure lower thresholds for AnalyticsAgent
    // Abstention: 0.15 (was 0.3), Delegation: 0.5 (was 0.7)
    try {
      $this->abstentionManager->setThresholds('AnalyticsAgent', 0.15, 0.5);
      $this->debugLog("Thresholds configured: abstention=0.15, delegation=0.5", "ABSTENTION");
    } catch (\Exception $e) {
      $this->debugLog("Failed to set thresholds: " . $e->getMessage(), "ABSTENTION");
    }

    // Get classification confidence (already calculated in isAnalyticsQuery)
    $translatedForClassification = SemanticAgent::translateToEnglish($question, 80);
    $cleanTranslation = $this->resultInterpreter->extractCleanTranslation($translatedForClassification);
    $classifier = new QueryClassifier($this->debug);
    $classificationResult = $classifier->classify($cleanTranslation, $cleanTranslation);

    $classificationConfidence = $classificationResult['confidence'] ?? 0.0;
    $this->debugLog("Classification confidence: {$classificationConfidence}", "ABSTENTION");

    // Use classification confidence if high, otherwise calculate complexity-based confidence
    if ($classificationConfidence >= 0.7) {
      // High classification confidence - use it directly
      $confidence = $classificationConfidence;
      $this->debugLog("Using classification confidence: {$confidence}", "ABSTENTION");
    } else {
      // Low classification confidence - calculate based on complexity
      $complexity = AnalyticsQueryHeuristics::estimateQueryComplexity($question);
      $this->debugLog("Query complexity: {$complexity}", "ABSTENTION");

      $confidence = $this->abstentionManager->evaluateConfidence(
        'AnalyticsAgent',
        $question,
        [
          'task_type' => 'analytics_query',
          'description' => $question,
          'parameters' => $feedbackContext,
          'complexity' => $complexity
        ]
      );
      $this->debugLog("Calculated confidence: {$confidence}", "ABSTENTION");
    }

    $decision = $this->abstentionManager->getAbstentionDecision(
      'AnalyticsAgent',
      $confidence,
      'analytics_query'
    );

    $this->debugLog("Abstention decision: {$decision['action']}", "ABSTENTION");
    $this->debugLog("Reason: {$decision['reason']}", "ABSTENTION");

    if ($decision['action'] === 'abstain') {
      // Log abstention to database
      $this->abstentionManager->logAbstention(
        'AnalyticsAgent',
        md5($question),
        'analytics_query',
        $confidence,
        $decision['reason'],
        'escalate_human'
      );

      $this->debugLog("ABSTAINING - Confidence too low", "ABSTENTION");

      // Return error requiring human intervention
      return [
        'type' => 'error',
        'message' => 'Confidence too low for autonomous execution. Human review required.',
        'reason' => $decision['reason'],
        'confidence' => $confidence,
        'requires_human' => true,
        'query' => $question
      ];
    }

    if ($decision['action'] === 'delegate') {
      // Log delegation intent
      $this->abstentionManager->logAbstention(
        'AnalyticsAgent',
        md5($question),
        'analytics_query',
        $confidence,
        $decision['reason'],
        'delegate_peer',
        $decision['suggested_delegate']
      );

      $this->debugLog("DELEGATING - Medium confidence", "ABSTENTION");
      $this->debugLog("Suggested delegate: " . ($decision['suggested_delegate'] ?? 'none'), "ABSTENTION");

      // For now, proceed with execution but log the delegation intent
      // TODO: Implement actual delegation mechanism when peer agents are available
    }

    $this->debugLog("EXECUTING - Confidence sufficient ({$confidence})", "ABSTENTION");

    return null;
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
   * Update system message for query (Schema RAG)
   *
   * If Schema RAG is enabled, updates the system message with only relevant
   * table schemas based on the query, reducing context size for small models
   *
   * @param string $query User query
   * @return void
   */
  private function updateSystemMessageForQuery(string $query): void
  {
    $useSchemaRAG = CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG;

    if (!$useSchemaRAG) {
      return; // Schema RAG disabled, use cached system message
    }

    try {
      $modelName = defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL') ? CLICSHOPPING_APP_CHATGPT_CH_MODEL : Gpt::getTechnicalFallbackModel();
      // Get model name from chat instance

      // Try to get actual model name from chat config
      if (method_exists($this->chat, 'getModel')) {
        $modelName = $this->chat->getModel();
      }

      $this->debugLog("Updating system message with Schema RAG", "SCHEMA_RAG");
      $this->debugLog("Model: {$modelName}", "SCHEMA_RAG");

      // Get query-specific system message
      $systemMessage = $this->promptBuilder->getSystemMessage('analytics', $query, $modelName);

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

  /**
   * Execute a sub-query from a compound query
   *
   * This method executes a single sub-query through the normal analytics flow
   * but skips compound query detection to prevent infinite recursion.
   *
   * @param string $subQuery The sub-query to execute
   * @param array $feedbackContext Feedback context for learning
   * @return array Query results
   */
  private function executeSubQuery(string $subQuery, array $feedbackContext = []): array
  {
    $this->debugLog("Executing sub-query: " . substr($subQuery, 0, 80), "COMPOUND_SUB");

    try {
      // Use executeQuery which will go through the normal flow
      // The sub-query will be processed individually
      $result = $this->executeQuery($subQuery, $feedbackContext);

      // If successful, try to get interpretation
      if (($result['type'] ?? 'error') !== 'error' && !empty($result['results'])) {
        $interpretation = $this->resultInterpreter->interpretResults($subQuery, $result['results']);
        $result['interpretation'] = $interpretation;
      }

      return $result;

    } catch (\Exception $e) {
      $this->debugLog("Sub-query execution failed: " . $e->getMessage(), "COMPOUND_SUB");

      return [
        'type' => 'error',
        'error' => $e->getMessage(),
        'query' => $subQuery
      ];
    }
  }

  /**
   * Validate and fix SQL date logic, re-executing the corrected query when needed
   * (extracted verbatim from processBusinessQuery to cut NPath).
   */
  private function validateAndReexecuteSqlDates(array $results, string $question): array
  {
    if (isset($results['sql_query']) && !empty($results['sql_query'])) {
      $dateValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SqlDateValidator($this->debug);
      $dateValidation = $dateValidator->validateAndFix($results['sql_query'], $question);

      if ($dateValidation['corrected']) {
        $this->debugLog(" SQL date logic corrected in processBusinessQuery: " . $dateValidation['reason']);

        // Update the SQL in results
        $results['original_sql_query'] = $results['sql_query'];
        $results['sql_query'] = $dateValidation['sql'];

        // Re-execute the corrected SQL
        $this->debugLog(" Re-executing corrected SQL...");
        try {
          $executionResult = $this->queryExecutor->execute($dateValidation['sql']);

          if ($executionResult['success']) {
            $results['results'] = $executionResult['data'];
            $results['count'] = count($executionResult['data']);
            $this->debugLog("✅ Corrected SQL executed successfully, returned " . $results['count'] . " rows");

            // 🔧 FIX: Clear cached interpretation so it gets regenerated with new results
            if (isset($results['interpretation'])) {
              unset($results['interpretation']);
              $this->debugLog("Cleared cached interpretation to force regeneration with corrected results");
            }
          } else {
            $this->debugLog("⚠️  Corrected SQL execution failed: " . ($executionResult['error'] ?? 'unknown'));
          }
        } catch (\Exception $e) {
          $this->debugLog("⚠️  Error re-executing corrected SQL: " . $e->getMessage());
        }
      }
    }

    return $results;
  }
}
