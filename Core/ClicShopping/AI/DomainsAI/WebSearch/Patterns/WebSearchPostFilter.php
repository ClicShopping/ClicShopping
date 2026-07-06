<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Patterns;


use ClicShopping\OM\Registry;

/**
 * WebSearchPostFilter
 *
 * Post-filter to override intent classification for web search queries.
 * Applied after UnifiedQueryAnalyzer to ensure web search queries are correctly routed.
 *
 * This pattern-based post-filter is an EXCEPTION to Pure LLM mode, similar to
 * TemporalFinancialPreFilter. It provides deterministic classification for queries
 * mentioning competitors or external sites, where LLM classification is inconsistent.
 *
 * ARCHITECTURE:
 * 1. Priority: SQL database (clic_rag_websearch) - sites configured by admin
 * 2. Fallback: SerpAPI - for sites not in database
 * 3. English-only keywords - queries are translated before pattern matching
 *
 * Pattern definitions are loaded from WebSearchPatterns.php
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Patterns
 * @since 2025-12-28
 * 
 * REFACTORING: Relocated from Patterns/ to Patterns/WebSearch/
 * - Namespace updated: ClicShopping\AI\Domain\Patterns → ClicShopping\AI\Domain\Patterns\WebSearch
 * - Internal reference to WebSearchPatterns updated to same namespace
 * @moved 2026-01-22 from Domain\Patterns\WebSearch to DomainsAI\WebSearch\Patterns
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class WebSearchPostFilter
{
  /**
   * Post-filter to override intent classification for web search queries
   *
   * Checks for:
   * 1. Competitor keywords (competitors, competitor, competition, rival)
   * 2. External site names from database (table_rag_websearch)
   * 3. Price comparison keywords (compare, vs, best price)
   *
   * If any detected, overrides intent_type to 'web_search' with high confidence.
   *
   * CRITICAL: Does NOT override if LLM already detected 'hybrid' classification.
   * Hybrid queries should be split first, then each sub-query classified separately.
   *
   * @param string $translatedQuery Query in English (after translation)
   * @param array $analysis Current analysis result from UnifiedQueryAnalyzer
   * @return array Modified analysis with web_search intent if applicable
   */
  public static function postFilter(string $translatedQuery, array $analysis): array
  {
    // ⚠️ CRITICAL: Do NOT override if LLM detected 'hybrid'
    // Hybrid queries must be split first, then sub-queries classified separately
    if (isset($analysis['type']) && $analysis['type'] === 'hybrid') {
      return $analysis; // Keep hybrid classification, don't override
    }
    
    if (isset($analysis['intent_type']) && $analysis['intent_type'] === 'hybrid') {
      return $analysis; // Keep hybrid classification, don't override
    }
    
    $query = strtolower($translatedQuery);
    
    // ========================================================================
    // STEP 1: Check for trends/news keywords (ENGLISH ONLY) - NEW 2025-01-02
    // ========================================================================
    
    // Queries about trends, news, latest info require external web search
    // BUT: Exclude if query contains database entity keywords (product, order, customer)
    // BUT: Also exclude if query contains financial metric keywords (revenue, turnover, sales)
    // Example: "latest trends" → web_search ✅
    // Example: "most recent order" → analytics ✅ (has entity keyword "order")
    // Example: "revenue this month" → analytics ✅ (has financial keyword "revenue")
    
    // Financial metric keywords that indicate analytics queries (not web search)
    // These are metrics that require database aggregation, not external search
    $financialMetricKeywords = [
      'revenue', 'turnover', 'sales', 'profit', 'margin', 'income',
      'cost', 'expense', 'spending', 'budget', 'forecast',
      'average', 'total', 'sum', 'count', 'number of', 'how many',
      'stock', 'inventory', 'quantity', 'units',
      'pending', 'delivered', 'cancelled', 'processing', 'shipped',
      'orders', 'customers', 'products', 'categories'
    ];
    
    // Check if query has entity keywords (database query, not web search)
    // 🔧 MIGRATION: Use centralized EntityKeywordsPattern instead of WebSearchPatterns
    $hasEntityKeyword = false;
    foreach (WebSearchPatterns::getEntityKeywords() as $entity) {
      if (str_contains($query, $entity)) {
        $hasEntityKeyword = true;
        break;
      }
    }
    
    // Check if query has financial metric keywords (analytics query, not web search)
    $hasFinancialKeyword = false;
    foreach ($financialMetricKeywords as $keyword) {
      if (str_contains($query, $keyword)) {
        $hasFinancialKeyword = true;
        break;
      }
    }
    
    // Only apply trends/news override if NO entity keywords AND NO financial keywords present
    if (!$hasEntityKeyword && !$hasFinancialKeyword && !$llmSaysAnalytics) {
      foreach (WebSearchPatterns::$trendsNewsKeywords as $keyword) {
        if (str_contains($query, $keyword)) {
          $analysis['intent_type'] = 'web_search';
          $analysis['confidence'] = 0.95;
          $analysis['override_reason'] = "Trends/news keyword detected: $keyword (no entity/financial keywords)";
          $analysis['detection_method'] = 'pattern_post_filter';
          return $analysis;
        }
      }
    }
    
    // ========================================================================
    // STEP 2: Check for competitor keywords (ENGLISH ONLY)
    // ========================================================================
    
    foreach (WebSearchPatterns::$competitorKeywords as $keyword) {
      if (str_contains($query, $keyword)) {
        $analysis['intent_type'] = 'web_search';
        $analysis['confidence'] = 0.95;
        $analysis['override_reason'] = "Competitor keyword detected: $keyword";
        $analysis['detection_method'] = 'pattern_post_filter';
        return $analysis;
      }
    }
    
    // ========================================================================
    // STEP 3: Check for external sites from database (admin-configured)
    // ========================================================================
    
    try {
      $db = Registry::get('Db');
      
      // Get all active external sites from database
      $sitesQuery = $db->query("
        SELECT site_domain 
        FROM :table_rag_websearch 
        WHERE status = 1
      ");
      
      $externalSites = [];

      while ($row = $sitesQuery->fetch(\PDO::FETCH_ASSOC)) {
        $externalSites[] = strtolower($row['site_domain']);
      }
      
      // Check if query mentions any configured external site
      foreach ($externalSites as $site) {
        if (str_contains($query, $site)) {
          $analysis['intent_type'] = 'web_search';
          $analysis['confidence'] = 0.95;
          $analysis['override_reason'] = "External site detected: $site (from database)";
          $analysis['detection_method'] = 'pattern_post_filter';
          return $analysis;
        }
      }
      
    } catch (\Exception $e) {
      // Database error - continue with fallback patterns
      error_log("WebSearchPostFilter: Database error - " . $e->getMessage());
    }
    
    // ========================================================================
    // STEP 4: Check for price comparison keywords (ENGLISH ONLY)
    // ========================================================================
    
    // Only check comparison if query is price-related
    $hasPriceKeyword = false;
    
    foreach (WebSearchPatterns::$priceKeywords as $priceWord) {
      if (str_contains($query, $priceWord)) {
        $hasPriceKeyword = true;
        break;
      }
    }
    
    if ($hasPriceKeyword) {
      foreach (WebSearchPatterns::$comparisonKeywords as $keyword) {
        if (str_contains($query, $keyword)) {
          // Additional check: ensure it's not just internal comparison
          // If query contains "database" or "internal", it's likely hybrid, not pure web_search
          if (!str_contains($query, 'database') && !str_contains($query, 'internal')) {
            $analysis['intent_type'] = 'web_search';
            $analysis['confidence'] = 0.90;
            $analysis['override_reason'] = "Price comparison detected: $keyword";
            $analysis['detection_method'] = 'pattern_post_filter';
            return $analysis;
          }
        }
      }
    }
    
    // ========================================================================
    // STEP 5: Check for "on [site]" or "at [site]" pattern (ENGLISH ONLY)
    // ========================================================================
    
    // Pattern 1: "price on [site]", "find on [site]", "cheaper on [site]"
    // Also matches: "of [product] on [site]", "for [product] on [site]"
    if (preg_match('/\b(price|find|search|look|check|cheaper|available|of|for)\s+.*?\s+(on|at)\s+(\w+)/i', $query, $matches)) {
      $site = strtolower($matches[3]);
      
      // Exclude internal keywords
      if (!in_array($site, WebSearchPatterns::$internalKeywords, true)) {
        $analysis['intent_type'] = 'web_search';
        $analysis['confidence'] = 0.95;
        $analysis['override_reason'] = "External site pattern detected: on/at $site";
        $analysis['detection_method'] = 'pattern_post_filter';
        return $analysis;
      }
    }
    
    // Pattern 2: "what is the price on [site]", "how much on [site]"
    // Handles queries where price keyword comes before "on [site]"
    if (preg_match('/\b(what|how|show|get|tell)\s+.*?\s+(on|at)\s+(\w+)/i', $query, $matches)) {
      $site = strtolower($matches[3]);
      
      // Exclude internal keywords
      if (!in_array($site, WebSearchPatterns::$internalKeywords, true)) {
        $analysis['intent_type'] = 'web_search';
        $analysis['confidence'] = 0.95;
        $analysis['override_reason'] = "External site pattern detected: on/at $site";
        $analysis['detection_method'] = 'pattern_post_filter';
        return $analysis;
      }
    }
    
    // ========================================================================
    // STEP 6: Check for "is it cheaper" pattern (ENGLISH ONLY)
    // ========================================================================
    
    // Pattern: "is it cheaper", "is it available", "can I find"
    foreach (WebSearchPatterns::$cheaperPatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        $analysis['intent_type'] = 'web_search';
        $analysis['confidence'] = 0.85;
        $analysis['override_reason'] = "External availability/price check pattern detected";
        $analysis['detection_method'] = 'pattern_post_filter';
        return $analysis;
      }
    }
    
    // No web search patterns detected, return original analysis
    return $analysis;
  }
}
