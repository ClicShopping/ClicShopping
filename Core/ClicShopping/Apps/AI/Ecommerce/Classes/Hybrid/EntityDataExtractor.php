<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Hybrid;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Config\DomainFields;

/**
 * EntityDataExtractor - Extract entity data from database rows
 *
 * Domain-specific class for extracting e-commerce entity data (products, orders, etc.)
 * from database rows using dynamic field discovery.
 *
 * Responsibilities:
 * - Detect entity type from row keys
 * - Extract fields using EntityConfig
 * - Provide fallback strategies for missing fields
 * - Cache EntityConfig lookups for performance
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\Hybrid
 * @since 2026-04-28
 */
class EntityDataExtractor
{
  /**
   * Cache for EntityConfig class instances to avoid repeated lookups
   *
   * @var array
   */
  private array $entityConfigCache = [];

  /**
   * Debug mode
   *
   * @var bool
   */
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->entityConfigCache = [];
  }

  /**
   * Extract entity data from a database row using dynamic field discovery
   *
   * This method uses EntityConfig to discover available fields instead of
   * hardcoding field names like "products_name" or "products_price".
   *
   * Fallback strategy:
   * 1. Try to detect entity type from row keys
   * 2. Use EntityConfig to get description fields for that entity
   * 3. Map common field patterns (id, name, price, model)
   * 4. Use generic fallbacks if specific fields not found
   *
   * @param array $row Database row data
   * @return array Entity data with standardized keys
   */
  public function extractFromRow(array $row): array
  {
    // Detect entity type from row keys (e.g., "products_id" -> "products")
    $entityType = $this->detectEntityType($row);

    // Get description fields for this entity type (if domain configured)
    $descriptionFields = [];

    if (!empty($entityType) && DomainConfig::getActivities() !== '') {
      // Use caching to avoid repeated lookups
      $cacheKey = 'EntityConfig_' . DomainConfig::getActivities();

      if (!isset($this->entityConfigCache[$cacheKey])) {
        $this->entityConfigCache[$cacheKey] = DomainFields::resolveAppClass(DomainConfig::getActivities(), 'EntityConfig');
      }

      $entityConfigClass = $this->entityConfigCache[$cacheKey];

      if ($entityConfigClass !== null && method_exists($entityConfigClass, 'getDescriptionFields')) {
        // Cache the description fields as well
        $descriptionCacheKey = 'DescriptionFields_' . DomainConfig::getActivities() . '_' . $entityType;

        if (!isset($this->entityConfigCache[$descriptionCacheKey])) {
          $this->entityConfigCache[$descriptionCacheKey] = $entityConfigClass::getDescriptionFields($entityType);
        }

        $descriptionFields = $this->entityConfigCache[$descriptionCacheKey];
      }
    }

    // Extract fields using dynamic discovery with fallbacks
    return [
      'entity_id' => $this->extractField($row, ['id', 'product_id', 'order_id', 'customer_id'], $entityType, 0),
      'name' => $this->extractField($row, ['name', 'title', 'description'], $entityType, 'Unknown Item'),
      'price' => (float)$this->extractField($row, ['price', 'cost', 'amount', 'total'], $entityType, 0),
      'model' => $this->extractField($row, ['model', 'sku', 'code', 'reference'], $entityType, ''),
      'entity_type' => $entityType,
      'available_fields' => array_keys($row),
      'description_fields' => $descriptionFields
    ];
  }

  /**
   * Detect entity type from row keys
   *
   * Looks for patterns like "products_id", "orders_id", "customers_id"
   * and extracts the entity type prefix.
   *
   * @param array $row Database row
   * @return string|null Entity type or null if not detected
   */
  public function detectEntityType(array $row): ?string
  {
    foreach (array_keys($row) as $key) {
      // Look for pattern: {entity}_id or {entity}_{field}
      if (preg_match('/^([a-z_]+)_(id|name|price|model|total|quantity)$/i', $key, $matches)) {
        return $matches[1]; // Return entity prefix (e.g., "products", "orders")
      }
    }

    // Fallback: check if we can get entity types from EntityConfig
    if (DomainConfig::getActivities() !== '') {
      $entityConfigClass = DomainFields::resolveAppClass(DomainConfig::getActivities(), 'EntityConfig');
      if ($entityConfigClass !== null && method_exists($entityConfigClass, 'getEntityTypes')) {
        $entityTypes = $entityConfigClass::getEntityTypes();
        foreach ($entityTypes as $entityType) {
          if (method_exists($entityConfigClass, 'getIdColumn')) {
            $idColumn = $entityConfigClass::getIdColumn($entityType);
            if (isset($row[$idColumn])) {
              return $entityType;
            }
          }
        }
      }
    }

    return null;
  }

  /**
   * Extract a field value from row using multiple possible field names
   *
   * Tries multiple field name patterns with entity type prefix:
   * - {entity}_{fieldName} (e.g., "products_name")
   * - {fieldName} (e.g., "name")
   *
   * @param array $row Database row
   * @param array $fieldNames Possible field names to try
   * @param string|null $entityType Entity type prefix
   * @param mixed $default Default value if field not found
   * @return mixed Field value or default
   */
  public function extractField(array $row, array $fieldNames, ?string $entityType, $default)
  {
    // Try with entity prefix first (e.g., "products_name")
    if (!empty($entityType)) {
      foreach ($fieldNames as $fieldName) {
        $prefixedKey = $entityType . '_' . $fieldName;
        if (isset($row[$prefixedKey])) {
          return $row[$prefixedKey];
        }
      }
    }

    // Try without prefix (e.g., "name")
    foreach ($fieldNames as $fieldName) {
      if (isset($row[$fieldName])) {
        return $row[$fieldName];
      }
    }

    return $default;
  }

  /**
   * Clear the entity config cache
   *
   * Useful for testing or when domain configuration changes
   *
   * @return void
   */
  public function clearCache(): void
  {
    $this->entityConfigCache = [];

    if ($this->debug) {
      error_log("[EntityDataExtractor] Cache cleared");
    }
  }
}
