<?php
/**
 * EntityHelper - Helper functions for entity type manipulation
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Entity;

/**
 * EntityHelper Class
 *
 * Provides utility functions for entity type manipulation and conversion.
 * Used by ContextResolver and other components that work with entity types.
 */
class EntityHelper
{
  /**
   * Convert plural entity type to singular form for pattern matching
   * 
   * This method handles English pluralization rules and special cases
   * specific to the ClicShopping entity naming conventions.
   * 
   * Examples:
   * - "products" -> "product"
   * - "categories" -> "category"
   * - "reviews_sentiment" -> "review_sentiment"
   * - "pages_manager" -> "page"
   * - "suppliers" -> "supplier"
   * - "manufacturers" -> "manufacturer"
   * - "orders" -> "order"
   * - "return_orders" -> "return_order"
   *
   * @param string $entityType Entity type (usually plural)
   * @return string Singular form
   */
  public static function getSingularForm(string $entityType): string
  {
    // Special cases that don't follow standard pluralization rules
    $specialCases = [
      'categories' => 'category',
      'pages_manager' => 'page',
      'reviews_sentiment' => 'review_sentiment',
    ];
    
    if (isset($specialCases[$entityType])) {
      return $specialCases[$entityType];
    }
    
    // Handle compound words with underscores (e.g., "return_orders" -> "return_order")
    if (str_contains($entityType, '_')) {
      $parts = explode('_', $entityType);
      $lastPart = array_pop($parts);
      
      // Apply singularization to the last part only
      $singularLastPart = self::singularizeWord($lastPart);
      $parts[] = $singularLastPart;
      
      return implode('_', $parts);
    }
    
    // Simple word - apply standard rules
    return self::singularizeWord($entityType);
  }

  /**
   * Convert plural word to singular (English rules)
   *
   * @param string $word Plural word
   * @return string Singular word
   */
  private static function singularizeWord(string $word): string
  {
    // Already singular or unknown
    if (strlen($word) < 2) {
      return $word;
    }
    
    // Words ending in "ies" -> "y" (e.g., "categories" -> "category")
    if (str_ends_with($word, 'ies')) {
      return substr($word, 0, -3) . 'y';
    }
    
    // Words ending in "es" -> remove "es" (e.g., "boxes" -> "box")
    if (str_ends_with($word, 'es')) {
      return substr($word, 0, -2);
    }
    
    // Words ending in "s" -> remove "s" (e.g., "products" -> "product")
    if (str_ends_with($word, 's')) {
      return substr($word, 0, -1);
    }
    
    // No change needed
    return $word;
  }

  /**
   * Convert singular entity type to plural form
   * 
   * Reverse operation of getSingularForm()
   * 
   * Examples:
   * - "product" -> "products"
   * - "category" -> "categories"
   * - "page" -> "pages_manager"
   *
   * @param string $entityType Entity type (singular)
   * @return string Plural form
   */
  public static function getPluralForm(string $entityType): string
  {
    // Special cases
    $specialCases = [
      'category' => 'categories',
      'page' => 'pages_manager',
      'review_sentiment' => 'reviews_sentiment',
    ];
    
    if (isset($specialCases[$entityType])) {
      return $specialCases[$entityType];
    }
    
    // Handle compound words with underscores
    if (str_contains($entityType, '_')) {
      $parts = explode('_', $entityType);
      $lastPart = array_pop($parts);
      
      // Apply pluralization to the last part only
      $pluralLastPart = self::pluralizeWord($lastPart);
      $parts[] = $pluralLastPart;
      
      return implode('_', $parts);
    }
    
    // Simple word - apply standard rules
    return self::pluralizeWord($entityType);
  }

  /**
   * Convert singular word to plural (English rules)
   *
   * @param string $word Singular word
   * @return string Plural word
   */
  private static function pluralizeWord(string $word): string
  {
    // Already plural or unknown
    if (strlen($word) < 2) {
      return $word;
    }
    
    // Words ending in "y" -> "ies" (e.g., "category" -> "categories")
    if (str_ends_with($word, 'y')) {
      return substr($word, 0, -1) . 'ies';
    }
    
    // Words ending in "s", "x", "z", "ch", "sh" -> add "es"
    if (preg_match('/(s|x|z|ch|sh)$/i', $word)) {
      return $word . 'es';
    }
    
    // Default: add "s"
    return $word . 's';
  }

  /**
   * Normalize entity type name
   * 
   * Ensures consistent naming (lowercase, underscores)
   *
   * @param string $entityType Entity type
   * @return string Normalized entity type
   */
  public static function normalizeEntityType(string $entityType): string
  {
    // Convert to lowercase
    $normalized = strtolower($entityType);
    
    // Replace spaces with underscores
    $normalized = str_replace(' ', '_', $normalized);
    
    // Remove multiple underscores
    $normalized = preg_replace('/_+/', '_', $normalized);
    
    // Trim underscores from start and end
    $normalized = trim($normalized, '_');
    
    return $normalized;
  }

  /**
   * Check if entity type is valid
   * 
   * Validates entity type name format
   *
   * @param string $entityType Entity type to validate
   * @return bool True if valid
   */
  public static function isValidEntityType(string $entityType): bool
  {
    // Must not be empty
    if (empty($entityType)) {
      return false;
    }
    
    // Must contain only lowercase letters, numbers, and underscores
    if (!preg_match('/^[a-z0-9_]+$/', $entityType)) {
      return false;
    }
    
    // Must be at least 2 characters
    if (strlen($entityType) < 2) {
      return false;
    }
    
    return true;
  }

  /**
   * Get display name for entity type
   * 
   * Converts entity type to human-readable format
   * 
   * Examples:
   * - "products" -> "Products"
   * - "pages_manager" -> "Pages Manager"
   * - "reviews_sentiment" -> "Reviews Sentiment"
   *
   * @param string $entityType Entity type
   * @return string Display name
   */
  public static function getDisplayName(string $entityType): string
  {
    // Replace underscores with spaces
    $displayName = str_replace('_', ' ', $entityType);
    
    // Capitalize each word
    $displayName = ucwords($displayName);
    
    return $displayName;
  }
}
