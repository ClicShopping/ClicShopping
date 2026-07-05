<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Prompt;

use ClicShopping\OM\Registry;
use ClicShopping\OM\Cache as OMCache;
use ClicShopping\AI\Infrastructure\Schema\SchemaRetriever;
use ClicShopping\AI\Config\DomainConfig;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * PromptBuilder
 * 
 * Centralizes all prompt construction logic for multiple agent types
 * Supports: Analytics, Semantic, WebSearch, Hybrid agents
 * Handles system message loading, caching, and enrichment
 * 
 * Responsibilities:
 * - System message construction from language definitions (per agent type)
 * - Static caching of system message (shared across instances, per agent type)
 * - Question enrichment with feedback context
 * - Question enrichment with last SQL query
 * - Template management and placeholder replacement
 * 
 * @package ClicShopping\AI\Infrastructure\Prompt
 */

class PromptBuilder
{
  private const AGENT_TYPES = ['analytics', 'semantic', 'websearch', 'hybrid'];
  private static array $systemMessageCache = [];
  private mixed $language;
  private int $languageId;
  private bool $debug;
  private string $useCache;
  private ?SchemaRetriever $schemaRetriever = null;
  private string $currentQuery = '';
  
  // Supported agent types
  private string $modelName;
  
  // Static cache for system messages (per agent type)
  private string $agentType = 'analytics';
  
  /**
   * Constructor
   * 
   * Initializes the PromptBuilder with language service and configuration
   * 
   * @param mixed $language Language service instance
   * @param int $languageId Language ID for placeholder replacement
   * @param bool $debug Debug mode flag for logging
   */
  public function __construct($language, int $languageId, bool $debug = false)
  {
    $this->language = $language;
    $this->languageId = $languageId;
    $this->debug = $debug;
    $this->useCache = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True' ? 'True' : 'False';
    $this->modelName = Gpt::defaultModel();

    // Initialize SchemaRetriever if Schema RAG is enabled
    $useSchemaRAG = CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG;
    if ($useSchemaRAG) {
      // Check if embeddings should be used
      $useEmbeddings = CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_USE_EMBEDDINGS;
      
      $this->schemaRetriever = new SchemaRetriever($debug, $useEmbeddings);
      
      if ($debug) {
        error_log("[PromptBuilder] Schema RAG initialized with embeddings: " . ($useEmbeddings ? 'ENABLED' : 'DISABLED (Pure LLM mode)'));
      }
    }
  }
  
  /**
   * Clear static cache (for testing)
   *
   * Clears the static system message cache for one or all agent types
   * Used primarily for testing to ensure fresh builds
   *
   * @param string|null $agentType Agent type to clear, or null to clear all
   * @return void
   */
  public static function clearCache(?string $agentType = null): void
  {
    if ($agentType === null) {
      // Clear all caches
      self::$systemMessageCache = [];
    } else {
      // Clear specific agent cache
      unset(self::$systemMessageCache[$agentType]);
    }
  }
  
