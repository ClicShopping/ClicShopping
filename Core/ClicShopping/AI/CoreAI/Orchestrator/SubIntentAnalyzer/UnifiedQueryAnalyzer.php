<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;


use ClicShopping\OM\Registry;
use ClicShopping\AI\DomainsAI\DomainRegistry;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\DomainsAI\Semantic\Processor\ClassificationEngine;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\DomainsAI\WebSearch\Patterns\WebSearchPostFilter;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\SuperlativePostFilter;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\MultiTemporalPostFilter;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\TimeRangePattern;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\TemporalPeriodMappingPattern;
use ClicShopping\AI\Config\DomainConfig;

/**
 * UnifiedQueryAnalyzer
 *
 * **HYBRID MODE (2026-02-08)**: This class now uses ClassificationEngine for intent detection
 * to ensure consistent hybrid query classification. The unified analyzer is used only for
 * translation and entity extraction.
 *
 * Classification Flow:
 * 1. Pre-translate query to English (SemanticAgent)
 * 2. Classify intent using ClassificationEngine (rag_classification.txt prompt)
 * 3. Extract entities and metadata using unified analyzer (rag_unified_analyzer.txt prompt)
 * 4. Merge results: classification from ClassificationEngine, metadata from unified analyzer
 *
 * Benefits:
 * - Consistent hybrid detection across all queries
 * - Single source of truth for classification rules (rag_classification.txt)
 * - No code duplication between ClassificationEngine and UnifiedQueryAnalyzer
 * - Unified analyzer focuses on translation and entity extraction
 *
 * Architecture:
 * - ClassificationEngine: Intent detection (analytics, semantic, hybrid, web_search)
 * - UnifiedQueryAnalyzer: Translation, entity extraction, metadata extraction
 * - Post-filters: Pattern-based overrides for edge cases (temporal, superlative, web search)
 */

