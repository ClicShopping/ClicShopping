<?php
/**
 * SuperlativePatterns.php
 * 
 * Pattern definitions for superlative query detection.
 * Contains ONLY pattern arrays - no logic.
 * 
 * @package ClicShopping\AI\Domain\Patterns
 * @since 2026-01-03
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

use ClicShopping\AI\DomainsAI\Shared\Patterns\Common\EntityKeywordsPattern;

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class SuperlativePatterns
{
  /**
   * Superlative keywords (English-only)
   * 
   * These terms indicate MIN/MAX/BEST/WORST queries:
   * - most expensive, cheapest, highest, lowest, best-selling, worst-selling
   * - most recent, latest, newest, oldest (temporal superlatives)
   */
  public static array $superlativeKeywords = [
    // Price superlatives
    'most expensive', 'least expensive', 'cheapest', 'priciest',
    'highest price', 'lowest price', 'highest priced', 'lowest priced',
    'most costly', 'least costly',
    
    // Sales superlatives
    'best-selling', 'best selling', 'worst-selling', 'worst selling',
    'most sold', 'least sold', 'most popular', 'least popular',
    'top-selling', 'top selling', 'bottom-selling', 'bottom selling',
    
    // Temporal superlatives (for orders, dates, etc.)
    'most recent', 'least recent', 'latest', 'earliest',
    'newest', 'oldest', 'most current', 'most up-to-date',
    'first', 'last',
    
    // General superlatives
    'most', 'least', 'best', 'worst', 'highest', 'lowest',
    'maximum', 'minimum', 'top', 'bottom',
    
    // Comparative forms that imply superlative
    'more expensive than', 'less expensive than', 'cheaper than', 'pricier than',
  ];
  
  /**
   * Entity keywords that indicate database queries
   * 
   * @deprecated Use EntityKeywordsPattern::$entityKeywords instead
   * @see \ClicShopping\AI\Domain\Patterns\Common\EntityKeywordsPattern
   */
  public static array $entityKeywords = [];
  
  /**
   * Get entity keywords from centralized pattern
   * 
   * @return array Entity keywords
   */
  public static function getEntityKeywords(): array
  {
    return EntityKeywordsPattern::$entityKeywords;
  }
}
