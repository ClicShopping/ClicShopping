<?php
/**
 * EcommerceEntityHelperRegistry - Registry for entity helpers
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\AI\InterfacesAI\EntityHelperInterface;

/**
 * EcommerceEntityHelperRegistry Class
 *
 * Singleton registry for managing entity helpers in the Ecommerce domain.
 * Provides centralized access to all entity helpers.
 * 
 * DESIGN PATTERN: Singleton + Registry Pattern
 * - Single instance per application
 * - Centralized access to all entity helpers
 * - Lazy loading via factory
 * - Caching for performance
 * 
 * USAGE:
 * ```php
 * $registry = EcommerceEntityHelperRegistry::getInstance(true);
 * $productHelper = $registry->getProductHelper();
 * $product = $productHelper->getProductById(123);
 * ```
 * 
 * @since 2026-01-21
 * @version 1.0.0
 */
class EcommerceEntityHelperRegistry
{
  private static ?self $instance = null;
  private EcommerceEntityHelperFactory $factory;
  private array $helpers = [];

  /**
   * Private constructor - Use getInstance() instead
   *
   * @param bool $debug Enable debug logging
   */
  private function __construct(bool $debug = false)
  {
    $this->factory = new EcommerceEntityHelperFactory($debug);
  }

  /**
   * Get singleton instance
   *
   * @param bool $debug Enable debug logging
   * @return self
   */
  public static function getInstance(bool $debug = false): self
  {
    if (self::$instance === null) {
      self::$instance = new self($debug);
    }
    return self::$instance;
  }

  /**
   * Get helper by entity type
   * 
   * @param string $entityType Entity type (product, category, manufacturer, etc.)
   * @return EntityHelperInterface|null Helper instance or null if type not found
   */
  public function getHelper(string $entityType): ?EntityHelperInterface
  {
    if (!isset($this->helpers[$entityType])) {
      $this->helpers[$entityType] = $this->factory->getHelper($entityType);
    }
    return $this->helpers[$entityType];
  }

  /**
   * Get product helper
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
   * @return EntityHelperInterface|null
   */
  public function getCategoryHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('category');
  }

  /**
   * Get manufacturer helper
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
   * @return EntityHelperInterface|null
   */
  public function getSupplierHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('supplier');
  }

  /**
   * Get order helper
   * 
   * @return EntityHelperInterface|null
   */
  public function getOrderHelper(): ?EntityHelperInterface
  {
    return $this->getHelper('order');
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
    return $this->factory->getAvailableEntityTypes($useCache);
  }

  /**
   * Check if entity type is available
   * 
   * @param string $entityType Entity type
   * @return bool True if entity type is available
   */
  public function hasEntityType(string $entityType): bool
  {
    return $this->factory->hasEntityType($entityType);
  }

  /**
   * Reset singleton instance
   * 
   * Useful for testing. Creates a new instance on next getInstance() call.
   */
  public static function reset(): void
  {
    self::$instance = null;
    EcommerceEntityHelperFactory::clearCache();
  }
}
