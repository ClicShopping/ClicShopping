<?php
/**
 * Price Comparison Detection Patterns
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * IMPORTANT: This class is a FALLBACK ONLY for when LLM detection fails.
 * 
 * Architecture Principle: Pure LLM Mode (AGENTS.md)
 * - PRIMARY: LLM detection via intent_type
 * - FALLBACK: Pattern matching (this class) when LLM fails
 * 
 * @deprecated Fallback only - LLM detection is preferred
 * @see AGENTS.md - "Pure LLM Mode" section
 */

namespace ClicShopping\AI\CoreAI\Planning\Patterns;

/**
 * Pattern-based price comparison detection
 * 
 * Used as fallback when LLM intent detection fails or returns invalid results.
 * 
 * Detection Logic:
 * 1. Check for price keywords (prix, price, cost)
 * 2. Check for competitor keywords (concurrent, competitor, compare)
 * 3. Both must be present for positive detection
 * 
 * @deprecated Fallback only - scheduled for removal when LLM reliability reaches 99.9%
 */
class PriceComparisonDetectionPatterns
{
  /**
   * Detect if query is a price comparison query using pattern matching
   * 
   * FALLBACK ONLY: This method should only be called when LLM detection fails.
   * 
   * 🆕 NEW (2026-05-07): Added detection for "on [site]" pattern
   * Queries like "price on amazon" are now detected as price comparison
   * 
   * @param string $query Query to analyze (any language)
   * @return bool True if price comparison detected, false otherwise
   */
  public static function isPriceComparisonQuery(string $query): bool
  {
    $queryLower = mb_strtolower($query, 'UTF-8');
    
    // Check for price keywords (multilingual)
    $hasPriceKeyword = (
      mb_strpos($queryLower, 'price') !== false ||
      mb_strpos($queryLower, 'cost') !== false
    );
    
    // Check for competitor/comparison keywords (multilingual)
    $hasCompetitorKeyword = (
      mb_strpos($queryLower, 'concurrent') !== false ||
      mb_strpos($queryLower, 'competitor') !== false ||
      mb_strpos($queryLower, 'compar') !== false ||  // Matches compare, comparison, comparaison
      mb_strpos($queryLower, 'rival') !== false ||
      mb_strpos($queryLower, 'versus') !== false ||
      mb_strpos($queryLower, 'vs') !== false
    );
    
    // 🆕 NEW (2026-05-07): Check for "on [site]" or "sur [site]" pattern
    // This indicates user wants to compare with external site prices
    $hasTargetSitePattern = (
      preg_match('/\b(on|at)\s+(amazon|cdiscount|fnac|darty|ebay|alibaba|aliexpress)/i', $queryLower) === 1
    );
    
    // Price comparison if:
    // 1. Has price keyword AND competitor keyword, OR
    // 2. Has price keyword AND target site pattern (e.g., "price on amazon")
    return $hasPriceKeyword && ($hasCompetitorKeyword || $hasTargetSitePattern);
  }
}
