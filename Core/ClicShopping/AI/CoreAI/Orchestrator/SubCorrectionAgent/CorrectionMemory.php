<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\DomainsAI\CoreAI\Entity\EntityRegistry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use LLPhant\Embeddings\Document;

/**
 * CorrectionMemory Class
 * Handles storage and retrieval of successful corrections
 * Manages entity extraction and pattern content creation
 */
class CorrectionMemory
{
  private MariaDBVectorStore $correctionStore;
  private SecurityLogger $logger;
  private string $userId;
  private int $languageId;
  private bool $debug;

  /**
   * Constructor
   *
   * @param MariaDBVectorStore $correctionStore Vector store for corrections
   * @param SecurityLogger $logger Security logger instance
   * @param string $userId User identifier
   * @param int $languageId Language ID
   * @param bool $debug Debug mode flag
   */
  public function __construct(
    MariaDBVectorStore $correctionStore,
    SecurityLogger $logger,
    string $userId,
    int $languageId,
    bool $debug
  ) {
    $this->correctionStore = $correctionStore;
    $this->logger = $logger;
    $this->userId = $userId;
    $this->languageId = $languageId;
    $this->debug = $debug;
  }

  /**
   * Memorize successful correction for future learning
   *
   * @param array $errorContext Original error context
   * @param array $correction Applied correction
   * @param array $errorAnalysis Error analysis
   */
  public function memorizeSuccessfulCorrection(
    array $errorContext,
    array $correction,
    array $errorAnalysis
  ): void {
    try {
      $entityInfo = $this->extractEntityInfoFromContext($errorContext, $correction);
      $entityId = $entityInfo['entity_id'];
      $entityType = $entityInfo['entity_type'];
      
      if ($entityId === null) {
        // Log warning but continue - we'll use a default value
        $this->logger->logSecurityEvent(
          "Cannot extract entity_id for correction memorization. Query: " . 
          substr($errorContext['failed_query'] ?? 'N/A', 0, 200),
          'warning',
          [
            'error_type' => $errorAnalysis['type'],
            'correction_method' => $correction['method'],
            'original_query' => $errorContext['original_query'] ?? 'N/A'
          ]
        );
        
        // Use default entity_id of 0 to indicate "no specific entity"
        $entityId = 0;
        $entityType = null;
      }
      
      $document = new Document();
      $document->content = $this->createCorrectionPatternContent(
        $errorContext,
        $correction,
        $errorAnalysis
      );
      $document->sourceType = 'correction_pattern';
      $document->sourceName = 'learned_correction';

      $document->metadata = [
        'type' => 'correction_pattern',
        'error_type' => $errorAnalysis['type'],
        'correction_method' => $correction['method'],
        'confidence' => $correction['confidence'] ?? 0.5,
        'original_query' => $errorContext['failed_query'],
        'corrected_query' => $correction['query'],
        'original_error' => $errorContext['error_message'],
        'correction_successful' => true,
        'timestamp' => time(),
        'user_id' => $this->userId,
        'language_id' => $this->languageId,
        'success_rate' => 1.0,
        'entity_id' => $entityId,
        'entity_type' => $entityType,
      ];

      $this->correctionStore->addDocument($document);

      if ($this->debug) {
        $entityTypeStr = $entityType ? " ({$entityType})" : '';
        $this->logger->logSecurityEvent(
          "Correction pattern memorized: " . $errorAnalysis['type'] . 
          " (entity_id: {$entityId}{$entityTypeStr})",
          'info'
        );
      }

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error memorizing correction: " . $e->getMessage(),
        'error',
        [
          'error_type' => $errorAnalysis['type'],
          'correction_method' => $correction['method'],
          'stack_trace' => $e->getTraceAsString()
        ]
      );
      
      if ($this->debug) {
        error_log("CorrectionMemory: Failed to memorize correction - " . $e->getMessage() . "\n");
      }
    }
  }

  /**
   * Extracts entity_id and entity_type from error context or correction results
   * 
   * Attempts to extract entity information from:
   * 1. Correction results (if query was re-executed)
   * 2. Original error context
   * 3. Query analysis (parsing SQL for entity references)
   *
   * @param array $errorContext Error context containing query and results
   * @param array $correction Correction data that may contain results
   * @return array Array with 'entity_id' and 'entity_type' keys
   */
  public function extractEntityInfoFromContext(array $errorContext, array $correction): array
  {
    // Method 1: Check if correction contains results with entity_id
    if (isset($correction['results']) && !empty($correction['results'])) {
      $entityInfo = $this->extractEntityIdFromResults($correction['results']);
      if ($entityInfo['entity_id'] !== null) {
        return $entityInfo;
      }
    }
    
    // Method 2: Check if error context contains results
    if (isset($errorContext['results']) && !empty($errorContext['results'])) {
      $entityInfo = $this->extractEntityIdFromResults($errorContext['results']);
      if ($entityInfo['entity_id'] !== null) {
        return $entityInfo;
      }
    }
    
    // Method 3: Try to extract from SQL query (parse for WHERE id = X patterns)
    $query = $correction['query'] ?? $errorContext['failed_query'] ?? '';
    if (!empty($query)) {
      $entityId = $this->extractEntityIdFromQuery($query);
      if ($entityId !== null) {
        // Try to determine entity_type from query
        $entityType = $this->extractEntityTypeFromQuery($query);
        return [
          'entity_id' => $entityId,
          'entity_type' => $entityType
        ];
      }
    }
    
    // Method 4: Check if entity_id is explicitly provided in context
    if (isset($errorContext['entity_id']) && $errorContext['entity_id'] !== null) {
      return [
        'entity_id' => (int) $errorContext['entity_id'],
        'entity_type' => $errorContext['entity_type'] ?? null
      ];
    }
    
    // No entity_id found
    return [
      'entity_id' => null,
      'entity_type' => null
    ];
  }

  /**
   * Extracts entity_id from query results using centralized EntityRegistry
   * 
   * @param array $results Query results array
   * @return array Array with 'entity_id' and 'entity_type' keys
   */
  public function extractEntityIdFromResults(array $results): array
  {
    $entityId = null;
    $entityType = null;

    if (empty($results)) {
      return ['entity_id' => $entityId, 'entity_type' => $entityType];
    }

    $firstRow = $results[0];

    // Use centralized EntityRegistry for ID column mappings
    $registry = EntityRegistry::getInstance();
    $idColumnNames = $registry->getIdColumnMappings();

    foreach ($idColumnNames as $idCol => $type) {
      if (isset($firstRow[$idCol]) && !empty($firstRow[$idCol])) {
        $entityId = (int) $firstRow[$idCol];
        $entityType = $type;
        break;
      }
    }

    return [
      'entity_id' => $entityId,
      'entity_type' => $entityType,
    ];
  }

  /**
   * Attempts to extract entity_id from SQL query by parsing WHERE clauses
   * 
   * Looks for patterns like:
   * - WHERE products_id = 123
   * - WHERE id = 456
   * - WHERE p.products_id = 789
   * 
   * @param string $query SQL query to parse
   * @return int|null Entity ID if found, null otherwise
   */
  public function extractEntityIdFromQuery(string $query): ?int
  {
    // Pattern 1: WHERE {table}_id = {number}
    if (preg_match('/WHERE\s+(?:\w+\.)?(\w+_id)\s*=\s*(\d+)/i', $query, $matches)) {
      return (int) $matches[2];
    }
    
    // Pattern 2: WHERE id = {number}
    if (preg_match('/WHERE\s+(?:\w+\.)?id\s*=\s*(\d+)/i', $query, $matches)) {
      return (int) $matches[1];
    }
    
    // Pattern 3: AND {table}_id = {number}
    if (preg_match('/AND\s+(?:\w+\.)?(\w+_id)\s*=\s*(\d+)/i', $query, $matches)) {
      return (int) $matches[2];
    }
    
    // Pattern 4: AND id = {number}
    if (preg_match('/AND\s+(?:\w+\.)?id\s*=\s*(\d+)/i', $query, $matches)) {
      return (int) $matches[1];
    }
    
    return null;
  }

  /**
   * Clean the table
   * @param string $fullTableName
   * @return string
   */
  private function normalizeTableName(string $fullTableName): string
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    return str_replace([$prefix, '_embedding'], '', $fullTableName);
  }

  /**
   * Attempts to extract entity_type from SQL query by analyzing table names
   * 
   * Uses centralized EntityRegistry to dynamically discover entity types from table names.
   * 
   * Looks for patterns like:
   * - FROM products WHERE ...
   * - FROM categories WHERE ...
   * - JOIN pages_manager ON ...
   * 
   * @param string $query SQL query to parse
   * @return string|null Entity type if found, null otherwise
   */
  public function extractEntityTypeFromQuery(string $query): ?string
  {
    // Use centralized EntityRegistry for dynamic entity type discovery
    $registry = EntityRegistry::getInstance();
    $allTables = $registry->getAllEntityTables();

    // Get table prefix dynamically
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    // Build a mapping of table names (without prefix) to entity types
    $tableToEntityType = [];
    foreach ($allTables as $fullTableName) {
      $entityType = $registry->getEntityTypeForTable($fullTableName);
      // Remove prefix and _embedding suffix to get base table name
// Build a mapping of table names (without prefix) to entity types
      $tableToEntityType = array_combine(
        array_map($this->normalizeTableName(...), $allTables),
        array_map($registry->getEntityTypeForTable(...), $allTables)
      );
    }

    // Pattern 1: FROM {table_name}
    if (preg_match('/FROM\s+(?:\w+\.)?(\w+)/i', $query, $matches)) {
      $tableName = strtolower($matches[1]);
      // Remove table prefix if present
      $tableName = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $tableName);

      if (isset($tableToEntityType[$tableName])) {
        return $tableToEntityType[$tableName];
      }
    }

    // Pattern 2: JOIN {table_name}
    if (preg_match('/JOIN\s+(?:\w+\.)?(\w+)/i', $query, $matches)) {
      $tableName = strtolower($matches[1]);
      // Remove table prefix if present
      $tableName = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $tableName);

      if (isset($tableToEntityType[$tableName])) {
        return $tableToEntityType[$tableName];
      }
    }

    // Pattern 3: {table}_id column name
    if (preg_match('/(?:WHERE|AND)\s+(?:\w+\.)?(\w+)_id\s*=/i', $query, $matches)) {
      $entityName = strtolower($matches[1]);

      if (isset($tableToEntityType[$entityName])) {
        return $tableToEntityType[$entityName];
      }
    }

    return null;
  }

  /**
   * Create correction pattern content for storage
   * 
   * @param array $errorContext Error context
   * @param array $correction Correction data
   * @param array $errorAnalysis Error analysis
   * @return string Pattern content
   */
  public function createCorrectionPatternContent(
    array $errorContext,
    array $correction,
    array $errorAnalysis
  ): string {
    $parts = [];

    $parts[] = "Error Pattern:";
    $parts[] = "Type: " . $errorAnalysis['type'];
    $parts[] = "Error: " . $errorContext['error_message'];
    $parts[] = "";
    $parts[] = "Original Query:";
    $parts[] = $errorContext['failed_query'];
    $parts[] = "";
    $parts[] = "Correction Applied:";
    $parts[] = "Method: " . $correction['method'];
    $parts[] = "Corrected Query:";
    $parts[] = $correction['query'];

    if (!empty($correction['reasoning'])) {
      $parts[] = "";
      $parts[] = "Reasoning:";
      $parts[] = $correction['reasoning'];
    }

    return implode("\n", $parts);
  }

  /**
   * Extracts entity_id and entity_type from an interaction by querying the interactions table
   * 
   * @param string $interactionId Interaction ID to look up
   * @return array Array with 'entity_id' and 'entity_type' keys
   */
  public function extractEntityInfoFromInteraction(string $interactionId): array
  {
    try {
      // Query rag_statistics table for entity information
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      $sql = "SELECT entity_id, 
                entity_type
              FROM {$prefix}rag_statistics
              WHERE interaction_id = :interaction_id
              LIMIT 1
            ";
      
      $result = DoctrineOrm::selectOne($sql, ['interaction_id' => $interactionId]);
      
      if ($result && isset($result['entity_id']) && $result['entity_id'] !== null) {
        return [
          'entity_id' => (int) $result['entity_id'],
          'entity_type' => $result['entity_type'] ?? null
        ];
      }
      
      return [
        'entity_id' => null,
        'entity_type' => null
      ];
      
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Error extracting entity info from interaction: " . $e->getMessage(),
          'error'
        );
      }
      return [
        'entity_id' => null,
        'entity_type' => null
      ];
    }
  }

  /**
   * Create textual content of correction for vector store
   * 
   * @param string $original Original response
   * @param string $corrected Corrected response
   * @param string $type Correction type
   * @return string Correction content
   */
  public function createCorrectionContent(string $original, string $corrected, string $type): string
  {
    $parts = [];
    $parts[] = "Correction Type: " . $type;
    $parts[] = "";
    $parts[] = "Original Response:";
    $parts[] = $original;
    $parts[] = "";
    $parts[] = "Corrected Response:";
    $parts[] = $corrected;
    $parts[] = "";
    $parts[] = "Transformation: User-provided correction";
    
    return implode("\n", $parts);
  }
}
