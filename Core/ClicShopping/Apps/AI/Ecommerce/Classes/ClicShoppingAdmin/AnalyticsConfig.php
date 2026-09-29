<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

/**
 * AnalyticsConfig Class
 *
 * Ecommerce field vocabulary for the analytics path, read through
 * DoctrineOrm::analyticsConfigList() and HybridQueryDecomposer.
 */
class AnalyticsConfig
{
  /**
   * Get non-database words (descriptive/explanatory terms)
   * 
   * These words are commonly used in e-commerce queries but are NOT database fields.
   * Used to filter out semantic queries from analytics queries.
   * 
   * @return array List of non-database words for e-commerce domain
   */
  public static function getNonDatabaseWords(): array
  {
    return [
      // Product descriptions
      'description', 'summary', 'information', 'details', 'info',
      'explanation', 'definition', 'meaning', 'purpose',
      'features', 'benefits', 'advantages', 'characteristics',
      'quality', 'performance', 'specifications', 'specs',
      
      // Business context
      'history', 'background', 'story', 'about',
      'why', 'how', 'what', 'when', 'where', 'who',
      
      // Policies and terms
      'policy', 'terms', 'conditions', 'rules', 'regulations',
      'warranty', 'guarantee', 'return', 'refund', 'shipping',
      
      // E-commerce specific
      'product', 'item', 'article', 'goods', 'merchandise',
      'order', 'purchase', 'sale', 'transaction',
      'customer', 'client', 'buyer', 'user',
      'category', 'brand', 'manufacturer', 'supplier'
    ];
  }
  
  /**
   * Get field abbreviations mapping
   * 
   * Maps full field names to their common abbreviations in e-commerce.
   * Used for field name normalization.
   * 
   * @return array Mapping of full name => abbreviation
   */
  public static function getFieldAbbreviations(): array
  {
    return [
      // Common e-commerce abbreviations
      'quantity' => 'qty',
      'reference' => 'ref',
      'description' => 'desc',
      'number' => 'no',
      'identifier' => 'id',
      
      // Product fields
      'product' => 'prod',
      'products' => 'prods',
      'manufacturer' => 'mfr',
      'category' => 'cat',
      
      // Order fields
      'order' => 'ord',
      'orders' => 'ords',
      'customer' => 'cust',
      'customers' => 'custs',
      
      // Measurements
      'weight' => 'wt',
      'height' => 'ht',
      'width' => 'wd',
      'length' => 'len',
      
      // Financial
      'price' => 'prc',
      'amount' => 'amt',
      'total' => 'tot',
      'subtotal' => 'subtot'
    ];
  }
  
  /**
   * Get fallback database fields for e-commerce
   * 
   * Provides a comprehensive list of common e-commerce database fields.
   * Used as fallback when dynamic discovery fails.
   * 
   * @return array List of common e-commerce database fields
   */
  public static function getFallbackDatabaseFields(): array
  {
    return array_unique([
      // Technical identifiers
      'sku', 'ean', 'upc', 'isbn', 'gtin', 'barcode', 'code', 'reference', 'ref', 'model',
      'id', 'number', 'serial',
      
      // Measurable attributes
      'price', 'cost', 'amount', 'value', 'total', 'subtotal',
      'stock', 'inventory', 'quantity', 'qty', 'available', 'count',
      'weight', 'height', 'width', 'length', 'dimension', 'dimensions', 'size',
      
      // Timestamps
      'date', 'time', 'created', 'updated', 'modified', 'deleted',
      'timestamp', 'datetime',
      
      // Status fields
      'status', 'state', 'active', 'enabled', 'disabled', 'published',
      'visible', 'sold', 'shipped', 'delivered', 'pending', 'cancelled',
      
      // Financial
      'tax', 'discount', 'margin', 'profit', 'revenue', 'sales',
      
      // Relationships
      'category', 'brand', 'manufacturer', 'supplier', 'vendor',
      
      // Contact info
      'email', 'phone', 'address', 'zip', 'postal', 'city', 'country',
      
      // Ratings and reviews
      'rating', 'score', 'rank', 'position', 'order', 'review', 'comment'
    ]);
  }
}
