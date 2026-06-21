<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;


use ClicShopping\OM\Registry;
use ClicShopping\AI\DomainsAI\DomainRegistry;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\MultiTemporalPostFilter;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\TimeRangePattern;
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
  private PostFilterPipeline $postFilterPipeline;
  private HybridSubTypeReconciler $hybridSubTypeReconciler;
  private AnalysisFinalizer $analysisFinalizer;
  private SequentialQueryClassifier $sequentialClassifier;
  private UnifiedMetadataExtractor $metadataExtractor;

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

    $this->postFilterPipeline = new PostFilterPipeline($this->logger, $this->language, $this->debug);
    $this->hybridSubTypeReconciler = new HybridSubTypeReconciler($this->logger, $this->debug);
    $this->analysisFinalizer = new AnalysisFinalizer($this->logger, $this->language, $this->debug);
    $this->sequentialClassifier = new SequentialQueryClassifier($this->debug);
    $this->metadataExtractor = new UnifiedMetadataExtractor($this->logger, $this->language, $this->debug);
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
      error_log("[INFO] " . $this->language->getDef('debug_analysis_start'));
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
      
      // Classify intent: split on sequential indicators + hybrid confidence,
      // else ClassificationEngine (extracted to SequentialQueryClassifier).
      $classification = $this->sequentialClassifier->classify($query);
      
      // Extract translation/entity metadata via the unified LLM prompt
      // (build + Gpt call + JSON parse + fallback; extracted to UnifiedMetadataExtractor).
      $analysis = $this->metadataExtractor->extract($query);
      
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

      // Apply pattern-based post-filters (Analytics, WebSearch, Superlative, MultiTemporal)
      // that override the LLM classification for deterministic edge cases.
      $analysis = $this->postFilterPipeline->apply($analysis, $query);
      
      // ⚠️ CRITICAL: Extract temporal metadata for ALL queries (not just overridden ones)
      // This ensures temporal metadata is always available for downstream processing
      // even when the LLM correctly classifies the query as hybrid
      $analysis = $this->extractTemporalMetadata($analysis);

      // Reconcile intent_type / sub_types coherence after the post-filters (restore hybrid,
      // keep multi-entity hybrid for decomposition, infer missing sub_types).
      $analysis = $this->hybridSubTypeReconciler->reconcile(
        $analysis,
        $query,
        fn(string $q): array => $this->sequentialClassifier->inferHybridSubTypes($q)
      );

      // Stamp final metadata + emit completion logging (extracted to AnalysisFinalizer).
      $analysis = $this->analysisFinalizer->finalize($analysis, $query, $originalQuery, $startTime);

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


}
