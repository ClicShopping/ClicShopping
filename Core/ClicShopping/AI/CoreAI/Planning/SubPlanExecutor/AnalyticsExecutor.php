<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Analytics\Agent\AnalyticsAgent;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\AnalyticsExecutorPatterns;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ConsensusBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics\AnalyticsCriticWrapper;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics\SqlQualityCriticWrapper;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\CriticDataCollector;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMWeightingEngine;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMPromptBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightNormalizer;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAuditLogger;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightedConsensusBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationTracker;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation;
use ClicShopping\AI\Config\AgentSystemConfig;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\DomainsAI\Analytics\Validator\SqlPerformanceValidator;
use ClicShopping\AI\DomainsAI\Analytics\Validator\SqlQualityValidator;
use ClicShopping\AI\DomainsAI\Analytics\Validator\SqlSecurityValidator;

/**
 * AnalyticsExecutor Class
 *
 * Responsible for executing analytics queries.
 * Separated from PlanExecutor to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Execute analytics queries
 * - Format analytics results
 * - Handle analytics errors
 * - Extract entity metadata from results
 */

class AnalyticsExecutor
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?AnalyticsAgent $analyticsAgent = null;
  private string $userId;
  private int $languageId;

  private bool $debugRAManager;
  private mixed $conversationMemory = null; // 🆕 Store ConversationMemory for lazy initialization

  /**
   * Constructor
   *
   * @param string $userId User ID
   * @param int $languageId Language ID
   * @param bool $debug Enable debug logging
   */
  public function __construct(string $userId = 'system', int $languageId = 1, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->userId = $userId;
    $this->languageId = $languageId;
    $this->debug = $debug;
    $this->debugRAManager = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')&& CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    if ($this->debug) {
      $this->logger->logSecurityEvent("AnalyticsExecutor initialized", 'info');
    }

    // Load shared user-facing labels once (interface language) for all methods
    Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');
  }
  
  /**
   * Set the conversation memory instance
   * Allows PlanExecutor to inject ConversationMemory
   * This is needed to pass it to AnalyticsAgent for contextual query resolution.
   *
   * @param mixed $conversationMemory ConversationMemory instance
   * @return void
   */
  public function setConversationMemory($conversationMemory): void
  {
    // If AnalyticsAgent is already instantiated, set it immediately
    if ($this->analyticsAgent !== null) {
      $this->analyticsAgent->setConversationMemory($conversationMemory);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent("ConversationMemory set on existing AnalyticsAgent", 'info');
      }
    }
    
    // Store it for later use when AnalyticsAgent is instantiated
    $this->conversationMemory = $conversationMemory;
    
    if ($this->debug) {
      $this->logger->logSecurityEvent("ConversationMemory stored in AnalyticsExecutor", 'info');
    }
  }

  /**
   * Execute analytics query with temporal error handling
   *
   * **Requirement 8.4**: Execute query with proper error handling for temporal periods
   *
   * This method wraps executeAnalyticsQuery with additional error handling
   * specific to temporal aggregations. It ensures that failures in one
   * temporal period don't prevent other periods from being processed.
   *
   * @param string $query Query to execute
   * @param array $context Context information including temporal_period
   * @return array Result with success status and error handling
   */
  public function executeTemporalAnalyticsQuery(string $query, array $context = []): array
  {
    $temporalPeriod = $context['temporal_period'] ?? 'unknown';

    try {
      // Log the temporal query execution
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Executing temporal analytics query for period: {$temporalPeriod}",
          'info',
          ['query' => substr($query, 0, 100), 'temporal_period' => $temporalPeriod]
        );
      }

      // Execute the query
      $result = $this->executeAnalyticsQuery($query, $context);

      // Check if execution was successful
      if (!isset($result['success']) || $result['success'] === false) {
        // If there's an error but it's not a complete failure, still return partial result
        if (!empty($result['results'])) {
          $result['partial_success'] = true;
          $result['temporal_period'] = $temporalPeriod;
          return $result;
        }

        // Complete failure - use error handler
        return $this->handleSqlGenerationFailure(
          $query,
          $temporalPeriod,
          null,
          $result['error'] ?? 'Query execution failed'
        );
      }

      // **Requirement 8.5**: Check for no data and handle gracefully
      if (empty($result['results'])) {
        return $this->handleNoDataForTemporalPeriod($query, $temporalPeriod, $context);
      }

      // Add temporal period to successful result
      $result['temporal_period'] = $temporalPeriod;
      return $result;

    } catch (\Exception $e) {
      // Handle exception with temporal-specific error handling
      return $this->handleSqlGenerationFailure($query, $temporalPeriod, $e);
    }
  }

  /**
   * Execute analytics query
   *
   * @param string $query Query to execute
   * @param array $context Context information
   * @return array Result
   */
  public function executeAnalyticsQuery(string $query, array $context = []): array
  {
    if ($this->debugRAManager) {
      error_log(str_repeat("=", 100));
      error_log("AnalyticsExecutor.executeAnalyticsQuery() CALLED");
      error_log(str_repeat("=", 100));
      error_log("Query received: '{$query}'");
      error_log("Query length: " . strlen($query));
      error_log("Query is empty: " . (empty($query) ? 'YES' : 'NO'));
    }


    try {
      // Initialize analytics agent if needed
      if ($this->analyticsAgent === null) {
        if ($this->debugRAManager) {
          error_log("Initializing AnalyticsAgent...");
        }

        $this->analyticsAgent = new AnalyticsAgent($this->languageId, true, $this->userId);
        
        // Set ConversationMemory if available
        // This enables contextual query resolution (e.g., "donne moi son sku")
        if ($this->conversationMemory !== null) {
          $this->analyticsAgent->setConversationMemory($this->conversationMemory);
          
          if ($this->debugRAManager) {
            error_log("ConversationMemory set on AnalyticsAgent during initialization");
          }
        }

        if ($this->debugRAManager) {
          error_log("AnalyticsAgent initialized successfully");
        }
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Executing analytics query: {$query}",
          'info'
        );
      }

      if (empty($query)) {
        if ($this->debugRAManager) {
          error_log("[error] ERROR: Query is EMPTY before calling processBusinessQuery!");
          error_log("This is the root cause - query was lost somewhere in the pipeline");
        }

        return [
          'type' => 'analytics_response',
          'success' => false,
          'error' => 'Empty query received by AnalyticsExecutor',
          'question' => '',
          'interpretation' => 'Error: Empty query received',
          'results' => [],
          'sql_query' => '',
        ];
      }

      if ($this->debugRAManager) {
        error_log("Calling AnalyticsAgent.processBusinessQuery()...");
      }

      $executionStart = microtime(true);

      // Execute query — skip classification since orchestrator already classified this as analytics
      $rawResult = $this->analyticsAgent->processBusinessQuery($query, true, [], true);
      $executionTimeMs = (int)round((microtime(true) - $executionStart) * 1000);

      if ($this->debugRAManager) {
        error_log("processBusinessQuery() returned:");
        error_log("  SQL query: " . ($rawResult['sql_query'] ?? 'EMPTY'));
        error_log("  Results count: " . count($rawResult['results'] ?? []));
      }
      
      //  Handle interpretation being an array or string
      $interpretation = $rawResult['interpretation'] ?? 'N/A';

      if (is_array($interpretation)) {
        $interpretationStr = json_encode($interpretation, JSON_UNESCAPED_UNICODE);
      } else {
        $interpretationStr = (string)$interpretation;
      }
      if ($this->debugRAManager) {
        error_log("  Interpretation: " . substr($interpretationStr, 0, 100));
      }
      // Format result
      $formattedResult = $this->formatAnalyticsResult($rawResult);

      if ($this->debugRAManager) {
        error_log("Formatted result SQL: " . ($formattedResult['sql_query'] ?? 'EMPTY'));
        error_log(str_repeat("=", 100) . "\n");
      }

      // Store entity in conversation memory for context enrichment in subsequent queries
      if ($this->conversationMemory !== null) {
        $entityId = $formattedResult['entity_id'] ?? null;
        $entityType = $formattedResult['entity_type'] ?? null;
        
        if ($entityId !== null && $entityType !== null) {
          // Extract entity name from results
          $entityName = null;
          $results = $formattedResult['results'] ?? [];
          
          if (!empty($results) && is_array($results[0])) {
            $firstRow = $results[0];
            $nameFields = ['products_name', 'name', 'manufacturers_name', 'categories_name', 'title'];
            foreach ($nameFields as $field) {
              if (isset($firstRow[$field]) && !empty($firstRow[$field])) {
                $entityName = $firstRow[$field];
                break;
              }
            }
          }
          
          try {
            $this->conversationMemory->setLastEntity((int)$entityId, $entityType, $entityName);
            
            if ($this->debug) {
              $nameInfo = $entityName ? " (name: {$entityName})" : "";
              $this->logger->logSecurityEvent(
                "Stored entity in memory from analytics: {$entityType} (ID: {$entityId}){$nameInfo}",
                'info'
              );
            }
            
            if ($this->debugRAManager) {
              $nameInfo = $entityName ? ", Name={$entityName}" : "";
              error_log("[AnalyticsExecutor] Stored entity in memory: ID={$entityId}, Type={$entityType}{$nameInfo}");
            }
          } catch (\Exception $e) {
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Error storing entity in memory: " . $e->getMessage(),
                'warning'
              );
            }
          }
        }
      }

      // Record real analytics execution + critic evaluation (no synthetic data)
      $this->recordAnalyticsEvaluation($query, $rawResult, $executionTimeMs);
      $this->registerSlowQueryObjective($query, $executionTimeMs);

      return $formattedResult;

    } catch (\Exception $e) {
      error_log("[error] EXCEPTION in executeAnalyticsQuery: " . $e->getMessage());
      error_log(str_repeat("=", 100) . "\n");
      return $this->handleAnalyticsError($e, $query);
    }
  }

  /**
   * Format analytics result
   *
   * @param array $rawResult Raw result from AnalyticsAgent
   * @return array Formatted result
   */
  public function formatAnalyticsResult(array $rawResult): array
  {
    if ($this->debugRAManager) {
      // 🔍 DEBUG: Trace results through formatting
      error_log("\n" . str_repeat("=", 100));
      error_log("DEBUG: AnalyticsExecutor.formatAnalyticsResult() CALLED");
      error_log(str_repeat("=", 100));
      error_log("Raw result type: " . ($rawResult['type'] ?? 'unknown'));
      error_log("Raw result has 'results' key: " . (isset($rawResult['results']) ? 'YES' : 'NO'));
    }

    if (isset($rawResult['results'])) {
      if ($this->debugRAManager) {
        error_log("Raw result 'results' count: " . count($rawResult['results']));
        error_log("Raw result 'results' is_array: " . (is_array($rawResult['results']) ? 'YES' : 'NO'));
      }

      if (!empty($rawResult['results']) && is_array($rawResult['results'])) {
        error_log("First row keys: " . implode(', ', array_keys($rawResult['results'][0])));
        error_log("First row data: " . json_encode($rawResult['results'][0]));
      }
    }
    if ($this->debugRAManager) {
      error_log(str_repeat("=", 100) . "\n");
    }
    // 🔧 FIX: Handle ambiguous results type
    // When query is ambiguous, the system returns multiple interpretations
    // Instead of passing ambiguous results to UI (which doesn't handle them),
    // we select the best interpretation and return it as a normal result
    if (isset($rawResult['type']) && $rawResult['type'] === 'analytics_results_ambiguous') {
      if ($this->debugRAManager) {
        error_log("✅ Detected ambiguous results - selecting best interpretation");
      }
      // Get the interpretation results
      $interpretationResults = $rawResult['interpretation_results'] ?? [];

      if (!empty($interpretationResults)) {
        // Find the interpretation with the most results
        $bestInterpretation = null;
        $maxResults = 0;

        foreach ($interpretationResults as $key => $interpretation) {
          $resultCount = count($interpretation['results'] ?? []);
          if ($this->debugRAManager) {
            error_log("  Interpretation '{$key}': {$resultCount} results");
            error_log("    Has 'interpretation' key: " . (isset($interpretation['interpretation']) ? 'YES' : 'NO'));
          }

          if (isset($interpretation['interpretation'])) {
            error_log("    Interpretation text: " . substr($interpretation['interpretation'], 0, 100));
          }

          if ($resultCount > $maxResults) {
            $maxResults = $resultCount;
            $bestInterpretation = $interpretation;
          }
        }

        // If we found a good interpretation, use it
        if ($bestInterpretation !== null && !empty($bestInterpretation['results'])) {
          if ($this->debugRAManager) {
            error_log("  Selected best interpretation with {$maxResults} results");
          }
          // Use the interpretation from the best result, or generate one
          $interpretation = $bestInterpretation['interpretation'] ?? null;

          // If interpretation is empty or generic, generate a better one
          if (empty($interpretation) || $interpretation === 'Résultats trouvés' || $interpretation === 'Results found') {
            if ($this->debugRAManager) {
              error_log("  Interpretation is empty/generic, generating from results...");
            }

            $interpretation = $this->generateInterpretationFromResults(
              $bestInterpretation['results'],
              $rawResult['query'] ?? ''
            );
            if ($this->debugRAManager) {
              error_log("  Generated interpretation: " . substr($interpretation, 0, 100));
            }
          }

          // Convert to standard analytics_response format
          return [
            'type' => 'analytics_response',
            'question' => $rawResult['query'] ?? '',
            'interpretation' => $interpretation,
            'results' => $bestInterpretation['results'],
            'sql_query' => $bestInterpretation['sql_query'] ?? '',
            'original_sql_query' => $bestInterpretation['sql_query'] ?? '',
            'entity_id' => null,
            'entity_type' => null,
            'source_attribution' => [
              'source_type' => 'Analytics Database',
              'source_icon' => '📊',
              'source_details' => 'Data retrieved from transactional database',
              'table_name' => $this->extractTableNameFromSql($bestInterpretation['sql_query'] ?? null),
            ],
          ];
        }
      }

      if ($this->debugRAManager) {
        // If no good interpretation found, fall through to generate default interpretation
        error_log("  No good interpretation found, falling through");
      }
    }

    // Check if result is already properly formatted as analytics_response
    if (isset($rawResult['type']) && $rawResult['type'] === 'analytics_response') {
      // Already formatted, just ensure entity metadata and source attribution are preserved
      if (isset($rawResult['entity_id']) && !isset($rawResult['_step_entity_metadata'])) {
        $rawResult['_step_entity_metadata'] = [
          'entity_id' => $rawResult['entity_id'],
          'entity_type' => $rawResult['entity_type'] ?? 'unknown',
        ];
      }

      // 🆕 Add source attribution if not already present
      if (!isset($rawResult['source_attribution'])) {
        $rawResult['source_attribution'] = [
          'source_type' => 'Analytics Database',
          'source_icon' => '📊',
          'source_details' => 'Data retrieved from transactional database',
          'table_name' => $this->extractTableNameFromSql($rawResult['sql_query'] ?? null),
        ];
      }

      return $rawResult;
    }

    // 🔧 FIX: Generate default interpretation if missing
    $interpretation = $rawResult['interpretation'] ?? null;

    if (empty($interpretation) || $interpretation === 'Analytics result processed') {
      $interpretation = $this->generateDefaultInterpretation($rawResult);

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Generated default interpretation: {$interpretation}",
          'info'
        );
      }
    }

    // Format for ResultFormatter compatibility
    $formatted = [
      'type' => 'analytics_response',
      'question' => $rawResult['question'] ?? '',
      'interpretation' => $interpretation,
      'results' => $rawResult['results'] ?? [],
      'sql_query' => $rawResult['sql_query'] ?? '',
      'original_sql_query' => $rawResult['original_sql_query'] ?? $rawResult['sql_query'] ?? '',
      'entity_id' => $rawResult['entity_id'] ?? null,
      'entity_type' => $rawResult['entity_type'] ?? null,
    ];

    // 🆕 Add source attribution for analytics queries
    $formatted['source_attribution'] = [
      'source_type' => 'Analytics Database',
      'source_icon' => '📊',
      'source_details' => 'Data retrieved from transactional database',
      'table_name' => $this->extractTableNameFromSql($formatted['sql_query']),
    ];

    // Add step entity metadata for tracking through pipeline
    if (isset($rawResult['entity_id'])) {
      $formatted['_step_entity_metadata'] = [
        'entity_id' => $rawResult['entity_id'],
        'entity_type' => $rawResult['entity_type'] ?? 'unknown',
      ];
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Formatted analytics result - has entity_id: " . (isset($formatted['entity_id']) ? 'YES' : 'NO'),
        'info'
      );
    }

    return $formatted;
  }

  /**
   * Generate interpretation from results
   *
   * NOTE: All messages in English per tech.md guidelines.
   * UI layer handles translation to user's language.
   *
   * @param array $results Results array
   * @param string $question Original question
   * @return string Interpretation in English
   */
  private function generateInterpretationFromResults(array $results, string $question): string
  {
    if (empty($results)) {
      // Standard, translated empty-results message (no hardcoded string, no "your query"
      // placeholder) — same message the EmptyResultFormatter shows.
      $emptyMessage = CLICSHOPPING::getDef('text_empty_results_base');

      return ($emptyMessage !== '' && $emptyMessage !== 'text_empty_results_base')
        ? $emptyMessage
        : 'No results found.';
    }

    $count = count($results);
    $firstResult = $results[0];

    // Build interpretation based on the data
    $parts = [];

    // Check for product name
    if (isset($firstResult['products_name'])) {
      $parts[] = "Product: {$firstResult['products_name']}";
    }

    // Check for price
    if (isset($firstResult['catalog_price'])) {
      $parts[] = "Price: {$firstResult['catalog_price']}€";
    } elseif (isset($firstResult['products_price'])) {
      $parts[] = "Price: {$firstResult['products_price']}€";
    }

    // Check for quantity
    if (isset($firstResult['products_quantity'])) {
      $parts[] = "Stock quantity: {$firstResult['products_quantity']}";
    } elseif (isset($firstResult['total_quantity'])) {
      $parts[] = "Total quantity: {$firstResult['total_quantity']}";
    }

    // Check for SKU/model
    if (isset($firstResult['products_model'])) {
      $parts[] = "Model: {$firstResult['products_model']}";
    } elseif (isset($firstResult['sku'])) {
      $parts[] = "SKU: {$firstResult['sku']}";
    }

    if (!empty($parts)) {
      return implode(', ', $parts);
    }

    // Fallback
    return "{$count} result" . ($count > 1 ? 's' : '') . " found";
  }

  /**
   * Extract table name from SQL query for source attribution
   *
   * Pattern logic moved to AnalyticsExecutorPatterns class.
   *
   * @param string|null $sql SQL query
   * @return string Table name or 'database'
   */
  private function extractTableNameFromSql(?string $sql): string
  {
    return AnalyticsExecutorPatterns::extractTableName($sql);
  }

  /**
   * Generate default interpretation when none is provided
   *
   * NOTE: All messages in English per tech.md guidelines.
   * UI layer handles translation to user's language.
   *
   * @param array $rawResult Raw result from AnalyticsAgent
   * @return string Default interpretation in English
   */
  private function generateDefaultInterpretation(array $rawResult): string
  {
    if ($this->debugRAManager) {
      // 🔍 DEBUG: Trace why "No results found" is generated
      error_log("\n" . str_repeat("=", 100));
      error_log("DEBUG: generateDefaultInterpretation() CALLED");
      error_log(str_repeat("=", 100));
      error_log("rawResult keys: " . implode(', ', array_keys($rawResult)));
      error_log("rawResult['results'] isset: " . (isset($rawResult['results']) ? 'YES' : 'NO'));
    }

    $results = $rawResult['results'] ?? [];
    if ($this->debugRAManager) {
      error_log("results after ?? []: is_array=" . (is_array($results) ? 'YES' : 'NO'));
      error_log("results count: " . count($results));
    }

    $count = count($results);
    $question = $rawResult['question'] ?? '';

    if ($this->debugRAManager) {
      error_log("count: {$count}");
      error_log("question: {$question}");
    }
    // No results
    if ($count === 0) {
      if ($this->debugRAManager) {
        error_log("[error] RETURNING: No results found (count === 0)");
        error_log("This is the message user sees!");
        error_log(str_repeat("=", 100) . "\n");
      }
      // Standard, translated empty-results message (no hardcoded string, no "your query"
      // placeholder) — same message the EmptyResultFormatter shows.
      $emptyMessage = CLICSHOPPING::getDef('text_empty_results_base');

      return ($emptyMessage !== '' && $emptyMessage !== 'text_empty_results_base')
        ? $emptyMessage
        : 'No results found.';
    }

    if ($this->debugRAManager) {
      error_log("✅ Has results, generating interpretation");
      error_log(str_repeat("=", 100) . "\n");
    }
    // Single result
    if ($count === 1) {
      return $question !== '' ? "1 result found for: {$question}" : '1 result found.';
    }

    // Multiple results - try to add more context
    $interpretation = $question !== '' ? "{$count} results found for: {$question}" : "{$count} results found.";

    // Try to add a summary of the first result
    if (!empty($results[0]) && is_array($results[0])) {
      $firstResult = $results[0];
      $keys = array_keys($firstResult);

      // If there's a name field, mention it
      $nameFields = ['name', 'manufacturers_name', 'products_name', 'categories_name', 'title'];
      foreach ($nameFields as $field) {
        if (isset($firstResult[$field])) {
          $interpretation .= " (e.g.: {$firstResult[$field]})";
          break;
        }
      }
    }

    return $interpretation;
  }

  /**
   * Record analytics execution and critic evaluation based on real output.
   * Uses AnalyticsCritic and records to rag_agent_actor_executions and rag_agent_critic_evaluations.
   */
  private function recordAnalyticsEvaluation(string $query, array $rawResult, int $executionTimeMs): void
  {
    $sql = $rawResult['sql_query'] ?? '';
    if ($sql === '') {
      return;
    }

    try {
      $context = new Context(
        $this->userId,
        $this->languageId,
        ['user_query' => $query, 'intent' => 'analytics'],
        [],
        ['source' => 'AnalyticsExecutor']
      );

      $actionId = 'analytics_query_' . uniqid('', true);
      $producerId = 'analytics_agent';

      $actionResult = new ActionResult(
        $actionId,
        $producerId,
        [
          'sql' => $sql,
          'explanation' => $rawResult['interpretation'] ?? '',
          'tables_used' => $rawResult['tables_used'] ?? [],
          'query_type' => $rawResult['query_type'] ?? 'unknown'
        ],
        'sql_query',
        [
          'execution_time_ms' => $executionTimeMs,
          'timestamp' => date('Y-m-d H:i:s')
        ],
        $context,
        'success'
      );

      $actorRegistry = new ActorRegistry();
      $actorRegistry->recordExecution(
        $producerId,
        $actionId,
        $actionResult->getResultId(),
        'analytics_query',
        'success',
        $executionTimeMs,
        null,
        'sql_query'
      );

      $criticRegistry = new CriticRegistry();

      // Create AnalyticsCriticWrapper with proper dependencies
      $qualityValidator = new SqlQualityValidator();
      $securityValidator = new SqlSecurityValidator(null, null, $this->debug);
      $performanceValidator = new SqlPerformanceValidator(null, $this->debug);
      
      // Get PDO for DatabaseSchemaManager
      $entityManager = \ClicShopping\AI\Infrastructure\Orm\DoctrineOrm::getEntityManager();
      $pdo = $entityManager->getConnection()->getNativeConnection();
      $securityLogger = new \ClicShopping\AI\Security\SecurityLogger();
      $schemaManager = new \ClicShopping\AI\DomainsAI\Analytics\Agent\DatabaseSchemaManager($pdo, $securityLogger, $this->debug);
      $schemaValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SchemaValidator($schemaManager, $this->debug);
      
      $evaluator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\AnalyticsQualityEvaluator(
        $qualityValidator,
        $securityValidator,
        $performanceValidator,
        $schemaValidator,
        $this->debug
      );
      
      $critic = new AnalyticsCriticWrapper($evaluator, $this->debug);

      $evaluationStart = microtime(true);
      $evaluation = $critic->evaluateAction($actionResult);
      
      // Create SqlQualityCriticWrapper with proper dependencies
      $secondCritic = new SqlQualityCriticWrapper($qualityValidator, $this->debug);
      $secondEvaluation = $secondCritic->evaluateAction($actionResult);
      $evaluationTimeMs = (int)round((microtime(true) - $evaluationStart) * 1000);

      $criticRegistry->recordEvaluation(
        $critic->getCriticId(),
        $evaluation->getEvaluationId(),
        $actionResult->getResultId(),
        $actionResult->getOutputType(),
        $actionResult->getProducerAgentId(),
        [
          'accuracy' => $evaluation->getAccuracyScore(),
          'completeness' => $evaluation->getCompletenessScore(),
          'efficiency' => $evaluation->getEfficiencyScore(),
          'clarity' => $evaluation->getClarityScore()
        ],
        $evaluation->getOverallScore(),
        $evaluation->getFeedback(),
        $evaluation->getStrengths(),
        $evaluation->getImprovements(),
        $evaluationTimeMs
      );

      $criticRegistry->recordEvaluation(
        $secondCritic->getCriticId(),
        $secondEvaluation->getEvaluationId(),
        $actionResult->getResultId(),
        $actionResult->getOutputType(),
        $actionResult->getProducerAgentId(),
        [
          'accuracy' => $secondEvaluation->getAccuracyScore(),
          'completeness' => $secondEvaluation->getCompletenessScore(),
          'efficiency' => $secondEvaluation->getEfficiencyScore(),
          'clarity' => $secondEvaluation->getClarityScore()
        ],
        $secondEvaluation->getOverallScore(),
        $secondEvaluation->getFeedback(),
        $secondEvaluation->getStrengths(),
        $secondEvaluation->getImprovements(),
        $evaluationTimeMs
      );

      $consensusBuilder = new ConsensusBuilder();
      $consensus = $consensusBuilder->buildConsensus([$evaluation, $secondEvaluation]);

      $consensusScore = $consensus->getScore();
      if (AgentSystemConfig::isAdaptiveWeightingEnabled()) {
        $adaptiveScore = $this->recordAdaptiveWeightingConsensus(
          $actionResult,
          [$evaluation, $secondEvaluation],
          [$critic, $secondCritic]
        );
        if ($adaptiveScore !== null) {
          $consensusScore = $adaptiveScore;
        }
      }

      $this->storeCoordinationResult(
        $actionResult,
        $consensus,
        $consensusScore,
        2,
        $executionTimeMs,
        $evaluationTimeMs
      );

      if (AgentSystemConfig::isReputationSystemEnabled()) {
        $this->recordReputationOutcomes(
          [$evaluation, $secondEvaluation],
          $consensusScore
        );
      }
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Failed to record analytics evaluation - " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  private function recordAdaptiveWeightingConsensus(
    ActionResult $actionResult,
    array $evaluations,
    array $critics
  ): ?float {
    try {
      $criticRegistry = Registry::exists('CriticRegistry') ? Registry::get('CriticRegistry') : new CriticRegistry();

      $criticDataCollector = new CriticDataCollector(
        new ReputationStore(),
        $criticRegistry
      );

      $weightingEngine = new LLMWeightingEngine(
        $criticDataCollector,
        new LLMPromptBuilder(),
        new WeightNormalizer(),
        new WeightAuditLogger()
      );

      $evaluationContext = [
        'evaluation_id' => 'eval_' . $actionResult->getResultId(),
        'output_type' => $actionResult->getOutputType(),
        'priority' => 'medium',
        'action_type' => 'analytics_query',
        'required_domains' => ['analytics'],
        'execution_metrics' => $actionResult->getExecutionMetrics(),
        'special_requirements' => []
      ];

      $weightResult = $weightingEngine->calculateAdaptiveWeights($critics, $evaluationContext);

      $weightedConsensusBuilder = new WeightedConsensusBuilder();
      $consensusResult = $weightedConsensusBuilder->buildDynamicConsensus($evaluations, $weightResult);

      return $consensusResult->getDynamicConsensus();
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Adaptive weighting failed - " . $e->getMessage(),
          'warning'
        );
      }
      return null;
    }
  }

  private function storeCoordinationResult(
    ActionResult $actionResult,
    $consensus,
    float $consensusScore,
    int $numCritics,
    int $executionTimeMs,
    int $evaluationTimeMs
  ): void {
    try {
      if (!$this->tableExists('rag_agent_coordinated_results')) {
        return;
      }

      $db = Registry::get('Db');
      $totalTimeMs = $executionTimeMs + $evaluationTimeMs;

      $sql = "INSERT INTO :table_rag_agent_coordinated_results
              (coordination_id, action_id, result_id, actor_id, consensus_id,
               consensus_score, num_evaluations, num_critics, execution_time_ms,
               evaluation_time_ms, total_time_ms, created_at)
              VALUES (:coordination_id, :action_id, :result_id, :actor_id, :consensus_id,
                      :consensus_score, :num_evaluations, :num_critics, :execution_time_ms,
                      :evaluation_time_ms, :total_time_ms, :created_at)";

      $stmt = $db->prepare($sql);
      $stmt->bindValue(':coordination_id', 'coord_' . uniqid('', true));
      $stmt->bindValue(':action_id', $actionResult->getActionId());
      $stmt->bindValue(':result_id', $actionResult->getResultId());
      $stmt->bindValue(':actor_id', $actionResult->getProducerAgentId());
      $stmt->bindValue(':consensus_id', $consensus->getConsensusId());
      $stmt->bindValue(':consensus_score', $consensusScore);
      $stmt->bindValue(':num_evaluations', $numCritics);
      $stmt->bindValue(':num_critics', $numCritics);
      $stmt->bindValue(':execution_time_ms', $executionTimeMs, \PDO::PARAM_INT);
      $stmt->bindValue(':evaluation_time_ms', $evaluationTimeMs, \PDO::PARAM_INT);
      $stmt->bindValue(':total_time_ms', $totalTimeMs, \PDO::PARAM_INT);
      $stmt->bindValue(':created_at', date('Y-m-d H:i:s'));
      $stmt->execute();
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Failed to store coordination result - " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  private function tableExists(string $tableName): bool
  {
    try {
      $db = Registry::get('Db');
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      $fullTableName = $prefix . $tableName;
      $sql = "SHOW TABLES LIKE ?";
      $stmt = $db->prepare($sql);
      $stmt->execute([$fullTableName]);
      return $stmt->rowCount() > 0;
    } catch (\Exception $e) {
      return false;
    }
  }

  private function recordReputationOutcomes(array $evaluations, float $consensusScore): void
  {
    try {
      $tracker = new ReputationTracker();

      foreach ($evaluations as $evaluation) {
        $agentEvaluation = new AgentEvaluation(
          $evaluation->getEvaluatorAgentId(),
          $evaluation->getOutputId(),
          [
            'accuracy' => $evaluation->getAccuracyScore(),
            'completeness' => $evaluation->getCompletenessScore(),
            'efficiency' => $evaluation->getEfficiencyScore(),
            'clarity' => $evaluation->getClarityScore()
          ],
          $evaluation->getFeedback(),
          $evaluation->getStrengths(),
          $evaluation->getImprovements()
        );

        $tracker->trackEvaluation($agentEvaluation, $consensusScore, false);
      }
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Failed to record reputation outcomes - " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  private function registerSlowQueryObjective(string $query, int $executionTimeMs): void
  {
    if ($executionTimeMs < 1500 || $this->analyticsAgent === null) {
      return;
    }

    try {
      $goalStatement = 'Optimize analytics query performance';
      $successCriteria = [
        'query' => $query,
        'max_execution_time_ms' => 1000
      ];
      $priority = $executionTimeMs >= 5000 ? 'high' : 'medium';

      $this->analyticsAgent->createLocalObjective($goalStatement, $successCriteria, $priority);
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Failed to register autonomous objective - " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  /**
   * Handle analytics error
   *
   * @param \Exception $e Exception
   * @param string $query Original query
   * @return array Error result
   */
  public function handleAnalyticsError(\Exception $e, string $query): array
  {
    $this->logger->logSecurityEvent(
      "Analytics query failed: {$query} - " . $e->getMessage(),
      'error'
    );

    return [
      'type' => 'analytics_response',
      'success' => false,
      'error' => $e->getMessage(),
      'question' => $query,
      'interpretation' => 'Error: ' . $e->getMessage(),
      'results' => [],
      'sql_query' => '',
    ];
  }

  /**
   * Handle SQL generation failure for temporal period
   *
   * **Requirement 8.4**: Handle SQL generation failures
   *
   * When SQL generation fails for a temporal period:
   * 1. Log error with details
   * 2. Continue with other sub-queries
   * 3. Return partial results with error message
   *
   * @param string $query The query that failed
   * @param string $temporalPeriod The temporal period that failed
   * @param \Exception|null $exception The exception if any
   * @param string|null $errorMessage Custom error message
   * @return array Error result with structure:
   *   - type: string ('analytics_response')
   *   - success: bool (false)
   *   - error: string (error message)
   *   - error_type: string ('sql_generation_failure')
   *   - temporal_period: string (the failed period)
   *   - question: string (original query)
   *   - interpretation: string (error explanation)
   *   - results: array (empty)
   *   - sql_query: string (empty or partial)
   *   - can_continue: bool (true - other sub-queries can proceed)
   *   - suggested_action: string (what user can do)
   */
  public function handleSqlGenerationFailure(
    string $query,
    string $temporalPeriod,
    ?\Exception $exception = null,
    ?string $errorMessage = null
  ): array {
    $errorMsg = $errorMessage ?? ($exception ? $exception->getMessage() : 'Unknown SQL generation error');

    // Log detailed error for debugging
    $this->logger->logSecurityEvent(
      "SQL generation failed for temporal period '{$temporalPeriod}': {$errorMsg}",
      'error',
      [
        'query' => $query,
        'temporal_period' => $temporalPeriod,
        'error' => $errorMsg,
        'exception_class' => $exception ? get_class($exception) : null,
        'trace' => $exception ? $exception->getTraceAsString() : null,
      ]
    );

    // Determine suggested action based on error type
    $suggestedAction = $this->determineSuggestedActionForSqlError($errorMsg, $temporalPeriod);

    return [
      'type' => 'analytics_response',
      'success' => false,
      'error' => $errorMsg,
      'error_type' => 'sql_generation_failure',
      'temporal_period' => $temporalPeriod,
      'question' => $query,
      'interpretation' => "Unable to generate SQL for {$temporalPeriod} aggregation: {$errorMsg}",
      'results' => [],
      'sql_query' => '',
      'can_continue' => true, // Other sub-queries can still proceed
      'suggested_action' => $suggestedAction,
      'source_attribution' => [
        'source_type' => 'Analytics Database',
        'source_icon' => '⚠️',
        'source_details' => "SQL generation failed for {$temporalPeriod} period",
        'table_name' => 'unknown',
      ],
    ];
  }

  /**
   * Determine suggested action for SQL generation error
   *
   * @param string $errorMessage The error message
   * @param string $temporalPeriod The temporal period
   * @return string Suggested action for user
   */
  private function determineSuggestedActionForSqlError(string $errorMessage, string $temporalPeriod): string
  {
    $errorLower = strtolower($errorMessage);

    // Check for common error patterns
    if (str_contains($errorLower, 'column') || str_contains($errorLower, 'field')) {
      return "The database may not have the required columns for {$temporalPeriod} aggregation. Try a different time period or check your data schema.";
    }

    if (str_contains($errorLower, 'table')) {
      return "The required table for {$temporalPeriod} aggregation may not exist. Verify your database structure.";
    }

    if (str_contains($errorLower, 'syntax')) {
      return "There was a SQL syntax error. Try rephrasing your query or using a simpler time period.";
    }

    if (str_contains($errorLower, 'permission') || str_contains($errorLower, 'access')) {
      return "You may not have permission to access the required data. Contact your administrator.";
    }

    if (str_contains($errorLower, 'timeout')) {
      return "The query took too long. Try a shorter time range or simpler aggregation.";
    }

    // Default suggestion
    return "Try rephrasing your query or using a different temporal period. If the problem persists, contact support.";
  }

  /**
   * Handle no data for temporal period
   *
   * **Requirement 8.5**: Handle no data for temporal period
   *
   * When no data exists for a temporal period:
   * 1. Return empty result set
   * 2. Display clear message
   * 3. Continue with other periods
   *
   * @param string $query The query that returned no data
   * @param string $temporalPeriod The temporal period with no data
   * @param array $context Additional context (time_range, base_metric, etc.)
   * @return array Result with empty data and clear message
   */
  public function handleNoDataForTemporalPeriod(
    string $query,
    string $temporalPeriod,
    array $context = []
  ): array {
    $timeRange = $context['time_range'] ?? 'the specified period';
    $baseMetric = $context['base_metric'] ?? 'data';

    // Log the no-data situation
    $this->logger->logSecurityEvent(
      "No data found for temporal period '{$temporalPeriod}'",
      'info',
      [
        'query' => $query,
        'temporal_period' => $temporalPeriod,
        'time_range' => $timeRange,
        'base_metric' => $baseMetric,
      ]
    );

    // Generate user-friendly message based on context
    $message = $this->generateNoDataMessage($temporalPeriod, $timeRange, $baseMetric);

    return [
      'type' => 'analytics_response',
      'success' => true, // Query succeeded, just no data
      'no_data' => true,
      'temporal_period' => $temporalPeriod,
      'question' => $query,
      'interpretation' => $message,
      'results' => [],
      'sql_query' => '', // SQL was executed but returned no rows
      'can_continue' => true, // Other temporal periods can still have data
      'message' => $message,
      'source_attribution' => [
        'source_type' => 'Analytics Database',
        'source_icon' => 'ℹ️',
        'source_details' => "No {$baseMetric} data available for {$temporalPeriod} aggregation",
        'table_name' => 'orders', // Default table for analytics
      ],
    ];
  }

  /**
   * Generate user-friendly message for no data situation
   *
   * @param string $temporalPeriod The temporal period
   * @param string $timeRange The time range
   * @param string $baseMetric The base metric
   * @return string User-friendly message
   */
  private function generateNoDataMessage(string $temporalPeriod, string $timeRange, string $baseMetric): string
  {
    // Format temporal period for display
    $periodDisplay = ucfirst($temporalPeriod);
    
    // Build message based on available context
    $message = "No {$baseMetric} data available";
    
    if ($temporalPeriod !== 'unknown') {
      $message .= " for {$periodDisplay} aggregation";
    }
    
    if ($timeRange !== 'the specified period') {
      $message .= " during {$timeRange}";
    }
    
    $message .= ".";
    
    // Add helpful suggestion
    $suggestions = [
      'day' => "Try a longer time range or check if there were any transactions on this day.",
      'week' => "Try a different week or check if there were any transactions during this period.",
      'month' => "Try a different month or verify that data exists for this period.",
      'quarter' => "Try a different quarter or check if data has been recorded for this period.",
      'semester' => "Try a different semester or verify that data exists for this 6-month period.",
      'year' => "Try a different year or check if data has been recorded for this period.",
    ];
    
    $suggestion = $suggestions[strtolower($temporalPeriod)] ?? "Try a different time period or verify that data exists.";
    $message .= " " . $suggestion;
    
    return $message;
  }

  /**
   * Check if result has data
   *
   * Helper method to determine if a query result contains actual data.
   *
   * @param array $result The query result
   * @return bool True if result has data
   */
  public function hasData(array $result): bool
  {
    // Check for explicit no_data flag
    if (isset($result['no_data']) && $result['no_data'] === true) {
      return false;
    }

    // Check for empty results array
    if (!isset($result['results']) || empty($result['results'])) {
      return false;
    }

    // Check if results is an array with actual data
    if (is_array($result['results']) && count($result['results']) > 0) {
      return true;
    }

    return false;
  }

  /**
   * Format empty result for display
   *
   * Creates a formatted empty result that can be displayed alongside
   * other temporal period results.
   *
   * @param string $temporalPeriod The temporal period
   * @param string $message The message to display
   * @return array Formatted empty result
   */
  public function formatEmptyResult(string $temporalPeriod, string $message): array
  {
    return [
      'type' => 'analytics_response',
      'success' => true,
      'no_data' => true,
      'temporal_period' => $temporalPeriod,
      'interpretation' => $message,
      'results' => [],
      'formatted_html' => '<div class="alert alert-info" role="alert">' .
                          '<i class="bi bi-info-circle"></i> ' .
                          htmlspecialchars($message) .
                          '</div>',
    ];
  }

  /**
   * Get analytics agent instance
   *
   * @return AnalyticsAgent|null
   */
  public function getAnalyticsAgent(): ?AnalyticsAgent
  {
    return $this->analyticsAgent;
  }

  /**
   * Apply temporal GROUP BY to an existing SQL query
   *
   * This method modifies an existing SQL query to add or replace the GROUP BY clause
   * based on the temporal period specified in the context.
   *
   * @param string $sql The original SQL query
   * @param array $context The sub-query context containing temporal metadata
   * @return string The modified SQL query with temporal GROUP BY
   */
  public function applyTemporalGroupBy(string $sql, array $context): string
  {
    // Detect temporal period from context
    $temporalPeriod = $this->detectTemporalPeriod($context);

    if ($temporalPeriod === null) {
      // No temporal period specified, return original SQL
      return $sql;
    }

    // Detect date column from SQL or use default
    $dateColumn = $this->detectDateColumnFromSql($sql) ?? 'orders_date';

    // Detect custom months if applicable
    $customMonths = $temporalPeriod === 'custom' ? $this->detectCustomPeriodMonths($context) : null;

    // Generate the GROUP BY clause
    $groupByClause = $this->generateGroupByClause($temporalPeriod, $dateColumn, $customMonths);

    // Check if SQL already has a GROUP BY clause
    if (preg_match('/\bGROUP\s+BY\b/i', $sql)) {
      // Replace existing GROUP BY clause
      $sql = preg_replace('/\bGROUP\s+BY\s+[^;]+/i', $groupByClause, $sql);
    } else {
      // Add GROUP BY clause before ORDER BY or at the end
      if (preg_match('/\bORDER\s+BY\b/i', $sql)) {
        $sql = preg_replace('/(\bORDER\s+BY\b)/i', "{$groupByClause} $1", $sql);
      } else {
        // Add at the end (before semicolon if present)
        $sql = rtrim($sql, ';') . " {$groupByClause}";
      }
    }

    // Log the modification
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Applied temporal GROUP BY: period={$temporalPeriod}, clause={$groupByClause}",
        'info'
      );
    }

    return $sql;
  }

  /**
   * Detect temporal period from sub-query metadata
   *
   * This method extracts the temporal period from the sub-query context array.
   * It's used to determine which GROUP BY clause to generate.
   *
   * @param array $context The sub-query context containing temporal metadata
   * @return string|null The temporal period or null if not found
   */
  public function detectTemporalPeriod(array $context): ?string
  {
    // Check for explicit temporal_period in context
    if (isset($context['temporal_period']) && !empty($context['temporal_period'])) {
      return strtolower($context['temporal_period']);
    }

    // Check for temporal_periods array (from multi-temporal queries)
    if (isset($context['temporal_periods']) && is_array($context['temporal_periods']) && !empty($context['temporal_periods'])) {
      return strtolower($context['temporal_periods'][0]);
    }

    // Check for period in intent metadata
    if (isset($context['intent']['temporal_period'])) {
      return strtolower($context['intent']['temporal_period']);
    }

    return null;
  }

  /**
   * Detect date column from SQL query
   *
   * This method attempts to identify the date column used in the SQL query
   * by looking for common date column patterns.
   * Pattern logic moved to AnalyticsExecutorPatterns class.
   *
   * @param string $sql The SQL query
   * @return string|null The detected date column or null if not found
   */
  private function detectDateColumnFromSql(string $sql): ?string
  {
    return AnalyticsExecutorPatterns::detectDateColumn($sql);
  }

  /**
   * Detect custom period months from sub-query metadata
   *
   * This method extracts the custom period months (e.g., 4 for "every 4 months")
   * from the sub-query context array.
   *
   * @param array $context The sub-query context containing temporal metadata
   * @return int|null The number of months per period or null if not a custom period
   */
  public function detectCustomPeriodMonths(array $context): ?int
  {
    // Check for explicit custom_months in context
    if (isset($context['custom_months']) && is_numeric($context['custom_months'])) {
      return (int)$context['custom_months'];
    }

    // Check for custom_period_months in intent metadata
    if (isset($context['intent']['custom_period_months']) && is_numeric($context['intent']['custom_period_months'])) {
      return (int)$context['intent']['custom_period_months'];
    }

    return null;
  }

  /**
   * Generate GROUP BY clause for temporal aggregation
   *
   * This method generates the correct SQL GROUP BY clause based on the temporal period.
   * It supports all standard temporal periods (month, quarter, semester, year, week, day)
   * and custom periods (e.g., every 4 months).
   *
   *
   * @param string $temporalPeriod The temporal period (month, quarter, semester, year, week, day, custom)
   * @param string $dateColumn The date column to use for grouping (default: 'orders_date')
   * @param int|null $customMonths For custom periods, the number of months per period (e.g., 4 for quarterly-like)
   * @return string The GROUP BY clause (e.g., "GROUP BY YEAR(orders_date), MONTH(orders_date)")
   */
  public function generateGroupByClause(
    string $temporalPeriod,
    string $dateColumn = 'orders_date',
    ?int $customMonths = null
  ): string {
    // Sanitize date column to prevent SQL injection
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $dateColumn);

    // Log temporal GROUP BY generation
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Generating GROUP BY clause for temporal period: {$temporalPeriod}, column: {$safeColumn}",
        'info'
      );
    }

    $groupByClause = match (strtolower($temporalPeriod)) {
      'month' => $this->generateMonthGroupBy($safeColumn),
      'quarter' => $this->generateQuarterGroupBy($safeColumn),
      'semester' => $this->generateSemesterGroupBy($safeColumn),
      'year' => $this->generateYearGroupBy($safeColumn),
      'week' => $this->generateWeekGroupBy($safeColumn),
      'day' => $this->generateDayGroupBy($safeColumn),
      'custom' => $this->generateCustomGroupBy($safeColumn, $customMonths ?? 4),
      default => $this->generateMonthGroupBy($safeColumn), // Default to month
    };

    // Log the generated clause
    error_log("[AnalyticsExecutor] Generated temporal GROUP BY: {$groupByClause}");

    return $groupByClause;
  }

  /**
   * Generate GROUP BY clause for monthly aggregation
   *
   * ** GROUP BY YEAR(date_column), MONTH(date_column)
   *
   * @param string $dateColumn The date column name
   * @return string GROUP BY clause for monthly aggregation
   */
  private function generateMonthGroupBy(string $dateColumn): string
  {
    return "GROUP BY YEAR({$dateColumn}), MONTH({$dateColumn})";
  }

  /**
   * Generate GROUP BY clause for quarterly aggregation
   *
   * **Requirement 6.2**: GROUP BY YEAR(date_column), QUARTER(date_column)
   *
   * @param string $dateColumn The date column name
   * @return string GROUP BY clause for quarterly aggregation
   */
  private function generateQuarterGroupBy(string $dateColumn): string
  {
    return "GROUP BY YEAR({$dateColumn}), QUARTER({$dateColumn})";
  }

  /**
   * Generate GROUP BY clause for semester aggregation
   *
   * **Requirement 6.3**: GROUP BY YEAR(date_column), CASE WHEN MONTH(date_column) <= 6 THEN 1 ELSE 2 END
   *
   * @param string $dateColumn The date column name
   * @return string GROUP BY clause for semester aggregation
   */
  private function generateSemesterGroupBy(string $dateColumn): string
  {
    return "GROUP BY YEAR({$dateColumn}), CASE WHEN MONTH({$dateColumn}) <= 6 THEN 1 ELSE 2 END";
  }

  /**
   * Generate GROUP BY clause for yearly aggregation
   *
   * **Requirement 6.4**: GROUP BY YEAR(date_column)
   *
   * @param string $dateColumn The date column name
   * @return string GROUP BY clause for yearly aggregation
   */
  private function generateYearGroupBy(string $dateColumn): string
  {
    return "GROUP BY YEAR({$dateColumn})";
  }

  /**
   * Generate GROUP BY clause for weekly aggregation
   *
   * **Requirement 6.5**: GROUP BY YEAR(date_column), WEEK(date_column)
   *
   * @param string $dateColumn The date column name
   * @return string GROUP BY clause for weekly aggregation
   */
  private function generateWeekGroupBy(string $dateColumn): string
  {
    return "GROUP BY YEAR({$dateColumn}), WEEK({$dateColumn})";
  }

  /**
   * Generate GROUP BY clause for daily aggregation
   *
   * **Requirement 6.6**: GROUP BY DATE(date_column)
   *
   * @param string $dateColumn The date column name
   * @return string GROUP BY clause for daily aggregation
   */
  private function generateDayGroupBy(string $dateColumn): string
  {
    return "GROUP BY DATE({$dateColumn})";
  }

  /**
   * Generate GROUP BY clause for custom period aggregation
   *
   * ** GROUP BY YEAR(date_column), FLOOR((MONTH(date_column)-1)/N)
   *
   * This allows grouping by custom periods like "every 4 months" or "every 2 months"
   *
   * @param string $dateColumn The date column name
   * @param int $monthsPerPeriod Number of months per period (e.g., 4 for every 4 months)
   * @return string GROUP BY clause for custom period aggregation
   */
  private function generateCustomGroupBy(string $dateColumn, int $monthsPerPeriod): string
  {
    // Ensure monthsPerPeriod is valid (1-12)
    $monthsPerPeriod = max(1, min(12, $monthsPerPeriod));

    return "GROUP BY YEAR({$dateColumn}), FLOOR((MONTH({$dateColumn})-1)/{$monthsPerPeriod})";
  }
}
