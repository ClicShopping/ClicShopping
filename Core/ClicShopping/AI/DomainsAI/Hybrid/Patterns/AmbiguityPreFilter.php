<?php
/**
 * AmbiguityPreFilter.php
 * 
 * ⚠️ EXCEPTION TO PURE LLM APPROACH ⚠️
 * 
 * This pattern-based pre-filter exists ONLY because LLM ambiguity detection
 * is inconsistent (85% success rate). It ensures 100% consistency for temporal
 * expressions and quantitative language.
 * 
 * WHY THIS EXISTS:
 * - LLM correctly identifies "this month" as NOT ambiguous: 85% of the time
 * - LLM correctly identifies "how many" as NOT ambiguous: 85% of the time
 * - Production requires 95%+ consistency
 * - Pattern-based pre-filter provides deterministic results
 * 
 * TEMPORAL AMBIGUITY HANDLING (Updated 2026-01-11):
 * - EXPLICIT temporal ("this month", "by month", "last year") → NOT AMBIGUOUS
 * - IMPLICIT temporal ("monthly", "weekly", "yearly" alone) → AMBIGUOUS (temporal_period_scope)
 *   - Could mean "current period" (this month's data)
 *   - Could mean "breakdown" (data grouped by month)
 * 
 * WHEN TO DELETE THIS FILE:
 * - When LLM ambiguity detection achieves 95%+ consistency
 * - When LLM temperature is reduced to 0.0 and tested
 * - When LLM model is upgraded (e.g., GPT-4o instead of GPT-4o-mini)
 * - Move to Old/ directory when no longer needed
 * 
 * PRINCIPLE:
 * DO NOT create new patterns unless LLM cannot do the job consistently (95%+)
 * 
 * ⚠️ IMPORTANT - POST-TRANSLATION PATTERN:
 * This pattern is called AFTER translation to English. All queries are
 * translated before reaching this pattern, so only English patterns are needed.
 * Multilingual patterns were removed in optimization (2025-12-23).
 * 
 * ARCHITECTURE:
 * User Query (any language) → Translation → English Query → AmbiguityPreFilter
 * 
 * @package ClicShopping\AI\DomainsAI\Hybrid\Patterns
 * @date 2025-12-21
 * @updated 2025-12-23 (Simplified: removed multilingual patterns)
 * @updated 2026-01-11 (Added temporal_period_scope ambiguity for implicit temporal expressions)
 * @updated 2026-01-22 (Migrated from Domain/Patterns/Hybrid to DomainsAI/Hybrid/Patterns)
 * @author ClicShopping Team
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

namespace ClicShopping\AI\DomainsAI\Hybrid\Patterns;

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class AmbiguityPreFilter
{
  /**
   * Check if query contains EXPLICIT temporal expressions (NOT ambiguous)
   * 
   * ⚠️ IMPORTANT: This method is called AFTER translation to English.
   * All queries are translated before reaching this pattern, so only
   * English patterns are needed.
   * 
   * EXPLICIT temporal expressions are CLEAR time specifications:
   * - "this month", "this year", "today", "yesterday" → NOT AMBIGUOUS
   * - "last month", "last year", "last week" → NOT AMBIGUOUS
   * - "by month", "by year", "by week" → NOT AMBIGUOUS (explicit GROUP BY)
   * 
   * IMPLICIT temporal expressions are AMBIGUOUS (temporal_period_scope):
   * - "monthly", "yearly", "weekly" WITHOUT explicit time reference
   *   → Could mean "current period" OR "breakdown by period"
   * 
   * @param string $query The query to check (already translated to English)
   * @return bool True if EXPLICIT temporal expression found (NOT ambiguous)
   */
  public static function hasTemporalExpression(string $query): bool
  {
    $query = strtolower($query);
    
    // English EXPLICIT temporal expressions (post-translation)
    // These are NOT ambiguous because they specify a clear time reference
    $explicitTemporalPatterns = [
      // "this X" patterns - explicit current period
      '/\bthis\s+(month|year|week|quarter|day)\b/i',
      
      // "last X" patterns - explicit past period
      '/\blast\s+(month|year|week|quarter|day|\d+\s+days?)\b/i',
      
      // "next X" patterns - explicit future period
      '/\bnext\s+(month|year|week|quarter|day)\b/i',
      
      // Single word temporal - explicit time reference
      '/\b(today|yesterday|tomorrow|tonight)\b/i',
      
      // "by X" patterns - explicit GROUP BY intent
      '/\bby\s+(month|year|week|quarter|day|hour)\b/i',
      
      // "per X" patterns - explicit GROUP BY intent
      '/\bper\s+(month|year|week|quarter|day|hour)\b/i',
      
      // "for X" patterns with specific period - explicit time range
      '/\bfor\s+(this|last|next)\s+(month|year|week|quarter)\b/i',
      
      // Date ranges - explicit time specification
      '/\bfrom\s+\w+\s+to\s+\w+\b/i',
      '/\bbetween\s+\w+\s+and\s+\w+\b/i',
      
      // Specific date patterns
      '/\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{4}\b/i',
      '/\bq[1-4]\s+\d{4}\b/i',
      '/\b\d{4}\b/i', // Year alone (e.g., "2024")
    ];
    
    foreach ($explicitTemporalPatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Check if query contains IMPLICIT temporal expressions (AMBIGUOUS)
   * 
   * IMPLICIT temporal expressions like "monthly", "weekly", "yearly" WITHOUT
   * explicit time reference are AMBIGUOUS because they could mean:
   * - "current_period": Show data for the current month/week/year
   * - "breakdown": Show data broken down by month/week/year
   * 
   * This is the "temporal_period_scope" ambiguity type.
   * 
   * @param string $query The query to check (already translated to English)
   * @return bool True if IMPLICIT temporal expression found (AMBIGUOUS)
   */
  public static function hasImplicitTemporalExpression(string $query): bool
  {
    $query = strtolower($query);
    
    // First check if there's an EXPLICIT temporal expression
    // If yes, the implicit one is clarified by context
    if (self::hasTemporalExpression($query)) {
      return false;
    }
    
    // IMPLICIT temporal expressions (frequency adverbs without context)
    // These are AMBIGUOUS because they don't specify a clear time reference
    $implicitTemporalPatterns = [
      // Frequency adverbs alone - AMBIGUOUS
      '/\b(monthly|yearly|weekly|daily|quarterly|annually)\b/i',
    ];
    
    foreach ($implicitTemporalPatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Check if query contains quantitative language (NOT ambiguous)
   * 
   * ⚠️ IMPORTANT: This method is called AFTER translation to English.
   * All queries are translated before reaching this pattern, so only
   * English patterns are needed.
   * 
   * Quantitative language indicates clear aggregation intent:
   * - Counting: "how many", "number of", "count of"
   * - Summing: "total", "sum of", "total amount"
   * - Averaging: "average", "mean"
   * - Extremes: "minimum", "maximum", "highest", "lowest"
   * 
   * @param string $query The query to check (already translated to English)
   * @return bool True if quantitative language found (NOT ambiguous)
   */
  public static function hasQuantitativeLanguage(string $query): bool
  {
    $query = strtolower($query);
    
    // English quantitative patterns (post-translation)
    $quantitativePatterns = [
      // Counting
      '/\bhow\s+many\b/i',
      '/\bnumber\s+of\b/i',
      '/\bcount\s+of\b/i',
      '/\btotal\s+number\b/i',
      
      // Summing
      '/\btotal\b/i',
      '/\bsum\s+of\b/i',
      '/\btotal\s+amount\b/i',
      '/\bcombined\b/i',
      
      // Averaging
      '/\baverage\b/i',
      '/\bmean\b/i',
      '/\baverage\s+of\b/i',
      
      // Extremes
      '/\b(minimum|maximum|highest|lowest|least|most|smallest|largest)\b/i',
    ];
    
    foreach ($quantitativePatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Pre-filter query for ambiguity before LLM call
   * 
   * This method provides fast, deterministic ambiguity detection for
   * common cases where LLM is inconsistent.
   * 
   * Returns NULL if pre-filter cannot determine (use LLM)
   * Returns array if pre-filter can determine (skip LLM)
   * 
   * TEMPORAL AMBIGUITY HANDLING:
   * - EXPLICIT temporal ("this month", "by month") → NOT AMBIGUOUS
   * - IMPLICIT temporal ("monthly" alone) → AMBIGUOUS (temporal_period_scope)
   * 
   * @param string $query The query to check
   * @return array|null Ambiguity result or null if LLM needed
   */
  public static function preFilter(string $query): ?array
  {
    // Check for IMPLICIT temporal expressions FIRST (these are AMBIGUOUS)
    // "monthly", "weekly", "yearly" without explicit time reference
    if (self::hasImplicitTemporalExpression($query)) {
      return [
        'is_ambiguous' => true,
        'ambiguity_type' => 'temporal_period_scope',
        'confidence' => 0.95,
        'interpretations' => [
          [
            'type' => 'current_period',
            'label' => 'Current period data',
            'description' => 'Show data for the current period (this month/week/year)',
            'sql_hint' => 'Filter by current period date range'
          ],
          [
            'type' => 'breakdown',
            'label' => 'Breakdown by period',
            'description' => 'Show data broken down by period (GROUP BY month/week/year)',
            'sql_hint' => 'GROUP BY period with aggregation'
          ]
        ],
        'recommendation' => 'generate_both',
        'default_interpretation' => 'breakdown',
        'reasoning' => 'Implicit temporal expression detected (e.g., "monthly" without explicit time reference). Could mean current period OR breakdown by period.',
        'detection_method' => 'pattern_prefilter'
      ];
    }
    
    // Check for EXPLICIT temporal expressions (these are NOT ambiguous)
    if (self::hasTemporalExpression($query)) {
      return [
        'is_ambiguous' => false,
        'ambiguity_type' => null,
        'confidence' => 1.0,
        'interpretations' => [],
        'recommendation' => 'use_default',
        'default_interpretation' => null,
        'reasoning' => 'Explicit temporal expression detected (pattern-based pre-filter)',
        'detection_method' => 'pattern_prefilter'
      ];
    }
    
    // Check for quantitative language
    if (self::hasQuantitativeLanguage($query)) {
      return [
        'is_ambiguous' => false,
        'ambiguity_type' => null,
        'confidence' => 1.0,
        'interpretations' => [],
        'recommendation' => 'use_default',
        'default_interpretation' => null,
        'reasoning' => 'Clear quantitative language detected (pattern-based pre-filter)',
        'detection_method' => 'pattern_prefilter'
      ];
    }
    
    // Cannot determine - use LLM
    return null;
  }
}
