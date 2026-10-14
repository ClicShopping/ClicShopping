<?php
/**
 * DynamicEntityHelper - Universal entity helper using DoctrineOrm
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\EntityHelpers;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\InterfacesAI\EntityHelperInterface;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * DynamicEntityHelper Class
 *
 * Universal entity helper that works with ANY entity type by dynamically discovering:
 * - Table name from entity type
 * - ID column name
 * - Name/title column name
 * 
 * This eliminates the need for hardcoded entity helpers (ProductEntityHelper, CategoryEntityHelper, etc.)
 * and provides a single solution that scales to 100+ entity types.
 * 
 * DESIGN PATTERN: Strategy Pattern + Dynamic Discovery
 * - Discovers table structure from database
 * - Adapts to any entity type
 * - Reuses DoctrineOrm for database operations
 * - Follows DRY principle
 * 
 * USAGE:
 * ```php
 * // Product
 * $productHelper = new DynamicEntityHelper('product', true);
 * $product = $productHelper->getById(123);
 * 
 * // Category
 * $categoryHelper = new DynamicEntityHelper('category', true);
 * $category = $categoryHelper->getById(456);
 * 
 * // Order
 * $orderHelper = new DynamicEntityHelper('order', true);
 * $order = $orderHelper->getById(789);
 * 
 * // Works with ANY entity type!
 * ```
 * 
 * @since 2026-01-21
 * @version 1.0.0
 */
class DynamicEntityHelper implements EntityHelperInterface
{
  private string $entityType;
  private string $tableName;
  private string $idColumn;
  private string $nameColumn;
  private ?SecurityLogger $logger = null;
  private bool $debug = false;

  /**
   * Constructor - Initialize with entity type
   * 
   * Dynamically discovers table structure from database.
   * 
   * @param string $entityType Entity type (product, category, order, customer, etc.)
   * @param bool $debug Enable debug logging
   * @throws \Exception If table or columns cannot be discovered
   */
  public function __construct(string $entityType, bool $debug = false)
  {
    $this->entityType = strtolower(trim($entityType));
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
    
    if (empty($this->entityType)) {
      throw new \Exception("Entity type cannot be empty");
    }
    
    // Discover table and columns dynamically
    $this->discoverTableStructure();
    
    if ($this->debug && $this->logger !== null) {
      $this->logger->logSecurityEvent(
        "DynamicEntityHelper: Initialized for {$this->entityType} - Table: {$this->tableName}, ID: {$this->idColumn}, Name: {$this->nameColumn}",
        'info'
      );
    }
  }

  /**
   * Discover table structure dynamically from database
   * 
   * Uses DoctrineOrm to find:
   * - Table name for entity type
   * - ID column name
   * - Name/title column name
   * 
   * @throws \Exception If table cannot be found
   */
  private function discoverTableStructure(): void
  {
    // Get all relevant tables from DoctrineOrm
    $allTables = DoctrineOrm::getRelevantTables();
    
    // Build table name
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $this->tableName = $prefix . $this->entityType;
    
    // Verify table exists
    if (!in_array($this->tableName, $allTables, true)) {
      throw new \Exception("Table not found for entity type: {$this->entityType}");
    }
    
    // Discover ID column
    $this->idColumn = $this->discoverIdColumn();
    
    // Discover name column
    $this->nameColumn = $this->discoverNameColumn();
  }

  /**
   * Discover ID column name dynamically
   * 
   * Tries common patterns:
   * - {entity}_id
   * - id
   * - {entity}s_id
   * 
   * @return string ID column name
   * @throws \Exception If ID column cannot be found
   */
  private function discoverIdColumn(): string
  {
    $candidates = [
      $this->entityType . '_id',
      'id',
      $this->entityType . 's_id',
    ];
    
    foreach ($candidates as $column) {
      if (DoctrineOrm::columnExists($this->tableName, $column)) {
        if ($this->debug && $this->logger !== null) {
          $this->logger->logSecurityEvent(
            "DynamicEntityHelper: Discovered ID column '{$column}' for {$this->entityType}",
            'info'
          );
        }
        return $column;
      }
    }
    
    throw new \Exception("Could not discover ID column for {$this->entityType}");
  }

  /**
   * Discover name column name dynamically
   * 
   * Tries common patterns:
   * - {entity}_name
   * - name
   * - title
   * - {entity}s_name
   * 
   * @return string Name column name (defaults to 'name' if not found)
   */
  private function discoverNameColumn(): string
  {
    $candidates = [
      $this->entityType . '_name',
      'name',
      'title',
      $this->entityType . 's_name',
    ];
    
    foreach ($candidates as $column) {
      if (DoctrineOrm::columnExists($this->tableName, $column)) {
        if ($this->debug && $this->logger !== null) {
          $this->logger->logSecurityEvent(
            "DynamicEntityHelper: Discovered name column '{$column}' for {$this->entityType}",
            'info'
          );
        }
        return $column;
      }
    }
    
    // Fallback to 'name' column
    if ($this->debug && $this->logger !== null) {
      $this->logger->logSecurityEvent(
        "DynamicEntityHelper: Using fallback name column 'name' for {$this->entityType}",
        'warning'
      );
    }
    
    return 'name';
  }

