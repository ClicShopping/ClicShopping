<?php
/**
 * MultiTemporalPostFilter.php
 * 
 * ⚠️ EXCEPTION TO PURE LLM APPROACH ⚠️
 * 
 * This pattern-based post-filter exists ONLY because LLM classification
 * is inconsistent for multi-temporal queries (classifies as analytics instead of hybrid).
 * It ensures 100% consistency for queries with multiple temporal aggregations.
 * 
 * WHY THIS EXISTS:
 * - LLM correctly classifies "revenue by month then by semester" as hybrid: ~5% of the time
 * - LLM almost always classifies multi-temporal queries as "analytics" (wrong)
 * - Production requires 95%+ consistency
 * - Pattern-based post-filter provides deterministic results
 * 
 * WHEN TO DELETE THIS FILE:
 * - When LLM classification achieves 95%+ consistency for multi-temporal queries
 * - When LLM model is upgraded and tested
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
 * @date 2026-01-08
 * @author ClicShopping Team
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class MultiTemporalPostFilter
{
  /**
   * Temporal period keywords (English-only)
   * These are the aggregation periods that can be combined
   */
  private static array $temporalPeriods = [
    'month', 'months', 'monthly',
    'quarter', 'quarters', 'quarterly',
    'semester', 'semesters', 'half-year', 'half year',
    'year', 'years', 'yearly', 'annual', 'annually',
    'week', 'weeks', 'weekly',
    'day', 'days', 'daily',
  ];
  
  /**
   * Temporal connectors (English-only)
   * These words indicate multiple temporal requests
   */
  private static array $temporalConnectors = [
    'then', 'and then', 'after that', 'followed by', 'next',
    'and', 'also', 'as well as', 'plus',
  ];
  
  /**
   * Financial metrics (English-only)
   * These indicate financial aggregation queries
   */
  private static array $financialMetrics = [
    'revenue', 'sales', 'turnover', 'profit', 'margin', 'income', 'earnings',
    'total sales', 'gross sales', 'net sales',
    'total revenue', 'gross revenue', 'net revenue',
  ];
  
  /**
   * Count unique temporal periods in query
   * 
   * @param string $query The query to check (must be in English)
   * @return array List of unique temporal periods found
   */
  public static function getDetectedTemporalPeriods(string $query): array
  {
    $query = strtolower($query);
    $detected = [];
    
    // Define base periods to detect
    $basePeriods = [
      'month' => ['month', 'months', 'monthly'],
      'quarter' => ['quarter', 'quarters', 'quarterly'],
      'semester' => ['semester', 'semesters', 'half-year', 'half year', 'semestre'],
      'year' => ['year', 'years', 'yearly', 'annual', 'annually'],
      'week' => ['week', 'weeks', 'weekly'],
      'day' => ['day', 'days', 'daily'],
    ];
    
    // First pass: Check for "by {period}" or "per {period}" patterns
    foreach ($basePeriods as $basePeriod => $variants) {
      foreach ($variants as $variant) {
        // Check for "by {period}" pattern
        if (preg_match('/\bby\s+' . preg_quote($variant, '/') . '\b/i', $query)) {
          if (!in_array($basePeriod, $detected, true)) {
            $detected[] = $basePeriod;
          }
          break;
        }
        // Check for "per {period}" pattern
        if (preg_match('/\bper\s+' . preg_quote($variant, '/') . '\b/i', $query)) {
          if (!in_array($basePeriod, $detected, true)) {
            $detected[] = $basePeriod;
          }
          break;
        }
      }
    }
    
    // Second pass: If we found less than 2 periods, try more aggressive detection
    // This handles cases where LLM translates differently
    if (count($detected) < 2) {
      $detected = []; // Reset
      
      foreach ($basePeriods as $basePeriod => $variants) {
        foreach ($variants as $variant) {
          // Check if the period appears anywhere in the query
          if (preg_match('/\b' . preg_quote($variant, '/') . '\b/i', $query)) {
            if (!in_array($basePeriod, $detected, true)) {
              $detected[] = $basePeriod;
            }
            break;
          }
        }
      }
    }
    
    return $detected;
  }
  
  /**
   * Check if query contains temporal connectors
   * 
   * @param string $query The query to check
   * @return bool True if temporal connector found
   */
  public static function hasTemporalConnector(string $query): bool
  {
    $query = strtolower($query);
    
    foreach (self::$temporalConnectors as $connector) {
      if (str_contains($query, $connector)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Get detected temporal connectors in query
   * 
   * @param string $query The query to check
   * @return array List of detected temporal connectors
   */
  public static function getDetectedTemporalConnectors(string $query): array
  {
    $query = strtolower($query);
    $detected = [];
    
    foreach (self::$temporalConnectors as $connector) {
      if (str_contains($query, $connector)) {
        $detected[] = $connector;
      }
    }
    
    return $detected;
  }
  
  /**
   * Check if query contains a financial metric
   * 
   * @param string $query The query to check
   * @return bool True if financial metric found
   */
  public static function hasFinancialMetric(string $query): bool
  {
    $query = strtolower($query);
    
    foreach (self::$financialMetrics as $metric) {
      if (str_contains($query, $metric)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Check if query is a multi-temporal query
   * 
   * A multi-temporal query has:
   * 1. A financial metric (revenue, sales, etc.)
   * 2. Multiple temporal periods (2+) (month, quarter, semester, etc.)
   * 3. Temporal connectors (then, and, etc.)
   * 
   * ⚠️ ENGLISH ONLY: This pattern is called AFTER translation to English
   * 
   * Examples (all in English after translation):
   * - "revenue by month then by semester" ✅ (2 periods: month, semester)
   * - "sales by week and by quarter" ✅ (2 periods: week, quarter)
   * - "profit by day then by month then by year" ✅ (3 periods: day, month, year)
   * - "revenue by month" ❌ (only 1 period)
   * - "sales this quarter" ❌ (only 1 period)
   * 
   * @param string $query The query to check (must be in English)
   * @return bool True if multi-temporal query
   */
  public static function isMultiTemporalQuery(string $query): bool
  {
    // Must have financial metric
    if (!self::hasFinancialMetric($query)) {
      return false;
    }
    
    // Must have 2+ temporal periods
    $periods = self::getDetectedTemporalPeriods($query);
    if (count($periods) < 2) {
      return false;
    }
    
    // Must have temporal connector
    if (!self::hasTemporalConnector($query)) {
      return false;
    }
    
    return true;
  }
  
  /**
   * Post-filter LLM analysis to override incorrect classifications
   * 
   * ⚠️ ENGLISH ONLY: This pattern is called AFTER translation to English
   * 
   * This method is called AFTER LLM classification to correct errors:
   * 1. If pattern detects multi-temporal query AND LLM intent_type != "hybrid"
   *    → Override to hybrid with confidence 0.95
   * 
   * WHY THIS EXISTS:
   * - LLM almost always classifies "revenue by month then by semester" as analytics (wrong)
   * - Pattern provides deterministic override for production consistency
   * 
   * WHEN TO USE:
   * - Call this method AFTER LLM classification
   * - Pass translated query (English) and LLM analysis
   * - Use returned analysis (may be modified)
   * 
   * Examples:
   * - LLM: analytics, confidence 1.0 → Pattern: hybrid, confidence 0.95 (override)
   * - LLM: hybrid, confidence 0.9 → Pattern: no change (LLM correct)
   * - LLM: analytics, no multi-temporal → Pattern: no change (LLM correct)
   * 
   * @param string $translatedQuery The translated query (must be in English)
   * @param array $llmAnalysis The LLM classification result
   * @return array Modified analysis (may be overridden by pattern)
   */
  public static function postFilter(string $translatedQuery, array $llmAnalysis): array
  {
    // Check if pattern detects multi-temporal query
    if (!self::isMultiTemporalQuery($translatedQuery)) {
      // Pattern does not detect - return LLM analysis unchanged
      return $llmAnalysis;
    }
    
    // Pattern detected multi-temporal query
    $shouldOverride = false;
    $overrideReason = '';
    
    // Check if LLM classified as anything other than hybrid
    if (!isset($llmAnalysis['intent_type']) || $llmAnalysis['intent_type'] !== 'hybrid') {
      $shouldOverride = true;
      $overrideReason = sprintf(
        'Pattern override: LLM classified multi-temporal query as %s (should be hybrid)',
        $llmAnalysis['intent_type'] ?? 'unknown'
      );
    }
    
    // If no override needed, return LLM analysis unchanged
    if (!$shouldOverride) {
      return $llmAnalysis;
    }
    
    // Get detected periods and connectors for logging
    $detectedPeriods = self::getDetectedTemporalPeriods($translatedQuery);
    $detectedConnectors = self::getDetectedTemporalConnectors($translatedQuery);
    
    // Log override
    $logMessage = sprintf(
      "[MultiTemporalPostFilter] POST-FILTER OVERRIDE: %s | Query: %s | LLM: %s (%.2f) | Pattern: hybrid (0.95) | Periods: %s | Connectors: %s",
      $overrideReason,
      $translatedQuery,
      $llmAnalysis['intent_type'] ?? 'unknown',
      $llmAnalysis['confidence'] ?? 0.0,
      implode(', ', $detectedPeriods),
      implode(', ', $detectedConnectors)
    );
    
    error_log($logMessage);
    
    // Override LLM analysis
    $overriddenAnalysis = $llmAnalysis;
    $overriddenAnalysis['intent_type'] = 'hybrid';
    $overriddenAnalysis['confidence'] = 0.95;
    $overriddenAnalysis['detection_method'] = 'pattern_postfilter_multi_temporal';
    $overriddenAnalysis['original_llm_intent'] = $llmAnalysis['intent_type'] ?? 'unknown';
    $overriddenAnalysis['original_llm_confidence'] = $llmAnalysis['confidence'] ?? 0.0;
    $overriddenAnalysis['override_reason'] = $overrideReason;
    $overriddenAnalysis['is_multi_temporal'] = true;
    $overriddenAnalysis['temporal_periods'] = $detectedPeriods;
    $overriddenAnalysis['temporal_connectors'] = $detectedConnectors;
    $overriddenAnalysis['temporal_period_count'] = count($detectedPeriods);
    
    return $overriddenAnalysis;
  }
}
