<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns;

/**
 * AnalyticsPatterns
 *
 * Ecommerce-specific analytics patterns for financial metrics and temporal queries.
 * Consolidated from three deprecated framework files:
 * - FinancialMetricsPattern.php
 * - TemporalFinancialPatterns.php
 * - TemporalFinancialPreFilter.php
 *
 * This class provides:
 * 1. Financial metrics detection (revenue, sales, profit, etc.)
 * 2. Temporal period detection (month, quarter, year, etc.)
 * 3. Pre-filter and post-filter logic for temporal financial queries
 *
 * ⚠️ EXCEPTION TO PURE LLM APPROACH ⚠️
 * 
 * This pattern-based logic exists ONLY because LLM classification
 * is inconsistent for temporal financial queries (hallucinations, wrong intent).
 * It ensures 100% consistency for "financial metric + time period" queries.
 * 
 * WHY THIS EXISTS:
 * - LLM correctly classifies "Revenue of the last quarter" as analytics: 60% of the time
 * - LLM sometimes hallucinates translation or classifies as "semantic" instead of "analytics"
 * - Production requires 95%+ consistency
 * - Pattern-based pre-filter provides deterministic results
 * 
 * WHEN TO DELETE THIS CLASS:
 * - When LLM classification achieves 95%+ consistency for temporal financial queries
 * - When LLM temperature is reduced to 0.0 and tested
 * - When LLM model is upgraded (e.g., GPT-4o instead of gpt-4.1-mini)
 * 
 * ⚠️ ENGLISH ONLY (Post-Translation):
 * - All patterns are called AFTER translation to English
 * - All keywords are English-only (no French/Spanish/German)
 * - Multilingual queries are translated before patterns are applied
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns
 * @since 2026-01-21
 * @author ClicShopping Team
 */
class AnalyticsPatterns
{
  // ============================================================================
  // SECTION 1: FINANCIAL METRICS PATTERNS
  // From: FinancialMetricsPattern.php
  // ============================================================================

  /**
   * Get financial metrics patterns
   *
   * Returns an ordered array of financial metric patterns.
   * Order matters - more specific patterns should come first.
   *
   * @return array Associative array of pattern => metric
   */
  public static function getMetricsPatterns(): array
  {
    return [
      'total revenue' => 'revenue',
      'gross revenue' => 'revenue',
      'net revenue' => 'revenue',
      'total sales' => 'sales',
      'gross sales' => 'sales',
      'net sales' => 'sales',
      'revenue' => 'revenue',
      'sales' => 'sales',
      'turnover' => 'turnover',
      'profit' => 'profit',
      'margin' => 'margin',
      'income' => 'income',
      'earnings' => 'earnings',
      'expenses' => 'expenses',
      'costs' => 'costs',
      'orders' => 'orders',
      'order count' => 'orders',
      'order total' => 'orders',
    ];
  }

  /**
   * Extract base metric from query
   *
   * Identifies the financial metric being queried (revenue, sales, profit, etc.)
   *
   * @param string $query The query (should be in English and lowercase)
   * @return string|null The base metric or null if not found
   */
  public static function extractBaseMetric(string $query): ?string
  {
    $query = strtolower($query);
    $metrics = self::getMetricsPatterns();
    
    foreach ($metrics as $pattern => $metric) {
      if (str_contains($query, $pattern)) {
        return $metric;
      }
    }
    
    return null;
  }

  /**
   * Get all unique metric types
   *
   * @return array List of unique metric types
   */
  public static function getMetricTypes(): array
  {
    return array_values(array_unique(self::getMetricsPatterns()));
  }

  /**
   * Check if query contains a financial metric
   *
   * @param string $query The query to check
   * @return bool True if a financial metric is detected
   */
  public static function hasFinancialMetric(string $query): bool
  {
    return self::extractBaseMetric($query) !== null;
  }

