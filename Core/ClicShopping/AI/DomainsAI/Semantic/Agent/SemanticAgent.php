<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Agent;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\InterfacesAI\ConfigurableComponent;
use ClicShopping\AI\InterfacesAI\QueryTypeDomainInterface;

// Import Semantic Processor components
use ClicShopping\AI\DomainsAI\Semantic\Processor\ClassificationEngine;
use ClicShopping\AI\DomainsAI\Semantic\Processor\TranslationHandler;
use ClicShopping\AI\Infrastructure\Cache\TranslationCache;
use ClicShopping\AI\DomainsAI\Semantic\Processor\ThresholdManager;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Config\DomainFields;
use ClicShopping\AI\InterfacesAI\SemanticConfigInterface;

/*
 * This class is responsible for semantic analysis and classification of queries.
 * It uses the OpenAI API to translate and classify queries, and also logs security events.
 * Implements ConfigurableComponent for dynamic configuration management.
 * Implements QueryTypeDomainInterface for domain-driven architecture.
 */

class SemanticAgent implements ConfigurableComponent, QueryTypeDomainInterface
{
  private static ?SecurityLogger $logger = null;

  // Configuration parameters with default values
  private static array $config = [
    'classification_threshold' => 3,
    'max_retries' => 3,
    'translation_cache_ttl' => 3600,
    'enable_fallback' => true,
    'enable_competitor_detection' => true
  ];

  public function __construct()
  {
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("=== SEMANTICAGENT CLASS LOADED (REFACTORED VERSION) ===");
      error_log("File: " . __FILE__);
      error_log("Modified: " . date("Y-m-d H:i:s", filemtime(__FILE__)));
    }