  /**
   * Get system message (with static and OM caching)
   *
   * Loads system message once per PHP process using static cache (per agent type)
   * Also checks OM cache for persistence across requests
   * Subsequent calls return cached version
   *
   * NOTE: When Schema RAG is enabled, caching is disabled because
   * the system message is query-specific
   *
   * @param string $agentType Agent type (analytics, semantic, websearch, hybrid)
   * @param string $query User query (optional, for Schema RAG)
   * @param string $modelName Model name (optional, for Schema RAG)
   * @return string Complete system message
   * @throws \InvalidArgumentException If agent type is invalid
   */
  public function getSystemMessage(string $agentType = 'analytics', string $query = '', string $modelName = 'gpt-4o-mini'): string
  {
    // Validate agent type
    if (!in_array($agentType, self::AGENT_TYPES, true)) {
      throw new \InvalidArgumentException("Invalid agent type: {$agentType}. Supported types: " . implode(', ', self::AGENT_TYPES));
    }

    // Store parameters for buildSystemMessage()
    $this->agentType = $agentType;
    $this->currentQuery = $query;
    $this->modelName = $modelName;

    // If Schema RAG is enabled and we have a query, skip caching (analytics only)
    $useSchemaRAG = CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG;

    if ($agentType === 'analytics' && $useSchemaRAG && !empty($query)) {
      // Build query-specific system message (no caching)
      $systemMessage = $this->buildSystemMessage($agentType);

      if ($this->debug) {
        error_log("[PromptBuilder] Built query-specific system message for {$agentType} with Schema RAG (" . strlen($systemMessage) . " chars)");
      }

      return $systemMessage;
    }

    // Standard caching flow (for full schema or when no query provided)

    // Check static cache first (fastest)
    if (isset(self::$systemMessageCache[$agentType])) {
      if ($this->debug) {
        error_log("[PromptBuilder] Using static cached system message for {$agentType}");
      }
      return self::$systemMessageCache[$agentType];
    }

    // Check OM cache if enabled
    if ($this->useCache === 'True') {
      $cacheKey = "{$agentType}_system_prompt_en_{$this->languageId}";
      $cache = new OMCache($cacheKey);
      $cached = $cache->get();

      if (!empty($cached)) {
        if ($this->debug) {
          error_log("[PromptBuilder] Using OM cached system message for {$agentType} (length: " . strlen($cached) . ")");
        }

        // Store in static cache for subsequent calls
        self::$systemMessageCache[$agentType] = $cached;
        return $cached;
      }
    }

    // Build system message
    $systemMessage = $this->buildSystemMessage($agentType);

    // Store in static cache
    self::$systemMessageCache[$agentType] = $systemMessage;

    // Store in OM cache if enabled
    if ($this->useCache === 'True') {
      $cacheKey = "{$agentType}_system_prompt_en_{$this->languageId}";
      $cache = new OMCache($cacheKey);
      $cache->save($systemMessage);
    }

    if ($this->debug) {
      error_log("[PromptBuilder] Built new system message for {$agentType} (" . strlen($systemMessage) . " chars)");
    }

    return $systemMessage;
  }
  
  /**
   * Build complete system message
   *
   * Routes to agent-specific builder based on agent type
   *
   * @param string $agentType Agent type (analytics, semantic, websearch, hybrid)
   * @return string Complete system message with placeholders replaced
   * @throws \InvalidArgumentException If agent type is invalid
   */
  private function buildSystemMessage(string $agentType): string
  {
    switch ($agentType) {
      case 'analytics':
        return $this->buildSystemMessageAnalytics();
      case 'semantic':
        return $this->buildSystemMessageSemantics();
      case 'websearch':
        return $this->buildSystemMessageWebSearch();
      case 'hybrid':
        return $this->buildSystemMessageHybrid();
      default:
        throw new \InvalidArgumentException("Invalid agent type: {$agentType}");
    }
  }
  
