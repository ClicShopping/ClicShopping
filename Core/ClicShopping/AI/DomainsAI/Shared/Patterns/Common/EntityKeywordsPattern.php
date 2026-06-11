<?php
/**
 * EntityKeywordsPattern.php
 * 
 * Centralized entity keywords shared across multiple domains.
 * Extracted from SuperlativePatterns, WebSearchPatterns, and EntityDetectionPattern
 * to avoid duplication and ensure consistency.
 * 
 * @package ClicShopping\AI\DomainsAI\Shared\Patterns\Common
 * @since 2026-01-09
 * 
 * REFACTORING: Centralized from multiple pattern files
 * - SuperlativePatterns::$entityKeywords
 * - WebSearchPatterns::$entityKeywords
 * - EntityDetectionPattern::getPatterns()
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

namespace ClicShopping\AI\DomainsAI\Shared\Patterns\Common;




// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class EntityKeywordsPattern
{
  /**
   * Flat list of entity keywords for simple matching
   * 
   * Used by Analytics and WebSearch domains for quick entity detection.
   * Merged from SuperlativePatterns and WebSearchPatterns.
   * 
   * @var array<string>
   */
  public static array $entityKeywords = [
    // Products
    'product', 'products', 'item', 'items', 'article', 'articles',
    
    // Orders/Sales
    'order', 'orders', 'sale', 'sales', 'purchase', 'purchases',
    
    // Customers
    'customer', 'customers', 'client', 'clients', 'user', 'users',
    
    // Suppliers/Manufacturers
    'supplier', 'suppliers', 'vendor', 'vendors', 'manufacturer', 'manufacturers',
    
    // Financial (from WebSearchPatterns)
    'invoice', 'invoices', 'payment', 'payments', 'transaction', 'transactions',
    
    // Categories (from EntityDetectionPattern)
    'category', 'categories', 'section', 'sections',
    
    // Reviews (from EntityDetectionPattern)
    'review', 'reviews', 'rating', 'ratings', 'comment', 'comments',
    
    // Brands (from EntityDetectionPattern)
    'brand', 'brands',
  ];
  
  /**
   * Entity patterns organized by entity type
   * 
   * Used by Ecommerce domain for structured entity detection.
   * Merged from EntityDetectionPattern::getPatterns().
   * 
   * @var array<string, array<string>>
   */
  public static array $entityPatterns = [
    'product' => ['product', 'products', 'item', 'items', 'article', 'articles'],
    'category' => ['category', 'categories', 'section', 'sections'],
    'customer' => ['customer', 'customers', 'client', 'clients', 'user', 'users'],
    'order' => ['order', 'orders', 'purchase', 'purchases', 'sale', 'sales'],
    'manufacturer' => ['manufacturer', 'manufacturers', 'brand', 'brands', 'supplier', 'suppliers', 'vendor', 'vendors'],
    'review' => ['review', 'reviews', 'rating', 'ratings', 'comment', 'comments'],
    'financial' => ['invoice', 'invoices', 'payment', 'payments', 'transaction', 'transactions'],
  ];
  
  /**
   * Get flat list of entity keywords
   * 
   * @return array<string>
   */
  public static function getKeywords(): array
  {
    return self::$entityKeywords;
  }
  
  /**
   * Get entity patterns organized by type
   * 
   * @return array<string, array<string>>
   */
  public static function getPatterns(): array
  {
    return self::$entityPatterns;
  }
  
  /**
   * Get keywords for a specific entity type
   * 
   * @param string $entityType Entity type (product, category, customer, etc.)
   * @return array<string> Keywords for the entity type, empty array if not found
   */
  public static function getKeywordsForEntity(string $entityType): array
  {
    return self::$entityPatterns[$entityType] ?? [];
  }
  
  /**
   * Check if a keyword belongs to any entity type
   * 
   * @param string $keyword Keyword to check
   * @return bool True if keyword is an entity keyword
   */
  public static function isEntityKeyword(string $keyword): bool
  {
    return in_array(mb_strtolower($keyword), self::$entityKeywords, true);
  }
  
  /**
   * Get entity type for a keyword
   * 
   * @param string $keyword Keyword to look up
   * @return string|null Entity type or null if not found
   */
  public static function getEntityTypeForKeyword(string $keyword): ?string
  {
    $keyword = mb_strtolower($keyword);
    
    foreach (self::$entityPatterns as $entityType => $keywords) {
      if (in_array($keyword, $keywords, true)) {
        return $entityType;
      }
    }
    
    return null;
  }
  
  /**
   * Get all entity types
   * 
   * @return array<string>
   */
  public static function getEntityTypes(): array
  {
    return array_keys(self::$entityPatterns);
  }
  
  /**
   * Get metadata about this pattern
   * 
   * @return array Pattern metadata
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'Entity Keywords Pattern',
      'description' => 'Centralized entity keywords shared across Analytics, WebSearch, and Ecommerce domains',
      'entity_types' => self::getEntityTypes(),
      'total_keywords' => count(self::$entityKeywords),
      'source_files' => [
        'SuperlativePatterns::$entityKeywords',
        'WebSearchPatterns::$entityKeywords',
        'EntityDetectionPattern::getPatterns()',
      ],
    ];
  }
}
