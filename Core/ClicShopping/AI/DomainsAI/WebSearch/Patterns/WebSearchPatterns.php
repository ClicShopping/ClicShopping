<?php
/**
 * WebSearchPatterns.php
 * 
 * Pattern definitions for web search detection.
 * Contains ONLY pattern arrays - no logic.
 * 
 * @package ClicShopping\AI\DomainsAI\WebSearch\Patterns
 * @since 2026-01-03
 * 
 * REFACTORING: Relocated from Patterns/ to Patterns/WebSearch/
 * - Namespace updated: ClicShopping\AI\Domain\Patterns → ClicShopping\AI\Domain\Patterns\WebSearch
 * - $entityKeywords replaced with reference to Common\EntityKeywordsPattern
 * @moved 2026-01-22 from Domain\Patterns\WebSearch to DomainsAI\WebSearch\Patterns
 * 
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Patterns;


use ClicShopping\AI\DomainsAI\Shared\Patterns\Common\EntityKeywordsPattern;


// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class WebSearchPatterns
{
  /**
   * Trends/news keywords (English-only)
   * 
   * Queries about trends, news, latest info require external web search
   */
  public static array $trendsNewsKeywords = [
    'trend',
    'trends',
    'trending',
    'latest',
    'recent',
    'news',
    'what\'s new',
    'whats new',
    ' new ', // Space-padded to avoid matching "renew", "newest" in other contexts
    'current',
    'nowadays',
    'today',
    'this week',
    'this month'
  ];
  
  /**
   * Entity keywords that indicate database queries (not web search)
   * 
   * @deprecated Use Common\EntityKeywordsPattern::$entityKeywords instead
   * @see EntityKeywordsPattern::$entityKeywords
   */
  public static array $entityKeywords = [
    'product', 'products', 'item', 'items', 'article', 'articles',
    'order', 'orders', 'sale', 'sales', 'purchase', 'purchases',
    'customer', 'customers', 'client', 'clients', 'user', 'users',
    'supplier', 'suppliers', 'vendor', 'vendors', 'manufacturer', 'manufacturers',
    'invoice', 'invoices', 'payment', 'payments', 'transaction', 'transactions'
  ];
  
  /**
   * Get entity keywords from centralized Common pattern
   * 
   * @return array<string> Entity keywords
   */
  public static function getEntityKeywords(): array
  {
    return EntityKeywordsPattern::getKeywords();
  }
  
  /**
   * Competitor keywords (English-only)
   */
  public static array $competitorKeywords = [
    'competitor',
    'competitors',
    'competition',
    'rival',
    'rivals'
  ];
  
  /**
   * Price keywords
   */
  public static array $priceKeywords = [
    'price', 
    'cost', 
    'pricing', 
    'prices', 
    'costs'
  ];
  
  /**
   * Comparison keywords
   */
  public static array $comparisonKeywords = [
    'compare',
    'comparison',
    'vs',
    'versus',
    'best price',
    'cheaper',
    'cheapest',
    'find',
    'search'
  ];
  
  /**
   * Internal keywords (to exclude from external site detection)
   * 
   * - "promotion", "sale", "special", "discount" should not be treated as external sites
   * - Fixes issue where "count products on promotion" was incorrectly classified as web_search
   */
  public static array $internalKeywords = [
    'database', 
    'site', 
    'website', 
    'store', 
    'shop', 
    'our', 
    'my', 
    'the', 
    'it',
    'promotion',
    'sale',
    'special',
    'discount',
    'offer',
    'deal'
  ];
  
  /**
   * Cheaper/availability patterns (regex)
   */
  public static array $cheaperPatterns = [
    '/\bis\s+it\s+(cheaper|available|sold)/i',
    '/\bcan\s+i\s+(find|buy|get)/i',
    '/\bwhere\s+(?:can|to)\s+(find|buy|get)/i'
  ];

  /**
   *
   * @var array|string[]
   * Initialize stopwords for title normalization
   */
  public static array $stopWordTitle = [
    'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
    'new', 'official', 'original'
  ];

}