  /**
   * Build Analytics agent system message
   *
   * Loads all language definitions and constructs the complete prompt
   * Combines multiple prompt components in the correct order
   * Replaces placeholders with actual values
   *
   * @return string Complete system message with placeholders replaced
   */
  private function buildSystemMessageAnalytics(): string
  {
    // Load language definitions from ClicShoppingAdmin/Core/languages/main.txt
    // This loads the AnalyticsAgent prompt definitions in English
    DomainConfig::loadLanguageFile('rag_analytics_agent');

    // Get all prompt components
    $baseSystemMessage = $this->language->getDef('text_system_message');
    $orderCalculation = $this->language->getDef('text_order_calculation');
    $queryExamples = $this->language->getDef('text_query_examples');
    $sqlGenerationRules = $this->language->getDef('text_sql_generation_rules');
    $aggregationRules = $this->language->getDef('text_aggregation_rules');  // 🚨 CRITICAL: Load aggregation rules
    $sqlFormatInstructions = $this->language->getDef('text_sql_format_instructions');
    $text_multi_query_warning = $this->language->getDef('text_multi_query_warning');
    $securityGuidelines = $this->language->getDef('text_security_guidelines');
    $entityMetadataGuidelines = $this->language->getDef('text_entity_metadata_guidelines');
    $multiTokenRules = $this->language->getDef('multi_token_rules');
    $responseFormat = $this->language->getDef('text_response_format');
    $text_rag_system_message_template = $this->language->getDef('text_rag_system_message_template');
    $text_rag_system_analytics_rules = $this->language->getDef('text_rag_system_analytics_rules');

    // TASK 1.1: Add current date context for relative date queries
    $dateContext = $this->buildDateContext();

    // Get table structure instructions (Schema RAG or full schema)
    $tableStructureInstructions = $this->getTableStructureInstructions();


    // Debug logging for loaded components
    if ($this->debug) {
      error_log("================================================================================");
      error_log("DEBUG PromptBuilder::buildSystemMessage()");
      error_log("================================================================================");
      error_log("text_system_message length: " . strlen($baseSystemMessage) . " chars");
      error_log("securityGuidelines length: " . strlen($securityGuidelines) . " chars");
      error_log("text_rag_system_analytics_rules length: " . strlen($text_rag_system_analytics_rules) . " chars");
      error_log("tableStructureInstructions length: " . strlen($tableStructureInstructions) . " chars");
      error_log("text_multi_query_warning length: " . strlen($text_multi_query_warning) . " chars");
      error_log("entityMetadataGuidelines length: " . strlen($entityMetadataGuidelines) . " chars");
      error_log("text_aggregation_rules length: " . strlen($aggregationRules) . " chars");
      error_log("text_sql_generation_rules length: " . strlen($sqlGenerationRules) . " chars");
      error_log("text_order_calculation length: " . strlen($orderCalculation) . " chars");
      error_log("text_query_examples length: " . strlen($queryExamples) . " chars");
      error_log("sqlFormatInstructions length: " . strlen($sqlFormatInstructions) . " chars");
      error_log("text_response_format length: " . strlen($responseFormat) . " chars");
      error_log("text_multi_token_rules length: " . strlen($multiTokenRules) . " chars");
      error_log("Contains 'MULTI-TOKEN' in multiTokenRules: " . (str_contains($multiTokenRules, 'MULTI-TOKEN') ? 'YES' : 'NO'));
      error_log("Contains 'ABSOLUTE RULE' in aggregationRules: " . (str_contains($aggregationRules, 'ABSOLUTE RULE') ? 'YES' : 'NO'));
      error_log("First 200 chars of multiTokenRules: " . substr($multiTokenRules, 0, 200));
      error_log("================================================================================");
    }

    // Construct complete message in the correct order
    $completeSystemMessage = $baseSystemMessage . "\n\n" . // 1. Role and essential ID rules
      $dateContext . "\n\n" .                                          // Current date context (CRITICAL for relative dates)
      $securityGuidelines . "\n\n" .                                 // 3. Security and prohibition rules
      $text_rag_system_analytics_rules . "\n\n" .                    // 4. Critical ambiguity rules
      $tableStructureInstructions . "\n\n" .                         // 5. Database schema (the playground)
      $entityMetadataGuidelines . "\n\n" .                           // 6. Schema metadata
      $aggregationRules . "\n\n" .                                   // 7. 🚨 CRITICAL: Aggregation rules (MUST be before SQL generation)
      $sqlGenerationRules . "\n\n" .                                 // 8. SQL construction rules (JOINs, etc.)
      $orderCalculation . "\n\n" .                                   // 9. Specific calculation rules
      $queryExamples . "\n\n" .                                      // 10. Examples (Few-shot learning)
      $sqlFormatInstructions . "\n\n" .                              // 11. SQL code format
      $text_multi_query_warning. "\n\n" .                            // 12. Critical multi pattern rules
      $responseFormat . "\n\n" .                                     // 13. Response format
      $text_rag_system_message_template . "\n\n" .                   // 14. RAG context
      $multiTokenRules . "\n\n"                                      // 15. Parsing rules
    ;

    // Replace placeholders with actual values
    // The prompt contains {{language_id}} placeholders that need to be replaced
    $finalMessage = str_replace('{{language_id}}', (string)$this->languageId, $completeSystemMessage);

    if ($this->debug) {
      $placeholderCount = substr_count($completeSystemMessage, '{{language_id}}');
      error_log("Replaced {$placeholderCount} occurrences of {{language_id}} with {$this->languageId}");
      error_log("================================================================================");
      error_log("DEBUG PromptBuilder::buildSystemMessage() - FINAL");
      error_log("================================================================================");
      error_log("Language ID: " . $this->languageId);
      error_log("Final message length: " . strlen($finalMessage));
      error_log("Contains 'CRITICAL RULES': " . (str_contains($finalMessage, 'CRITICAL RULES') ? 'YES' : 'NO'));
      error_log("Contains 'products_quantity': " . (str_contains($finalMessage, 'products_quantity') ? 'YES' : 'NO'));
      error_log("First 500 chars: " . substr($finalMessage, 0, 500));
      error_log("================================================================================");
    }

    return $finalMessage;
  }
  
