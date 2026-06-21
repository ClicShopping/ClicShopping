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
 * HybridSubTypeReconciler
 *
 * Reconciles the hybrid intent type against the detected sub_types after the
 * post-filters have run: restores 'hybrid' when sub_types hold multiple DIFFERENT
 * types, keeps 'hybrid' for multi-entity same-type queries (to enable decomposition),
 * and infers missing sub_types for hybrid queries. Extracted verbatim from
 * UnifiedQueryAnalyzer::analyzeQuery to cut that method's complexity; behaviour
 * is unchanged.
 */
class HybridSubTypeReconciler
{
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * @param SecurityLogger $logger Structured logger (shared with UnifiedQueryAnalyzer)
   * @param bool $debug Enable debug logging
   */
  public function __construct(SecurityLogger $logger, bool $debug = false)
  {
    $this->logger = $logger;
    $this->debug = $debug;
  }

  /**
   * Reconcile intent_type / sub_types coherence on the analysis array.
   *
   * @param array $analysis Post-filtered analysis result
   * @param string $query Translated (English) query, used for logging and sub_type inference
   * @param callable $inferSubTypes fn(string $query): array — infers sub_types when
   *        ClassificationEngine returns hybrid without them
   * @return array Reconciled analysis
   */
  public function reconcile(array $analysis, string $query, callable $inferSubTypes): array
  {
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
      $inferredSubTypes = $inferSubTypes(
        $analysis['translated_query'] ?? $query
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

    return $analysis;
  }
}