    self::initializeLogger();
    self::loadConfig();
  }

  /**
   * Loads configuration from JSON file
   * 
   * @return void
   */
  private static function loadConfig(): void
  {
    $configFile = __DIR__ . '/../../config/chat_system_config.json';
    
    if (file_exists($configFile)) {
      $json = file_get_contents($configFile);
      $config = json_decode($json, true);
      
      if (isset($config['Semantics']) && is_array($config['Semantics'])) {
        // Merge loaded config with defaults
        self::$config = array_merge(self::$config, $config['Semantics']);

        if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
          error_log("SemanticAgent configuration loaded from file");
        }
      }
    }
  }

  /**
   * Saves current configuration to JSON file
   * 
   * @return bool True if successful, false otherwise
   */
  private static function saveConfig(): bool
  {
    $configFile = __DIR__ . '/../../config/chat_system_config.json';
    
    // Load existing config
    $fullConfig = [];
    if (file_exists($configFile)) {
      $json = file_get_contents($configFile);
      $fullConfig = json_decode($json, true) ?? [];
    }
    
    // Update Semantics section
    $fullConfig['Semantics'] = self::$config;
    
    // Save to file
    $json = json_encode($fullConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $result = file_put_contents($configFile, $json, LOCK_EX);
    
    if ($result !== false && defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("SemanticAgent configuration saved to file");
    }
    
    return $result !== false;
  }

  /**
   * Returns a singleton instance of the SecurityLogger
   * Initializes the logger with specified parameters if not already created
   *
   * @return void Instance of SecurityLogger
   */
  private static function initializeLogger(): void
  {
    if (self::$logger === null) {
      self::$logger = new SecurityLogger();
    }
  }

  /**
   * Logs security-related events
   * Delegates logging to the SecurityLogger instance
   *
   * @param string $text
   * @param string $alert
   * @return void
   */
  private static function logSecurityEvent(string $text, string $alert): void
  {
    self::initializeLogger(); // Ensure logger is initialized
    self::$logger->logSecurityEvent($text, $alert); // Call the logger's method
  }

  /**
   * Translate a given text to English using the OpenAI API.
   * to prevent blocking when translation service fails
   * 
   * @param string $message
   * @param int|null $token
   * @return string Translated text or original message if translation fails
   */
  public static function translateToEnglish(string $message, int|null $token = 80): string
  {
    try {
      // Check if Language is registered in Registry
      if (!Registry::exists('Language')) {
        if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
          error_log("[error] CRITICAL: Language not registered in Registry");
          error_log("   This usually means the method is called outside normal application context");
          error_log("   Using hardcoded translation prompt as fallback");
        }

        // Hardcoded fallback prompt
        $prompt = "Translate the following query to English. Follow these rules strictly:\n" .
                  "1. Preserve technical terms exactly as written.\n" .
                  "2. Do not alter acronyms or codes (e.g., EAN).\n" .
                  "3. If the content is in english, do not change anything.\n" .
                  "4. Provide only the raw translated text nothing else.\n";
      } else {
        // Load SYSTEM prompt in English for better LLM evaluation (internal process)
        // Note: This evaluates the response quality, not user-facing
        DomainConfig::loadLanguageFile('rag_translation');

        $CLICSHOPPING_Language = Registry::get('Language');
        $prompt = $CLICSHOPPING_Language->getDef('text_fix_translation');
      }

      // Call TranslationHandler
      $startTime = microtime(true);
      $translated = TranslationHandler::translateToEnglish($prompt . ' ' . $message, self::$config['translation_cache_ttl'] ?? 3600, $message);
      $duration = microtime(true) - $startTime;

      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log('================================');
        error_log('Translation result SemanticAgent:');
        error_log($translated);
        error_log("Duration: {$duration}s");
        error_log('================================');
      }

      if (empty(trim($translated))) {
        error_log("⚠️ WARNING: Translation is empty after TranslationHandler, using original message");
        $translated = $message;
        
        self::logSecurityEvent(
          "Translation returned empty, using original message: {$message}",
          'warning'
        );
      }

      return $translated;
      
    } catch (\Exception $e) {
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log("[error] TRANSLATION FAILED: " . $e->getMessage());
        error_log("   Original query: " . substr($message, 0, 100));
        error_log("🔄 FALLBACK: Using original message\n");
      }

      self::logSecurityEvent(
        "Translation failed, using original message as fallback: " . $e->getMessage() . " | Original: {$message}",
        'warning'
      );
      
      // Return original message instead of throwing exception
      return $message;
    }
  }

  /**
   * Classifies query as 'analytics' or 'semantic' (delegates to ClassificationEngine)
   * 
   * @param string $text Text to classify
   * @return array ['type' => string, 'confidence' => float, 'reasoning' => string, 'sub_types' => array]
   */
  public static function checkSemantics(string $text): array
  {
    // Delegate to ClassificationEngine component
    return ClassificationEngine::checkSemantics($text);
  }

  /**
   * 🆕 Clean translation extraction
   * @param string $translatedQuery
   * @return string
   */
  private static function extractCleanTranslation(string $translatedQuery): string
  {
    // Delegate to TranslationHandler component
    return TranslationHandler::extractCleanTranslation($translatedQuery);
  }

  /**
   * Defines and returns regex patterns for parsing analytics-related queries.
   *
   * Patterns are organized into categories:
   * - entity: Basic query types for products, orders, customers, etc.
   * - time: Date and time period expressions
   * - stock: Inventory-related queries
   * - reference: Product identifiers (SKU, EAN, etc.)
   * - price: Price-related expressions and comparisons
   * - quantity: Quantity expressions and comparisons
   * - performance: Sales and business metrics
   * - comparison: Comparative analysis expressions
   * - category: Product categorization queries
   * - customer: Customer-related queries
   * - calculation: Mathematical operations
   * - filters: Query filtering expressions
   * - sorting: Result ordering expressions
   *
   *
   * @return array<string, array<string>> Associative array where:
   *                                     - key: pattern category (string)
   *                                     - value: array of regex patterns (string[])
   *
   */
  public static function analyticsPatterns(): array
  {
    // Pure LLM mode: patterns are disabled
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') &&  CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("SemanticAgent::analyticsPatterns() - Pure LLM mode, returning empty array");
    }
    return [];
  }

  /**
   * Defines and returns regex patterns for parsing semantic-related queries.
   *
   * Patterns are organized into categories:
   * - geographic: Location and spatial queries
   * - product_info: Product information and details
   * - support: Customer support and help
   * - explanation: How-to and explanation requests
   * - preference: User preferences and recommendations
   * - availability: Service and product availability
   * - policy: Business policies and procedures
   * - contact: Contact and communication
   * - account: Account-related queries
   * - feedback: Reviews and opinions
   *

   *
   * @return array<string, array<string>> Associative array where:
   *                                     - key: pattern category (string)
   *                                     - value: array of regex patterns (string[])
   */
  public static function semanticPatterns(): array
  {
    // Pure LLM mode: patterns are disabled
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') &&  CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("SemanticAgent::semanticPatterns() - Pure LLM mode, returning empty array");
    }
    return [];
  }

  /**
   * Classify the query as 'analytics' or 'semantic' . * This method first translates the text to English, then checks for critical patterns,
   * calculates a score based on matched patterns, and finally classifies the query.
   *
   * @param string $text The text to classify.
   * @param int threshold Adjust this threshold based on your needs
   * @param bool $alreadyTranslated If true, skip translation (text is already in English)
   * @return string The classification result: 'analytics' or 'semantic'.
   */
  public static function classifyQuery(string $text, int|null $threshold = null, bool $alreadyTranslated = false): string
  {
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("=== SEMANTICAGENT::classifyQuery() CALLED ===");
      error_log("Input text: " . substr($text, 0, 100));
      error_log("Already translated: " . ($alreadyTranslated ? 'YES' : 'NO'));
    }

    // Use configured threshold if not provided
    if ($threshold === null) {
      $threshold = self::$config['classification_threshold'];
    }
    
    // Skip translation if already done
    if ($alreadyTranslated) {
      $translated = $text;
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log("Skipping translation, using input as-is");
      }
    } else {
      $translated = self::translateToEnglish($text);
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log("Translated to: " . substr($translated, 0, 100));
      }
    }
    
    // Clean the translation to remove GPT prefixes
    $cleanTranslated = self::extractCleanTranslation($translated);

    // DEBUG
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("=== CLASSIFY QUERY ===");
      error_log("Original: " . substr($text, 0, 100));
      error_log("Translated (raw): " . substr($translated, 0, 100));
      error_log("Translated (clean): " . substr($cleanTranslated, 0, 100));
      
      // Structured logging for query translation
      self::initializeLogger();
      self::$logger->logStructured(
        'info',
        'SemanticAgent',
        'translateQuery',
        [
          'original_query' => substr($text, 0, 200),
          'translated_query' => substr($cleanTranslated, 0, 200)
        ]
      );
    }
    
    // Use the cleaned translation for all subsequent processing
    $translated = $cleanTranslated;

    // 🆕 STEP 1: Check for web search patterns FIRST (highest priority)
    if (self::isWebSearchQuery($translated)) {
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log("✓ WEB SEARCH PATTERN DETECTED → WEB_SEARCH");
        
        // Structured logging for web search detection
        self::initializeLogger();
        self::$logger->logStructured(
          'info',
          'SemanticAgent',
          'webSearchDetected',
          [
            'query' => substr($translated, 0, 200),
            'result' => 'web_search'
          ]
        );
      }
      return 'web_search';
    }

    $hasCriticalMatch = self::hasCriticalMatch($translated);

    // Check geographic exceptions first
    // @deprecated Pattern-based detection removed in Pure LLM mode
    // This code is never executed (USE_PATTERN_BASED_DETECTION removed in task 5.1.6)
    // TODO: Remove this dead code block in Q2 2026

    if ($hasCriticalMatch === true) {
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log("✓ CRITICAL MATCH FOUND → ANALYTICS");
        
        // Structured logging for critical match
        self::initializeLogger();
        self::$logger->logStructured(
          'info',
          'SemanticAgent',
          'criticalMatch',
          [
            'query' => substr($translated, 0, 200),
            'result' => 'analytics'
          ]
        );
      }
      return 'analytics';
    }

    // Pure LLM mode: Pattern-based scoring is disabled
    // Skip directly to LLM classification
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("Pure LLM mode: Skipping pattern-based scoring, using LLM classification");
    }

    $classificationResult = self::checkSemantics($translated);
    
    // Extract type for backward compatibility
    $result = $classificationResult['type'] ?? 'semantic';

    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("Final classification: " . $result . " (confidence: " . ($classificationResult['confidence'] ?? 0.5) . ")");
      self::logSecurityEvent("Final classification: " . $result . " (confidence: " . ($classificationResult['confidence'] ?? 0.5) . ")", 'info');
      
      // Structured logging for final classification
      self::initializeLogger();
      self::$logger->logStructured(
        'info',
        'SemanticAgent',
        'finalClassification',
        [
          'query' => substr($text, 0, 200),
          'translated_query' => substr($translated, 0, 200),
          'had_critical_match' => $hasCriticalMatch ?? false,
          'result' => $result,
          'confidence' => $classificationResult['confidence'] ?? 0.5,
          'reasoning' => $classificationResult['reasoning'] ?? '',
          'sub_types' => $classificationResult['sub_types'] ?? []
        ]
      );
    }

    return $result;
  }

  /**
   * Detects competitor comparison queries (delegates to PatternAnalyzer)
   * 
   * 
   * @param string $text Text to analyze
   * @return bool True if competitor comparison detected
   */
  public static function isCompetitorComparisonQuery(string $text): bool
  {
    // Pure LLM mode: patterns are disabled, return false
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') &&  CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("SemanticAgent::isCompetitorComparisonQuery() - Pure LLM mode, returning false");
    }
    return false;
  }

  /**
   * Checks if text contains critical analytics patterns (delegates to PatternAnalyzer)
   * 
   * 
   * @param string $text Text to analyze
   * @return bool True if critical pattern found
   */
  public static function hasCriticalMatch(string $text): bool
  {
    // Pure LLM mode: patterns are disabled, return false
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log("SemanticAgent::hasCriticalMatch() - Pure LLM mode, returning false");
    }
    return false;
  }
  /*************************
   * Implementation of ConfigurableComponent interface
   **************************/

  /**
   * Returns the list of configurable parameters
   * 
   * @return array Array of parameter definitions
   */
  public function getConfigurableParameters(): array
  {
    return [
      [
        'name' => 'classification_threshold',
        'type' => 'int',
        'default' => 3,
        'description' => 'Minimum score threshold for analytics classification',
        'min' => 1,
        'max' => 10
      ],
      [
        'name' => 'max_retries',
        'type' => 'int',
        'default' => 3,
        'description' => 'Maximum number of retry attempts for GPT validation',
        'min' => 1,
        'max' => 5
      ],
      [
        'name' => 'translation_cache_ttl',
        'type' => 'int',
        'default' => 3600,
        'description' => 'Translation cache time-to-live in seconds',
        'min' => 60,
        'max' => 86400
      ],
      [
        'name' => 'enable_fallback',
        'type' => 'bool',
        'default' => true,
        'description' => 'Enable fallback classification when GPT fails'
      ],
      [
        'name' => 'enable_competitor_detection',
        'type' => 'bool',
        'default' => true,
        'description' => 'Enable competitor comparison query detection'
      ]
    ];
  }

  /**
   * Sets a configuration parameter value
   * 
   * @param string $name Parameter name
   * @param mixed $value New value
   * @return bool True if successful, false otherwise
   */
  public function setParameter(string $name, mixed $value): bool
  {
    // Check if parameter exists
    if (!array_key_exists($name, self::$config)) {
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log("SemanticAgent::setParameter() - Unknown parameter: {$name}");
      }
      return false;
    }

    // Validate the value
    $validation = $this->validateParameter($name, $value);
    if (!$validation['valid']) {
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log("SemanticAgent::setParameter() - Validation failed for {$name}: " . implode(', ', $validation['errors']));
      }
      return false;
    }

    // Set the value
    self::$config[$name] = $value;

    // Save configuration to file
    self::saveConfig();

    // Log the change
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      self::initializeLogger();
      self::$logger->logStructured(
        'info',
        'SemanticAgent',
        'setParameter',
        [
          'parameter' => $name,
          'value' => $value
        ]
      );
    }

    return true;
  }

  /**
   * Gets the current value of a configuration parameter
   * 
   * @param string $name Parameter name
   * @return mixed Current value or null if parameter doesn't exist
   */
  public function getParameter(string $name): mixed
  {
    return self::$config[$name] ?? null;
  }

  /**
   * Validates a parameter value
   * 
   * @param string $name Parameter name
   * @param mixed $value Value to validate
   * @return array Validation result with 'valid' and 'errors' keys
   */
  public function validateParameter(string $name, mixed $value): array
  {
    $errors = [];
    $warnings = [];

    // Check if parameter exists
    if (!array_key_exists($name, self::$config)) {
      return [
        'valid' => false,
        'errors' => ["Parameter '{$name}' does not exist"],
        'warnings' => []
      ];
    }

    // Get parameter definition
    $params = $this->getConfigurableParameters();
    $paramDef = null;
    foreach ($params as $param) {
      if ($param['name'] === $name) {
        $paramDef = $param;
        break;
      }
    }

    if (!$paramDef) {
      return [
        'valid' => false,
        'errors' => ["Parameter definition not found for '{$name}'"],
        'warnings' => []
      ];
    }

    // Type validation
    $expectedType = $paramDef['type'];
    $actualType = gettype($value);

    // Map PHP types to expected types
    $typeMap = [
      'integer' => 'int',
      'double' => 'float',
      'boolean' => 'bool',
      'string' => 'string'
    ];

    $actualType = $typeMap[$actualType] ?? $actualType;

    if ($actualType !== $expectedType) {
      $errors[] = "Expected type '{$expectedType}', got '{$actualType}'";
    }

    // Range validation for numeric types
    if (in_array($expectedType, ['int', 'float'], true)) {
      if (isset($paramDef['min']) && $value < $paramDef['min']) {
        $errors[] = "Value {$value} is below minimum {$paramDef['min']}";
      }
      if (isset($paramDef['max']) && $value > $paramDef['max']) {
        $errors[] = "Value {$value} is above maximum {$paramDef['max']}";
      }
    }

    // Enum validation
    if (isset($paramDef['allowed_values']) && !in_array($value, $paramDef['allowed_values'], true)) {
      $errors[] = "Value '{$value}' is not in allowed values: " . implode(', ', $paramDef['allowed_values']);
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'warnings' => $warnings
    ];
  }


  /**
   * Search for documents in the embedding database using semantic similarity
   *
   * This method provides a simplified interface for semantic search using the MultiDBRAGManager.
   * It searches across all embedding tables and returns relevant documents with similarity scores.
   *
   *
   * @param string $query Search query
   * @param int|null $limit Maximum number of results to return (null uses admin/domain config)
   * @param float|null $minScore Minimum similarity score threshold 0.0-1.0 (null uses admin/domain config)
   * @param int|null $languageId Language ID for filtering results (optional)
   * @param string|null $entityType Entity type for filtering results (optional)
   * @param int|null $entityId Entity ID for context (optional, for memory integration)
   * @param string|null $interactionId Interaction ID for context (optional, for memory integration)
   * @return array Search results with structure:
   *   [
   *     'success' => bool,
   *     'results' => [
   *       [
   *         'content' => string,
   *         'score' => float,
   *         'metadata' => array
   *       ],
   *       ...
   *     ],
   *     'error' => string|null
   *   ]
   */
  public static function search(
    string $query,
    ?int $limit = null,
    ?float $minScore = null,
    int|null $languageId = null,
    string|null $entityType = null,
    int|null $entityId = null,
    string|null $interactionId = null
  ): array
  {
    // Start timing for statistics
    $startTime = microtime(true);
    $finalMinScore = null;
    $finalLimit = null;
    
    try {
      self::initializeLogger();

      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        self::logSecurityEvent(
          "SemanticAgent::search() called with query: {$query}, limit: " . ($limit ?? 'null') . ", minScore: " . ($minScore ?? 'null'),
          'info'
        );
      }

      // Validate inputs
      if (empty(trim($query))) {
        return [
          'success' => false,
          'results' => [],
          'error' => 'Query cannot be empty'
        ];
      }

      // Apply admin defaults first
      $defaultMinScore = defined('CLICSHOPPING_APP_CHATGPT_RA_MIN_SIMILARITY_SCORE') ? (float)CLICSHOPPING_APP_CHATGPT_RA_MIN_SIMILARITY_SCORE : 0.25;
      $defaultLimit = defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_RESULTS_PER_STORE') ? (int)CLICSHOPPING_APP_CHATGPT_RA_MAX_RESULTS_PER_STORE : 5;

      $finalMinScore = $defaultMinScore;
      $finalLimit = $defaultLimit;

      // Apply domain overrides if present
      $semanticConfig = self::getSemanticDomainConfig();
      if ($semanticConfig['min_score'] !== null) {
        $finalMinScore = (float)$semanticConfig['min_score'];
      }
      if ($semanticConfig['limit'] !== null) {
        $finalLimit = (int)$semanticConfig['limit'];
      }

      // Apply explicit call overrides last
      if ($minScore !== null) {
        $finalMinScore = $minScore;
      }
      if ($limit !== null) {
        $finalLimit = $limit;
      }

      // Ensure limit is reasonable
      $finalLimit = max(1, min($finalLimit, 20));
      
      // Ensure minScore is in valid range
      $finalMinScore = max(0.0, min($finalMinScore, 1.0));

      // Get language ID from Registry if not provided
      if ($languageId === null && Registry::exists('Language')) {
        $languageId = Registry::get('Language')->getId();
      }

      // Create MultiDBRAGManager instance
      // Pass empty array for tableNames to use all embedding tables
      $ragManager = new MultiDBRAGManager(null, $semanticConfig['tables']);

      // Perform semantic search
      $searchResult = $ragManager->searchDocuments($query, $finalLimit, $finalMinScore, $languageId, $entityType);

      // Extract documents from search result
      $documents = $searchResult['documents'] ?? [];
      $auditMetadata = $searchResult['audit_metadata'] ?? [];

      // Format results
      $formattedResults = [];

      foreach ($documents as $doc) {
        $formattedResults[] = [
          'content' => $doc->content ?? '',
          'score' => $doc->metadata['score'] ?? 0.0,
          'metadata' => [
            'entity_type' => $doc->metadata['entity_type'] ?? null,
            'entity_id' => $doc->metadata['entity_id'] ?? null,
            'language_id' => $doc->metadata['language_id'] ?? null,
            'type' => $doc->metadata['type'] ?? null,
            'source' => $doc->sourceName ?? 'unknown',
            'priority_boost' => $doc->metadata['priority_boost'] ?? false,
          ]
        ];
      }

      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        self::logSecurityEvent("SemanticAgent::search() found " . count($formattedResults) . " results", 'info');
      }

      // Calculate response time
      $endTime = microtime(true);
      $responseTime = (int)round(($endTime - $startTime) * 1000);

      // Record statistics    
      self::recordSearchStatistics(
        $query,
        $responseTime,
        true,
        count($formattedResults),
        $languageId,
        $interactionId,
        $finalMinScore,
        $finalLimit
      );

      return [
        'success' => true,
        'results' => $formattedResults,
        'audit_metadata' => $auditMetadata,
        'error' => null
      ];

    } catch (\Exception $e) {
      self::logSecurityEvent("Error in SemanticAgent::search(): " . $e->getMessage(), 'error');

      // Calculate response time even on error
      $endTime = microtime(true);
      $responseTime = (int)round(($endTime - $startTime) * 1000);

      // Record statistics for failed search
      self::recordSearchStatistics(
        $query,
        $responseTime,
        false,
        0,
        $languageId,
        $interactionId,
        $finalMinScore,
        $finalLimit
      );

      return [
        'success' => false,
        'results' => [],
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Load semantic config for active domain.
   *
   * @return array{min_score: float|null, limit: int|null, tables: array}
   */
  private static function getSemanticDomainConfig(): array
  {
    $activeDomain = DomainConfig::getActivities();
    $semanticConfigClass = DomainFields::resolveAppClass($activeDomain, 'SemanticConfig');
    
    if ($semanticConfigClass === null) {
      return ['min_score' => null, 'limit' => null, 'tables' => []];
    }
    
    try {
      if (is_subclass_of($semanticConfigClass, SemanticConfigInterface::class)) {
        $tables = $semanticConfigClass::getEmbeddingTables();
        return [
          'min_score' => $semanticConfigClass::getSimilarityThreshold(),
          'limit' => $semanticConfigClass::getMaxResultsPerStore(),
          'tables' => is_array($tables) ? $tables : []
        ];
      }
    } catch (\Exception $e) {
      self::logSecurityEvent("Failed to load SemanticConfig: " . $e->getMessage(), 'warning');
    }
    
    return ['min_score' => null, 'limit' => null, 'tables' => []];
  }

  /**
   * Record search statistics to rag_statistics table
   * 
   * 
   * @param string $query Search query
   * @param int $responseTime Response time in milliseconds
   * @param bool $success Whether the search was successful
   * @param int $resultsCount Number of results found
   * @param int|null $languageId Language ID
   * @param string|null $interactionId Interaction ID
   * @return void
   */
  private static function recordSearchStatistics(
    string $query,
    int $responseTime,
    bool $success,
    int $resultsCount,
    ?int $languageId,
    ?string $interactionId,
    ?float $minScore,
    ?int $limit
  ): void
  {
    try {
      // Get database connection
      $db = Registry::get('Db');
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      // Get user ID and session ID
      $userId = 1; // Default user ID
      $sessionId = session_id();
      
      // Get language ID if not provided
      if ($languageId === null && Registry::exists('Language')) {
        $languageId = Registry::get('Language')->getId();
      }
      
      // Build metadata
      $metadata = json_encode([
        'source' => 'documents',
        'query' => $query,
        'results_count' => $resultsCount,
        'min_score' => $minScore,
        'limit' => $limit
      ]);
      
      // Insert statistics
      $sql = "INSERT INTO {$prefix}rag_statistics 
              (query_type, success, response_time, response_time_ms, metadata, 
               interaction_id, user_id, session_id, language_id, date_added, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
      
      $stmt = $db->prepare($sql);
      $stmt->execute([
        'semantic',
        $success ? 1 : 0,
        $responseTime,
        $responseTime,
        $metadata,
        $interactionId,
        $userId,
        $sessionId,
        $languageId
      ]);
      
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        self::logSecurityEvent(
          "Statistics recorded: query_type=semantic, success={$success}, response_time={$responseTime}ms, results={$resultsCount}",
          'info'
        );
      }
      
    } catch (\Exception $e) {
      // Log error but don't throw - statistics recording should not break the search
      error_log("SemanticAgent::recordSearchStatistics() error: " . $e->getMessage());
    }
  }

  /**
   *  Create a taxonomy from the given text.
   *  The taxonomy is structured as [domain]: xxx, [type]: yyy, [subject]: zzz, etc.
   * @param string $text
   * @param int|null $min_character
   * @param string|null $getdef
   * @param string|null $language_code
   * @return string
   * @throws \Exception
   */
  public function createTaxonomy(string $text, string $prompt, ?string $language_code, ?int $min_character = 300): string
  {
    $result = '';
    $text = trim($text);

   // $prompt = CLICSHOPPING::getDef('text_create_taxonomy', ['document_text' => $text]);

    if (strlen($prompt) > $min_character && str_word_count($text) > $min_character) {
      $result = Gpt::getGptResponse($prompt);
    }

    return is_string($result) ? trim($result) : '';
  }

  /*************************
   * Implementation of QueryTypeDomainInterface
   **************************/

  /**
   * Get the domain name
   * 
   * @return string Domain name
   */
  public function getName(): string
  {
    return 'semantic';
  }

  /**
   * Get the domain agent
   * 
   * @return object Domain agent instance (returns self)
   */
  public function getAgent(): object
  {
    return $this;
  }

  /**
   * Check if domain can handle the query
   * 
   * @param string $query The user query to evaluate
   * @param array $context Additional context for evaluation
   * @return bool True if domain can handle the query
   */
  public function canHandle(string $query, array $context = []): bool
  {
    // Check if intent is explicitly semantic
    if (isset($context['intent']) && $context['intent'] === 'semantic') {
      return true;
    }

    // Check if requires_semantic flag is set
    if (isset($context['requires_semantic']) && $context['requires_semantic'] === true) {
      return true;
    }

    // Use classification to determine if we can handle it
    $classification = self::classifyQuery($query, null, $context['already_translated'] ?? false);
    
    return $classification === 'semantic';
  }

  /**
   * Get domain capabilities
   * 
   * @return array Domain capabilities information
   */
  public function getCapabilities(): array
  {
    return [
      'query_types' => [
        'factual',
        'informational',
        'navigational',
        'conversational',
        'product_inquiry',
        'support_request'
      ],
      'entity_types' => [
        'product',
        'category',
        'manufacturer',
        'content',
        'faq',
        'documentation'
      ],
      'operations' => [
        'search',
        'retrieve',
        'classify',
        'translate',
        'embed'
      ],
      'features' => [
        'semantic_search',
        'vector_embeddings',
        'multilingual_support',
        'translation_cache',
        'classification_engine',
        'fallback_handling'
      ],
      'limitations' => [
        'max_results' => 20,
        'min_score' => 0.0,
        'max_score' => 1.0,
        'translation_timeout' => 5,
        'cache_ttl' => 3600
      ],
      'dependencies' => [
        'embedding_model',
        'rag_database',
        'translation_service',
        'classification_engine'
      ],
      'performance' => [
        'avg_latency' => 0.5,
        'cache_hit_rate' => 0.7,
        'translation_cache_enabled' => true
      ]
    ];
  }

  /**
   * Get domain metrics
   * 
   * @return array Domain metrics
   */
  public function getMetrics(): array
  {
    // TODO: Implement actual metrics collection from database
    // For now, return placeholder metrics
    return [
      'total_queries' => 0,
      'successful_queries' => 0,
      'failed_queries' => 0,
      'avg_execution_time' => 0.0,
      'cache_hit_rate' => 0.0,
      'avg_confidence' => 0.0,
      'error_rate' => 0.0,
      'last_execution' => null,
      'uptime' => 1.0,
      'resource_usage' => [
        'memory' => 0,
        'cpu' => 0
      ],
      'quality_metrics' => [
        'accuracy' => 0.0,
        'relevance' => 0.0
      ]
    ];
  }
}