  /**
   * Build current date context for analytics queries
   *
   * to help the LLM correctly interpret queries like "last month" across year boundaries.
   *
   * This is CRITICAL for queries in January that reference "last month" (December of previous year).
   *
   * @return string Formatted date context for prompt
   */
  private function buildDateContext(): string
  {
    // Get current date information
    $currentDate = date('Y-m-d');
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');
    $currentMonthName = date('F');
    $currentDayOfMonth = (int)date('d');

    // Calculate last month (handles year boundary correctly)
    $lastMonthTimestamp = strtotime('first day of last month');
    $lastMonthStart = date('Y-m-01', $lastMonthTimestamp);
    $lastMonthEnd = date('Y-m-t', $lastMonthTimestamp);
    $lastMonthYear = (int)date('Y', $lastMonthTimestamp);
    $lastMonthNumber = (int)date('m', $lastMonthTimestamp);
    $lastMonthName = date('F Y', $lastMonthTimestamp);

    // Calculate this month
    $thisMonthStart = date('Y-m-01');
    $thisMonthEnd = date('Y-m-t');

    // Calculate last year
    $lastYear = $currentYear - 1;
    $lastYearStart = $lastYear . '-01-01';
    $lastYearEnd = $lastYear . '-12-31';

    DomainConfig::loadAgnosticLanguageFile('rag_context_builder');

    $incorrectComment = ($currentMonth === 1)
      ? "-- This would query December {$currentYear}, which doesn't exist yet!\n-- This will return ZERO RESULTS because December {$currentYear} is in the future!"
      : "-- This is less efficient and can cause errors at year boundaries";

    $array = [
      'currentDate' => $currentDate,
      'currentMonthName' => $currentMonthName,
      'currentDayOfMonth' => $currentDayOfMonth,
      'currentYear' =>  $currentYear,
      'currentMonth' => $currentMonth,
      'lastYear' => $lastYear,
      'lastMonthStart' => $lastMonthStart,
      'lastMonthEnd' => $lastMonthEnd,
      'lastMonthName' => $lastMonthName,
      'lastMonthNumber' => $lastMonthNumber,
      'thisMonthStart' => $thisMonthStart,
      'lastYearStart' => $lastYearStart,
      'lastYearEnd' => $lastYearEnd,
      'incorrectComment' => $incorrectComment,
      'lastMonthYear' => $lastMonthYear
    ];

    $context = $this->language->getDef('rag_context_builder_text', $array);

    // Add critical warning for year boundaries FIRST
    if ($currentMonth === 1) {
      $context .= "\n\n" . $this->language->getDef('rag_context_builder_text_warn', $array);
    }

    $context .= "\n\n" . $this->language->getDef('rag_context_builder_relative_mappings', $array);
    if ($currentMonth === 1) {
      $context .= "\n" . $this->language->getDef('rag_context_builder_relative_mappings_warn_jan', $array);
    }

    $context .= "\n\n" . $this->language->getDef('rag_context_builder_this_month', $array);
    $context .= "\n\n" . $this->language->getDef('rag_context_builder_last_year', $array);
    $context .= "\n\n" . $this->language->getDef('rag_context_builder_sql_best_practices', $array);

    if ($currentMonth === 1) {
      $context .= "\n\n" . $this->language->getDef('rag_context_builder_january_final', $array);
    }

    return $context;
  }
  
