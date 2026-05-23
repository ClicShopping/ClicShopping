<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Cache;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\EntityExtractor;
use ClicShopping\AI\CoreAI\Context\ContextRetriever;
use ClicShopping\AI\Infrastructure\Async\AsyncOperationManager;
use ClicShopping\AI\Infrastructure\Monitoring\PerformanceMonitor;

// 🔧 PHASE 9: Import new SubIntentAnalyzer components
use ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer\TranslationService;
use ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer\IntentAnalyzerFactory;

// 🔧 PHASE 14: Import UnifiedQueryAnalyzer for unified language + intent detection
use ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer\UnifiedQueryAnalyzer;

/**
 * IntentAnalyzer Class (REFACTORED)
 *
 * Responsible for analyzing user queries to determine intent and extract metadata.
 * Separated from OrchestratorAgent to follow Single Responsibility Principle.
 *
 * 🔧 PHASE 9 REFACTORING (2025-12-14):
 * - Reduced from 2034 lines to <500 lines (75% reduction)
 * - Extracted translation logic to TranslationService
 * - Extracted intent detection to IntentAnalyzerFactory + specialized analyzers
 * - Kept only orchestration and caching logic
 * - Maintained backward compatibility
 *
 * Responsibilities:
 * - Orchestrate translation and intent analysis
 * - Manage caching for performance
 * - Extract entities and metadata
 * - Resolve contextual references
 * - Monitor performance
 */

class IntentAnalyzer
{
  private SecurityLogger $logger;
  private ?ConversationMemory $conversationMemory;
  private bool $debug;

  private mixed $language;
  private EntityExtractor $entityExtractor;
  
  // Cache support
  private bool $cacheEnabled = true;
  private int $cacheTTL = 5; // 5 minutes
  
  // Context retrieval
  private ?ContextRetriever $contextRetriever = null;
  
  // Async operations
  private ?AsyncOperationManager $asyncManager = null;
  
  // Performance monitoring
  private ?PerformanceMonitor $performanceMonitor = null;

  // 🔧 PHASE 9: New SubIntentAnalyzer components (deprecated - kept for backward compatibility)
  private TranslationService $translationService;
  private IntentAnalyzerFactory $intentFactory;

  // 🔧 PHASE 14: Unified analyzer for language + intent detection (ALWAYS used)
  private ?UnifiedQueryAnalyzer $unifiedAnalyzer = null;
  private bool $useUnifiedAnalyzer = true; // Always true - required for analytics
  private bool $useHybridMode = false; // Deprecated - Pure LLM mode only

  /**
   * Constructor
   *
   * @param ConversationMemory $conversationMemory For context resolution
   * @param bool $debug Enable debug logging
   */
  public function __construct(?ConversationMemory $conversationMemory, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->conversationMemory = $conversationMemory;
    $this->debug = $debug;
    $this->language = Registry::get('Language');

    $this->entityExtractor = new EntityExtractor();
    $this->cacheEnabled = true;
    $this->contextRetriever = new ContextRetriever($conversationMemory, $debug);
    $this->asyncManager = new AsyncOperationManager();
    $this->performanceMonitor = new PerformanceMonitor($debug, 100.0);

    // 🔧 PHASE 9: Initialize new SubIntentAnalyzer components
    $this->translationService = new TranslationService($debug);
    $this->intentFactory = new IntentAnalyzerFactory($debug);

    // 🔧 PHASE 14: Initialize UnifiedQueryAnalyzer (ALWAYS used - required for analytics)
    // UnifiedQueryAnalyzer is hardcoded to always be used because:
    // 1. Analytics queries break without it
    // 2. Required for Pure LLM mode operation
    // 3. Provides comprehensive intent classification (analytics, semantic, web_search, hybrid)
    $this->useUnifiedAnalyzer = true;
    $this->unifiedAnalyzer = new UnifiedQueryAnalyzer($debug);
    
    if ($this->debug) {
      $this->logger->logSecurityEvent("IntentAnalyzer initialized with UNIFIED analyzer (Phase 14 - always enabled)", 'info');
    }
  }

