<?php
/**
 * EcommerceEntityHelperFactory - Factory for creating entity helpers
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\InterfacesAI\EntityHelperInterface;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\EntityHelpers\DynamicEntityHelper;

/**
 * EcommerceEntityHelperFactory Class
 *
 * Factory for creating and managing entity helpers for the Ecommerce domain.
 * Now uses DynamicEntityHelper for universal entity handling.
 * 
 * DESIGN PATTERN: Factory Pattern + Dynamic Discovery
 * - Centralizes entity helper creation
 * - Provides lazy loading and caching
 * - Uses DynamicEntityHelper for ANY entity type
 * - Dynamically discovers available entity types from database
 * - No hardcoding needed - scales to 100+ entities
 * 
 * USAGE:
 * ```php
 * $factory = new EcommerceEntityHelperFactory(true);
 * 
 * // Works with ANY entity type - no code changes needed!
 * $productHelper = $factory->getHelper('product');
 * $categoryHelper = $factory->getHelper('category');
 * $orderHelper = $factory->getHelper('order');
 * $customerHelper = $factory->getHelper('customer');
 * 
 * // Get all available entity types
 * $types = $factory->getAvailableEntityTypes();
 * ```
 * 
 * @since 2026-01-21
 * @version 3.0.0 (Phase 3 - Removed ProductEntityHelper)
 */
class EcommerceEntityHelperFactory
{
  private static array $helpers = [];
  private static ?array $cachedEntityTypes = null;
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
  }

  /**
   * Get entity helper by type
   * 
   * Implements lazy loading - helpers are created only when requested.
   * Caches helpers for reuse.
   * 
   * Now uses DynamicEntityHelper which works with ANY entity type.
   * No need to hardcode each entity type.
   * 
   * @param string $entityType Entity type (product, category, order, customer, etc.)
   * @return EntityHelperInterface|null Helper instance or null if type not found
   */
  public function getHelper(string $entityType): ?EntityHelperInterface
  {
    $entityType = strtolower(trim($entityType));
    
    if (empty($entityType)) {
      return null;
    }
    
    if (!isset(self::$helpers[$entityType])) {
      self::$helpers[$entityType] = $this->createHelper($entityType);
    }
    
    return self::$helpers[$entityType];
  }

  /**
   * Create helper instance by type
   * 
   * Now uses DynamicEntityHelper for universal entity handling.
   * Automatically discovers table structure from database.
   * 
   * Special case: 'product' entity type returns ProductHelper for backward compatibility.
   * Note: Actual table is 'products' (plural), but we accept 'product' (singular) for convenience.
   * 
   * @param string $entityType Entity type
   * @return EntityHelperInterface|null Helper instance or null if table not found
   */
  private function createHelper(string $entityType): ?EntityHelperInterface
  {
    try {
      // Special case: Return ProductHelper for 'product' entity type (backward compatibility)
      if ($entityType === 'product') {
        $helper = new ProductHelper($this->debug);
        
        if ($this->debug) {
          error_log("EcommerceEntityHelperFactory: Created ProductHelper for 'product' (backward compatibility)");
        }
        
        return $helper;
      }
      
      // Use DynamicEntityHelper for all other entity types
      $helper = new DynamicEntityHelper($entityType, $this->debug);
      
      if ($this->debug) {
        error_log("EcommerceEntityHelperFactory: Created DynamicEntityHelper for '{$entityType}'");
      }
      
      return $helper;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("EcommerceEntityHelperFactory: Failed to create helper for '{$entityType}': " . $e->getMessage());
      }
      return null;
    }
  }

  /**
   * Get all available entity types
   * 
   * Dynamically discovers entity types from database.
   * No hardcoding needed - automatically finds all tables.
   * 
   * @param bool $useCache Whether to use cached results (default: true)
   * @return array List of available entity types
   */
  public function getAvailableEntityTypes(bool $useCache = true): array
  {
    if ($useCache && self::$cachedEntityTypes !== null) {
      return self::$cachedEntityTypes;
    }
    
    try {
      $allTables = DoctrineOrm::getRelevantTables();
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      $entityTypes = [];
      
      foreach ($allTables as $table) {
        // Extract entity type from table name
        $entityType = str_replace($prefix, '', $table);
        
        // Remove _description suffix
        $entityType = preg_replace('/_description$/', '', $entityType);
        
        // Take first part for compound names
        $parts = explode('_', $entityType);
        $entityType = $parts[0];
        
        // Add to list if not already present
        if ($entityType && !in_array($entityType, $entityTypes, true)) {
          $entityTypes[] = $entityType;
        }
      }
      
      // Sort for consistency
      sort($entityTypes);
      
      if ($this->debug) {
        error_log("EcommerceEntityHelperFactory: Discovered " . count($entityTypes) . " entity types: " . implode(', ', $entityTypes));
      }
      
      self::$cachedEntityTypes = $entityTypes;
      return $entityTypes;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("EcommerceEntityHelperFactory: Failed to discover entity types: " . $e->getMessage());
      }
      
      // Fallback to common entity types
      return self::getFallbackEntityTypes();
    }
  }

  /**
   * Get fallback entity types (static list)
   * 
   * Used when dynamic discovery fails.
   * 
   * @return array Static list of common entity types
   */
  private static function getFallbackEntityTypes(): array
  {
    return [
      'product',
      'category',
      'manufacturer',
      'supplier',
      'order',
      'customer',
      'review',
    ];
  }

  /**
   * Check if entity type is available
   * 
   * @param string $entityType Entity type
   * @return bool True if entity type is available
   */
  public function hasEntityType(string $entityType): bool
  {
    return in_array(strtolower($entityType), $this->getAvailableEntityTypes(), true);
  }

  /**
   * Clear cached helpers and entity types
   * 
   * Useful for testing or when you need to reset helpers.
   */
  public static function clearCache(): void
  {
    self::$helpers = [];
    self::$cachedEntityTypes = null;
  }

  /**
   * Get product helper
   * 
   * Convenience method for getting product helper.
   * 
   * @return EntityHelperInterface|null
   */
  public function getProductHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('product');
  }

  /**
   * Get category helper
   * 
   * Convenience method for getting category helper.
   * 
   * @return EntityHelperInterface|null
   */
  public function getCategoryHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('category');
  }

  /**
   * Get manufacturer helper
   * 
   * Convenience method for getting manufacturer helper.
   * 
   * @return EntityHelperInterface|null
   */
  public function getManufacturerHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('manufacturer');
  }

  /**
   * Get supplier helper
   * 
   * Convenience method for getting supplier helper.
   * 
   * @return EntityHelperInterface|null
   */
  public function getSupplierHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('supplier');
  }

  /**
   * Get order helper
   * 
   * Convenience method for getting order helper.
   * 
   * @return EntityHelperInterface|null
   */
  public function getOrderHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('order');
  }

  /**
   * Get customer helper
   * 
   * Convenience method for getting customer helper.
   * 
   * @return EntityHelperInterface|null
   */
  public function getCustomerHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('customer');
  }
}
