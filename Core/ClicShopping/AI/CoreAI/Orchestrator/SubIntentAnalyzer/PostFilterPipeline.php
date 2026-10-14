<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;

use ClicShopping\AI\DomainsAI\DomainRegistry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\WebSearch\Patterns\WebSearchPostFilter;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\SuperlativePostFilter;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\MultiTemporalPostFilter;

/**
 * PostFilterPipeline
 *
 * Applies the pattern-based post-filters that override the LLM classification for
 * deterministic edge cases (temporal-financial, web search, superlative, multi-temporal).
 * Extracted verbatim from UnifiedQueryAnalyzer::analyzeQuery to cut that method's
 * complexity; behaviour is unchanged.
 */
class PostFilterPipeline
{
  private SecurityLogger $logger;
  private mixed $language;
  private bool $debug;

  /**
   * @param SecurityLogger $logger Structured logger (shared with UnifiedQueryAnalyzer)
   * @param mixed $language Language registry object (defs loaded by the caller)
   * @param bool $debug Enable debug logging
   */
  public function __construct(SecurityLogger $logger, mixed $language, bool $debug = false)
  {
    $this->logger = $logger;
    $this->language = $language;
    $this->debug = $debug;
  }

  /**
   * Apply all pattern-based post-filters to the analysis array.
   *
   * @param array $analysis Classification/analysis result to post-filter
   * @param string $query Translated (English) query, used for structured logging
   * @return array Post-filtered analysis
   */
  public function apply(array $analysis, string $query): array
  {
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

    return $analysis;
  }
}