  /**
   * Analyze query to determine intent, confidence, and metadata
   *
   * 🔧 PHASE 9 REFACTORED: Simplified to use SubIntentAnalyzer components
   *
   * @param string $query User query (any language)
   * @return array Intent analysis with type, confidence, metadata, etc.
   */
  public function analyze(string $query): array
  {
    // Start performance tracking
    if ($this->performanceMonitor) {
      $this->performanceMonitor->startOperation('intent_analysis_full', ['query' => $query]);
    }
    
    $timings = [
      'start' => microtime(true),
      'cache_check' => 0,
      'translation' => 0,
      'intent_analysis' => 0,
      'metadata_extraction' => 0,
      'context_retrieval' => 0,
      'entity_detection' => 0,
      'total' => 0,
    ];

    if ($this->debug) {
      error_log(str_repeat("=", 100));
      error_log("DEBUG: IntentAnalyzer.analyze() - START (REFACTORED)");
      error_log(str_repeat("=", 100));
      error_log("Input query: '{$query}'");
    }

    // 1. Check cache first
    $cacheCheckStart = microtime(true);
    $cacheKey = md5(strtolower(trim($query)));
    
    if ($this->cacheEnabled) {
      $cachedResult = $this->checkCache($cacheKey, $query);
      if ($cachedResult !== null) {
        $timings['cache_check'] = (microtime(true) - $cacheCheckStart) * 1000;
        $timings['total'] = (microtime(true) - $timings['start']) * 1000;
        
        $cachedResult['from_cache'] = true;
        $cachedResult['performance_timings'] = $timings;
        
        if ($this->performanceMonitor) {
          $this->performanceMonitor->endOperation('intent_analysis_full', [
            'from_cache' => true,
            'duration_ms' => round($timings['total'], 2),
          ]);
        }
        
        if ($this->debug) {
          error_log("[info] CACHE HIT - Returning cached result");
          error_log(str_repeat("=", 100));
        }
        
        return $cachedResult;
      }
    }
    
    $timings['cache_check'] = (microtime(true) - $cacheCheckStart) * 1000;

    // 2. Use UnifiedQueryAnalyzer (ALWAYS - required for analytics)
    // UnifiedQueryAnalyzer provides comprehensive intent classification for all query types
    if ($this->debug) {
      error_log("[info] Using UNIFIED analyzer (Pure LLM mode - always enabled)");
    }
    
    $unifiedStart = microtime(true);
    $unifiedResult = $this->unifiedAnalyzer->analyzeQuery($query);
    $unifiedTime = (microtime(true) - $unifiedStart) * 1000;
    
    $translatedQuery = $unifiedResult['translated_query'];
    $queryType = $unifiedResult['intent_type'];
    $confidence = $unifiedResult['confidence'];
    $metadata = [
      'language' => $unifiedResult['language'],
      'was_translated' => $unifiedResult['was_translated'],
      'detection_method' => 'llm',
    ];
    
    // Create intentResult structure for unified mode
    $intentResult = [
      'type' => $queryType,
      'confidence' => $confidence,
      'metadata' => $metadata,
      'reasoning' => ['unified_analyzer'], // Unified mode doesn't provide detailed reasoning
      'is_hybrid' => ($queryType === 'hybrid'), // Set based on actual query type
    ];
    
    $timings['translation'] = 0; // Included in unified call
    $timings['intent_analysis'] = $unifiedTime;
    
    if ($this->debug) {
      error_log("Language: {$unifiedResult['language']}");
      error_log("Translation: " . ($unifiedResult['was_translated'] ? 'PERFORMED' : 'SKIPPED'));
      error_log("Translated query: '{$translatedQuery}'");
      error_log("Intent type: '{$queryType}' (confidence: " . round($confidence, 3) . ")");
      error_log("Unified analysis time: " . round($unifiedTime, 2) . "ms");
      
      // ✅ Log temporal metadata for debugging
      if (!empty($unifiedResult['is_multi_temporal']) && $unifiedResult['is_multi_temporal']) {
        error_log("[INFO : TIME] TEMPORAL METADATA DETECTED:");
        error_log("  is_multi_temporal: true");
        error_log("  temporal_periods: " . implode(', ', $unifiedResult['temporal_periods'] ?? []));
        error_log("  temporal_connectors: " . implode(', ', $unifiedResult['temporal_connectors'] ?? []));
        error_log("  base_metric: " . ($unifiedResult['base_metric'] ?? 'none'));
        error_log("  time_range: " . ($unifiedResult['time_range'] ?? 'none'));
        error_log("  temporal_period_count: " . ($unifiedResult['temporal_period_count'] ?? 0));
      }
    }

    // 4. Extract additional metadata (entities, context, etc.)
    $metadataStart = microtime(true);
    $enrichedMetadata = $this->enrichMetadata($metadata, $translatedQuery, $query, $queryType, $confidence);
    $timings['metadata_extraction'] = (microtime(true) - $metadataStart) * 1000;

    // 5. Build final result
    // ✅ CRITICAL FIX (2026-01-08): Include temporal metadata from UnifiedQueryAnalyzer
    // This ensures temporal_periods, temporal_connectors, base_metric, time_range are passed
    // to HybridQueryProcessor for proper temporal splitting
    // This ensures hybrid queries have their sub_types preserved for decomposition
    $result = [
      'original_query' => $query,
      'translated_query' => $translatedQuery,
      'query_type' => $queryType,
      'type' => $queryType, // Add 'type' alias for backward compatibility
      'confidence' => $confidence,
      'metadata' => $enrichedMetadata,
      'reasoning' => $intentResult['reasoning'] ?? [],
      'is_hybrid' => $intentResult['is_hybrid'] ?? false, // Add is_hybrid flag
      'sub_types' => $unifiedResult['sub_types'] ?? [], // Include sub_types for hybrid queries
      'from_cache' => false,
      'performance_timings' => $timings,
      // TEMPORAL METADATA (from UnifiedQueryAnalyzer via MultiTemporalPostFilter)
      'is_multi_temporal' => $unifiedResult['is_multi_temporal'] ?? false,
      'temporal_periods' => $unifiedResult['temporal_periods'] ?? [],
      'temporal_connectors' => $unifiedResult['temporal_connectors'] ?? [],
      'base_metric' => $unifiedResult['base_metric'] ?? null,
      'time_range' => $unifiedResult['time_range'] ?? null,
      'temporal_period_count' => $unifiedResult['temporal_period_count'] ?? 0,
    ];

    // 6. Cache result
    if ($this->cacheEnabled) {
      $this->cacheResult($cacheKey, $result);
    }

    // End performance tracking
    $timings['total'] = (microtime(true) - $timings['start']) * 1000;
    $result['performance_timings'] = $timings;

    if ($this->performanceMonitor) {
      $this->performanceMonitor->endOperation('intent_analysis_full', [
        'from_cache' => false,
        'duration_ms' => round($timings['total'], 2),
        'query_type' => $queryType,
        'confidence' => $confidence,
      ]);
    }

    if ($this->debug) {
      error_log("\n--- PERFORMANCE TIMINGS ---");
      error_log(sprintf("Cache check:           %6.2f ms", $timings['cache_check']));
      error_log(sprintf("Translation:           %6.2f ms", $timings['translation']));
      error_log(sprintf("Intent analysis:       %6.2f ms", $timings['intent_analysis']));
      error_log(sprintf("Metadata extraction:   %6.2f ms", $timings['metadata_extraction']));
      error_log(sprintf("TOTAL:                 %6.2f ms", $timings['total']));
      error_log(str_repeat("=", 100) . "\n");
    }

    return $result;
  }