  /**
   * Get metadata about financial metrics
   *
   * @return array Metadata about the pattern
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'AnalyticsPatterns',
      'description' => 'Ecommerce-specific analytics patterns for financial metrics and temporal queries',
      'domain' => 'Ecommerce',
      'metrics_count' => count(self::getMetricsPatterns()),
      'unique_metrics' => count(self::getMetricTypes()),
    ];
  }

  // ============================================================================
  // SECTION 2: TEMPORAL FINANCIAL PATTERNS
  // From: TemporalFinancialPatterns.php
  // ============================================================================

  /**
   * Financial metric keywords (English-only)
   * 
   * These terms indicate financial/sales data queries:
   * - revenue, sales, turnover, profit, margin, income, earnings
   */
  public static array $financialMetrics = [
    // Core financial metrics
    'revenue', 'sales', 'turnover', 'profit', 'margin', 'income', 'earnings',
    
    // Variations
    'total sales', 'gross sales', 'net sales', 
    'total revenue', 'gross revenue', 'net revenue',
    'total income', 'gross income', 'net income',
  ];
  
  /**
   * Time period keywords (English-only)
   * 
   * These terms indicate temporal filtering:
   * - month, quarter, year, week, day, last, this, current
   */
  public static array $timePeriods = [
    // Time units
    'month', 'months', 'quarter', 'quarters', 'year', 'years', 
    'week', 'weeks', 'day', 'days',
    
    // Time modifiers
    'last', 'this', 'current', 'today', 'yesterday',
    
    // Common phrases
    'last month', 'this month', 'current month',
    'last quarter', 'this quarter', 'current quarter',
    'last year', 'this year', 'current year',
    'last week', 'this week', 'current week',
  ];

  // ============================================================================
  // SECTION 3: PRE-FILTER AND POST-FILTER LOGIC
  // From: TemporalFinancialPreFilter.php
  // ============================================================================

