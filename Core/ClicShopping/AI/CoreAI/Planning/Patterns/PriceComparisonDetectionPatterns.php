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

use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;

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
   * Also detects an explicit target site ("price on <site>") fully agnostically:
   * a candidate site token is matched against the registered SiteRouters
   * (Apps/AI/{Domain}) — no retailer brand is ever hard-coded in Core.
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
    
    // Check for an explicit "on/at/from <site>" target site — domain-agnostic.
    $hasTargetSitePattern = false;
    if (preg_match('/\b(?:on|at|from)\s+([a-z0-9][a-z0-9.\-]+)/i', $queryLower, $m) === 1) {
      $hasTargetSitePattern = WebSearchEngineRegistry::getInstance()->findSiteRouter($m[1]) !== null;
    }
    
    // Price comparison if:
    // 1. Has price keyword AND competitor keyword, OR
    // 2. Has price keyword AND target site pattern (e.g., "price on <site>")
    return $hasPriceKeyword && ($hasCompetitorKeyword || $hasTargetSitePattern);
  }
}