  /**
   * Get table structure instructions (Schema RAG or full schema)
   *
   * Decides whether to use Schema RAG (relevant tables only) or full schema
   * based on configuration and query context
   *
   * @return string Table structure instructions
   */
  private function getTableStructureInstructions(): string
  {
    // Check if Schema RAG is enabled
    $useSchemaRAG = CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG;

    // Get max tables configuration
    $maxTables = CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_MAX_TABLES;

    if ($useSchemaRAG && !empty($this->currentQuery) && $this->schemaRetriever !== null) {
      // Use Schema RAG (relevant tables only)
      try {
        $tableStructureInstructions = $this->schemaRetriever->getRelevantSchema(
          $this->currentQuery,
          $this->modelName,
          $maxTables
        );

        if ($this->debug) {
          $tokenCount = $this->estimateTokenCount($tableStructureInstructions);
          error_log("[PromptBuilder] Using Schema RAG: " . strlen($tableStructureInstructions) . " chars (~{$tokenCount} tokens)");
        }

        return $tableStructureInstructions;
      } catch (\Exception $e) {
        // Fallback to full schema if Schema RAG fails
        error_log("[PromptBuilder] Schema RAG failed: " . $e->getMessage());

        $fallbackEnabled = defined('CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_FALLBACK_FULL') && CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_FALLBACK_FULL === 'True';

        if (!$fallbackEnabled) {
          throw $e; // Re-throw if fallback is disabled
        }

        if ($this->debug) {
          error_log("[PromptBuilder] Falling back to full schema");
        }
      }
    }

    // Use full schema (default or fallback)
    // Generate schema with comments from database
    if ($this->debug) {
      error_log("[PromptBuilder] Schema RAG disabled, generating full schema with comments from database");
    }

    return $this->buildFullSchemaWithComments();
  }
  
  /**
   * Estimate token count for text
   *
   * Rough estimate: 4 characters per token
   *
   * @param string $text Text to estimate
   * @return int Estimated token count
   */
  private function estimateTokenCount(string $text): int
  {
    return (int)ceil(strlen($text) / 4);
  }
  
  /**
   * Build full schema with column comments from database
   *
   * @return string Full schema text with column comments
   */
  private function buildFullSchemaWithComments(): string
  {
    $db = Registry::get('Db');

    // Get all tables
    $tablesQuery = "SHOW TABLES";
    $tablesResult = $db->query($tablesQuery);

    $schema = "DATABASE SCHEMA WITH COLUMN DESCRIPTIONS:\n\n";

    while ($tableRow = $tablesResult->fetch()) {
      $tableName = array_values($tableRow)[0];

      // Skip non-clic tables
      if (!str_starts_with($tableName, 'clic_')) {
        continue;
      }

      $schema .= "Table: {$tableName}\n";

      // Get columns with comments
      $columnsQuery = "SHOW FULL COLUMNS FROM {$tableName}";
      $columnsResult = $db->query($columnsQuery);

      while ($column = $columnsResult->fetch()) {
        $field = $column['Field'];
        $type = $column['Type'];
        $comment = !empty($column['Comment']) ? $column['Comment'] : '';

        if (!empty($comment)) {
          $schema .= "  - {$field} ({$type}): {$comment}\n";
        } else {
          $schema .= "  - {$field} ({$type})\n";
        }
      }

      $schema .= "\n";
    }

    return $schema;
  }
  
  /**
   * Build Semantic agent system message
   *
   * Loads semantic-specific language definitions and constructs the prompt
   * Includes: embedding search rules, similarity thresholds, vector matching
   *
   * @return string Complete system message with placeholders replaced
   */
  private function buildSystemMessageSemantics(): string
  {
    // Load language definitions for Semantic agent
    DomainConfig::loadLanguageFile('rag_semantic_agent');

    // Get semantic-specific components
    $baseSystemMessage = $this->language->getDef('text_system_message');
    $embeddingSearchRules = $this->language->getDef('text_embedding_search_rules');
    $similarityThresholds = $this->language->getDef('text_similarity_thresholds');
    $vectorMatching = $this->language->getDef('text_vector_matching');

    // Get shared components
    $securityGuidelines = $this->language->getDef('text_security_guidelines');
    $entityMetadataGuidelines = $this->language->getDef('text_entity_metadata_guidelines');
    $multiTokenRules = $this->language->getDef('multi_token_rules');
    $responseFormat = $this->language->getDef('text_response_format');
    $text_rag_system_message_template = $this->language->getDef('text_rag_system_message_template');

    // Construct complete message
    $completeSystemMessage = $baseSystemMessage . "\n\n" .
      $securityGuidelines . "\n\n" .
      $embeddingSearchRules . "\n\n" .
      $similarityThresholds . "\n\n" .
      $vectorMatching . "\n\n" .
      $entityMetadataGuidelines . "\n\n" .
      $responseFormat . "\n\n" .
      $text_rag_system_message_template . "\n\n" .
      $multiTokenRules . "\n\n";

    // Replace placeholders
    $finalMessage = str_replace('{{language_id}}', (string)$this->languageId, $completeSystemMessage);

    if ($this->debug) {
      error_log("[PromptBuilder] Built Semantic agent message (" . strlen($finalMessage) . " chars)");
    }

    return $finalMessage;
  }
  
