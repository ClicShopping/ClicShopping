<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Entity;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Rag\EmbeddingTableDiscovery;

/**
 * EntityRegistry Class
 *
 * Centralized registry for entity table mappings, ID columns, and metadata.
 * Provides a single source of truth for entity detection across all components.
 *
 * Features:
 * - Dynamic table discovery from database
 * - Static fallback for known tables
 * - ID column mapping for entity extraction
 * - Memory table integration
 * - Extensible for new entity types
 *
 * Usage:
 * ```php
 * $registry = EntityRegistry::getInstance();
 * $idColumn = $registry->getIdColumnForTable('products');
 * $entityType = $registry->getEntityTypeForTable('products');
 * $allTables = $registry->getAllEntityTables();
 * ```
 */
class EntityRegistry
{
  private static ?EntityRegistry $instance = null;
  private array $entityTableCache = [];
  private array $idColumnCache = [];
  private array $knownEntityTypeCache = [];
  private array $classificationCache = [];
  private ?SecurityLogger $securityLogger = null;
  private bool $debug = false;
  private ?\PDO $db = null;

  /**
   * Private constructor for singleton pattern
   */
  private function __construct()
  {
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') 
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    
    $this->securityLogger = new SecurityLogger();
    
    if (Registry::exists('Db')) {
      $this->db = Registry::get('Db');
    }
  }