  /**
   * Check cache for existing analysis result
   *
   * Cache location: Work/Cache/Rag/Intent/intent_*.cache
   * Cache key: md5(strtolower(trim($query)))
   * Cache TTL: 5 minutes (300 seconds)
   *
   * @param string $cacheKey Cache key
   * @param string $query Original query
   * @return array|null Cached result or null if not found
   */
  private function checkCache(string $cacheKey, string $query): ?array
  {
    try {
      if ($this->performanceMonitor) {
        $this->performanceMonitor->startOperation('cache_check', ['query' => $query]);
      }
      
      // Use 'Rag/Intent' namespace to store cache in Work/Cache/Rag/Intent/
      $cache = new Cache($cacheKey, 'Rag/Intent');
      
      if ($cache->exists($this->cacheTTL)) {
        $cached = $cache->get();
        
        if ($cached !== null && is_array($cached)) {
          if ($this->performanceMonitor) {
            $this->performanceMonitor->endOperation('cache_check');
            $this->performanceMonitor->logCacheHit('intent_cache', [
              'query' => $query,
              'cache_key' => $cacheKey,
            ]);
          }
          
          return $cached;
        }
      }
      
      if ($this->performanceMonitor) {
        $this->performanceMonitor->endOperation('cache_check');
        $this->performanceMonitor->logCacheMiss('intent_cache', [
          'query' => $query,
          'cache_key' => $cacheKey,
        ]);
      }
      
      return null;
      
    } catch (\Exception $e) {
      if ($this->performanceMonitor) {
        $this->performanceMonitor->endOperation('cache_check', ['error' => $e->getMessage()]);
      }
      
      $this->logger->logStructured(
        'warning',
        'IntentAnalyzer',
        'cache_read_error',
        [
          'query' => $query,
          'error' => $e->getMessage(),
        ]
      );
      
      if ($this->debug) {
        error_log("⚠️ CACHE READ ERROR: " . $e->getMessage());
      }
      
      return null;
    }
  }

  /**
   * Enrich metadata with entities, context, and additional information
   *
   * @param array $metadata Base metadata from intent analyzer
   * @param string $translatedQuery Translated query
   * @param string $originalQuery Original query
   * @param string $queryType Query type
   * @return array Enriched metadata
   */
  private function enrichMetadata(array $metadata, string $translatedQuery, string $originalQuery, string $queryType, float $confidence): array
  {
    // Initialize entities array if not present
    if (!isset($metadata['entities'])) {
      $metadata['entities'] = [];
    }

    // Add query information
    $metadata['word_count'] = str_word_count($translatedQuery);
    $metadata['char_count'] = mb_strlen($translatedQuery);

    // Add context if available
    if ($this->contextRetriever) {
      $contextStart = microtime(true);

      // Build classification array for context retrieval
      $classification = [
        'type' => $queryType,
        'confidence' => $confidence,
        'metadata' => $metadata
      ];

      $context = $this->contextRetriever->retrieveContext($classification, $translatedQuery);
      $metadata['context'] = $context;
      $metadata['context_retrieval_time_ms'] = (microtime(true) - $contextStart) * 1000;
    }

    return $metadata;
  }