  /**
   * Build WebSearch agent system message
   *
   * Loads websearch-specific language definitions and constructs the prompt
   * Includes: external search rules, citation rules, source validation
   *
   * @return string Complete system message with placeholders replaced
   */
  private function buildSystemMessageWebSearch(): string
  {
    // Load language definitions for WebSearch agent
    DomainConfig::loadLanguageFile('rag_websearch_agent');

    // Get websearch-specific components
    $baseSystemMessage = $this->language->getDef('text_system_message');
    $externalSearchRules = $this->language->getDef('text_external_search_rules');
    $citationRules = $this->language->getDef('text_citation_rules');
    $sourceValidation = $this->language->getDef('text_source_validation');

    // Get shared components
    $securityGuidelines = $this->language->getDef('text_security_guidelines');
    $entityMetadataGuidelines = $this->language->getDef('text_entity_metadata_guidelines');
    $multiTokenRules = $this->language->getDef('multi_token_rules');
    $responseFormat = $this->language->getDef('text_response_format');
    $text_rag_system_message_template = $this->language->getDef('text_rag_system_message_template');

    // Construct complete message
    $completeSystemMessage = $baseSystemMessage . "\n\n" .
      $securityGuidelines . "\n\n" .
      $externalSearchRules . "\n\n" .
      $citationRules . "\n\n" .
      $sourceValidation . "\n\n" .
      $entityMetadataGuidelines . "\n\n" .
      $responseFormat . "\n\n" .
      $text_rag_system_message_template . "\n\n" .
      $multiTokenRules . "\n\n";

    // Replace placeholders
    $finalMessage = str_replace('{{language_id}}', (string)$this->languageId, $completeSystemMessage);

    if ($this->debug) {
      error_log("[PromptBuilder] Built WebSearch agent message (" . strlen($finalMessage) . " chars)");
    }

    return $finalMessage;
  }
  
  /**
   * Build Hybrid agent system message
   *
   * Loads hybrid-specific language definitions and constructs the prompt
   * Includes: query splitting, mode selection, result aggregation, plus analytics rules
   *
   * @return string Complete system message with placeholders replaced
   */
  private function buildSystemMessageHybrid(): string
  {
    // Load language definitions for Hybrid agent
    DomainConfig::loadLanguageFile('rag_hybrid_agent');

    // Get hybrid-specific components
    $baseSystemMessage = $this->language->getDef('text_system_message');
    $querySplittingRules = $this->language->getDef('text_query_splitting_rules');
    $modeSelection = $this->language->getDef('text_mode_selection');
    $resultAggregation = $this->language->getDef('text_result_aggregation');

    // Get analytics components (hybrid needs SQL generation)
    $orderCalculation = $this->language->getDef('text_order_calculation');
    $queryExamples = $this->language->getDef('text_query_examples');
    $sqlGenerationRules = $this->language->getDef('text_sql_generation_rules');
    $aggregationRules = $this->language->getDef('text_aggregation_rules');
    $sqlFormatInstructions = $this->language->getDef('text_sql_format_instructions');
    $text_multi_query_warning = $this->language->getDef('text_multi_query_warning');
    $text_rag_system_analytics_rules = $this->language->getDef('text_rag_system_analytics_rules');

    // Get shared components
    $securityGuidelines = $this->language->getDef('text_security_guidelines');
    $entityMetadataGuidelines = $this->language->getDef('text_entity_metadata_guidelines');
    $multiTokenRules = $this->language->getDef('multi_token_rules');
    $responseFormat = $this->language->getDef('text_response_format');
    $text_rag_system_message_template = $this->language->getDef('text_rag_system_message_template');

    // Get table structure (hybrid needs schema for SQL)
    $tableStructureInstructions = $this->getTableStructureInstructions();

    // Construct complete message
    $completeSystemMessage = $baseSystemMessage . "\n\n" .
      $securityGuidelines . "\n\n" .
      $text_rag_system_analytics_rules . "\n\n" .
      $querySplittingRules . "\n\n" .
      $modeSelection . "\n\n" .
      $resultAggregation . "\n\n" .
      $tableStructureInstructions . "\n\n" .
      $entityMetadataGuidelines . "\n\n" .
      $aggregationRules . "\n\n" .
      $sqlGenerationRules . "\n\n" .
      $orderCalculation . "\n\n" .
      $queryExamples . "\n\n" .
      $sqlFormatInstructions . "\n\n" .
      $text_multi_query_warning . "\n\n" .
      $responseFormat . "\n\n" .
      $text_rag_system_message_template . "\n\n" .
      $multiTokenRules . "\n\n";

    // Replace placeholders
    $finalMessage = str_replace('{{language_id}}', (string)$this->languageId, $completeSystemMessage);

    if ($this->debug) {
      error_log("[PromptBuilder] Built Hybrid agent message (" . strlen($finalMessage) . " chars)");
    }

    return $finalMessage;
  }
  
