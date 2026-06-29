<?php
/**
 * EntityTypeRegistry - Dynamic Entity Type Management
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Rag\EmbeddingTableDiscovery;

/**
 * EntityTypeRegistry Class
 *
 * Dynamically discovers and manages entity types from embedding tables.
 * Replaces hardcoded entity type lists with automatic discovery.
 *
 * Features:
 * - Auto-discovery of embedding tables from EmbeddingTableDiscovery
 * - Caching for performance
 * - Mapping between table names and entity types
 * - Support for custom entity types
 * - No code duplication - uses the lightweight EmbeddingTableDiscovery
 */

class EntityTypeRegistry
{
  private static ?EntityTypeRegistry $instance = null;
  private SecurityLogger $logger;
  private bool $debug;
  private array $entityTypes = [];
  private array $tableToEntityMap = [];
  private array $entityToTableMap = [];
  private bool $initialized = false;
  private string $prefix;
  private ?EmbeddingTableDiscovery $ragManager = null;

  /**
   * Private constructor for singleton pattern
   */
  private function __construct()
  {
    $this->logger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    // MultiDBRAGManager god-class, instantiated here only to list embedding tables).
    try {
      $this->ragManager = new EmbeddingTableDiscovery($this->debug, $this->logger);
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Failed to initialize EmbeddingTableDiscovery: " . $e->getMessage(),
        'warning'
      );
      $this->ragManager = null;
    }
  }

  /**
   * Get singleton instance
   *
   * @return EntityTypeRegistry
   */
  public static function getInstance(): EntityTypeRegistry
  {
    if (self::$instance === null) {
      self::$instance = new EntityTypeRegistry();
    }
    return self::$instance;
  }

  /**
   * Get all entity types
   *
   * @return array List of entity types
   */
  public function getAllEntityTypes(): array
  {
    if (!$this->initialized) {
      $this->initialize();
    }

    return $this->entityTypes;
  }

  /**
   * Initialize entity types from database
   *
   * @param bool $forceRefresh Force refresh from database
   * @return void
   */
  public function initialize(bool $forceRefresh = false): void
  {
    if ($this->initialized && !$forceRefresh) {
      return;
    }

    try {
      // Discover embedding tables from database
      $embeddingTables = $this->discoverEmbeddingTables();

      // Build entity type mappings
      foreach ($embeddingTables as $tableName) {
        $entityType = $this->extractEntityTypeFromTable($tableName);

        if ($entityType !== null) {
          $this->entityTypes[] = $entityType;
          $this->tableToEntityMap[$tableName] = $entityType;
          $this->entityToTableMap[$entityType] = $tableName;
        }
      }

      // Remove duplicates
      $this->entityTypes = array_unique($this->entityTypes);

      $this->initialized = true;

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "EntityTypeRegistry initialized with " . count($this->entityTypes) . " entity types: " .
          implode(', ', $this->entityTypes),
          'info'
        );
      }

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error initializing EntityTypeRegistry: " . $e->getMessage(),
        'error'
      );

      // Fallback to static list
      $this->initializeFallback();
    }
  }

  /**
   * Discover embedding tables from EmbeddingTableDiscovery
   *
   * Uses EmbeddingTableDiscovery::discover() instead of duplicating code
   *
   * @return array List of embedding table names
   */
  private function discoverEmbeddingTables(): array
  {
    $tables = [];

    try {
      if ($this->ragManager !== null) {
        // Discover embedding tables via the lightweight service
        $allTables = $this->ragManager->discover(true);

        // Filter out system tables
        foreach ($allTables as $tableName) {
          if (!self::isSystemTable($tableName)) {
            $tables[] = $tableName;
          }
        }

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Discovered " . count($tables) . " embedding tables from EmbeddingTableDiscovery (filtered from " . count($allTables) . " total)",
            'info'
          );
        }
      } else {
        throw new \Exception("EmbeddingTableDiscovery not available");
      }

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error discovering embedding tables from EmbeddingTableDiscovery: " . $e->getMessage(),
        'warning'
      );

      // Fallback: try direct database query
      $tables = $this->discoverEmbeddingTablesDirectly();
    }

    return $tables;
  }

  /**
   * Check if table is a system table (should be excluded)
   *
   * @param string $tableName Table name
   * @return bool True if system table
   */
  public static function isSystemTable(string $tableName): bool
  {
    // Internal RAG embedding tables (NOT knowledge-base documents): excluded from the document
    // search. Matched as substrings of the FULL table name. Canonical tables are rag_-prefixed
    $systemTables = [
      'rag_conversation_memory_embedding',  // chat history (own dedicated store)
      'rag_correction_pattern',             // SQL correction patterns (own dedicated store)
      'rag_web_cache_embedding',            // web search result cache
      'rag_schema_embedding',               // DB schema embeddings for NL->SQL generation
    ];

    foreach ($systemTables as $systemTable) {
      if (str_contains($tableName, $systemTable)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Fallback: Discover embedding tables directly from database
   *
   * Only used if MultiDBRAGManager is not available
   *
   * @return array List of embedding table names
   */
  private function discoverEmbeddingTablesDirectly(): array
  {
    $tables = [];

    try {
      $pattern = $this->prefix . '%_embedding';
      $sql = "SHOW TABLES LIKE :pattern";
      $results = DoctrineOrm::select($sql, ['pattern' => $pattern]);

      foreach ($results as $row) {
        // Get the table name from the result (key varies by database)
        $tableName = reset($row); // Get first value from array
        // Exclude system tables
        if (!self::isSystemTable($tableName)) {
          $tables[] = $tableName;
        }
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Discovered " . count($tables) . " embedding tables from DoctrineOrm (fallback)",
          'info'
        );
      }

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error in DoctrineOrm query: " . $e->getMessage(),
        'error'
      );
    }

    return $tables;
  }

  /**
   * Extract entity type from table name
   *
   * Converts table names like 'clicshopping_products_embedding' to 'products'
   *
   * @param string $tableName Full table name
   * @return string|null Entity type or null if invalid
   */
  private function extractEntityTypeFromTable(string $tableName): ?string
  {
    // Remove prefix
    $withoutPrefix = str_replace($this->prefix, '', $tableName);

    // Remove '_embedding' suffix
    $entityType = str_replace('_embedding', '', $withoutPrefix);

    // Validate entity type
    if (empty($entityType) || strlen($entityType) < 2) {
      return null;
    }

    return $entityType;
  }

  /**
   * Initialize with fallback using EmbeddingTableDiscovery
   *
   * Uses EmbeddingTableDiscovery's discover() as fallback
   * No more hardcoded lists!
   *
   * @return void
   */
  private function initializeFallback(): void
  {
    try {
      // Try to get tables from EmbeddingTableDiscovery
      if ($this->ragManager !== null) {
        $knownTables = $this->ragManager->discover(false); // Don't use cache for fallback
      } else {
        // Last resort: direct database query
        $knownTables = $this->discoverEmbeddingTablesDirectly();
      }

      // Filter out system tables
      $filteredTables = [];
      foreach ($knownTables as $tableName) {
        if (!self::isSystemTable($tableName)) {
          $filteredTables[] = $tableName;
        }
      }

      foreach ($filteredTables as $tableName) {
        $entityType = $this->extractEntityTypeFromTable($tableName);

        if ($entityType !== null) {
          $this->entityTypes[] = $entityType;
          $this->tableToEntityMap[$tableName] = $entityType;
          $this->entityToTableMap[$entityType] = $tableName;
        }
      }

      $this->entityTypes = array_unique($this->entityTypes);
      $this->initialized = true;

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "EntityTypeRegistry initialized with fallback from EmbeddingTableDiscovery: " . implode(', ', $this->entityTypes),
          'warning'
        );
      }

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error in fallback initialization: " . $e->getMessage(),
        'error'
      );

      // Absolute last resort: empty initialization
      $this->initialized = true;
    }
  }

  /**
   * Get entity type from table name
   *
   * @param string $tableName Table name
   * @return string|null Entity type or null if not found
   */
  public function getEntityTypeFromTable(string $tableName): ?string
  {
    if (!$this->initialized) {
      $this->initialize();
    }

    return $this->tableToEntityMap[$tableName] ?? null;
  }

  /**
   * Get table name from entity type
   *
   * @param string $entityType Entity type
   * @return string|null Table name or null if not found
   */
  public function getTableFromEntityType(string $entityType): ?string
  {
    if (!$this->initialized) {
      $this->initialize();
    }

    return $this->entityToTableMap[$entityType] ?? null;
  }


  /**
   * Get entity types as associative array for ContextResolver
   *
   * Returns array with entity types as keys and empty arrays as values
   *
   * @return array Entity types structure
   */
  public function getEntityTypesStructure(): array
  {
    if (!$this->initialized) {
      $this->initialize();
    }

    $structure = [];
    foreach ($this->entityTypes as $entityType) {
      $structure[$entityType] = [];
    }

    // Add common extraction fields
    $structure['time_ranges'] = [];
    $structure['numbers'] = [];
    $structure['last_product'] = null;
    $structure['last_entity'] = null;
    $structure['previous_entity'] = null;

    return $structure;
  }

  /**
   * Refresh entity types from database
   *
   * @return void
   */
  public function refresh(): void
  {
    $this->entityTypes = [];
    $this->tableToEntityMap = [];
    $this->entityToTableMap = [];
    $this->initialized = false;
    $this->initialize(true);
  }

  /**
   * Get statistics
   *
   * @return array Statistics about registered entity types
   */
  public function getStatistics(): array
  {
    if (!$this->initialized) {
      $this->initialize();
    }

    return [
      'total_entity_types' => count($this->entityTypes),
      'entity_types' => $this->entityTypes,
      'table_count' => count($this->tableToEntityMap),
      'initialized' => $this->initialized,
    ];
  }
}
