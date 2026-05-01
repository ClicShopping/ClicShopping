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
 * HybridPreFilter - E-commerce Specific Pattern
 *
 * Pre-filter to detect hybrid queries AFTER translation (English-only).
 * Applied AFTER translation to catch queries with multiple intents connected by conjunctions.
 *
 * **E-COMMERCE SPECIFIC**: This pattern contains e-commerce specific keywords:
 * - Analytics: price, stock, revenue, sales
 * - Semantic: shipping, payment, delivery, warranty, guarantee, return
 *
 * This pattern-based pre-filter is an EXCEPTION to Pure LLM mode.
 * It provides deterministic detection for hybrid queries where LLM tends to focus
 * on the first intent and ignore the second.
 *
 * CRITICAL: This filter operates on ENGLISH queries only (after translation).
 * All keywords are in English. Multilingual queries are translated before this filter.
 *
 * ARCHITECTURE:
 * 1. Check for English conjunctions (and, also, or)
 * 2. Check if parts have different intent keywords (English only)
 * 3. If yes, return hybrid classification
 * 4. If no, return null (let LLM classify)
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns
 * @since 2025-01-02
 * @updated 2026-01-22 - Moved to Ecommerce App (e-commerce specific keywords)
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 *
 * @moved 2026-01-22 - From Domain/Patterns/Hybrid/ to Apps/AI/Ecommerce/
 *        Reason: Contains e-commerce specific keywords (shipping, payment, price, stock)
 *        Part of multi-domain-agnostic-ai architecture
 **/
// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class HybridPreFilter
{
  /**
   * Pre-filter to detect hybrid queries before LLM classification
   *
   * CRITICAL: This filter is applied AFTER translation, so queries are in ENGLISH ONLY.
   * All keywords must be in English.
   *
   * **E-COMMERCE SPECIFIC KEYWORDS**:
   * - Analytics: price, stock, revenue, sales, count, how many
   * - Semantic: policy, shipping, payment, delivery, warranty, guarantee, return
   * - Web search platforms: amazon, ebay, google, alibaba, aliexpress, etsy
   * - Web search actions: compare with, search online, find on, check on, price on
   * - Web search indicators: trends, news, latest, recent, competitors
   *
   * Checks for:
   * 1. Conjunctions (and, also, or)
   * 2. Different intent keywords in each part
   *
   * If detected, returns hybrid classification.
   * If not detected, returns null (let LLM classify).
   *
   * @param string $translatedQuery Query in English (after translation)
   * @return array|null Hybrid classification if detected, null otherwise
   */
  public static function preFilter(string $translatedQuery): ?array
  {
    $query = strtolower($translatedQuery);
    
    // Step 1: Check for conjunctions (ENGLISH ONLY - query is already translated)
    $conjunctions = [
      ' and ',
      ' also ',
      ' or '
    ];
    
    $hasConjunction = false;
    $conjunctionUsed = '';
    
    foreach ($conjunctions as $conj) {
      if (strpos($query, $conj) !== false) {
        $hasConjunction = true;
        $conjunctionUsed = trim($conj);
        break;
      }
    }
    
    if (!$hasConjunction) {
      return null; // No conjunction, not hybrid
    }
    
    // Step 2: Split query by conjunction (ENGLISH ONLY)
    $parts = preg_split('/\s+(and|also|or)\s+/i', $query);
    
    if (count($parts) < 2) {
      return null; // Can't split, not hybrid
    }
    
    // Step 3: Check intent keywords in each part (ENGLISH ONLY - E-COMMERCE SPECIFIC)
    $analyticsKeywords = [
      'price',      // E-commerce specific
      'stock',      // E-commerce specific
      'revenue',    // E-commerce specific
      'sales',      // E-commerce specific
      'count',
      'how many',
      'number of',
      'total',
      'average',
      'sum'
    ];
    
    $semanticKeywords = [
      'policy',
      'how',
      'what is',
      'explain',
      'return',      // E-commerce specific
      'shipping',    // E-commerce specific
      'payment',     // E-commerce specific
      'delivery',    // E-commerce specific
      'warranty',    // E-commerce specific
      'guarantee'    // E-commerce specific
    ];
    
    $webSearchKeywords = [
      // E-commerce platforms (external marketplaces)
      'amazon',       // E-commerce platform
      'ebay',         // E-commerce platform
      'google',       // Search engine / shopping
      'alibaba',      // E-commerce platform
      'aliexpress',   // E-commerce platform
      'etsy',         // E-commerce platform
      
      // Web search actions (English)
      'compare with',
      'search online',
      'find on',
      'check on',
      'price on',
      
      // General web search indicators
      'trends',
      'news',
      'latest',
      'recent',
      'competitors',
      'competition',
      'market',
      'compare',
      'comparison',
      'what\'s new',
      'current'
    ];
    
    // Detect intent for each part
    $intents = [];
    
    foreach ($parts as $part) {
      $part = trim($part);
      $partIntent = null;
      
      // Check analytics
      foreach ($analyticsKeywords as $keyword) {
        if (strpos($part, $keyword) !== false) {
          $partIntent = 'analytics';
          break;
        }
      }
      
      // Check semantic
      if ($partIntent === null) {
        foreach ($semanticKeywords as $keyword) {
          if (strpos($part, $keyword) !== false) {
            $partIntent = 'semantic';
            break;
          }
        }
      }
      
      // Check web_search
      if ($partIntent === null) {
        foreach ($webSearchKeywords as $keyword) {
          if (strpos($part, $keyword) !== false) {
            $partIntent = 'web_search';
            break;
          }
        }
      }
      
      if ($partIntent !== null) {
        $intents[] = $partIntent;
      }
    }
    
    // Step 4: Check if we have different intents
    $uniqueIntents = array_unique($intents);
    
    if (count($uniqueIntents) >= 2) {
      // Hybrid detected!
      return [
        'type' => 'hybrid',
        'intent_type' => 'hybrid',
        'confidence' => 0.90,
        'reasoning' => [
          'Pattern detected hybrid query with conjunction "' . $conjunctionUsed . '" connecting ' . implode(' and ', $uniqueIntents) . ' intents'
        ],
        'is_hybrid' => true,
        'sub_types' => array_values($uniqueIntents),
        'detection_method' => 'pattern_pre_filter'
      ];
    }
    
    // Not hybrid (same intent in both parts, or couldn't detect intents)
    return null;
  }
}
