<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\CoreAI\Entity;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\CoreAI\Entity\EntityRegistry;

/**
 * EntityIdExtractor Class
 *
 * 
 * This class provides centralized logic for extracting entity information
 * from database query results, which is essential for populating memory tables correctly.
 *
 * Responsibilities:
 * - Extract entity_id from SQL result rows
 * - Determine entity_type based on column names
 * - Handle multiple entity types (products, categories, orders, customers, etc.)
 * - Provide fallback values when no entity is found
 */

class EntityIdExtractor
{
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * Static cache for entity column mappings
   * NOTE: This cache is now managed by EntityRegistry
   */
  private static ?array $cachedEntityColumnMap = null;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
  }

  /**
   * Extract entity_id and entity_type from SQL query results
   *
   *
   * @param array $sqlResults SQL query results (array of rows)
   * @param string|null $sqlQuery Optional SQL query for context
   * @return array ['entity_id' => int|null, 'entity_type' => string|null]
   */
  public function extractFromSqlResults(array $sqlResults, ?string $sqlQuery = null): array
  {
    if (empty($sqlResults)) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "No SQL results to extract entity from",
          'info'
        );
      }
      return ['entity_id' => null, 'entity_type' => null];
    }

    // Get first row (most relevant result)
    $firstRow = $sqlResults[0] ?? [];

    if (empty($firstRow)) {
      return ['entity_id' => null, 'entity_type' => null];
    }

    // Get entity column mappings (dynamic discovery)
    $entityColumnMap = $this->getEntityColumnMap();

    // Try to find entity ID column in the result
    foreach ($entityColumnMap as $columnName => $entityType) {
      if (isset($firstRow[$columnName])) {
        $entityId = (int)$firstRow[$columnName];

        if ($entityId > 0) {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Extracted entity from SQL results - entity_id: {$entityId}, entity_type: {$entityType} (column: {$columnName})",
              'info'
            );
          }

          return [
            'entity_id' => $entityId,
            'entity_type' => $entityType,
          ];
        }
      }
    }

    // If no entity column found, try to infer from SQL query
    if ($sqlQuery !== null) {
      $inferredEntity = $this->inferEntityFromQuery($sqlQuery, $entityColumnMap);
      if ($inferredEntity['entity_id'] !== null) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Inferred entity from SQL query - entity_type: {$inferredEntity['entity_type']}",
            'info'
          );
        }
        return $inferredEntity;
      }
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "No entity found in SQL results (columns: " . implode(', ', array_keys($firstRow)) . ")",
        'warning'
      );
    }

    return ['entity_id' => null, 'entity_type' => null];
  }

  /**
   * Get entity column mappings using centralized EntityRegistry
   *
   *
   * @return array Map of column names to entity types
   */
  private function getEntityColumnMap(): array
  {
    // Use cached mappings if available
    if (self::$cachedEntityColumnMap !== null) {
      return self::$cachedEntityColumnMap;
    }

    // Use centralized EntityRegistry for dynamic discovery
    $registry = EntityRegistry::getInstance();
    self::$cachedEntityColumnMap = $registry->getIdColumnMappings();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "TASK 4.4.2: Retrieved " . count(self::$cachedEntityColumnMap) . " entity mappings from EntityRegistry",
        'info'
      );
    }

    return self::$cachedEntityColumnMap;
  }

  /**
   * Infer entity type from SQL query
   *
   * @param string $sqlQuery SQL query
   * @param array $entityColumnMap Entity column mappings
   * @return array ['entity_id' => null, 'entity_type' => string|null]
   */
  private function inferEntityFromQuery(string $sqlQuery, array $entityColumnMap): array
  {
    $sqlLower = strtolower($sqlQuery);

    // Check for table names in FROM clause
    foreach ($entityColumnMap as $columnName => $entityType) {
      $tableName = str_replace('_id', '', $columnName);

      // Check if table name appears in query
      if (strpos($sqlLower, "from {$tableName}") !== false ||
          strpos($sqlLower, "from clic_{$tableName}") !== false ||
          strpos($sqlLower, "join {$tableName}") !== false ||
          strpos($sqlLower, "join clic_{$tableName}") !== false) {

        return [
          'entity_id' => null, // Can't extract ID from query alone
          'entity_type' => $entityType,
        ];
      }
    }

    return ['entity_id' => null, 'entity_type' => null];
  }

  /**
   * Get all supported entity types (dynamic discovery)
   *
   * @return array Map of column names to entity types
   */
  public function getSupportedEntityTypes(): array
  {
    return $this->getEntityColumnMap();
  }

  /**
   * Check if a column name is an entity ID column
   *
   * @param string $columnName Column name
   * @return bool True if it's an entity ID column
   */
  public function isEntityIdColumn(string $columnName): bool
  {
    $entityColumnMap = $this->getEntityColumnMap();
    return isset($entityColumnMap[$columnName]);
  }

  /**
   * Get entity type from column name
   *
   * @param string $columnName Column name
   * @return string|null Entity type or null if not found
   */
  public function getEntityTypeFromColumn(string $columnName): ?string
  {
    $entityColumnMap = $this->getEntityColumnMap();
    return $entityColumnMap[$columnName] ?? null;
  }

  /**
   * Extract entity_id and entity_type from metadata (for semantic queries)
   *
   *
   * @param array $metadata Metadata from semantic search results
   * @return array ['entity_id' => int|null, 'entity_type' => string|null]
   */
  public function extractFromMetadata(array $metadata): array
  {
    $entityId = null;
    $entityType = null;

    // Check if metadata has entity information directly
    if (isset($metadata['entity_id']) && !empty($metadata['entity_id'])) {
      $entityId = (int) $metadata['entity_id'];
    }

    if (isset($metadata['type']) && !empty($metadata['type'])) {
      $entityType = $metadata['type'];
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "TASK 4.4.2 FIX: Extracted entity from metadata - entity_id: " . ($entityId ?? 'NULL') . ", entity_type: " . ($entityType ?? 'NULL'),
        'info'
      );
    }

    return [
      'entity_id' => $entityId,
      'entity_type' => $entityType,
    ];
  }

  /**
   * Extract entity from mixed results (SQL or metadata)
   *
   *
   * @param array $results Results array (can be SQL rows or semantic results with metadata)
   * @param string|null $sqlQuery Optional SQL query for context
   * @return array ['entity_id' => int|null, 'entity_type' => string|null]
   */
  public function extractFromMixedResults(array $results, ?string $sqlQuery = null): array
  {
    if (empty($results)) {
      return ['entity_id' => null, 'entity_type' => null];
    }

    $firstResult = $results[0];

    // Check if this is a semantic result with metadata
    if (isset($firstResult['metadata']) && is_array($firstResult['metadata'])) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "TASK 4.4.2 FIX: Detected semantic result with metadata",
          'info'
        );
      }
      return $this->extractFromMetadata($firstResult['metadata']);
    }

    // Otherwise, treat as SQL result
    return $this->extractFromSqlResults($results, $sqlQuery);
  }
}