class UnifiedQueryAnalyzer
{
  private SemanticAgent $semantics;
  private SecurityLogger $logger;
  private mixed $language;
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->semantics = new SemanticAgent();
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    
    // Load language definitions
    $this->language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_unified_analyzer');
  }

  /**
   * Analyze query: detect language, translate, classify intent, and extract structured information
   *
   * **HYBRID MODE (2026-02-08)**: This method now uses ClassificationEngine for intent detection.
   * The unified analyzer is used only for translation and entity extraction.
   *
   * Classification Flow:
   * 1. Pre-translate query to English using SemanticAgent
   * 2. Classify intent using ClassificationEngine (rag_classification.txt prompt)
   * 3. Extract entities and metadata using unified analyzer (rag_unified_analyzer.txt prompt)
   * 4. Merge results: classification from ClassificationEngine, metadata from unified analyzer
   * 5. Apply post-filters for edge cases (temporal, superlative, web search)
   *
   * This method combines multiple operations:
   * 1. Language detection (ISO 639-1 code) - via unified analyzer
   * 2. Translation to English - via SemanticAgent pre-translation
   * 3. Intent classification (analytics/semantic/hybrid/web_search) - via ClassificationEngine
   * 4. Entity type detection (product, order, customer, etc.) - via unified analyzer
   * 5. Time constraint detection (comparison, relative_period, specific_date, none) - via unified analyzer
   * 6. Status keywords extraction (active, pending, etc.) - via unified analyzer
   * 7. Sub-query decomposition for complex queries - via unified analyzer
   * 8. Temporal metadata extraction (periods, connectors, base metric, time range) - via post-filters
   *
   * @param string $query User query in any language
   * @return array Analysis result with:
   *   - 'language' (string): ISO 639-1 language code (en, fr, ja, zh, es, de, ar, etc.)
   *   - 'translated_query' (string): Query translated to English
   *   - 'original_query' (string): Original query
   *   - 'intent_type' (string): analytics|semantic|hybrid|web_search (from ClassificationEngine)
   *   - 'entity_type' (array): List of entities (product, order, customer, general)
   *   - 'time_constraint' (string): comparison|relative_period|specific_date|none
   *   - 'status_keywords' (array): List of status keywords (active, pending, etc.)
   *   - 'sub_queries' (array): List of sub-queries for complex queries
   *   - 'confidence' (float): 0.0-1.0 (from ClassificationEngine)
   *   - 'was_translated' (bool): Whether translation was performed
   *   - 'analysis_time_ms' (float): Total analysis time in milliseconds
   *   - 'detection_method' (string): 'classification_engine' or pattern name if overridden
   *   - 'sub_types' (array): Sub-types for hybrid queries (from ClassificationEngine)
   *   - 'classification_reasoning' (string): Reasoning for classification (from ClassificationEngine)
   *   - 'is_multi_temporal' (bool): Whether query contains multiple temporal aggregations
   *   - 'temporal_periods' (array): List of temporal periods (month, quarter, semester, etc.)
   *   - 'temporal_connectors' (array): List of temporal connectors (then, and, etc.)
   *   - 'base_metric' (string|null): Base metric for temporal queries (revenue, sales, etc.)
   *   - 'time_range' (string|null): Time range for temporal queries (year 2025, etc.)
   *   - 'temporal_period_count' (int): Number of temporal periods detected
   */
  public function analyzeQuery(string $query): array
  {
    $startTime = microtime(true);

    if ($this->debug) {
      error_log("[info] " . $this->language->getDef('debug_analysis_start'));
      error_log(sprintf($this->language->getDef('debug_input_query'), $query));
    }

    try {
      //  CRITICAL: Pre-translate query to English using Semantics
      // This ensures the query is in English BEFORE sending to LLM for classification
      // The LLM classification prompt works best with English input
      $originalQuery = $query;
      $preTranslatedQuery = $this->semantics->translateToEnglish($query);

      // Use pre-translated query if translation was successful
      if (!empty($preTranslatedQuery) && $preTranslatedQuery !== $query) {
        $query = $preTranslatedQuery;
        if ($this->debug) {
          error_log("[INFO translate] Pre-translated query: {$query}");
        }
      }
      
      // Detect and fix translation hallucinations
      // If the original query contains "article" + number but the translated query doesn't,
      // it's likely a hallucination (e.g., "article 5 et article 6" → "revenue by quarter")
      if (preg_match('/article\s+\d+/i', $originalQuery) && !preg_match('/article\s+\d+/i', $query)) {
        if ($this->debug) {
          error_log("\n⚠️ [UnifiedQueryAnalyzer] TRANSLATION HALLUCINATION DETECTED:");
          error_log("  Original query contains 'article + number': {$originalQuery}");
          error_log("  Translated query does NOT contain 'article + number': {$query}");
          error_log("  This is a hallucination - reverting to original query");
        }
        
        // Revert to original query to prevent hallucination
        $query = $originalQuery;
        
        $this->logger->logStructured(
          'warning',
          'UnifiedQueryAnalyzer',
          'translation_hallucination_detected',
          [
            'original_query' => $originalQuery,
            'hallucinated_translation' => $preTranslatedQuery,
            'action' => 'reverted_to_original'
          ]
        );
      }
      
      // Split query on sequential indicators ("puis", "ensuite", "then", etc.)
      // and classify each sub-query independently to determine if hybrid
      $splitResult = $this->splitQueryOnSequentialIndicators($query);
      
      if ($splitResult['has_sequential_indicator']) {
        if ($this->debug) {
          error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Sequential indicator detected - splitting query:");
          error_log("  Indicator: {$splitResult['indicator']}");
          error_log("  Sub-queries: " . count($splitResult['sub_queries']));
        }
        
        // Classify each sub-query independently
        $subQueryClassifications = $this->classifySubQueries($splitResult['sub_queries']);
        
        // Use multi-factor confidence scoring to determine if query should be hybrid
        $confidenceResult = $this->calculateHybridConfidence($query, $splitResult, $subQueryClassifications);
        
        if ($this->debug) {
          error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Hybrid confidence scoring result:");
          error_log("  Confidence score: " . round($confidenceResult['confidence_score'], 2));
          error_log("  Is hybrid: " . ($confidenceResult['is_hybrid'] ? 'YES' : 'NO'));
          error_log("  Factors:");
          error_log("    Sequential words: " . $confidenceResult['factors']['sequential_words']);
          error_log("    Multiple types: " . $confidenceResult['factors']['multiple_types']);
          error_log("    Mixed keywords: " . $confidenceResult['factors']['mixed_keywords']);
        }
        
        // Use confidence scoring result to determine hybrid classification
        if ($confidenceResult['is_hybrid']) {
          // Determine sub-types from sub-query classifications
          $types = array_map(function($c) { return $c['type']; }, $subQueryClassifications);
          $uniqueTypes = array_unique($types);
          
          // Override classification with hybrid result
          $classification = [
            'type' => 'hybrid',
            'confidence' => $confidenceResult['confidence_score'],
            'reasoning' => $confidenceResult['reasoning'],
            'sub_types' => array_values($uniqueTypes),
          ];
          
          if ($this->debug) {
            error_log("[UnifiedQueryAnalyzer] Query classified as HYBRID via confidence scoring:");
            error_log("  Sub-types: " . implode(', ', $uniqueTypes));
            error_log("  Confidence: " . round($confidenceResult['confidence_score'], 3));
          }
        } else {
          // Not hybrid - use first sub-query classification
          $classification = [
            'type' => $subQueryClassifications[0]['type'],
            'confidence' => $subQueryClassifications[0]['confidence'],
            'reasoning' => $confidenceResult['reasoning'],
            'sub_types' => [],
          ];
          
          if ($this->debug) {
            error_log("[UnifiedQueryAnalyzer] Sequential indicator found but NOT hybrid:");
            error_log("  Confidence score too low: " . round($confidenceResult['confidence_score'], 2) . " < 0.5");
            error_log("  Using type: {$classification['type']}");
          }
        }
      } else {
        // No sequential indicator - use ClassificationEngine normally
        // This ensures consistent classification using the correct hybrid detection rules
        // from rag_classification.txt instead of rag_unified_analyzer.txt
        if ($this->debug) {
          error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Using ClassificationEngine for classification:");
          error_log("  Query to classify: {$query}");
        }
        
        $classification = ClassificationEngine::checkSemantics($query);
        
        if ($this->debug) {
          error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] ClassificationEngine result:");
          error_log("  Type: " . $classification['type']);
          error_log("  Confidence: " . $classification['confidence']);
          error_log("  Reasoning: " . ($classification['reasoning'] ?? 'N/A'));
          error_log("  Sub-types: " . implode(', ', $classification['sub_types'] ?? []));
        }
      }
      
      // Build unified prompt with (potentially pre-translated) query
      // This is now ONLY used for translation and entity extraction, NOT classification
      $prompt = $this->buildUnifiedPrompt($query);
      
      // 🔍 DEBUG: Log prompt loading verification
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Prompt Loading Verification:");
        error_log("  Prompt length: " . strlen($prompt) . " characters");
        error_log("  Prompt preview (first 200 chars): " . substr($prompt, 0, 200) . "...");
        error_log("  Query in prompt: " . (strpos($prompt, $query) !== false ? 'YES' : 'NO'));
      }

      // Single GPT call for everything
      // Use Gpt::getGptResponse() instead of non-existent complete() method
      
      // 🔍 DEBUG: Log before GPT call
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Calling Gpt::getGptResponse():");
        error_log("  Max tokens: 300");
        error_log("  Temperature: 0.0");
        error_log("  Timestamp: " . date('Y-m-d H:i:s'));
      }
      
      $response = Gpt::getGptResponse(
        $prompt,
        300, // max_tokens
        0.0  // temperature (deterministic for consistency)
      );

      // 🔍 DEBUG: Log GPT response
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] GPT Response Received:");
        error_log("  Response length: " . strlen($response) . " characters");
        error_log("  Response type: " . gettype($response));
        error_log("  Response preview (first 500 chars): " . substr($response, 0, 500));
        error_log("  Full response: " . $response);
        error_log(sprintf($this->language->getDef('debug_gpt_response'), $response));
      }

      // Clean response (remove markdown code blocks if present)
      $cleanedResponse = $this->cleanJsonResponse($response);
      
      // 🔍 DEBUG: Log JSON cleaning
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] JSON Response Cleaning:");
        error_log("  Original response length: " . strlen($response));
        error_log("  Cleaned response length: " . strlen($cleanedResponse));
        error_log("  Was cleaned: " . ($response !== $cleanedResponse ? 'YES' : 'NO'));
        error_log("  Cleaned response: " . $cleanedResponse);
      }

      // Parse JSON response
      $analysis = json_decode($cleanedResponse, true);
      
      // 🔍 DEBUG: Log JSON parsing
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] JSON Parsing:");
        error_log("  JSON decode success: " . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO'));
        if (json_last_error() !== JSON_ERROR_NONE) {
          error_log("  JSON error: " . json_last_error_msg());
          error_log("  JSON error code: " . json_last_error());
        } else {
          error_log("  Parsed array keys: " . implode(', ', array_keys($analysis ?? [])));
        }
      }

      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->logStructured(
          'error',
          'UnifiedQueryAnalyzer',
          'json_parse_error',
          [
            'query' => $query,
            'response' => $response,
            'cleaned_response' => $cleanedResponse,
            'error' => json_last_error_msg()
          ]
        );

        // Fallback to default
        $analysis = null;
      }

      // ClassificationEngine provides: type, confidence, reasoning, sub_types
      // Unified analyzer provides: language, translated_query, entity_type, time_constraint, status_keywords, sub_queries
      if ($analysis === null) {
        // If unified analyzer failed, create minimal analysis with classification result
        // Use the pre-translated query as the translated_query
        $analysis = [
          'language' => 'en',
          'translated_query' => $query, // Use pre-translated query (already in English)
          'entity_type' => ['general'],
          'time_constraint' => 'none',
          'status_keywords' => [],
          'sub_queries' => []
        ];
        
        if ($this->debug) {
          error_log("[UnifiedQueryAnalyzer] Unified analyzer failed, using minimal analysis:");
          error_log("  Using pre-translated query: {$query}");
        }
      }
      
      // Ensure translated_query is not empty and is the correct query
      // If unified analyzer returned an empty or invalid translated_query, use the pre-translated query
      if (empty($analysis['translated_query']) || trim($analysis['translated_query']) === '') {
        $analysis['translated_query'] = $query;
        
        if ($this->debug) {
          error_log("[UnifiedQueryAnalyzer] Empty translated_query, using pre-translated query:");
          error_log("  Using: {$query}");
        }
      }
      
      // Override intent_type and confidence with ClassificationEngine result
      $analysis['intent_type'] = $classification['type'];
      $analysis['confidence'] = $classification['confidence'];
      $analysis['sub_types'] = $classification['sub_types'] ?? [];
      $analysis['classification_reasoning'] = $classification['reasoning'] ?? '';
      $analysis['detection_method'] = 'classification_engine'; // Mark as using ClassificationEngine
      $analysis['action'] = $classification['action'] ?? null;
      $analysis['action_params'] = $classification['action_params'] ?? [];
      
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Merged Analysis:");
        error_log("  Intent type (from ClassificationEngine): " . $analysis['intent_type']);
        error_log("  Confidence (from ClassificationEngine): " . $analysis['confidence']);
        error_log("  Sub-types (from ClassificationEngine): " . implode(', ', $analysis['sub_types']));
        error_log("  Language (from unified analyzer): " . ($analysis['language'] ?? 'N/A'));
        error_log("  Translated query (from unified analyzer): " . ($analysis['translated_query'] ?? 'N/A'));
      }

      // Validate and sanitize
      $analysis = $this->validateAnalysis($analysis, $query);

      // 🔍 DEBUG: Log intent_type detection after validation
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Intent Type Detection (After Validation):");
        error_log("  Detected intent_type: " . ($analysis['intent_type'] ?? 'NOT SET'));
        error_log("  Confidence: " . ($analysis['confidence'] ?? 'NOT SET'));
        error_log("  Detection method: " . ($analysis['detection_method'] ?? 'NOT SET'));
        error_log("  Language: " . ($analysis['language'] ?? 'NOT SET'));
        error_log("  Translated query: " . ($analysis['translated_query'] ?? 'NOT SET'));
        error_log("  Entity types: " . implode(', ', $analysis['entity_type'] ?? []));
        error_log("  Time constraint: " . ($analysis['time_constraint'] ?? 'NOT SET'));
      }

      // ⚠️ CRITICAL: Apply AnalyticsPatterns post-filter (EXCEPTION to Pure LLM)
      // This pattern-based post-filter overrides LLM classification for temporal financial queries
      // where LLM is inconsistent (hallucinations, wrong intent, low confidence)
      // Pattern is called on translated query (English) for deterministic results
      
      $originalIntentType = $analysis['intent_type'];
      $originalConfidence = $analysis['confidence'];
      
      // Load AnalyticsPatterns from active domain (domain-agnostic approach)
      $domainApp = DomainRegistry::getInstance()->getActiveApp();
      if ($domainApp && method_exists($domainApp, 'getAnalyticsPatternsClass')) {
        $analyticsPatternsClass = $domainApp->getAnalyticsPatternsClass();
        
        if ($analyticsPatternsClass && class_exists($analyticsPatternsClass)) {
          $analysis = $analyticsPatternsClass::postFilter(
            $analysis['translated_query'],
            $analysis
          );
        }
      }
      
      // Log when pattern overrides LLM classification
      if ($analysis['intent_type'] !== $originalIntentType || $analysis['confidence'] !== $originalConfidence) {
        // 🔍 DEBUG: Enhanced logging for pattern override
        if ($this->debug) {
          error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] TemporalFinancialPreFilter Override:");
          error_log("  BEFORE - Intent: {$originalIntentType}, Confidence: {$originalConfidence}");
          error_log("  AFTER  - Intent: {$analysis['intent_type']}, Confidence: {$analysis['confidence']}");
          error_log("  Override reason: " . ($analysis['override_reason'] ?? 'unknown'));
          error_log("  Detection method: " . ($analysis['detection_method'] ?? 'unknown'));
        }
        
        $this->logger->logStructured(
          'info',
          'UnifiedQueryAnalyzer',
          'temporal_financial_pattern_override',
          [
            'query' => $query,
            'translated_query' => $analysis['translated_query'],
            'original_intent' => $originalIntentType,
            'original_confidence' => $originalConfidence,
            'overridden_intent' => $analysis['intent_type'],
            'overridden_confidence' => $analysis['confidence'],
            'override_reason' => $analysis['override_reason'] ?? 'unknown',
            'detection_method' => $analysis['detection_method'] ?? 'unknown',
            'domain' => $domainApp ? $domainApp->getDomainId() : 'none'
          ]
        );
        
        if ($this->debug) {
          error_log("🔧 " . $this->language->getDef('debug_pattern_override'));
          error_log("  Original: {$originalIntentType} (confidence: {$originalConfidence})");
          error_log("  Overridden: {$analysis['intent_type']} (confidence: {$analysis['confidence']})");
          error_log("  Reason: " . ($analysis['override_reason'] ?? 'unknown'));
        }
      }
      
      //  CRITICAL: Apply WebSearchPostFilter post-filter (EXCEPTION to Pure LLM)
      // This pattern-based post-filter overrides LLM classification for web search queries
      // where LLM is inconsistent (misclassifies as analytics or semantic)
      // Pattern is called on translated query (English) for deterministic results
      $originalIntentType = $analysis['intent_type'];
      $originalConfidence = $analysis['confidence'];
      
      $analysis = WebSearchPostFilter::postFilter(
        $analysis['translated_query'],
        $analysis
      );
      
      // Log when pattern overrides LLM classification
      if ($analysis['intent_type'] !== $originalIntentType || $analysis['confidence'] !== $originalConfidence) {
        // 🔍 DEBUG: Enhanced logging for pattern override
        if ($this->debug) {
          error_log("[UnifiedQueryAnalyzer] WebSearchPostFilter Override:");
          error_log("  BEFORE - Intent: {$originalIntentType}, Confidence: {$originalConfidence}");
          error_log("  AFTER  - Intent: {$analysis['intent_type']}, Confidence: {$analysis['confidence']}");
          error_log("  Override reason: " . ($analysis['override_reason'] ?? 'unknown'));
          error_log("  Detection method: " . ($analysis['detection_method'] ?? 'unknown'));
        }
        
        $this->logger->logStructured(
          'info',
          'UnifiedQueryAnalyzer',
          'websearch_pattern_override',
          [
            'query' => $query,
            'translated_query' => $analysis['translated_query'],
            'original_intent' => $originalIntentType,
            'original_confidence' => $originalConfidence,
            'overridden_intent' => $analysis['intent_type'],
            'overridden_confidence' => $analysis['confidence'],
            'override_reason' => $analysis['override_reason'] ?? 'unknown',
            'detection_method' => $analysis['detection_method'] ?? 'unknown'
          ]
        );
        
        if ($this->debug) {
          error_log("🔧 WebSearch " . $this->language->getDef('debug_pattern_override'));
          error_log("  Original: {$originalIntentType} (confidence: {$originalConfidence})");
          error_log("  Overridden: {$analysis['intent_type']} (confidence: {$analysis['confidence']})");
          error_log("  Reason: " . ($analysis['override_reason'] ?? 'unknown'));
        }
      }
      
      //  CRITICAL: Apply SuperlativePostFilter post-filter (EXCEPTION to Pure LLM)
      // This pattern-based post-filter overrides LLM classification for superlative queries
      // where LLM is inconsistent (misclassifies as semantic instead of analytics)
      // Pattern is called on translated query (English) for deterministic results
      $originalIntentType = $analysis['intent_type'];
      $originalConfidence = $analysis['confidence'];
      
      $analysis = SuperlativePostFilter::postFilter(
        $analysis['translated_query'],
        $analysis
      );
      
      // Log when pattern overrides LLM classification
      if ($analysis['intent_type'] !== $originalIntentType || $analysis['confidence'] !== $originalConfidence) {
        // 🔍 DEBUG: Enhanced logging for pattern override
        if ($this->debug) {
          error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] SuperlativePostFilter Override:");
          error_log("  BEFORE - Intent: {$originalIntentType}, Confidence: {$originalConfidence}");
          error_log("  AFTER  - Intent: {$analysis['intent_type']}, Confidence: {$analysis['confidence']}");
          error_log("  Override reason: " . ($analysis['override_reason'] ?? 'unknown'));
          error_log("  Detection method: " . ($analysis['detection_method'] ?? 'unknown'));
        }
        
        $this->logger->logStructured(
          'info',
          'UnifiedQueryAnalyzer',
          'superlative_pattern_override',
          [
            'query' => $query,
            'translated_query' => $analysis['translated_query'],
            'original_intent' => $originalIntentType,
            'original_confidence' => $originalConfidence,
            'overridden_intent' => $analysis['intent_type'],
            'overridden_confidence' => $analysis['confidence'],
            'override_reason' => $analysis['override_reason'] ?? 'unknown',
            'detection_method' => $analysis['detection_method'] ?? 'unknown'
          ]
        );
        
        if ($this->debug) {
          error_log("🔧 Superlative " . $this->language->getDef('debug_pattern_override'));
          error_log("  Original: {$originalIntentType} (confidence: {$originalConfidence})");
          error_log("  Overridden: {$analysis['intent_type']} (confidence: {$analysis['confidence']})");
          error_log("  Reason: " . ($analysis['override_reason'] ?? 'unknown'));
        }
      }
      
      //  CRITICAL: Apply MultiTemporalPostFilter post-filter (EXCEPTION to Pure LLM)
      // This pattern-based post-filter overrides LLM classification for multi-temporal queries
      // where LLM is inconsistent (classifies as analytics instead of hybrid)
      // Pattern is called on translated query (English) for deterministic results
      $originalIntentType = $analysis['intent_type'];
      $originalConfidence = $analysis['confidence'];
      
      $analysis = MultiTemporalPostFilter::postFilter(
        $analysis['translated_query'],
        $analysis
      );
      
      // Log when pattern overrides LLM classification
      if ($analysis['intent_type'] !== $originalIntentType || $analysis['confidence'] !== $originalConfidence) {
        // 🔍 DEBUG: Enhanced logging for pattern override
        if ($this->debug) {
          error_log("[INFO] [UnifiedQueryAnalyzer] MultiTemporalPostFilter Override:");
          error_log("  BEFORE - Intent: {$originalIntentType}, Confidence: {$originalConfidence}");
          error_log("  AFTER  - Intent: {$analysis['intent_type']}, Confidence: {$analysis['confidence']}");
          error_log("  Override reason: " . ($analysis['override_reason'] ?? 'unknown'));
          error_log("  Detection method: " . ($analysis['detection_method'] ?? 'unknown'));
          error_log("  Temporal periods: " . implode(', ', $analysis['temporal_periods'] ?? []));
          error_log("  Temporal connectors: " . implode(', ', $analysis['temporal_connectors'] ?? []));
        }
        
        $this->logger->logStructured(
          'info',
          'UnifiedQueryAnalyzer',
          'multi_temporal_pattern_override',
          [
            'query' => $query,
            'translated_query' => $analysis['translated_query'],
            'original_intent' => $originalIntentType,
            'original_confidence' => $originalConfidence,
            'overridden_intent' => $analysis['intent_type'],
            'overridden_confidence' => $analysis['confidence'],
            'override_reason' => $analysis['override_reason'] ?? 'unknown',
            'detection_method' => $analysis['detection_method'] ?? 'unknown',
            'temporal_periods' => $analysis['temporal_periods'] ?? [],
            'temporal_connectors' => $analysis['temporal_connectors'] ?? []
          ]
        );
        
        if ($this->debug) {
          error_log("🔧 MultiTemporal " . $this->language->getDef('debug_pattern_override'));
          error_log("  Original: {$originalIntentType} (confidence: {$originalConfidence})");
          error_log("  Overridden: {$analysis['intent_type']} (confidence: {$analysis['confidence']})");
          error_log("  Reason: " . ($analysis['override_reason'] ?? 'unknown'));
          error_log("  Temporal Periods: " . implode(', ', $analysis['temporal_periods'] ?? []));
        }
      }
      
      // ⚠️ CRITICAL: Extract temporal metadata for ALL queries (not just overridden ones)
      // This ensures temporal metadata is always available for downstream processing
      // even when the LLM correctly classifies the query as hybrid
      $analysis = $this->extractTemporalMetadata($analysis);

      // Post-filters may incorrectly override 'hybrid' to a single type
      // If sub_types contain multiple DIFFERENT types, force intent_type = 'hybrid'
      // If sub_types contain multiple of the SAME type, keep as single type
      // 
      // Hybrid combinations (DIFFERENT types):
      // - semantic + analytics (e.g., "find product X and count sales")
      // - semantic + web_search (e.g., "find product info and search competitors")
      // - analytics + web_search (e.g., "sales data and market trends")
      // - semantic + analytics + web_search (e.g., "product info, sales, and market research")
      //
      // NOT Hybrid (SAME type):
      // - semantic + semantic (e.g., "article 5 and article 6") → semantic
      // - analytics + analytics (e.g., "count products and count orders") → analytics (if separate questions)
      if (isset($analysis['sub_types']) && 
          is_array($analysis['sub_types']) && 
          count($analysis['sub_types']) >= 2 &&
          $analysis['intent_type'] !== 'hybrid') {
        
        // Get unique sub_types (remove duplicates)
        $uniqueSubTypes = array_unique($analysis['sub_types']);
        
        // Check if we have multiple DIFFERENT query types
        $hasMultipleDifferentTypes = count($uniqueSubTypes) >= 2;
        
        if ($hasMultipleDifferentTypes) {
          $originalIntentType = $analysis['intent_type'];
          $analysis['intent_type'] = 'hybrid';
          $analysis['override_reason'] = 'sub_types_indicate_hybrid';
          $analysis['detection_method'] = 'sub_types_validation';
          
          if ($this->debug) {
            error_log("\n⚠️ [UnifiedQueryAnalyzer] HYBRID TYPE RESTORED:");
            error_log("  Post-filter changed type from 'hybrid' to '{$originalIntentType}'");
            error_log("  But sub_types contain multiple DIFFERENT query types: " . implode(', ', $uniqueSubTypes));
            error_log("  Restoring intent_type to 'hybrid'");
            error_log("  This ensures proper routing to HybridQueryProcessor");
          }
          
          $this->logger->logStructured(
            'warning',
            'UnifiedQueryAnalyzer',
            'hybrid_type_restored',
            [
              'query' => $query,
              'translated_query' => $analysis['translated_query'],
              'original_intent' => $originalIntentType,
              'restored_intent' => 'hybrid',
              'sub_types' => $uniqueSubTypes,
              'sub_types_count' => count($uniqueSubTypes),
              'reason' => 'Post-filter incorrectly overrode hybrid type - multiple DIFFERENT sub_types detected'
            ]
          );
        }
      }

      // If sub_types are all the SAME type BUT there are multiple entities,
      // we need to decide: keep as hybrid (for decomposition) or downgrade to single type?
      //
      // DECISION: Keep as HYBRID to enable decomposition
      // Reason: "article 4 et article 8" needs to be split into 2 separate searches
      // Even though both are semantic, they need separate retrieval operations
      //
      // Exception: Only downgrade if it's truly a single query with no decomposition needed
      if (isset($analysis['sub_types']) && 
          is_array($analysis['sub_types']) && 
          count($analysis['sub_types']) >= 2 &&
          $analysis['intent_type'] === 'hybrid') {
        
        // Get unique sub_types
        $uniqueSubTypes = array_unique($analysis['sub_types']);
        
        // If all sub_types are the SAME, we have a multi-entity query of the same type
        // Keep as HYBRID to enable decomposition (don't downgrade)
        if (count($uniqueSubTypes) === 1) {
          $singleType = $uniqueSubTypes[0];
          
          if ($this->debug) {
            error_log("[INFO] [UnifiedQueryAnalyzer] MULTI-ENTITY QUERY DETECTED:");
            error_log("  Classification returned 'hybrid' with sub_types: " . implode(', ', $analysis['sub_types']));
            error_log("  All sub_types are the SAME type: {$singleType}");
            error_log("  KEEPING as 'hybrid' to enable decomposition");
            error_log("  This ensures each entity is retrieved separately");
          }
          
          $this->logger->logStructured(
            'info',
            'UnifiedQueryAnalyzer',
            'multi_entity_same_type_detected',
            [
              'query' => $query,
              'translated_query' => $analysis['translated_query'],
              'intent_type' => 'hybrid',
              'sub_types' => $analysis['sub_types'],
              'unique_sub_types' => $uniqueSubTypes,
              'reason' => 'Multi-entity query of same type - keeping as hybrid for decomposition'
            ]
          );
        }
      }

      // If hybrid but sub_types are missing, infer them to enable decomposition.
      // This avoids recording decomposition failures when LLM omits sub_types.
      if ($analysis['intent_type'] === 'hybrid' && (empty($analysis['sub_types']) || !is_array($analysis['sub_types']))) {
        $inferredSubTypes = $this->inferHybridSubTypes(
          $analysis['translated_query'] ?? $query,
          $originalQuery
        );

        if (!empty($inferredSubTypes)) {
          $analysis['sub_types'] = $inferredSubTypes;

          $this->logger->logStructured(
            'info',
            'UnifiedQueryAnalyzer',
            'hybrid_sub_types_inferred',
            [
              'query' => $query,
              'translated_query' => $analysis['translated_query'] ?? $query,
              'sub_types' => $inferredSubTypes,
              'reason' => 'ClassificationEngine returned hybrid without sub_types'
            ]
          );

          if ($this->debug) {
            error_log("[UnifiedQueryAnalyzer] Inferred sub_types for hybrid query: " . implode(', ', $inferredSubTypes));
          }
        } else if ($this->debug) {
          error_log("[UnifiedQueryAnalyzer] Unable to infer sub_types for hybrid query");
        }
      }

      // Add metadata
      // Use $originalQuery (before pre-translation) as the true original
      $analysis['original_query'] = $originalQuery;
      $analysis['was_translated'] = ($analysis['language'] !== 'en') || ($originalQuery !== $query);
      $analysis['analysis_time_ms'] = (microtime(true) - $startTime) * 1000;
      
      // 🔍 DEBUG: Final analysis summary
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] FINAL ANALYSIS SUMMARY:");
        error_log("  ========================================");
        error_log("  Original query: " . $originalQuery);
        error_log("  Translated query: " . $analysis['translated_query']);
        error_log("  Language: " . $analysis['language']);
        error_log("  Was translated: " . ($analysis['was_translated'] ? 'YES' : 'NO'));
        error_log("  ========================================");
        error_log("   FINAL INTENT TYPE: " . $analysis['intent_type']);
        error_log("   FINAL CONFIDENCE: " . $analysis['confidence']);
        error_log("   DETECTION METHOD: " . ($analysis['detection_method'] ?? 'unknown'));
        error_log("  ========================================");
        error_log("  Entity types: " . implode(', ', $analysis['entity_type'] ?? []));
        error_log("  Time constraint: " . $analysis['time_constraint']);
        error_log("  Status keywords: " . implode(', ', $analysis['status_keywords'] ?? []));
        error_log("  Sub-queries count: " . count($analysis['sub_queries'] ?? []));
        error_log("  Analysis time: " . round($analysis['analysis_time_ms'], 2) . " ms");
        error_log("  ========================================\n");
      }

      $this->logger->logStructured(
        'info',
        'UnifiedQueryAnalyzer',
        'analysis_completed',
        [
          'query' => $query,
          'language' => $analysis['language'],
          'intent_type' => $analysis['intent_type'],
          'confidence' => $analysis['confidence'],
          'was_translated' => $analysis['was_translated'],
          'analysis_time_ms' => round($analysis['analysis_time_ms'], 2),
          'is_multi_temporal' => $analysis['is_multi_temporal'] ?? false,
          'temporal_periods' => $analysis['temporal_periods'] ?? [],
          'temporal_connectors' => $analysis['temporal_connectors'] ?? [],
          'base_metric' => $analysis['base_metric'] ?? null,
          'time_range' => $analysis['time_range'] ?? null,
          'temporal_period_count' => $analysis['temporal_period_count'] ?? 0
        ]
      );

      if ($this->debug) {
        error_log("✅ " . $this->language->getDef('debug_analysis_result'));
        error_log("  " . sprintf($this->language->getDef('debug_language_detected'), $analysis['language']));
        error_log("  " . sprintf($this->language->getDef('debug_intent_detected'), $analysis['intent_type'], $analysis['confidence']));
        error_log("  " . sprintf($this->language->getDef('debug_translated_query'), $analysis['translated_query']));
        error_log("  " . sprintf($this->language->getDef('debug_analysis_time'), round($analysis['analysis_time_ms'], 2)));
        
        // Log additional fields
        if (!empty($analysis['entity_type'])) {
          error_log("  " . sprintf($this->language->getDef('debug_entity_types'), implode(', ', $analysis['entity_type'])));
        }
        if ($analysis['time_constraint'] !== 'none') {
          error_log("  " . sprintf($this->language->getDef('debug_time_constraint'), $analysis['time_constraint']));
        }
        if (!empty($analysis['status_keywords'])) {
          error_log("  " . sprintf($this->language->getDef('debug_status_keywords'), implode(', ', $analysis['status_keywords'])));
        }
        if (!empty($analysis['sub_queries'])) {
          error_log("  " . sprintf($this->language->getDef('debug_sub_queries'), count($analysis['sub_queries'])));
        }
        
        // Log temporal metadata
        if (!empty($analysis['is_multi_temporal']) && $analysis['is_multi_temporal']) {
          error_log("  🕐 Multi-Temporal Query Detected:");
          error_log("    Temporal Periods: " . implode(', ', $analysis['temporal_periods'] ?? []));
          error_log("    Temporal Connectors: " . implode(', ', $analysis['temporal_connectors'] ?? []));
          error_log("    Base Metric: " . ($analysis['base_metric'] ?? 'none'));
          error_log("    Time Range: " . ($analysis['time_range'] ?? 'none'));
          error_log("    Period Count: " . ($analysis['temporal_period_count'] ?? 0));
        }
      }

      return $analysis;

    } catch (\Exception $e) {
      $this->logger->logStructured(
        'error',
        'UnifiedQueryAnalyzer',
        'analysis_exception',
        [
          'query' => $query,
          'error' => $e->getMessage(),
          'trace' => $e->getTraceAsString()
        ]
      );

      if ($this->debug) {
        error_log("[error] " . $this->language->getDef('error_analysis_exception') . ": " . $e->getMessage());
      }

      // Return safe fallback
      return [
        'language' => 'en',
        'translated_query' => $query,
        'original_query' => $query,
        'intent_type' => 'semantic',
        'entity_type' => ['general'],
        'time_constraint' => 'none',
        'status_keywords' => [],
        'sub_queries' => [],
        'confidence' => 0.5,
        'was_translated' => false,
        'analysis_time_ms' => (microtime(true) - $startTime) * 1000,
        'error' => $e->getMessage(),
        'is_multi_temporal' => false,
        'temporal_periods' => [],
        'temporal_connectors' => [],
        'base_metric' => null,
        'time_range' => null,
        'temporal_period_count' => 0,
      ];
    }
  }

  /**
   * Split query on sequential indicators
   *
   * Detects sequential indicators in the query and splits it into sub-queries.
   * Sequential indicators include:
   * - English: "then", "next", "after", "afterwards", "followed by"
   *
   * @param string $query Original query
   * @return array Array with:
   *   - 'has_sequential_indicator' (bool): Whether sequential indicator was found
   *   - 'indicator' (string|null): The sequential indicator found
   *   - 'sub_queries' (array): Array of sub-query strings
   *   - 'split_position' (int|null): Position where split occurred
   */
  public function splitQueryOnSequentialIndicators(string $query): array
  {
    // Define sequential indicators (ordered by priority - longer phrases first)
    $indicators = [
      'followed by',
      'afterwards',
      'then',
      'next',
      'after',
    ];

    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Checking for sequential indicators in query:");
      error_log("  Query: {$query}");
    }

    // Search for sequential indicators (case-insensitive)
    foreach ($indicators as $indicator) {
      // Use word boundaries to avoid partial matches
      // e.g., "then" should not match "authentic"
      $pattern = '/\b' . preg_quote($indicator, '/') . '\b/i';

      if (preg_match($pattern, $query, $matches, PREG_OFFSET_CAPTURE)) {
        $foundIndicator = $matches[0][0];
        $position = $matches[0][1];

        // Split query at the indicator
        $beforeIndicator = trim(substr($query, 0, $position));
        $afterIndicator = trim(substr($query, $position + strlen($foundIndicator)));

        // Validate that both parts are non-empty
        if (empty($beforeIndicator) || empty($afterIndicator)) {
          if ($this->debug) {
            error_log("  ️ Found indicator '{$foundIndicator}' but one part is empty - skipping");
          }
          continue;
        }

        if ($this->debug) {
          error_log("   Found sequential indicator: '{$foundIndicator}' at position {$position}");
          error_log("  Sub-query 1: {$beforeIndicator}");
          error_log("  Sub-query 2: {$afterIndicator}");
        }

        return [
          'has_sequential_indicator' => true,
          'indicator' => $foundIndicator,
          'sub_queries' => [$beforeIndicator, $afterIndicator],
          'split_position' => $position,
        ];
      }
    }

    if ($this->debug) {
      error_log("No sequential indicators found");
    }

    // No sequential indicator found
    return [
      'has_sequential_indicator' => false,
      'indicator' => null,
      'sub_queries' => [$query],
      'split_position' => null,
    ];
  }

  /**
   * Classify each sub-query independently
   *
   * Task 11.3: Classify each sub-query independently
   *
   * Takes an array of sub-queries and classifies each one independently
   * using the ClassificationEngine. This allows us to determine if a query
   * should be hybrid based on having multiple different query types.
   *
   * @param array $subQueries Array of sub-query strings
   * @return array Array of classification results, each with:
   *   - 'query' (string): The sub-query text
   *   - 'type' (string): Classification type (analytics, semantic, web_search)
   *   - 'confidence' (float): Classification confidence
   *   - 'reasoning' (string): Classification reasoning
   *
   * @since 2026-02-11
   */
  public function classifySubQueries(array $subQueries): array
  {
    $classifications = [];

    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Classifying " . count($subQueries) . " sub-queries independently:");
    }

    foreach ($subQueries as $index => $subQuery) {
      if ($this->debug) {
        error_log("  Sub-query " . ($index + 1) . ": {$subQuery}");
      }

      try {
        // Use ClassificationEngine to classify each sub-query
        $classification = ClassificationEngine::checkSemantics($subQuery);

        $classifications[] = [
          'query' => $subQuery,
          'type' => $classification['type'],
          'confidence' => $classification['confidence'],
          'reasoning' => $classification['reasoning'] ?? '',
        ];

        if ($this->debug) {
          error_log("Type: {$classification['type']} (confidence: {$classification['confidence']})");
        }

      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("Classification failed: " . $e->getMessage());
        }

        // Fallback to semantic if classification fails
        $classifications[] = [
          'query' => $subQuery,
          'type' => 'semantic',
          'confidence' => 0.5,
          'reasoning' => 'Fallback due to classification error',
        ];
      }
    }

    return $classifications;
  }

  /**
   * Calculate hybrid confidence score based on multiple factors
   *
   * This method calculates a confidence score for hybrid classification based on:
   * 1. Sequential words present (+0.3) - "puis", "ensuite", "then", etc.
   * 2. Multiple question types detected (+0.4) - different sub-query types from LLM classification
   * 3. LLM confidence scores (+0.3) - average confidence from sub-query classifications
   *
   * The query is classified as hybrid if the total score >= 0.5
   *
   * **Pure LLM Mode**: This method relies on LLM classification results for sub-queries
   * rather than pattern matching. The only pattern-based check is for sequential indicators,
   * which is necessary to split the query before LLM classification.
   *
   * Examples:
   * - "sku puis résume cgv" → sequential(+0.3) + multiple types(+0.4) + high LLM confidence(+0.3) = 1.0 → HYBRID
   * - "sku et prix" → no sequential(0) + single type(0) + low LLM confidence(0) = 0.0 → NOT HYBRID
   * - "prix puis ventes" → sequential(+0.3) + single type(0) + medium LLM confidence(+0.15) = 0.45 → NOT HYBRID
   *
   * @param string $query Original query to analyze
   * @param array $splitResult Result from splitQueryOnSequentialIndicators()
   * @param array $classifications Result from classifySubQueries() (LLM-based)
   * @return array Result with:
   *   - 'confidence_score' (float): Total confidence score (0.0-1.0)
   *   - 'is_hybrid' (bool): Whether query should be hybrid (score >= 0.5)
   *   - 'factors' (array): Breakdown of confidence factors
   *   - 'reasoning' (string): Explanation of confidence calculation
   */
  public function calculateHybridConfidence(string $query, array $splitResult, array $classifications): array
  {
    $confidenceScore = 0.0;
    $factors = [];
    $reasoning = [];

    // Factor 1: Sequential words present (+0.3)
    // This is the only pattern-based check - necessary to split query before LLM classification
    if ($splitResult['has_sequential_indicator']) {
      $confidenceScore += 0.3;
      $factors['sequential_words'] = 0.3;
      $reasoning[] = "Sequential indicator '{$splitResult['indicator']}' detected (+0.3)";
    } else {
      $factors['sequential_words'] = 0.0;
    }

    // Factor 2: Multiple question types detected (+0.4)
    // Uses LLM classification results from classifySubQueries()
    $types = array_map(function($c) { return $c['type']; }, $classifications);
    $uniqueTypes = array_unique($types);

    if (count($uniqueTypes) >= 2) {
      $confidenceScore += 0.4;
      $factors['multiple_types'] = 0.4;
      $reasoning[] = "Multiple question types detected via LLM: " . implode(', ', $uniqueTypes) . " (+0.4)";
    } else {
      $factors['multiple_types'] = 0.0;
    }

    // Factor 3: LLM confidence scores (+0.3)
    // Uses average confidence from LLM classifications
    $confidences = array_map(function($c) { return $c['confidence']; }, $classifications);
    $avgConfidence = array_sum($confidences) / count($confidences);

    // Scale average confidence to 0.0-0.3 range
    $confidenceFactor = $avgConfidence * 0.3;
    $confidenceScore += $confidenceFactor;
    $factors['llm_confidence'] = $confidenceFactor;
    $reasoning[] = "LLM classification confidence: " . round($avgConfidence, 3) . " (+" . round($confidenceFactor, 3) . ")";

    // Determine if hybrid (score >= 0.5)
    $isHybrid = $confidenceScore >= 0.5;

    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Hybrid Confidence Scoring (Task 11.3 - Pure LLM Mode):");
      error_log("  Query: {$query}");
      error_log("  Sequential words: " . ($factors['sequential_words'] > 0 ? 'YES (+0.3)' : 'NO (0.0)'));
      error_log("  Multiple types (LLM): " . ($factors['multiple_types'] > 0 ? 'YES (+0.4)' : 'NO (0.0)'));
      error_log("  LLM confidence: " . round($avgConfidence, 3) . " (+" . round($confidenceFactor, 3) . ")");
      error_log("  Total confidence: " . round($confidenceScore, 2));
      error_log("  Is hybrid: " . ($isHybrid ? 'YES (>= 0.5)' : 'NO (< 0.5)'));
    }

    return [
      'confidence_score' => $confidenceScore,
      'is_hybrid' => $isHybrid,
      'factors' => $factors,
      'reasoning' => implode("\n", $reasoning),
    ];
  }
  
  /**
   * Build unified prompt for language + intent detection
   *
   * This prompt asks GPT to analyze the query, translate it, and break down
   * complex analytic questions. Returns structured JSON with entity types,
   * time constraints, status keywords, and sub-queries.
   *
   * @param string $query User query
   * @return string GPT prompt
   */
  private function buildUnifiedPrompt(string $query): string
  {
    // Build prompt by concatenating sections from language file
    // This approach allows better maintainability and avoids placeholder issues

    $prompt = '';
    $prompt .= $this->language->getDef('unified_analyzer_prompt_header') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_anti_hallucination') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_multi_temporal') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_compound') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_basic_analytics') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_classification') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_output_format') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_query_section') . "\n";
    $prompt .= $query . "\n\n";  // Insert the actual query here
    $prompt .= $this->language->getDef('unified_analyzer_prompt_final_instructions') . "\n";

    if ($this->debug) {
      error_log("UnifiedQueryAnalyzer: Built prompt from language file sections");
      error_log("UnifiedQueryAnalyzer: Query to analyze: {$query}");
      error_log("UnifiedQueryAnalyzer: Total prompt length: " . strlen($prompt) . " characters");
      error_log("UnifiedQueryAnalyzer: Prompt contains query: " . (strpos($prompt, $query) !== false ? 'YES' : 'NO'));
    }

    return $prompt;
  }

  /**
   * Clean JSON response by removing markdown code blocks
   *
   * GPT sometimes wraps JSON in markdown code blocks like:
   * ```json
   * { ... }
   * ```
   *
   * This method extracts the JSON content from such blocks.
   *
   * @param string $response Raw GPT response
   * @return string Cleaned JSON string
   */
  private function cleanJsonResponse(string $response): string
  {
    // Remove markdown code blocks (```json ... ``` or ``` ... ```)
    $response = trim($response);

    // Pattern 1: ```json\n{...}\n```
    if (preg_match('/^```(?:json)?\s*\n(.*?)\n```$/s', $response, $matches)) {
      return trim($matches[1]);
    }

    // Pattern 2: ```{...}```
    if (preg_match('/^```(?:json)?\s*(\{.*?\})\s*```$/s', $response, $matches)) {
      return trim($matches[1]);
    }

    // No markdown blocks found, return as-is
    return $response;
  }

  /**
   * Validate and sanitize analysis result
   *
   * Ensures the GPT response has all required fields with valid values.
   * Provides safe fallbacks for missing or invalid data.
   *
   * @param array|null $analysis Parsed JSON from GPT
   * @param string $originalQuery Original query for fallback
   * @return array Validated analysis
   */
  private function validateAnalysis(?array $analysis, string $originalQuery): array
  {
    // Default fallback
    $default = [
      'language' => 'en',
      'translated_query' => $originalQuery,
      'intent_type' => 'semantic',
      'entity_type' => ['general'],
      'time_constraint' => 'none',
      'status_keywords' => [],
      'sub_queries' => [],
      'confidence' => 0.5,
      'detection_method' => 'llm', // CRITICAL FIX (2026-01-02): Always set detection_method
      'is_multi_temporal' => false,
      'temporal_periods' => [],
      'temporal_connectors' => [],
      'base_metric' => null,
      'time_range' => null,
      'temporal_period_count' => 0,
    ];

    if (!is_array($analysis)) {
      if ($this->debug) {
        error_log("" . $this->language->getDef('validation_using_default'));
      }
      return $default;
    }

    // ✅ CRITICAL FIX (2026-01-02): Ensure detection_method is always set
    // Default to 'llm' if not set by pattern filters
    if (!isset($analysis['detection_method']) || empty($analysis['detection_method'])) {
      $analysis['detection_method'] = 'llm';
    }

    // Validate language code (must be 2 letters)
    if (!isset($analysis['language']) || !is_string($analysis['language']) || strlen($analysis['language']) !== 2) {
      if ($this->debug) {
        error_log("⚠️ " . $this->language->getDef('validation_invalid_language_code'));
      }
      $analysis['language'] = 'en';
    } else {
      $analysis['language'] = strtolower($analysis['language']);
    }

    // Validate translated query
    if (!isset($analysis['translated_query']) || !is_string($analysis['translated_query']) || empty(trim($analysis['translated_query']))) {
      if ($this->debug) {
        error_log("⚠️ " . $this->language->getDef('validation_invalid_translated_query'));
      }
      $analysis['translated_query'] = $originalQuery;
    } else {
      $analysis['translated_query'] = trim($analysis['translated_query']);
    }

    // Validate intent type
    $validIntents = ['analytics', 'semantic', 'hybrid', 'web_search'];
    if (!isset($analysis['intent_type']) || !in_array($analysis['intent_type'], $validIntents, true)) {
      if ($this->debug) {
        error_log("⚠️ " . $this->language->getDef('validation_invalid_intent_type'));
      }
      $analysis['intent_type'] = 'semantic';
    }

    // Validate entity_type (must be array)
    if (!isset($analysis['entity_type']) || !is_array($analysis['entity_type']) || empty($analysis['entity_type'])) {
      if ($this->debug) {
        error_log("⚠️ " . $this->language->getDef('validation_invalid_entity_type'));
      }
      $analysis['entity_type'] = ['general'];
    } else {
      // Sanitize entity types
      $validEntities = ['product', 'order', 'customer', 'category', 'manufacturer', 'supplier', 'general'];
      $analysis['entity_type'] = array_values(array_intersect($analysis['entity_type'], $validEntities));
      if (empty($analysis['entity_type'])) {
        $analysis['entity_type'] = ['general'];
      }
    }

    // Validate time_constraint
    $validTimeConstraints = ['comparison', 'relative_period', 'specific_date', 'none'];
    if (!isset($analysis['time_constraint']) || !in_array($analysis['time_constraint'], $validTimeConstraints, true)) {
      if ($this->debug) {
        error_log("⚠️ " . $this->language->getDef('validation_invalid_time_constraint'));
      }
      $analysis['time_constraint'] = 'none';
    }

    // Validate status_keywords (must be array)
    if (!isset($analysis['status_keywords']) || !is_array($analysis['status_keywords'])) {
      if ($this->debug) {
        error_log("⚠️ " . $this->language->getDef('validation_invalid_status_keywords'));
      }
      $analysis['status_keywords'] = [];
    } else {
      // Sanitize status keywords (lowercase, trim) - ensure all elements are strings
      $sanitized = [];
      foreach ($analysis['status_keywords'] as $keyword) {
        if (is_string($keyword)) {
          $trimmed = trim(strtolower($keyword));
          if (!empty($trimmed)) {
            $sanitized[] = $trimmed;
          }
        }
      }
      $analysis['status_keywords'] = $sanitized;
    }

    // Validate sub_queries (must be array)
    if (!isset($analysis['sub_queries']) || !is_array($analysis['sub_queries'])) {
      if ($this->debug) {
        error_log("⚠️ " . $this->language->getDef('validation_invalid_sub_queries'));
      }
      $analysis['sub_queries'] = [];
    } else {
      // Sanitize sub-queries
      // Handle both string arrays and object arrays (sub-queries can be strings or arrays with metadata)
      $sanitized = [];
      foreach ($analysis['sub_queries'] as $sub_query) {
        if (is_string($sub_query)) {
          // Simple string sub-query: trim and add if not empty
          $trimmed = trim($sub_query);
          if (!empty($trimmed)) {
            $sanitized[] = $trimmed;
          }
        } elseif (is_array($sub_query)) {
          // Complex sub-query object: keep as-is (already validated by ComplexQueryHandler)
          $sanitized[] = $sub_query;
        }
      }
      $analysis['sub_queries'] = $sanitized;
    }

    // Validate confidence (must be between 0.0 and 1.0)
    if (!isset($analysis['confidence']) || !is_numeric($analysis['confidence'])) {
      if ($this->debug) {
        error_log("⚠️ " . $this->language->getDef('validation_invalid_confidence'));
      }
      $analysis['confidence'] = 0.5;
    } else {
      $analysis['confidence'] = max(0.0, min(1.0, (float)$analysis['confidence']));
    }

    return $analysis;
  }

  /**
   * Extract temporal metadata from analysis result
   *
   * This method ensures temporal metadata is always present in the analysis result,
   * even when the MultiTemporalPostFilter doesn't override the LLM classification.
   * It uses the MultiTemporalPostFilter's detection methods to extract:
   * - temporal_periods: List of temporal periods (month, quarter, semester, etc.)
   * - temporal_connectors: List of temporal connectors (then, and, etc.)
   * - base_metric: Base metric for temporal queries (revenue, sales, etc.)
   * - time_range: Time range for temporal queries (year 2025, etc.)
   * - is_multi_temporal: Whether query contains multiple temporal aggregations
   * - temporal_period_count: Number of temporal periods detected
   *
   * @param array $analysis The analysis result from LLM and post-filters
   * @return array Analysis result with temporal metadata fields populated
   */
  private function extractTemporalMetadata(array $analysis): array
  {
    // If temporal metadata already exists (from MultiTemporalPostFilter override), return as-is
    if (isset($analysis['is_multi_temporal']) && $analysis['is_multi_temporal'] === true) {
      // Ensure all fields are present
      $analysis['temporal_periods'] = $analysis['temporal_periods'] ?? [];
      $analysis['temporal_connectors'] = $analysis['temporal_connectors'] ?? [];

      // Load AnalyticsPatterns dynamically from active domain
      $domainApp = DomainRegistry::getInstance()->getActiveApp();
      if ($domainApp && method_exists($domainApp, 'getAnalyticsPatternsClass')) {
        $analyticsPatternsClass = $domainApp->getAnalyticsPatternsClass();
        if ($analyticsPatternsClass && class_exists($analyticsPatternsClass) && method_exists($analyticsPatternsClass, 'extractBaseMetric')) {
          $analysis['base_metric'] = $analysis['base_metric'] ?? $analyticsPatternsClass::extractBaseMetric($analysis['translated_query'] ?? '');
        } else {
          $analysis['base_metric'] = $analysis['base_metric'] ?? null;
        }
      } else {
        $analysis['base_metric'] = $analysis['base_metric'] ?? null;
      }

      $analysis['time_range'] = $analysis['time_range'] ?? TimeRangePattern::extractTimeRange($analysis['translated_query'] ?? '');
      $analysis['temporal_period_count'] = count($analysis['temporal_periods']);
      return $analysis;
    }

    // Extract temporal metadata from translated query
    $translatedQuery = $analysis['translated_query'] ?? '';

    // Use MultiTemporalPostFilter's detection methods
    $temporalPeriods = MultiTemporalPostFilter::getDetectedTemporalPeriods($translatedQuery);
    $temporalConnectors = MultiTemporalPostFilter::getDetectedTemporalConnectors($translatedQuery);

    // Determine if this is a multi-temporal query
    $isMultiTemporal = count($temporalPeriods) >= 2 && !empty($temporalConnectors);

    // Extract base metric and time range using pattern classes
    // Load AnalyticsPatterns dynamically from active domain
    $baseMetric = null;
    $domainApp = DomainRegistry::getInstance()->getActiveApp();
    if ($domainApp && method_exists($domainApp, 'getAnalyticsPatternsClass')) {
      $analyticsPatternsClass = $domainApp->getAnalyticsPatternsClass();
      if ($analyticsPatternsClass && class_exists($analyticsPatternsClass) && method_exists($analyticsPatternsClass, 'extractBaseMetric')) {
        $baseMetric = $analyticsPatternsClass::extractBaseMetric($translatedQuery);
      }
    }

    $timeRange = TimeRangePattern::extractTimeRange($translatedQuery);

    // Populate temporal metadata fields
    $analysis['is_multi_temporal'] = $isMultiTemporal;
    $analysis['temporal_periods'] = $temporalPeriods;
    $analysis['temporal_connectors'] = $temporalConnectors;
    $analysis['base_metric'] = $baseMetric;
    $analysis['time_range'] = $timeRange;
    $analysis['temporal_period_count'] = count($temporalPeriods);

    // Log temporal metadata extraction
    if ($this->debug && ($isMultiTemporal || !empty($temporalPeriods))) {
      error_log("🕐 Temporal Metadata Extracted:");
      error_log("  Is Multi-Temporal: " . ($isMultiTemporal ? 'yes' : 'no'));
      error_log("  Periods: " . implode(', ', $temporalPeriods));
      error_log("  Connectors: " . implode(', ', $temporalConnectors));
      error_log("  Base Metric: " . ($baseMetric ?? 'none'));
      error_log("  Time Range: " . ($timeRange ?? 'none'));
    }

    return $analysis;
  }

  /**
   * Infer sub_types for hybrid queries when ClassificationEngine omits them.
   *
   * @param string $query
   * @param string $originalQuery
   * @return array
   */
  private function inferHybridSubTypes(string $query, string $originalQuery): array
  {
    $splitResult = $this->splitQueryOnSequentialIndicators($query);
    $subQueries = $splitResult['sub_queries'] ?? [$query];

    if (count($subQueries) < 2) {
      $subQueries = $this->splitQueryOnConjunctions($query);
    }

    if (count($subQueries) < 2 && $originalQuery !== $query) {
      $subQueries = $this->splitQueryOnConjunctions($originalQuery);
    }

    if (count($subQueries) < 2) {
      return [];
    }

    $types = [];
    foreach ($subQueries as $subQuery) {
      try {
        $classification = ClassificationEngine::checkSemantics($subQuery);
        $types[] = $classification['type'] ?? 'semantic';
      } catch (\Exception $e) {
        $types[] = 'semantic';
      }
    }

    return $types;
  }

  /**
   * Split query on conjunctions (English/French) when sequential indicators are absent.
   *
   * @param string $query
   * @return array
   */
  private function splitQueryOnConjunctions(string $query): array
  {
    $connectors = [
      ' and ',
      ' et ',
      ' or ',
      ' ou ',
      ' then ',
      ' puis ',
      ' ensuite ',
      ' after ',
      ' après ',
      ' & ',
      ' + ',
      ';',
      ',',
    ];

    $lower = strtolower($query);
    foreach ($connectors as $connector) {
      $pos = strpos($lower, $connector);
      if ($pos !== false) {
        $left = trim(substr($query, 0, $pos));
        $right = trim(substr($query, $pos + strlen($connector)));
        if ($left !== '' && $right !== '') {
          return [$left, $right];
        }
      }
    }

    return [$query];
  }

  /**
   * Handle unrecognized temporal periods
   *
   * ** Fall back to LLM interpretation for unrecognized temporal periods
   *
   * When a temporal period is detected but not in the standard list (month, quarter,
   * semester, year, week, day), this method attempts to interpret it using LLM.
   *
   * Examples of unrecognized periods that might be interpreted:
   * - "biweekly" → every 2 weeks
   * - "fortnightly" → every 2 weeks
   * - "bimonthly" → every 2 months
   * - "trimester" → every 3 months (similar to quarter)
   * - "fiscal year" → year (with fiscal calendar)
   * - "rolling 12 months" → custom period
   *
   * @param string $unrecognizedPeriod The unrecognized temporal period string
   * @param string $query The full query for context
   * @return array Interpretation result with structure:
   *   - recognized: bool (true if LLM could interpret)
   *   - standard_period: string|null (mapped to standard period if possible)
   *   - custom_period: array|null (custom period definition if not standard)
   *   - interpretation: string (human-readable interpretation)
   *   - confidence: float (0.0-1.0)
   *   - needs_clarification: bool (true if user should confirm)
   *   - clarification_message: string|null (message to show user)
   */
  public function handleUnrecognizedTemporalPeriod(string $unrecognizedPeriod, string $query): array
  {
    $defaultResult = [
      'recognized' => false,
      'standard_period' => null,
      'custom_period' => null,
      'interpretation' => "Could not interpret temporal period: {$unrecognizedPeriod}",
      'confidence' => 0.0,
      'needs_clarification' => true,
      'clarification_message' => "I'm not sure what '{$unrecognizedPeriod}' means. Could you specify a standard time period like month, quarter, semester, or year?",
    ];

    // First, try pattern-based mapping for common variations
    $patternMapping = TemporalPeriodMappingPattern::mapPeriod($unrecognizedPeriod);
    if ($patternMapping['recognized']) {
      $this->logger->logStructured(
        'info',
        'UnifiedQueryAnalyzer',
        'unrecognized_period_mapped_by_pattern',
        [
          'original' => $unrecognizedPeriod,
          'mapped_to' => $patternMapping['standard_period'] ?? $patternMapping['custom_period'],
          'confidence' => $patternMapping['confidence'],
        ]
      );
      return $patternMapping;
    }

    // Fall back to LLM interpretation
    try {
      $llmInterpretation = $this->interpretTemporalPeriodWithLLM($unrecognizedPeriod, $query);

      if ($llmInterpretation['recognized']) {
        $this->logger->logStructured(
          'info',
          'UnifiedQueryAnalyzer',
          'unrecognized_period_interpreted_by_llm',
          [
            'original' => $unrecognizedPeriod,
            'interpretation' => $llmInterpretation['interpretation'],
            'standard_period' => $llmInterpretation['standard_period'],
            'confidence' => $llmInterpretation['confidence'],
          ]
        );
        return $llmInterpretation;
      }
    } catch (\Exception $e) {
      $this->logger->logStructured(
        'warning',
        'UnifiedQueryAnalyzer',
        'llm_interpretation_failed',
        [
          'period' => $unrecognizedPeriod,
          'error' => $e->getMessage(),
        ]
      );
    }

    // Log unrecognized period for future enhancement
    $this->logger->logStructured(
      'warning',
      'UnifiedQueryAnalyzer',
      'unrecognized_temporal_period',
      [
        'period' => $unrecognizedPeriod,
        'query' => $query,
        'action' => 'requesting_clarification',
      ]
    );

    return $defaultResult;
  }

  /**
   * Interpret unrecognized temporal period using LLM
   *
   * @param string $period The unrecognized period
   * @param string $query The full query for context
   * @return array Interpretation result
   */
  private function interpretTemporalPeriodWithLLM(string $period, string $query): array
  {
    $array = [
      'period' => $period,
      'query' => $query,
    ];

    $prompt = $this->language->getDef('text_interpret_temporal_period_with_llm', $array);

    try {
      $response = Gpt::getGptResponse($prompt, 200, 0.0);
      $cleanedResponse = $this->cleanJsonResponse($response);
      $result = json_decode($cleanedResponse, true);

      if (json_last_error() === JSON_ERROR_NONE && isset($result['recognized'])) {
        return [
          'recognized' => (bool)($result['recognized'] ?? false),
          'standard_period' => $result['standard_period'] ?? null,
          'custom_period' => $result['custom_period'] ?? null,
          'interpretation' => $result['interpretation'] ?? "Interpreted as: {$result['standard_period']}",
          'confidence' => (float)($result['confidence'] ?? 0.7),
          'needs_clarification' => (bool)($result['needs_clarification'] ?? false),
          'clarification_message' => $result['clarification_message'] ?? null,
        ];
      }
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("LLM temporal interpretation failed: " . $e->getMessage());
      }
    }

    return [
      'recognized' => false,
      'standard_period' => null,
      'custom_period' => null,
      'interpretation' => null,
      'confidence' => 0.0,
      'needs_clarification' => true,
      'clarification_message' => "Could not interpret '{$period}'. Please specify a standard time period.",
    ];
  }
  
  /**
   * Determine if query should be hybrid based on sub-query classifications
   *
   * Analyzes the classifications of sub-queries to determine if the overall
   * query should be classified as hybrid. A query is hybrid if:
   * 1. It has multiple sub-queries (from sequential indicator split)
   * 2. The sub-queries have DIFFERENT types (e.g., analytics + semantic)
   *
   * @param array $classifications Array of classification results from classifySubQueries()
   * @return array Result with:
   *   - 'is_hybrid' (bool): Whether query should be hybrid
   *   - 'sub_types' (array): Unique types found in sub-queries
   *   - 'reasoning' (string): Explanation of hybrid determination
   *   - 'confidence' (float): Average confidence across sub-queries
   *
   * @since 2026-02-11
   */
  public function determineHybridFromSubQueries(array $classifications): array
  {
    if (count($classifications) < 2) {
      return [
        'is_hybrid' => false,
        'sub_types' => [],
        'reasoning' => 'Single query - not hybrid',
        'confidence' => $classifications[0]['confidence'] ?? 0.5,
      ];
    }

    // Extract unique types
    $types = array_map(function($c) { return $c['type']; }, $classifications);
    $uniqueTypes = array_unique($types);

    // Calculate average confidence
    $confidences = array_map(function($c) { return $c['confidence']; }, $classifications);
    $avgConfidence = array_sum($confidences) / count($confidences);

    // Determine if hybrid (multiple DIFFERENT types)
    $isHybrid = count($uniqueTypes) >= 2;

    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Hybrid determination:");
      error_log("  Sub-query types: " . implode(', ', $types));
      error_log("  Unique types: " . implode(', ', $uniqueTypes));
      error_log("  Is hybrid: " . ($isHybrid ? 'YES' : 'NO'));
      error_log("  Average confidence: " . round($avgConfidence, 3));
    }

    if ($isHybrid) {
      return [
        'is_hybrid' => true,
        'sub_types' => array_values($uniqueTypes),
        'reasoning' => 'Sequential indicator detected with multiple different query types: ' . implode(' + ', $uniqueTypes),
        'confidence' => min(0.95, $avgConfidence + 0.1), // Boost confidence for clear hybrid
      ];
    } else {
      return [
        'is_hybrid' => false,
        'sub_types' => array_values($uniqueTypes),
        'reasoning' => 'Sequential indicator detected but all sub-queries are same type: ' . $uniqueTypes[0],
        'confidence' => $avgConfidence,
      ];
    }
  }
}