  /**
   * Cache analysis result
   *
   * Cache location: Work/Cache/Rag/Intent/intent_*.cache
   *
   * @param string $cacheKey Cache key
   * @param array $result Analysis result
   */
  private function cacheResult(string $cacheKey, array $result): void
  {
    try {
      // Use 'Rag/Intent' namespace to store cache in Work/Cache/Rag/Intent/
      $cache = new Cache($cacheKey, 'Rag/Intent');
      $cache->save($result);

      if ($this->debug) {
        error_log("[SUCCESS] Result cached successfully in Work/Cache/Rag/Intent/");
      }

    } catch (\Exception $e) {
      $this->logger->logStructured(
        'warning',
        'IntentAnalyzer',
        'cache_write_error',
        [
          'error' => $e->getMessage(),
        ]
      );

      if ($this->debug) {
        error_log("[WARNING] CACHE WRITE ERROR: " . $e->getMessage());
      }
    }
  }

  /**
   * Get performance dashboard data
   *
   * @return array Performance dashboard data
   */
  public function getPerformanceDashboardData(): array
  {
    if (!$this->performanceMonitor) {
      return [];
    }
    
    return $this->performanceMonitor->getDashboardData();
  }

  /**
   * Get performance monitor instance
   *
   * @return PerformanceMonitor|null Performance monitor instance
   */
  public function getPerformanceMonitor(): ?PerformanceMonitor
  {
    return $this->performanceMonitor;
  }

  /**
   * Detect entity from embeddings (delegated to EntityExtractor)
   *
   * @param string $query Query to analyze
   * @param string $entityType Type of entity to search for
   * @return array|null Entity data or null if not found
   */
  public function detectEntityFromEmbeddings(string $query, string $entityType = 'product'): ?array
  {
    return $this->entityExtractor->detectEntityFromEmbeddings($query, $entityType);
  }

  /**
   * Detect keywords (delegated to EntityExtractor)
   *
   * @param string $query Query to analyze
   * @param string $entityType Type of entity to search for
   * @return array|null Entity data or null if not found
   */
  public function detectKeywords(string $query, string $entityType = 'product'): ?array
  {
    return $this->entityExtractor->detectKeywords($query, $entityType);
  }

  /**
   * Enable or disable unified analyzer (Phase 14)
   * 
   * @deprecated UnifiedQueryAnalyzer is now always enabled (required for analytics)
   * @param bool $enable Ignored - unified analyzer is always enabled
   * @return void
   */
  public function setUseUnifiedAnalyzer(bool $enable): void
  {
    // UnifiedQueryAnalyzer is now always enabled - this method is deprecated
    if ($this->debug) {
      error_log("⚠️ DEPRECATED: setUseUnifiedAnalyzer() called but UnifiedQueryAnalyzer is always enabled");
    }
  }

  /**
   * Check if unified analyzer is enabled
   * 
   * @deprecated UnifiedQueryAnalyzer is now always enabled (required for analytics)
   * @return bool Always returns true
   */
  public function isUsingUnifiedAnalyzer(): bool
  {
    return true; // Always true - unified analyzer is always enabled
  }
  
  /**
   * Enable or disable hybrid mode (Phase 14)
   * 
   * When enabled, uses pattern-based detection for FR/EN (fast path)
   * and GPT for other languages. When disabled, uses GPT for all languages.
   * 
   * Hybrid mode provides optimal balance:
   * - Fast path for 90%+ of queries (FR/EN) - ~560ms, $0.0001
   * - GPT for other languages (10%) - ~660ms, $0.00015
   * - Total cost increase: +8.3%
   * 
   * Full GPT mode:
   * - All queries use GPT - ~650ms, $0.00015
   * - Total cost increase: +66.7%
   * 
   * @param bool $enable True to enable hybrid mode, false for full GPT
   * @return void
   */
  public function setUseHybridMode(bool $enable): void
  {
    $this->useHybridMode = $enable;
    
    if ($this->debug) {
      $mode = $enable ? 'HYBRID (patterns for FR/EN, GPT for others)' : 'FULL GPT (all languages)';
      error_log("[INFO] Unified analyzer mode: {$mode}");
    }
  }
  
  /**
   * Check if hybrid mode is enabled
   * 
   * @return bool True if hybrid mode is enabled
   */
  public function isUsingHybridMode(): bool
  {
    return $this->useHybridMode;
  }
}
