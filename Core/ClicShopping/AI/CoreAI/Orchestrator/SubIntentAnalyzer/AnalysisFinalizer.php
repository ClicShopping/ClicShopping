<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * AnalysisFinalizer
 *
 * Stamps the final metadata on the analysis array (original query, translation flag,
 * elapsed time), emits the completion log and the debug summary. Extracted verbatim
 * from UnifiedQueryAnalyzer::analyzeQuery to cut that method's complexity; behaviour
 * is unchanged.
 */
class AnalysisFinalizer
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
   * Add final metadata and emit completion logging.
   *
   * @param array $analysis Reconciled analysis result
   * @param string $query Translated (English) query, used for structured logging
   * @param string $originalQuery Original (pre-translation) query
   * @param float $startTime Analysis start time (microtime(true)) for elapsed measurement
   * @return array Finalized analysis
   */
  public function finalize(array $analysis, string $query, string $originalQuery, float $startTime): array
  {
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
      if (!empty($analysis['is_multi_temporal'])) {
        error_log("  🕐 Multi-Temporal Query Detected:");
        error_log("    Temporal Periods: " . implode(', ', $analysis['temporal_periods'] ?? []));
        error_log("    Temporal Connectors: " . implode(', ', $analysis['temporal_connectors'] ?? []));
        error_log("    Base Metric: " . ($analysis['base_metric'] ?? 'none'));
        error_log("    Time Range: " . ($analysis['time_range'] ?? 'none'));
        error_log("    Period Count: " . ($analysis['temporal_period_count'] ?? 0));
      }
    }

    return $analysis;
  }
}
