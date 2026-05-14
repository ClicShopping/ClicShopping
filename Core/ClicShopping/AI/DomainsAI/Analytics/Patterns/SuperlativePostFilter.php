<?php
/**
 * SuperlativePostFilter.php
 * 
 * ⚠️ EXCEPTION TO PURE LLM APPROACH ⚠️
 * 
 * This pattern-based post-filter exists ONLY because LLM classification
 * is inconsistent for superlative queries (classifies as semantic instead of analytics).
 * It ensures 100% consistency for MIN/MAX/BEST/WORST queries.
 * 
 * WHY THIS EXISTS:
 * - LLM correctly classifies "What is the most expensive product" as analytics: 40% of the time
 * - LLM often classifies superlative queries as "semantic" instead of "analytics"
 * - Production requires 95%+ consistency
 * - Pattern-based post-filter provides deterministic results
 * 
 * WHEN TO DELETE THIS FILE:
 * - When LLM classification achieves 95%+ consistency for superlative queries
 * - When LLM temperature is reduced to 0.0 and tested
 * - When LLM model is upgraded (e.g., GPT-4o instead of GPT-4o-mini)
 * - Move to Old/ directory when no longer needed
 * 
 * PRINCIPLE:
 * DO NOT create new patterns unless LLM cannot do the job consistently (95%+)
 * 
 * ⚠️ ENGLISH ONLY (Post-Translation):
 * - This pattern is called AFTER translation to English
 * - All keywords are English-only (no French/Spanish/German)
 * - Multilingual queries are translated before this pattern is applied
 * 
 * @package ClicShopping
 * @subpackage AI\Domain\Patterns
 * @date 2026-01-03
 * @author ClicShopping Team
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class SuperlativePostFilter
{
  /**
   * Check if query contains a superlative pattern
   * 
   * @param string $query The query to check
   * @return bool True if superlative pattern found
   */
  public static function hasSuperlativePattern(string $query): bool
  {
    $query = strtolower($query);
    
    foreach (SuperlativePatterns::$superlativeKeywords as $pattern) {
      if (str_contains($query, $pattern)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Check if query contains an entity keyword
   * 
   * @param string $query The query to check
   * @return bool True if entity keyword found
   */
  public static function hasEntityKeyword(string $query): bool
  {
    $query = strtolower($query);
    
    foreach (SuperlativePatterns::$entityKeywords as $entity) {
      if (strpos($query, $entity) !== false) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Check if query is a superlative query
   * 
   * A superlative query has:
   * 1. A superlative pattern (most expensive, cheapest, etc.)
   * 2. An entity keyword (product, order, customer, etc.)
   * 
   * ⚠️ ENGLISH ONLY: This pattern is called AFTER translation to English
   * 
   * Examples (all in English after translation):
   * - "What is the most expensive product" ✅ (translated from "Quel est le produit le plus cher")
   * - "What is the cheapest product" ✅
   * - "What is the best-selling product" ✅ (translated from "Quel est le produit le plus vendu")
   * - "What is the highest priced item" ✅
   * - "What is the price" ❌ (no superlative)
   * - "Most expensive" ❌ (no entity - ambiguous)
   * 
   * @param string $query The query to check (must be in English)
   * @return bool True if superlative query
   */
  public static function isSuperlativeQuery(string $query): bool
  {
    return self::hasSuperlativePattern($query) && self::hasEntityKeyword($query);
  }
  
  /**
   * Get detected superlative patterns in query
   * 
   * @param string $query The query to check
   * @return array List of detected superlative patterns
   */
  public static function getDetectedSuperlativePatterns(string $query): array
  {
    $query = strtolower($query);
    $detected = [];
    
    foreach (SuperlativePatterns::$superlativeKeywords as $pattern) {
      if (str_contains($query, $pattern)) {
        $detected[] = $pattern;
      }
    }
    
    return $detected;
  }
  
  /**
   * Get detected entity keywords in query
   * 
   * @param string $query The query to check
   * @return array List of detected entity keywords
   */
  public static function getDetectedEntityKeywords(string $query): array
  {
    $query = strtolower($query);
    $detected = [];
    
    foreach (SuperlativePatterns::$entityKeywords as $entity) {
      if (strpos($query, $entity) !== false) {
        $detected[] = $entity;
      }
    }
    
    return $detected;
  }
  
  /**
   * Post-filter LLM analysis to override incorrect classifications
   * 
   * ⚠️ ENGLISH ONLY: This pattern is called AFTER translation to English
   * 
   * This method is called AFTER LLM classification to correct errors:
   * 1. If pattern detects superlative query AND LLM intent_type = "semantic"
   *    → Override to analytics with confidence 1.0
   * 2. If pattern detects superlative query AND LLM confidence < 0.9
   *    → Override to analytics with confidence 1.0
   * 
   * WHY THIS EXISTS:
   * - LLM often classifies "What is the most expensive product" as semantic (wrong)
   * - LLM sometimes has low confidence for superlative queries
   * - Pattern provides deterministic override for production consistency
   * 
   * WHEN TO USE:
   * - Call this method AFTER LLM classification
   * - Pass translated query (English) and LLM analysis
   * - Use returned analysis (may be modified)
   * 
   * Examples:
   * - LLM: semantic, confidence 0.9 → Pattern: analytics, confidence 1.0 (override)
   * - LLM: analytics, confidence 0.7 → Pattern: analytics, confidence 1.0 (override)
   * - LLM: analytics, confidence 0.95 → Pattern: no change (LLM correct)
   * - LLM: semantic, no superlative → Pattern: no change (LLM correct)
   * 
   * @param string $translatedQuery The translated query (must be in English)
   * @param array $llmAnalysis The LLM classification result
   * @return array Modified analysis (may be overridden by pattern)
   */
  public static function postFilter(string $translatedQuery, array $llmAnalysis): array
  {
    // Check if pattern detects superlative query
    if (!self::isSuperlativeQuery($translatedQuery)) {
      // Pattern does not detect - return LLM analysis unchanged
      return $llmAnalysis;
    }
    
    // Pattern detected superlative query
    $shouldOverride = false;
    $overrideReason = '';
    
    // Check if LLM classified as semantic (wrong)
    if (isset($llmAnalysis['intent_type']) && $llmAnalysis['intent_type'] === 'semantic') {
      $shouldOverride = true;
      $overrideReason = 'Pattern override: LLM classified superlative query as semantic (incorrect)';
    }
    
    // Check if LLM has low confidence (< 0.9)
    if (isset($llmAnalysis['confidence']) && $llmAnalysis['confidence'] < 0.9) {
      $shouldOverride = true;
      $overrideReason = sprintf(
        'Pattern override: LLM confidence %.2f < 0.9 for superlative query',
        $llmAnalysis['confidence']
      );
    }
    
    // If no override needed, return LLM analysis unchanged
    if (!$shouldOverride) {
      return $llmAnalysis;
    }
    
    // Log override
    $logMessage = sprintf(
      "[SuperlativePostFilter] POST-FILTER OVERRIDE: %s | Query: %s | LLM: %s (%.2f) | Pattern: analytics (1.0)",
      $overrideReason,
      $translatedQuery,
      $llmAnalysis['intent_type'] ?? 'unknown',
      $llmAnalysis['confidence'] ?? 0.0
    );
    
    error_log($logMessage);
    
    // Override LLM analysis
    $overriddenAnalysis = $llmAnalysis;
    $overriddenAnalysis['intent_type'] = 'analytics';
    $overriddenAnalysis['confidence'] = 1.0;
    $overriddenAnalysis['detection_method'] = 'pattern_postfilter_override';
    $overriddenAnalysis['original_llm_intent'] = $llmAnalysis['intent_type'] ?? 'unknown';
    $overriddenAnalysis['original_llm_confidence'] = $llmAnalysis['confidence'] ?? 0.0;
    $overriddenAnalysis['override_reason'] = $overrideReason;
    $overriddenAnalysis['superlative_pattern_detected'] = self::hasSuperlativePattern($translatedQuery);
    $overriddenAnalysis['entity_keyword_detected'] = self::hasEntityKeyword($translatedQuery);
    $overriddenAnalysis['detected_superlative_patterns'] = self::getDetectedSuperlativePatterns($translatedQuery);
    $overriddenAnalysis['detected_entity_keywords'] = self::getDetectedEntityKeywords($translatedQuery);
    
    return $overriddenAnalysis;
  }
}