  /**
   * Get entity by ID (EntityHelperInterface implementation - static)
   * 
   * Note: Static method requires entity type context.
   * Use instance method getById() instead.
   * 
   * @param int $id Entity ID
   * @param int|null $languageId Language ID (optional)
   * @return array|null Entity data or null if not found
   */
  public static function getEntityById(int $id, ?int $languageId = null): ?array
  {
    throw new \Exception("Use instance method getById() instead of static getEntityById()");
  }

  /**
   * Get entity by ID (instance method)
   * 
   * Dynamically queries the discovered table
   * 
   * @param int $id Entity ID
   * @param int|null $languageId Language ID (optional)
   * @return array|null Entity data or null if not found
   */
  public function getById(int $id, ?int $languageId = null): ?array
  {
    try {
      $sql = "SELECT * FROM `{$this->tableName}` WHERE `{$this->idColumn}` = :id LIMIT 1";
      
      $result = DoctrineOrm::selectOne($sql, ['id' => $id]);
      
      if ($result === null) {
        if ($this->debug && $this->logger !== null) {
          $this->logger->logSecurityEvent(
            "DynamicEntityHelper: {$this->entityType} not found with ID {$id}",
            'warning'
          );
        }
        return null;
      }
      
      if ($this->debug && $this->logger !== null) {
        $this->logger->logSecurityEvent(
          "DynamicEntityHelper: Retrieved {$this->entityType} by ID {$id}",
          'info'
        );
      }
      
      return $result;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "DynamicEntityHelper: Error getting {$this->entityType} by ID {$id}: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Get entity name by ID (EntityHelperInterface implementation - static)
   * 
   * @param int $id Entity ID
   * @param int|null $languageId Language ID (optional)
   * @return string|null Entity name or null if not found
   */
  public static function getEntityName(int $id, ?int $languageId = null): ?string
  {
    throw new \Exception("Use instance method getNameById() instead of static getEntityName()");
  }

  /**
   * Get entity name by ID (instance method)
   * 
   * @param int $id Entity ID
   * @param int|null $languageId Language ID (optional)
   * @return string|null Entity name or null if not found
   */
  public function getNameById(int $id, ?int $languageId = null): ?string
  {
    try {
      $sql = "SELECT `{$this->nameColumn}` FROM `{$this->tableName}` WHERE `{$this->idColumn}` = :id LIMIT 1";
      
      $name = DoctrineOrm::selectValue($sql, ['id' => $id]);
      
      return $name ?: null;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "DynamicEntityHelper: Error getting {$this->entityType} name: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Get multiple entities by IDs (EntityHelperInterface implementation - static)
   * 
   * @param array $ids Array of entity IDs
   * @param int|null $languageId Language ID (optional)
   * @return array Array of entity data indexed by ID
   */
  public static function getEntitiesByIds(array $ids, ?int $languageId = null): array
  {
    throw new \Exception("Use instance method getByIds() instead of static getEntitiesByIds()");
  }

  /**
   * Get multiple entities by IDs (instance method)
   * 
   * @param array $ids Array of entity IDs
   * @param int|null $languageId Language ID (optional)
   * @return array Array of entity data indexed by ID
   */
  public function getByIds(array $ids, ?int $languageId = null): array
  {
    if (empty($ids)) {
      return [];
    }
    
    try {
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $sql = "SELECT * FROM `{$this->tableName}` WHERE `{$this->idColumn}` IN ({$placeholders})";
      
      $results = DoctrineOrm::select($sql, $ids);
      
      // Index by ID
      $indexed = [];
      foreach ($results as $row) {
        $indexed[$row[$this->idColumn]] = $row;
      }
      
      if ($this->debug && $this->logger !== null) {
        $this->logger->logSecurityEvent(
          "DynamicEntityHelper: Retrieved " . count($indexed) . " {$this->entityType} entities",
          'info'
        );
      }
      
      return $indexed;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "DynamicEntityHelper: Error getting {$this->entityType} by IDs: " . $e->getMessage(),
          'error'
        );
      }
      return [];
    }
  }

  /**
   * Check if entity exists (EntityHelperInterface implementation - static)
   * 
   * @param int $id Entity ID
   * @return bool True if entity exists
   */
  public static function entityExists(int $id): bool
  {
    throw new \Exception("Use instance method exists() instead of static entityExists()");
  }

  /**
   * Check if entity exists (instance method)
   * 
   * @param int $id Entity ID
   * @return bool True if entity exists
   */
  public function exists(int $id): bool
  {
    try {
      $sql = "SELECT COUNT(*) as count FROM `{$this->tableName}` WHERE `{$this->idColumn}` = :id";
      
      $count = DoctrineOrm::selectValue($sql, ['id' => $id]);
      
      return (int)$count > 0;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "DynamicEntityHelper: Error checking {$this->entityType} existence: " . $e->getMessage(),
          'error'
        );
      }
      return false;
    }
  }

  /**
   * Get entity type
   * 
   * @return string Entity type (product, category, order, etc.)
   */
  public function getEntityType(): string
  {
    return $this->entityType;
  }

  /**
   * Get table name
   * 
   * @return string Full table name with prefix
   */
  public function getTableName(): string
  {
    return $this->tableName;
  }

  /**
   * Get ID column name
   * 
   * @return string ID column name
   */
  public function getIdColumn(): string
  {
    return $this->idColumn;
  }

  /**
   * Get name column name
   * 
   * @return string Name column name
   */
  public function getNameColumn(): string
  {
    return $this->nameColumn;
  }
}