  /**
   * Enrich question with feedback context
   *
   * Adds learning examples from previous corrections
   * Limits to top 3 most relevant examples to avoid prompt bloat
   * Only includes corrections with SQL queries
   *
   * @param string $question Original question
   * @param array $feedbackContext Array of feedback items with correction data
   * @return string Enriched question with learning examples prepended
   */
  public function enrichWithFeedback(string $question, array $feedbackContext): string
  {
    if (empty($feedbackContext)) {
      return $question;
    }

    if ($this->debug) {
      error_log("\n--- Enriching question with " . count($feedbackContext) . " feedback items ---");
    }

    // Limit to top 3 most relevant examples to avoid prompt bloat
    $relevantFeedback = array_slice($feedbackContext, 0, 3);

    $learningExamples = [];

    foreach ($relevantFeedback as $feedback) {
      // Only use corrections with SQL queries
      if ($feedback['feedback_type'] === 'correction' && !empty($feedback['sql_query'])) {
        $example = "Previous example:\n";
        $example .= "Question: " . $feedback['original_query'] . "\n";

        if (!empty($feedback['corrected_response'])) {
          $example .= "Correct SQL: " . $feedback['corrected_response'] . "\n";
        } else {
          $example .= "SQL: " . $feedback['sql_query'] . "\n";
        }

        if (!empty($feedback['correction_comment'])) {
          $example .= "Note: " . $feedback['correction_comment'] . "\n";
        }

        $learningExamples[] = $example;

        if ($this->debug) {
          error_log("Added learning example from interaction: " . $feedback['interaction_id']);
        }
      }
    }

    if (empty($learningExamples)) {
      return $question;
    }

    // Prepend learning examples to the question
    $enrichedQuestion = "Learn from these previous corrections:\n\n";
    $enrichedQuestion .= implode("\n", $learningExamples);
    $enrichedQuestion .= "\nNow answer this question using the same approach:\n";
    $enrichedQuestion .= $question;

    if ($this->debug) {
      error_log("Question enriched with " . count($learningExamples) . " learning examples");
    }

    return $enrichedQuestion;
  }
  
  /**
   * Enrich question with last SQL query
   *
   * Used for modification requests (e.g., "add column X", "change to Y")
   * Loads template from language definitions and replaces placeholders
   *
   * @param string $question User question
   * @param string $lastSQL Last executed SQL query
   * @return string Enriched question with last SQL context
   */
  public function enrichWithLastSQL(string $question, string $lastSQL): string
  {
    // Load language definitions for the template
    DomainConfig::loadLanguageFile('rag_analytics_agent');

    // Get the enrichment template and replace placeholders
    $array = [
      'last_sql' => $lastSQL,
      'question' => $question
    ];

    $enrichedQuestion = $this->language->getDef('text_enrich_with_last_sql', $array);

    if ($this->debug) {
      error_log("[INFO SQL QUERY] Question enriched with last SQL query");
      error_log("Last SQL: " . substr($lastSQL, 0, 100) . "...");
    }

    return $enrichedQuestion;
  }
}
