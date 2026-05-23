<?php
/**
 * PatternAnalysisPattern
 * 
 * Pattern detection for pattern analysis and trend identification queries
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

namespace ClicShopping\AI\DomainsAI\Semantic\Patterns;



/**
 * PatternAnalysisPattern Class
 * 
 * Provides regex patterns for detecting queries related to:
 * - Pattern analysis
 * - Trend identification
 * - Dominant motifs
 * - Recurring themes
 * - Market trends
 * 
 * USAGE:
 * This class should be used by planners and analyzers to detect
 * pattern analysis intent in user queries.
 * 
 * NOTE: According to Pure LLM Mode guidelines, these patterns are
 * for FUTURE USE only. Current implementation should use LLM-based
 * detection instead of pattern matching.
 * 
 * @moved 2026-01-09 from Domain\Patterns to Domain\Patterns\Semantic
 * @moved 2026-01-22 from Domain\Patterns\Semantic to DomainsAI\Semantic\Patterns
 */

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class PatternAnalysisPattern
{
  /**
   * Get all pattern analysis detection patterns
   * 
   * @return array Array of regex patterns for pattern analysis detection
   */
  public static function getPatterns(): array
  {
    return [
      // Basic pattern/trend keywords
      '/\b(patterns?|trends?|dominant|trending)\b/i',
      
      // "What are the patterns/styles" queries
      '/\b(what\s+.*\s+(patterns?|styles?))\b/i',
      
      // "Analyze patterns/trends" queries
      '/\b(analyze?\s+.*\s+(patterns?|trends?))\b/i',
      
      // Explicit pattern/trend analysis
      '/\b(pattern\s+analysis|trend\s+analysis)\b/i',
      
      // Dominant patterns/trends
      '/\b(dominant\s+(patterns?|trends?))\b/i',
      
      // Recurring patterns/themes
      '/\b(recurring\s+(patterns?|themes?))\b/i',
      
      // Market trends/patterns
      '/\b(market\s+(trends?|patterns?))\b/i',
    ];
  }
  
  /**
   * Check if a query matches pattern analysis patterns
   * 
   * @param string $query Query to check
   * @return bool True if query matches pattern analysis patterns
   */
  public static function matches(string $query): bool
  {
    $patterns = self::getPatterns();
    
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $query)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Get pattern analysis metadata
   * 
   * @return array Metadata about pattern analysis patterns
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'Pattern Analysis Pattern',
      'description' => 'Detects queries related to pattern analysis, trends, and dominant motifs',
      'pattern_count' => \count(self::getPatterns()),
      'categories' => [
        'pattern_detection',
        'trend_analysis',
        'dominant_motifs',
        'recurring_themes',
        'market_trends'
      ],
      'use_case' => 'Task planning for pattern analysis queries',
      'note' => 'For FUTURE USE - Pure LLM Mode recommends LLM-based detection'
    ];
  }
}