  /**
   * Check if query contains a time period
   * 
   * @param string $query The query to check
   * @return bool True if time period found
   */
  public static function hasTimePeriod(string $query): bool
  {
    $query = strtolower($query);
    
    foreach (self::$timePeriods as $period) {
      if (strpos($query, $period) !== false) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Check if query is a temporal financial query
   * 
   * A temporal financial query has BOTH:
   * 1. A financial metric (revenue, sales, etc.)
   * 2. A time period (month, quarter, year, etc.)
   * 
   * ⚠️ ENGLISH ONLY: This pattern is called AFTER translation to English
   * 
   * Examples (all in English after translation):
   * - "Revenue of the last quarter" ✅ (translated from "Chiffre d'affaires du dernier trimestre")
   * - "Revenue this month" ✅
   * - "Sales of the quarter" ✅ (translated from "Umsatz des Quartals")
   * - "Income of the month" ✅ (translated from "Ingresos del mes")
   * - "What is the price" ❌ (no time period)
   * - "Orders this month" ❌ (no financial metric)
   * 
   * @param string $query The query to check (must be in English)
   * @return bool True if temporal financial query
   */
  public static function isTemporalFinancialQuery(string $query): bool
  {
    return self::hasFinancialMetric($query) && self::hasTimePeriod($query);
  }
  
  /**
   * Pre-filter query for intent classification before LLM call
   * 
   * ⚠️ ENGLISH ONLY: This pattern is called AFTER translation to English
   * 
   * This method provides fast, deterministic intent classification for
   * temporal financial queries where LLM is inconsistent.
   * 
   * Returns NULL if pre-filter cannot determine (use LLM)
   * Returns array if pre-filter can determine (skip LLM)
   * 
   * @param string $query The query to check (must be in English)
   * @return array|null Classification result or null if LLM needed
   */
  public static function preFilter(string $query): ?array
  {
    // Check for temporal financial query
    if (self::isTemporalFinancialQuery($query)) {
      return [
        'intent_type' => 'analytics',
        'confidence' => 1.0,
        'reasoning' => 'Temporal financial query detected: financial metric + time period (pattern-based pre-filter)',
        'detection_method' => 'pattern_prefilter_temporal_financial',
        'financial_metric_detected' => self::hasFinancialMetric($query),
        'time_period_detected' => self::hasTimePeriod($query),
      ];
    }
    
    // Cannot determine - use LLM
    return null;
  }
  
  /**
   * Get detected financial metrics in query
   * 
   * @param string $query The query to check
   * @return array List of detected financial metrics
   */
  public static function getDetectedFinancialMetrics(string $query): array
  {
    $query = strtolower($query);
    $detected = [];
    
    foreach (self::$financialMetrics as $metric) {
      if (strpos($query, $metric) !== false) {
        $detected[] = $metric;
      }
    }
    
    return $detected;
  }
  
  /**
   * Get detected time periods in query
   * 
   * @param string $query The query to check
   * @return array List of detected time periods
   */
  public static function getDetectedTimePeriods(string $query): array
  {
    $query = strtolower($query);
    $detected = [];
    
    foreach (self::$timePeriods as $period) {
      if (strpos($query, $period) !== false) {
        $detected[] = $period;
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
   * 1. If pattern detects temporal financial query AND LLM confidence < 0.9
   *    → Override to analytics with confidence 1.0
   * 2. If pattern detects temporal financial query AND LLM intent_type = "semantic"
   *    → Override to analytics with confidence 1.0
   * 
   * WHY THIS EXISTS:
   * - LLM sometimes classifies "Revenue of the last quarter" as semantic (wrong)
   * - LLM sometimes has low confidence for temporal financial queries
   * - Pattern provides deterministic override for production consistency
   * 
   * WHEN TO USE:
   * - Call this method AFTER LLM classification
   * - Pass translated query (English) and LLM analysis
   * - Use returned analysis (may be modified)
   * 
   * Examples:
   * - LLM: semantic, confidence 0.8 → Pattern: analytics, confidence 1.0 (override)
   * - LLM: analytics, confidence 0.7 → Pattern: analytics, confidence 1.0 (override)
   * - LLM: analytics, confidence 0.95 → Pattern: no change (LLM correct)
   * - LLM: semantic, no temporal financial → Pattern: no change (LLM correct)
   * - LLM: hybrid, any confidence → Pattern: no change (PRESERVE hybrid for compound queries)
   * 
   * ⚠️ CRITICAL (2026-01-11): NEVER override hybrid classification!
   * Hybrid queries contain multiple distinct parts (e.g., "pending orders AND revenue")
   * that must be processed separately by HybridQueryProcessor.
   * 
   * @param string $translatedQuery The translated query (must be in English)
   * @param array $llmAnalysis The LLM classification result
   * @return array Modified analysis (may be overridden by pattern)
   */
  public static function postFilter(string $translatedQuery, array $llmAnalysis): array
  {
    // Check if pattern detects temporal financial query
    if (!self::isTemporalFinancialQuery($translatedQuery)) {
      // Pattern does not detect - return LLM analysis unchanged
      return $llmAnalysis;
    }
    
    // CRITICAL FIX (2026-01-11): NEVER override hybrid classification
    // If LLM classified as hybrid, it means the query has multiple distinct parts
    // (e.g., "pending orders AND monthly revenue" = 2 separate analytics queries)
    // Overriding to analytics would break compound query handling
    
    if (isset($llmAnalysis['intent_type']) && $llmAnalysis['intent_type'] === 'hybrid') {
      error_log("[AnalyticsPatterns] PRESERVING hybrid classification for compound query: {$translatedQuery}");
      return $llmAnalysis;
    }
    
    // Pattern detected temporal financial query
    $shouldOverride = false;
    $overrideReason = '';
    
    // Check if LLM has low confidence (< 0.9)
    if (isset($llmAnalysis['confidence']) && $llmAnalysis['confidence'] < 0.9) {
      $shouldOverride = true;
      $overrideReason = sprintf(
        'Pattern override: LLM confidence %.2f < 0.9 for temporal financial query',
        $llmAnalysis['confidence']
      );
    }
    
    // Check if LLM classified as semantic (wrong)
    if (isset($llmAnalysis['intent_type']) && $llmAnalysis['intent_type'] === 'semantic') {
      $shouldOverride = true;
      $overrideReason = 'Pattern override: LLM classified temporal financial query as semantic (incorrect)';
    }
    
    // If no override needed, return LLM analysis unchanged
    if (!$shouldOverride) {
      return $llmAnalysis;
    }
    
    // Log override
    $logMessage = sprintf(
      "[AnalyticsPatterns] POST-FILTER OVERRIDE: %s | Query: %s | LLM: %s (%.2f) | Pattern: analytics (1.0)",
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
    $overriddenAnalysis['financial_metric_detected'] = self::hasFinancialMetric($translatedQuery);
    $overriddenAnalysis['time_period_detected'] = self::hasTimePeriod($translatedQuery);
    $overriddenAnalysis['detected_financial_metrics'] = self::getDetectedFinancialMetrics($translatedQuery);
    $overriddenAnalysis['detected_time_periods'] = self::getDetectedTimePeriods($translatedQuery);
    
    return $overriddenAnalysis;
  }
}
