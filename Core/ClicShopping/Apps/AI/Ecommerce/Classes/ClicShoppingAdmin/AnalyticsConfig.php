<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Rag\EmbeddingTableDiscovery;
use ClicShopping\AI\DomainsAI\Shared\Entity\EntityRegistry;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * AnalyticsConfig Class
 *
 * Provides DYNAMIC analytics configuration for the Ecommerce domain.
 * 
 * DESIGN PRINCIPLES:
 * - DRY: Reuses EmbeddingTableDiscovery::discover() for table discovery
 * - DRY: Reuses EntityRegistry::getIdColumnForEntityType() for ID columns
 * - DRY: Reuses DoctrineOrm for schema introspection
 * - SIMPLE: Uses relevant analytics fields (no restrictive filtering)
 * - FLEXIBLE: Hooks can customize field selection per use case
 * - DYNAMIC: No hardcoded entity lists or column mappings
 *
 * Usage:
 * ```php
 * $config = AnalyticsConfig::getEntityMappings();
 * $productColumns = $config['products']['columns'];
 * ```
 */


class AnalyticsConfig
{
  private static ?array $configCache = null;
  private static ?EmbeddingTableDiscovery $ragManager = null;
  private static ?EntityRegistry $entityRegistry = null;

  /**
   * Get analytics entity mappings for the Ecommerce domain
   *
   * DYNAMIC DISCOVERY:
   * 1. Uses EmbeddingTableDiscovery::discover() to discover tables
   * 2. Uses EntityRegistry::getIdColumnForEntityType() for ID columns
   * 3. Uses DoctrineOrm to introspect table schema
   * 4. Returns analytics-relevant fields (numeric, date, text fields)
   * 5. Caches results for performance
   *
   * @param bool $useCache Whether to use cached configuration
   * @return array Associative array of entity mappings with table and columns
   */
  public static function getEntityMappings(bool $useCache = true): array
  {
    if ($useCache && self::$configCache !== null) {
      return self::$configCache;
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    try {
      $ragManager = self::getRAGManager();
      $entityRegistry = self::getEntityRegistry();
      
      // Discover all embedding tables via the lightweight service
      $embeddingTables = $ragManager->discover(false);
      
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
        
        // Get analytics-relevant fields from table schema using DoctrineOrm
        $analyticsFields = self::getAnalyticsColumns($table, $idColumn);
        
        if (!empty($analyticsFields)) {
          $config[$entityType] = [
            'table' => $table,
            'id_column' => $idColumn,
            'columns' => $analyticsFields,  // Analytics-relevant columns
            'entity_type' => $entityType,
            'display_name' => ucwords(str_replace('_', ' ', $entityType)) // Dynamic
          ];
        }
      }
      
      self::$configCache = $config;
      return $config;
      
    } catch (\Exception $e) {
      error_log("AnalyticsConfig: Failed to discover analytics mappings: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get analytics-relevant columns from table schema
   *
   * Filters columns to include only those useful for analytics:
   * - ID columns (for grouping)
   * - Numeric columns (for aggregation: SUM, AVG, COUNT, MIN, MAX)
   * - Date/timestamp columns (for temporal analysis)
   * - Text columns (for filtering and grouping)
   * - Status columns (for categorization)
   *
   * Excludes:
   * - System timestamps (created_at, updated_at, deleted_at)
   * - Binary/blob columns
   * - Encrypted columns
   * - Internal system columns
   *
   * @param string $tableName Full table name
   * @param string $idColumn ID column to include
   * @return array List of analytics-relevant column names
   */
  private static function getAnalyticsColumns(string $tableName, string $idColumn): array
  {
    try {
      // Use existing DoctrineOrm::getTableColumns() method (DRY!)
      $allFields = DoctrineOrm::getTableColumns($tableName);
      
      // Get column types for filtering
      $columnTypes = self::getColumnTypes($tableName);
      
      // Filter for analytics-relevant columns
      $analyticsFields = array_values(array_filter($allFields, function($field) use ($idColumn, $columnTypes) {
        // Always include ID column
        if ($field === $idColumn) {
          return true;
        }
        
        // Exclude system timestamp fields
        if (in_array($field, ['created_at', 'updated_at', 'deleted_at'])) {
          return false;
        }
        
        // Get column type
        $type = $columnTypes[$field] ?? 'string';
        
        // Include numeric columns (for aggregation)
        if (in_array($type, ['int', 'bigint', 'smallint', 'decimal', 'float', 'double'])) {
          return true;
        }
        
        // Include date/timestamp columns (for temporal analysis)
        if (in_array($type, ['date', 'datetime', 'timestamp', 'time'])) {
          return true;
        }
        
        // Include text columns (for filtering and grouping)
        if (in_array($type, ['varchar', 'char', 'text', 'enum'])) {
          return true;
        }
        
        // Exclude everything else (binary, blob, json, etc.)
        return false;
      }));
      
      return $analyticsFields;
      
    } catch (\Exception $e) {
      error_log("AnalyticsConfig: Failed to get analytics columns: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get column types from table schema
   *
   * Uses DoctrineOrm to introspect table and get column data types.
   * This helps filter columns for analytics relevance.
   *
   * @param string $tableName Full table name
   * @return array Associative array of column names to types
   */
  private static function getColumnTypes(string $tableName): array
  {
    try {
      // Try to get column types from DoctrineOrm if available
      // Fallback to empty array if method doesn't exist
      if (method_exists(DoctrineOrm::class, 'getColumnTypes')) {
        return DoctrineOrm::getColumnTypes($tableName);
      }
      
      return [];
      
    } catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Get or create the lightweight embedding-table discovery service
   * (was MultiDBRAGManager, instantiated only to list embedding tables).
   */
  private static function getRAGManager(): EmbeddingTableDiscovery
  {
    if (self::$ragManager === null) {
      self::$ragManager = new EmbeddingTableDiscovery();
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

  /**
   * Get analytics mapping for a specific entity type
   *
   * @param string $entityType Entity type (e.g., 'products', 'orders')
   * @return array|null Analytics mapping or null if not found
   */
  public static function getEntityMapping(string $entityType): ?array
  {
    $config = self::getEntityMappings();
    return $config[$entityType] ?? null;
  }

  /**
   * Get all entity types with analytics mappings
   *
   * @return array List of entity types
   */
  public static function getEntityTypes(): array
  {
    return array_keys(self::getEntityMappings());
  }

  /**
   * Get table name for an entity type
   *
   * @param string $entityType Entity type
   * @return string|null Table name or null if not found
   */
  public static function getTableName(string $entityType): ?string
  {
    $mapping = self::getEntityMapping($entityType);
    return $mapping['table'] ?? null;
  }

  /**
   * Get ID column for an entity type
   *
   * @param string $entityType Entity type
   * @return string|null ID column name or null if not found
   */
  public static function getIdColumn(string $entityType): ?string
  {
    $mapping = self::getEntityMapping($entityType);
    return $mapping['id_column'] ?? null;
  }

  /**
   * Get analytics columns for an entity type
   *
   * @param string $entityType Entity type
   * @return array List of analytics-relevant columns
   */
  public static function getColumns(string $entityType): array
  {
    $mapping = self::getEntityMapping($entityType);
    return $mapping['columns'] ?? [];
  }

  /**
   * Get display name for an entity type
   *
   * @param string $entityType Entity type
   * @return string Display name
   */
  public static function getDisplayName(string $entityType): string
  {
    $mapping = self::getEntityMapping($entityType);
    return $mapping['display_name'] ?? ucfirst($entityType);
  }

  /**
   * Check if an entity type has analytics mappings
   *
   * @param string $entityType Entity type
   * @return bool True if entity has analytics mappings
   */
  public static function hasEntityType(string $entityType): bool
  {
    $config = self::getEntityMappings();
    return isset($config[$entityType]);
  }

  // ========================================
  // Field Pattern Methods (E-commerce specific)
  // ========================================

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