  /**
   * Get singleton instance
   *
   * @return EntityRegistry
   */
  public static function getInstance(): EntityRegistry
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    
    return self::$instance;
  }

  /**
   * Get all entity tables (embedding tables + memory tables)
   *
   * This method combines:
   * 1. Dynamically discovered embedding tables (*_embedding)
   * 2. Memory-related tables (rag_conversation_memory, rag_memory_retention_log)
   * 3. Static fallback list if dynamic discovery fails
   *
   * @param bool $useCache Whether to use cached results (default: true)
   * @return array List of all entity table names (with prefix)
   */
  public function getAllEntityTables(bool $useCache = true): array
  {
    if ($useCache && !empty($this->entityTableCache)) {
      return $this->entityTableCache;
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $allTables = [];

    // 1. Get embedding tables (dynamically discovered)
    $embeddingTables = $this->discoverEmbeddingTables();
    $allTables = array_merge($allTables, $embeddingTables);

    // 2. Add memory-related tables (static list)
    $memoryTables = $this->getMemoryTables();
    $allTables = array_merge($allTables, $memoryTables);

    // 3. Remove duplicates and sort
    $allTables = array_unique($allTables);
    sort($allTables);

    // Cache the results
    $this->entityTableCache = $allTables;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "EntityRegistry: Discovered " . count($allTables) . " entity tables",
        'info',
        ['tables' => $allTables]
      );
    }

    return $allTables;
  }

  /**
   * Discover embedding tables dynamically from database
   *
   * Uses DoctrineOrm::getEmbeddingTables() which already implements
   * comprehensive table discovery with multiple fallback methods
   *
   * @return array List of embedding table names
   */
  private function discoverEmbeddingTables(): array
  {
    try {
      // Use existing DoctrineOrm method which already handles:
      // 1. INFORMATION_SCHEMA query for vector columns
      // 2. SHOW TABLES LIKE fallback
      // 3. Hardcoded fallback with validation
      $tables = DoctrineOrm::getEmbeddingTables();

      if (!empty($tables)) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Discovered " . count($tables) . " embedding tables via DoctrineOrm",
            'info'
          );
        }
        return $tables;
      }

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Failed to discover embedding tables via DoctrineOrm: " . $e->getMessage(),
        'warning'
      );
    }

    // Final fallback: Static list of known embedding tables
    return $this->getStaticEmbeddingTables();
  }

  /**
   * Get static list of known embedding tables
   *
   * Uses EmbeddingTableDiscovery::discover() which already provides
   * a comprehensive static list with dynamic discovery fallback
   *
   * @return array List of embedding table names
   */
  private function getStaticEmbeddingTables(): array
  {
    try {
      $discovery = new EmbeddingTableDiscovery($this->debug, $this->securityLogger);
      return $discovery->discover(false); // false = bypass cache for fresh data
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Failed to get embedding tables from EmbeddingTableDiscovery: " . $e->getMessage(),
        'warning'
      );
      
      // Ultimate fallback: minimal list
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      return [
        $prefix . 'products_embedding',
        $prefix . 'categories_embedding',
        $prefix . 'pages_manager_embedding',
      ];
    }
  }

  /**
   * Get memory-related tables
   *
   * These tables store conversation memory and retention data
   *
   * @return array List of memory table names
   */
  /**
   * Get memory tables used by the RAG system
   * 
   * IMPORTANT: Table Naming Convention
   * - Tables with '_embedding' suffix contain vector embeddings for semantic search
   * - Tables without suffix are system/operational tables without embeddings
   * 
   * See DomainsAI/Shared/README.md (Entity/) for the naming convention
   * 
   * @return array List of memory table names with prefix
   */
  private function getMemoryTables(): array
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    return [
      $prefix . 'rag_conversation_memory_embedding',  // Embedding table: conversation history with vectors
      $prefix . 'rag_memory_retention_log',           // System table: retention logs (no embeddings)
      $prefix . 'rag_correction_patterns_embedding',  // Embedding table: correction patterns with vectors
      $prefix . 'rag_web_cache_embedding',            // Embedding table: web cache with vectors
    ];
  }

  /**
   * Get ID column name for a given table
   *
   * Returns the primary key column name used for entity_id extraction
   *
   * @param string $tableName Full table name (with prefix)
   * @return string|null ID column name or null if not found
   */
  public function getIdColumnForTable(string $tableName): ?string
  {
    // Check cache first
    if (isset($this->idColumnCache[$tableName])) {
      return $this->idColumnCache[$tableName];
    }

    // A satellite reports its target's type, so it also borrows its target's id column:
    // that is exactly what its entity_id holds.
    $idColumn = $this->getIdColumnForEntityType($this->getEntityTypeForTable($tableName));
    
    // Cache the result
    $this->idColumnCache[$tableName] = $idColumn;
    
    return $idColumn;
  }

  /**
   * Get ID column name for a given entity type
   *
   * Handles special cases and standard patterns
   * 
   * IMPORTANT: Entity types here do NOT include '_embedding' suffix
   * The suffix is stripped before this method is called
   * See DomainsAI/Shared/README.md (Entity/) for the naming convention
   *
   * @param string $entityType Entity type (e.g., 'products', 'categories')
   * @return string|null ID column name or null if not determinable
   */
  public function getIdColumnForEntityType(string $entityType): ?string
  {
    // Special cases where the ID column doesn't follow the standard pattern
    // Note: entityType has already had '_embedding' suffix stripped by getIdColumnForTable()
    $specialCases = [
      'pages_manager' => 'pages_id',
      'return_orders' => 'return_id',
      'reviews_sentiment' => 'id',
      'rag_conversation_memory' => 'id',        // Maps to rag_conversation_memory_embedding table
      'rag_memory_retention_log' => 'id',       // System table (no embedding)
      'rag_correction_patterns' => 'id',        // Maps to rag_correction_patterns_embedding table
      'rag_web_cache' => 'id',
    ];

    if (isset($specialCases[$entityType])) {
      return $specialCases[$entityType];
    }

    // Standard pattern: {entity_type}_id
    // Most tables use the plural form directly (products_id, categories_id, etc.)
    return $entityType . '_id';
  }

  /**
   * Get entity type for a given table name
   *
   * A satellite store ('products_seo', 'reviews_sentiment') carries the id of ANOTHER entity,
   * so it reports its target's type; only a store related to no entity keeps its own name.
   *
   * @param string $tableName Full table name (with prefix)
   * @return string Entity type (e.g., 'products', 'categories')
   */
  public function getEntityTypeForTable(string $tableName): string
  {
    $classification = $this->getTableClassification();

    if (isset($classification[$tableName]['entity_type'])) {
      return $classification[$tableName]['entity_type'];
    }

    return $this->deriveNameFromTable($tableName);
  }

  /**
   * Derive the name a table declares, without interpreting it
   *
   * @param string $tableName Full table name (with prefix)
   * @return string
   */
  private function deriveNameFromTable(string $tableName): string
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    return str_replace([$prefix, '_embedding'], '', $tableName);
  }

  /**
   * Classify every known table as entity / satellite / system
   *
   * Memoized: the classification reads INFORMATION_SCHEMA through DoctrineOrm (itself cached),
   * and every entity-vocabulary accessor below is built on it.
   *
   * @return array<string, array{kind: string, entity_type: string|null}>
   */
  private function getTableClassification(): array
  {
    if ($this->classificationCache !== []) {
      return $this->classificationCache;
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    $this->classificationCache = EntityTableClassifier::classify(
      $this->getAllEntityTables(),
      $prefix,
      DoctrineOrm::getTableColumns(...),
      $this->getIdColumnForEntityType(...)
    );

    return $this->classificationCache;
  }

  /**
   * Tables whose nature is the given kind
   *
   * @param string $kind One of EntityTableClassifier::ENTITY|SATELLITE|SYSTEM
   * @return array<string, array{kind: string, entity_type: string|null}>
   */
  private function getTablesOfKind(string $kind): array
  {
    return array_filter(
      $this->getTableClassification(),
      static fn(array $entry): bool => $entry['kind'] === $kind
    );
  }

  /**
   * Get the canonical entity type vocabulary of this install
   *
   * The canonical form is the table-name form ('products', 'categories', 'pages_manager'): it is
   * the only one derivable from the schema, so it needs no per-domain singular/plural dictionary.
   *
   * Only entity tables contribute: a satellite already resolves to its target's type, and a
   * system store is not a nature of entity at all.
   *
   * @return array List of canonical entity types
   */
  public function getKnownEntityTypes(): array
  {
    if ($this->knownEntityTypeCache !== []) {
      return $this->knownEntityTypeCache;
    }

    $types = [];

    foreach ($this->getTablesOfKind(EntityTableClassifier::ENTITY) as $entry) {
      if ($entry['entity_type'] !== null && $entry['entity_type'] !== '') {
        $types[$entry['entity_type']] = true;
      }
    }

    // An install with discovered tables but NO entity among them means the schema could not be
    // read (classification needs INFORMATION_SCHEMA), not that this install has no entity.
    // Publishing an empty vocabulary would silence entity tracking everywhere, so degrade to
    // the table names — the pre-classification behaviour — and say so.
    if ($types === [] && $this->getAllEntityTables() !== []) {
      $this->securityLogger->logApplicationError(
        'EntityRegistry: no entity table classified, falling back to table-name vocabulary'
      );

      foreach ($this->getAllEntityTables() as $tableName) {
        $name = $this->deriveNameFromTable($tableName);

        if ($name !== '') {
          $types[$name] = true;
        }
      }
    }

    $this->knownEntityTypeCache = array_keys($types);

    return $this->knownEntityTypeCache;
  }

  /**
   * Normalize an entity type to the canonical form
   *
   * entity_type is written by several producers and compared by consumers, so it needs one form.
   * The agnostic layer derives it from the table name, while a domain subsystem may author its own
   * singular form in the embedding metadata it owns (SEO writes 'product' / 'category'); that label
   * travels verbatim into conversation memory unless it is coerced here.
   *
   * A value that matches no entity table is returned as-is: 'general' / 'unknown' are not entities,
   * and inventing a type would be worse than leaving the producer's label alone.
   *
   * @param string $entityType Entity type in any producer's form
   * @return string Canonical entity type, or the hygienized input when no entity table matches
   */
  public function normalizeEntityType(string $entityType): string
  {
    $normalized = EntityHelper::normalizeEntityType($entityType);

    if ($normalized === '') {
      return $normalized;
    }

    try {
      $known = $this->getKnownEntityTypes();
    } catch (\Exception $e) {
      // Degrade to the producer's label: losing entity tracking would be worse than losing the form
      $this->securityLogger->logApplicationError(
        'EntityRegistry: cannot normalize entity type, registry unavailable: ' . $e->getMessage()
      );

      return $normalized;
    }

    if (in_array($normalized, $known, true)) {
      return $normalized;
    }

    $plural = EntityHelper::getPluralForm($normalized);

    return in_array($plural, $known, true) ? $plural : $normalized;
  }

  /**
   * Get ID column mappings for all entity tables
   *
   * Returns an associative array mapping ID column names to entity types
   * Used for entity extraction (see extractEntityFromRow)
   *
   * The bare 'id' column is claimed by several system tables (reviews_sentiment, rag_*), which
   * would overwrite each other and label any `SELECT id, …` row with whichever came last.
   * It stays generic: no specific type may own it.
   *
   * Only entity tables contribute: a satellite has no id column of its own, so mapping one
   * would invent a column no query can ever return.
   *
   * @return array Associative array [id_column => entity_type]
   */
  public function getIdColumnMappings(): array
  {
    $mappings = [];

    foreach ($this->getTablesOfKind(EntityTableClassifier::ENTITY) as $entry) {
      $entityType = (string)$entry['entity_type'];
      $idColumn = $this->getIdColumnForEntityType($entityType);

      if ($idColumn && $idColumn !== 'id') {
        $mappings[$idColumn] = $entityType;
      }
    }

    $mappings['id'] = 'generic';

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "EntityRegistry: Generated " . count($mappings) . " ID column mappings",
        'info',
        ['mappings' => $mappings]
      );
    }

    return $mappings;
  }

  /**
   * Extract entity_id and entity_type from a result row
   *
   * Single source of truth for entity extraction: the registry owns the id_column => entity_type
   * mapping, so it owns the lookup. A falsy id yields no entity — an entity_type without a usable
   * id would still reach the last_entity path.
   *
   * @param array $row A single result row (column => value)
   * @return array ['entity_id' => int|null, 'entity_type' => string|null]
   */
  public function extractEntityFromRow(array $row): array
  {
    if ($row === []) {
      return ['entity_id' => null, 'entity_type' => null];
    }

    foreach ($this->getIdColumnMappings() as $idColumn => $entityType) {
      if (!empty($row[$idColumn])) {
        return [
          'entity_id' => (int)$row[$idColumn],
          'entity_type' => $entityType,
        ];
      }
    }

    return ['entity_id' => null, 'entity_type' => null];
  }

  /**
   * Check if a table is an entity table
   *
   * @param string $tableName Full table name (with prefix)
   * @return bool True if it's an entity table
   */
  public function isEntityTable(string $tableName): bool
  {
    $allTables = $this->getAllEntityTables();
    return in_array($tableName, $allTables, true);
  }

  /**
   * Check if a table is an embedding table
   *
   * @param string $tableName Full table name (with prefix)
   * @return bool True if it's an embedding table
   */
  public function isEmbeddingTable(string $tableName): bool
  {
    return str_ends_with($tableName, '_embedding');
  }

  /**
   * Check if a table is a memory table
   *
   * @param string $tableName Full table name (with prefix)
   * @return bool True if it's a memory table
   */
  public function isMemoryTable(string $tableName): bool
  {
    $memoryTables = $this->getMemoryTables();
    return in_array($tableName, $memoryTables, true);
  }

  /**
   * Get table metadata
   *
   * Returns comprehensive metadata about a table
   *
   * @param string $tableName Full table name (with prefix)
   * @return array Metadata array
   */
  public function getTableMetadata(string $tableName): array
  {
    return [
      'table_name' => $tableName,
      'entity_type' => $this->getEntityTypeForTable($tableName),
      'id_column' => $this->getIdColumnForTable($tableName),
      'is_embedding_table' => $this->isEmbeddingTable($tableName),
      'is_memory_table' => $this->isMemoryTable($tableName),
      'is_entity_table' => $this->isEntityTable($tableName),
    ];
  }

  /**
   * Clear all caches
   *
   * Forces re-discovery of tables on next access
   *
   * @return void
   */
  public function clearCache(): void
  {
    $this->entityTableCache = [];
    $this->idColumnCache = [];
    $this->knownEntityTypeCache = [];
    $this->classificationCache = [];

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "EntityRegistry: Cache cleared",
        'info'
      );
    }
  }

  /**
   * Validate entity types against actual database data
   *
   * Queries each embedding table to discover what 'type' values are actually stored.
   * This helps validate that Hooks are using the correct entity types when creating embeddings.
   *
   * Returns an associative array mapping table names to their discovered types and counts.
   *
   * Example output:
   * ```php
   * [
   *   'products_embedding' => ['products' => 150],
   *   'categories_embedding' => ['categories' => 25],
   *   'pages_manager_embedding' => ['pages_manager' => 10]
   * ]
   * ```
   *
   * @return array Associative array [table_name => [type => count]]
   */
  public function validateEntityTypes(): array
  {
    $discoveredTypes = [];
    $allTables = $this->getAllEntityTables();

    foreach ($allTables as $tableName) {
      if ($this->isEmbeddingTable($tableName)) {
        try {
          // Query actual 'type' values from embedding table
          $query = $this->db->prepare("
            SELECT DISTINCT type, COUNT(*) as count 
            FROM {$tableName} 
            WHERE type IS NOT NULL
            GROUP BY type
          ");
          $query->execute();
          $types = $query->fetchAll(\PDO::FETCH_KEY_PAIR);

          if (!empty($types)) {
            $discoveredTypes[$tableName] = $types;
          }

        } catch (\Exception $e) {
          // Table might not have 'type' column or might be empty
          if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
              "EntityRegistry: Could not validate types for table {$tableName}: " . $e->getMessage(),
              'debug'
            );
          }
        }
      }
    }

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "EntityRegistry: Validated entity types for " . count($discoveredTypes) . " tables",
        'info',
        ['discovered_types' => $discoveredTypes]
      );
    }

    return $discoveredTypes;
  }

  /**
   * Register entity table metadata at runtime (IN-MEMORY ONLY)
   *
   * ⚠️  IMPORTANT: This method does NOT create tables in the database!
   * It only registers metadata in memory for the current request.
   *
   * Use cases:
   * - Override ID column mapping for an existing table
   * - Add metadata for a table that exists but isn't auto-discovered
   * - Temporary metadata registration for testing
   *
   * The registration is lost when the request ends or cache is cleared.
   * To permanently add a new entity type, create the table in the database
   * and it will be auto-discovered on next request.
   *
   * @param string $tableName Full table name (with prefix) - MUST EXIST in database
   * @param string $idColumn ID column name for this table
   * @param string|null $entityType Entity type (optional, will be derived from table name)
   * @return void
   */
  public function registerEntityTable(string $tableName, string $idColumn, ?string $entityType = null): void
  {
    // Add to entity table cache (IN-MEMORY ONLY)
    if (!in_array($tableName, $this->entityTableCache, true)) {
      $this->entityTableCache[] = $tableName;
    }

    // Add to ID column cache (IN-MEMORY ONLY)
    $this->idColumnCache[$tableName] = $idColumn;

    // The canonical vocabulary is derived from the table list, so a late registration must reset it
    $this->knownEntityTypeCache = [];
    $this->classificationCache = [];

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "EntityRegistry: Registered entity table metadata (in-memory only)",
        'info',
        [
          'table_name' => $tableName,
          'id_column' => $idColumn,
          'entity_type' => $entityType ?? $this->getEntityTypeForTable($tableName),
          'note' => 'No database changes made - metadata only'
        ]
      );
    }
  }
}
