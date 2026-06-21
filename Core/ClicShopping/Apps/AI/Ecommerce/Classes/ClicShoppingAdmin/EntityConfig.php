<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\DomainsAI\Shared\Entity\EntityRegistry;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * EntityConfig Class
 *
 * Provides DYNAMIC entity configuration for the Ecommerce domain.
 * 
 * DESIGN PRINCIPLES:
 * - DRY: Reuses MultiDBRAGManager::knownEmbeddingTable() for table discovery
 * - DRY: Reuses EntityRegistry::getIdColumnForEntityType() for ID columns
 * - SIMPLE: Uses ALL table fields (no restrictive filtering)
 * - FLEXIBLE: Hooks can customize field selection per use case
 * - DYNAMIC: No hardcoded entity lists
 *
 * Usage:
 * ```php
 * $config = EntityConfig::getConfig();
 * $productEntity = $config['products'];
 * ```
 */


class EntityConfig
{
  private static ?array $configCache = null;
  private static ?MultiDBRAGManager $ragManager = null;
  private static ?EntityRegistry $entityRegistry = null;

  /**
   * Get entity configuration for the Ecommerce domain
   *
   * DYNAMIC DISCOVERY:
   * 1. Uses MultiDBRAGManager::knownEmbeddingTable() to discover tables
   * 2. Uses EntityRegistry::getIdColumnForEntityType() for ID columns
   * 3. Uses DoctrineOrm to introspect table schema
   * 4. Returns ALL fields (no restrictive filtering)
   *
   * @param bool $useCache Whether to use cached configuration
   * @return array Associative array of entity configurations
   */
  public static function getConfig(bool $useCache = true): array
  {
    if ($useCache && self::$configCache !== null) {
      return self::$configCache;
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    try {
      $ragManager = self::getRAGManager();
      $entityRegistry = self::getEntityRegistry();
      
      // Use MultiDBRAGManager to discover all embedding tables
      $embeddingTables = $ragManager->knownEmbeddingTable(false);
      
      if (empty($embeddingTables)) {
        return [];
      }
      
      $config = [];
      
      foreach ($embeddingTables as $embeddingTable) {
        // Extract entity type: clic_products_embedding -> products
        $entityType = str_replace([$prefix, '_embedding'], '', $embeddingTable);
        $table = $prefix . $entityType;
        
        // Get ID column from EntityRegistry (reuse existing logic)
        $idColumn = $entityRegistry->getIdColumnForEntityType($entityType);
        
        // Get ALL fields from table schema using DoctrineOrm
        $allFields = self::getTableColumns($table, $idColumn);

        // Skip AI-internal embedding stores that have NO matching business table:
        // "table doesn't exist" while building the NL-to-SQL schema. getTableColumns()
        // returns [] for a non-existent table, so an empty field set means "no real
        // business table" → skip.
        if (empty($allFields)) {
          continue;
        }

        $config[$entityType] = [
          'table' => $table,
          'id_column' => $idColumn,
          'embedding_table' => $embeddingTable,
          'description_fields' => $allFields,  // ALL fields
          'searchable_fields' => $allFields,   // ALL fields
          'entity_type' => $entityType,
          'display_name' => ucwords(str_replace('_', ' ', $entityType)) // Dynamic
        ];
      }
      
      self::$configCache = $config;
      return $config;
      
    } catch (\Exception $e) {
      error_log("EntityConfig: Failed to discover entities: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get table columns using DoctrineOrm (reuses existing method)
   *
   * @param string $tableName Full table name
   * @param string $idColumn ID column to exclude
   * @return array List of column names
   */
  private static function getTableColumns(string $tableName, string $idColumn): array
  {
    try {
      // Use existing DoctrineOrm::getTableColumns() method (DRY!)
      $fields = DoctrineOrm::getTableColumns($tableName);
      
      // Exclude only ID and system timestamp fields
      return array_values(array_filter($fields, function($field) use ($idColumn) {
        return $field !== $idColumn 
          && !in_array($field, ['created_at', 'updated_at', 'deleted_at']);
      }));
      
    } catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Get or create MultiDBRAGManager instance
   */
  private static function getRAGManager(): MultiDBRAGManager
  {
    if (self::$ragManager === null) {
      self::$ragManager = new MultiDBRAGManager();
    }
    return self::$ragManager;
  }

  /**
   * Get or create EntityRegistry instance
   */
  private static function getEntityRegistry(): EntityRegistry
  {
    if (self::$entityRegistry === null) {
      self::$entityRegistry = EntityRegistry::getInstance();
    }
    return self::$entityRegistry;
  }

  /**
   * Clear configuration cache
   */
  public static function clearCache(): void
  {
    self::$configCache = null;
    self::$ragManager = null;
    self::$entityRegistry = null;
  }

  // ========================================
  // Public accessor methods
  // ========================================

  public static function getEntityConfig(string $entityType): ?array
  {
    $config = self::getConfig();
    return $config[$entityType] ?? null;
  }

  public static function getEntityTypes(): array
  {
    return array_keys(self::getConfig());
  }

  public static function getTableName(string $entityType): ?string
  {
    $config = self::getEntityConfig($entityType);
    return $config['table'] ?? null;
  }

  public static function getIdColumn(string $entityType): ?string
  {
    $config = self::getEntityConfig($entityType);
    return $config['id_column'] ?? null;
  }

  public static function getEmbeddingTable(string $entityType): ?string
  {
    $config = self::getEntityConfig($entityType);
    return $config['embedding_table'] ?? null;
  }

  public static function getDescriptionFields(string $entityType): array
  {
    $config = self::getEntityConfig($entityType);
    return $config['description_fields'] ?? [];
  }

  public static function getSearchableFields(string $entityType): array
  {
    $config = self::getEntityConfig($entityType);
    return $config['searchable_fields'] ?? [];
  }

  public static function hasEntityType(string $entityType): bool
  {
    $config = self::getConfig();
    return isset($config[$entityType]);
  }

  public static function getDisplayName(string $entityType): string
  {
    $config = self::getEntityConfig($entityType);
    return $config['display_name'] ?? ucfirst($entityType);
  }
}
