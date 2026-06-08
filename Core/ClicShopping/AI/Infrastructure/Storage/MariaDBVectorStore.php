<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Storage;

use ClicShopping\OM\CLICSHOPPING;
use Doctrine\DBAL\ParameterType;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use LLPhant\Embeddings\VectorStores\VectorStoreBase;
use Doctrine\DBAL\Connection;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Cache\Cache;

/**
 * MariaDBVectorStore Class
 *
 * A vector store implementation using MariaDB for storing and retrieving document embeddings.
 * This class extends VectorStoreBase and provides functionality for managing document
 * embeddings in a MariaDB database with vector similarity search capabilities.
 *
 * Features:
 * - Document storage with vector embeddings
 * - Similarity search using vector operations
 * - Document metadata management
 * - Support for different entity types (products, categories, page manager)
 * - Document CRUD operations
 *
 * Requirements:
 * - MariaDB 11.7.0 or higher with vector support
 * - Doctrine ORM configuration
 * - Valid embedding generator implementation
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Rag
 */
 

class MariaDBVectorStore extends VectorStoreBase
{
  private Connection $connection;
  private string $tableName;
  private EmbeddingGeneratorInterface $embeddingGenerator;
  private SecurityLogger $securityLogger;
  private bool $debug = false;
  
  /**
   * Constructor for MariaDBVectorStore
   *
   * @param EmbeddingGeneratorInterface $embeddingGenerator The embedding generator to use
   * @param string $tableName Optional custom table name (defaults to 'rag_embeddings')
   * @throws \Exception If database connection or table creation fails
   */
  public function __construct(EmbeddingGeneratorInterface $embeddingGenerator, string $tableName = 'rag_embeddings')
  {
    $this->embeddingGenerator = $embeddingGenerator;
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    if (!empty($prefix) && str_starts_with($tableName, $prefix)) {
      $this->tableName = $tableName;
    } else {
      $this->tableName = $prefix . $tableName;
    }

    $entityManager = DoctrineOrm::getEntityManager();
    $this->connection = $entityManager->getConnection();
    $this->debug  = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True';

    $this->securityLogger = new SecurityLogger();

    if ($this->debug) {
      error_log("===================================================");
      error_log("[INFO] MariaDBVectorStore initialized");
      error_log("Input table name: {$tableName}");
      error_log("Final table name: {$this->tableName}");
      error_log("Prefix: {$prefix}");
      error_log("===================================================");
    }
  }

  /**
   * Table name return
   *
   * @return string The name of the table used for storing embeddings
   */
  public function getTableName(): string
  {
    return $this->tableName;
  }

  /**
   * Check if a column exists in the table
   *
   * @param string $columnName Column name to check
   * @return bool True if column exists
   */
  private function hasColumn(string $columnName): bool
  {
    try {
      $result = $this->connection->executeQuery(
        "SELECT COUNT(*) as count 
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = ? 
         AND COLUMN_NAME = ?",
        [$this->tableName, $columnName]
      );

      $row = $result->fetchAssociative();
      return ($row['count'] ?? 0) > 0;
    } catch (\Exception $e) {
      // If check fails, assume column doesn't exist
      return false;
    }
  }
  
  /**
   * Validates the format of the embedding
   *
   * @param array $embedding The embedding to validate
   * @throws \InvalidArgumentException If the embedding format is invalid
   */
  private function validateEmbeddingFormat(array $embedding): void {
    if (!is_array($embedding)) {
      throw new \InvalidArgumentException('Embedding must be an array.');
    }

    foreach ($embedding as $value) {
      if (!is_numeric($value)) {
        throw new \InvalidArgumentException('Embedding contains non-numeric values.');
      }
    }

    if (empty($embedding)) {
      throw new \InvalidArgumentException('Embedding array cannot be empty.');
    }
  }

  /**
   * Prepares the embedding and metadata for storage
   *
   * @param string $content The content to embed
   * @param array $metadata Metadata associated with the content
   * @return array Prepared data including embedding and metadata
   */
  private function prepareEmbeddingAndMetadata(string $content, array $metadata): array {
    $embedding = $this->embeddingGenerator->embedText($content);
    $embeddingText = '[' . implode(',', $embedding) . ']';

    return [
      'embeddingText' => $embeddingText,
      'type' => $metadata['type'] ?? null,
      'sourcetype' => $metadata['sourcetype'] ?? 'manual',
      'sourcename' => $metadata['sourcename'] ?? 'manual',
      'chunknumber' => $metadata['chunknumber'] ?? 128,
      'language_id' => $metadata['language_id'] ?? 1,
      'date_modified' => date('Y-m-d H:i:s'),
      'entity_id' => $metadata['entity_id'] ?? null,
      'entity_type' => $metadata['entity_type'] ?? null,
      'user_id' => $metadata['user_id'] ?? null,
      'interaction_id' => $metadata['interaction_id'] ?? null,
      'metadata' => $metadata['metadata'] ?? null,
    ];
  }

  /**
   * Adds a single document to the vector store
   *
   * @param Document $document The document to add, containing content and metadata
   * @throws \Exception If document addition fails
   */
  public function addDocument(Document $document): void
  {
    if (!isset($document->content) || $document->content === '') {
      $this->securityLogger->logSecurityEvent(
        'Document content is missing or not initialized (Typed property access error prevented).',
        'warning'
      );
      return;
    }

    $embedding = $this->embeddingGenerator->embedText($document->content);
    $this->validateEmbeddingFormat($embedding);

    $metadata = isset($document->metadata) ? $document->metadata : [];

    $preparedData = $this->prepareEmbeddingAndMetadata($document->content, $metadata);

    $documentMetadata = [
      'id' => $document->id ?? null,
      'sourceType' => $document->sourceType ?? 'manual',
      'sourceName' => $document->sourceName ?? 'manual',
      'chunkNumber' => $document->chunkNumber ?? 0,
      'hash' => $document->hash ?? '',
    ];

    if (!empty($metadata) && is_array($metadata)) {
      $documentMetadata = array_merge($documentMetadata, $metadata);
    }

    $metadataJson = json_encode($documentMetadata);

    $hasUserIdColumn = $this->hasColumn('user_id');
    $hasInteractionIdColumn = $this->hasColumn('interaction_id');
    $hasEntityTypeColumn = $this->hasColumn('entity_type');

    if ($hasUserIdColumn && empty($preparedData['user_id'])) {
      $this->securityLogger->logSecurityEvent('Warning: user_id is empty when inserting into ' . $this->tableName, 'warning');
    }

    if ($hasInteractionIdColumn && empty($preparedData['interaction_id'])) {
      $this->securityLogger->logSecurityEvent( 'Warning: interaction_id is empty when inserting into ' . $this->tableName, 'warning');
    }

    if ($hasUserIdColumn || $hasInteractionIdColumn || $hasEntityTypeColumn) {
      $columns = "(content, 
      type, 
      sourcetype, 
      sourcename, 
      embedding, 
      chunknumber, 
      date_modified, 
      entity_id, 
      language_id";
      $values = "
      (?, 
      ?, 
      ?, 
      ?, 
      VEC_FromText(?), 
      ?, 
      ?, 
      ?, 
      ?";
      $params = [
        $document->content,
        $preparedData['type'],
        $preparedData['sourcetype'],
        $preparedData['sourcename'],
        $preparedData['embeddingText'],
        $preparedData['chunknumber'],
        $preparedData['date_modified'],
        $preparedData['entity_id'],
        $preparedData['language_id'],
      ];

      if ($hasEntityTypeColumn) {
        $columns .= ", entity_type";
        $values .= ", ?";
        $params[] = $preparedData['entity_type'];
      }

      if ($hasUserIdColumn) {
        $columns .= ", user_id";
        $values .= ", ?";
        $params[] = $preparedData['user_id'];
      }

      if ($hasInteractionIdColumn) {
        $columns .= ", interaction_id";
        $values .= ", ?";
        $params[] = $preparedData['interaction_id'];
      }

      $columns .= ", metadata)";
      $values .= ", ?)";
      $params[] = $metadataJson;

      $this->connection->executeStatement(
        "INSERT INTO {$this->tableName} {$columns} VALUES {$values}",
        $params
      );
    } else {
      $this->connection->executeStatement(
        "INSERT INTO {$this->tableName}
          (content, 
	  type, 
	  sourcetype, 
	  sourcename, 
	  embedding, 
	  chunknumber, 
	  date_modified, 
	  entity_id, 
	  entity_type, 
	  language_id,
	  metadata) 
          VALUES (?, 
	  ?, 
	  ?, 
	  ?, 
	  VEC_FromText(?), 
	  ?, 
	  ?, 
	  ?, 
	  ?, 
	  ?,
	  ?)",
        [
          $document->content,
          $preparedData['type'],
          $preparedData['sourcetype'],
          $preparedData['sourcename'],
          $preparedData['embeddingText'],
          $preparedData['chunknumber'],
          $preparedData['date_modified'],
          $preparedData['entity_id'],
          $preparedData['entity_type'],
          $preparedData['language_id'],
          $metadataJson
        ]
      );
    }

    // Clear semantic cache to ensure search results reflect new data
    $this->invalidateSemanticCache($preparedData);
  }

  /**
   * Adds multiple documents to the vector store
   *
   * Processes and stores multiple documents in sequence.
   *
   * @param array $documents Array of Document objects to add
   * @throws \Exception If any document addition fails
   */
  public function addDocuments(array $documents): void
  {
    foreach ($documents as $document) {
      $this->addDocument($document);
    }
  }

  /**
   * Invalidates semantic cache when embeddings are updated
   * 
   * When new embeddings are added to the vector store, we need to invalidate
   * the semantic search cache to ensure search results reflect the new data.
   * 
   * Strategy:
   * - Clear all semantic cache (conservative approach)
   * - Ensures consistency between embeddings and search results
   * - Cache will be rebuilt on next search (warm-up)
   * 
   * @param array $preparedData The prepared data containing entity_type and language_id
   * @return void
   */
  private function invalidateSemanticCache(array $preparedData): void
  {
    try {
      // Extract entity type and language ID from prepared data
      $entityType = $preparedData['entity_type'] ?? null;
      $languageId = $preparedData['language_id'] ?? null;

      // Clear semantic cache for this entity type and language
      // Note: Since cache keys are md5 hashes, we can't selectively clear by entity type
      // We use a conservative approach: clear all semantic cache
      $cleared = Cache::clearSemanticCache($entityType, $languageId);

      if ($cleared > 0) {
        $context = [];
        if ($entityType !== null) {
          $context[] = "EntityType: {$entityType}";
        }
        if ($languageId !== null) {
          $context[] = "LanguageId: {$languageId}";
        }
        $contextStr = !empty($context) ? ' (' . implode(', ', $context) . ')' : '';
        
        error_log("[INFO] Semantic cache invalidated after embedding update: {$cleared} files cleared{$contextStr}");
      }
    } catch (\Exception $e) {
      // Log error but don't fail the document insertion
      error_log("[warning]️ Failed to invalidate semantic cache: " . $e->getMessage());
    }
  }

  /**
   * Performs a similarity search for documents
   *
   * @param mixed $query Search query (string) or embedding vector (array)
   * @param int $k Maximum number of results to return
   * @param mixed $minScore Minimum similarity score (0-1) for results
   * @param callable|null $filter Optional callback function for filtering results
   * @return iterable Collection of matching Document objects with similarity scores
   * @throws \Exception If search operation fails
   */
  public function similaritySearch(mixed $query, int $k = 4, mixed $minScore = 0.0, ?callable $filter = null): iterable
  {
    try {
      $embedding = is_array($query) ? $query : $this->embeddingGenerator->embedText($query);

      if (!is_array($embedding)) {
        $embeddingType = gettype($embedding);
        $embeddingValue = is_string($embedding) ? substr($embedding, 0, 100) : var_export($embedding, true);
        $generatorClass = get_class($this->embeddingGenerator);
        throw new \RuntimeException("Embedding generator ({$generatorClass}) returned non-array value. Type: {$embeddingType}, Value: {$embeddingValue}");
      }

      $embedding = array_map('floatval', $embedding);
      $embeddingText = '[' . implode(',', $embedding) . ']';

      $hasMetadataColumn = $this->hasColumn('metadata');
      
      $hasLanguageIdColumn = $this->hasColumn('language_id');
      
      $metadataSelect = $hasMetadataColumn ? 'metadata,' : '';
      $languageIdSelect = $hasLanguageIdColumn ? 'language_id,' : '';
      
      $whereClause = "WHERE embedding IS NOT NULL";
      $params = [$embeddingText];
      $types = [ParameterType::STRING];
      
      $sql = "SELECT id, 
                     content, 
                     type, 
                     sourcetype,
                     sourcename,
                     embedding,
                     chunknumber, 
                     date_modified, 
                     entity_id, 
                     {$languageIdSelect}
                     {$metadataSelect}
                     VEC_DISTANCE_COSINE(embedding, VEC_FromText(?)) AS distance
                FROM {$this->tableName}
                {$whereClause}
                ORDER BY distance ASC
                LIMIT ?";
                
      $params[] = $k;
      $types[] = ParameterType::INTEGER;

      $stmt = $this->connection->executeQuery($sql, $params, $types);

      //too many connexion risk
      try {
        $results = $stmt->fetchAllAssociative();
      } finally {
        $stmt->free();
      }

      if ($this->debug) {
        error_log("[stats] SQL returned " . count($results) . " raw results");
      }

      $documents = [];
      $filteredCount = 0;
      $belowThresholdCount = 0;

      foreach ($results as $r) {
        $distance = (float) ($r['distance'] ?? 1.0);
        $similarity = 1 / (1 + $distance);

        if ($similarity < (float) $minScore) {
          $belowThresholdCount++;
          continue;
        }

        $doc = new Document();
        $doc->id = $r['id'];
        $doc->content = $r['content'];
        $doc->sourceType = $r['sourcetype'] ?? 'manual';
        $doc->sourceName = $r['sourcename'] ?? 'manual';
        $doc->chunkNumber = $r['chunknumber'] ?? 128;

        $storedMetadata = [];
        if ($hasMetadataColumn && !empty($r['metadata'])) {
          $storedMetadata = json_decode($r['metadata'], true) ?? [];
        }
	
        $metadataArray = [
          'id' => $r['id'],
          'type' => $r['type'] ?? null,
          'date_modified' => $r['date_modified'] ?? null,
          'entity_id' => $r['entity_id'] ?? 0,
          'score' => $similarity,
          'table_name' => $this->tableName,
          'distance' => $distance,
          'user_id' => $r['user_id'] ?? $storedMetadata['user_id'] ?? null,
          'interaction_id' => $r['interaction_id'] ?? $storedMetadata['interaction_id'] ?? null,
        ];
        
        if ($hasLanguageIdColumn) {
          $metadataArray['language_id'] = $r['language_id'] ?? 1;
        }
        
        if (!isset($storedMetadata['entity_type']) && isset($storedMetadata['type'])) {
          $metadataArray['entity_type'] = $storedMetadata['type'];
        } elseif (isset($storedMetadata['entity_type'])) {
          $metadataArray['entity_type'] = $storedMetadata['entity_type'];
        } elseif (isset($r['type'])) {
          $metadataArray['entity_type'] = $r['type'];
        }
        
        $doc->metadata = array_merge($storedMetadata, $metadataArray);

        if ($filter !== null && !$filter($doc->metadata)) {
          $filteredCount++;
          continue;
        }

        $documents[] = $doc;
      }

      if ($this->debug) {
        error_log("===================================================");
        error_log("[stats] FINAL RESULTS:");
        error_log("Raw SQL results: " . count($results));
        error_log("Below threshold: {$belowThresholdCount}");
        error_log("Filtered by custom filter: {$filteredCount}");
        error_log("Final documents returned: " . count($documents));
        error_log("===================================================");
      }

      return $documents;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("===================================================");
        error_log("[error] EXCEPTION in similaritySearch()");
        error_log("Error: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        error_log("===================================================");
      }

      $this->securityLogger->logSecurityEvent(
        'Error in similarity search for table ' . $this->tableName . ': ' . $e->getMessage(),
        'error'
      );

      return [];
    }
  }

  /**
   * Deletes a document from the vector store
   *
   * Removes a document and its embeddings from the database.
   *
   * @param int $id ID of the document to delete
   * @return bool True if successful, false if deletion fails
   */
  public function deleteDocument(int $id): bool
  {
    try {
      $this->connection->executeStatement(
        "DELETE FROM {$this->tableName} WHERE id = ?",
        [$id]
      );

      $this->invalidateSemanticCache([]);

      return true;
    } catch (\Exception $e) {
      if ($this->debug == 'True') {
        $this->securityLogger->logSecurityEvent('Error while deleting the document: ' . $e->getMessage(), 'error');
      }

      return false;
    }
  }

  /**
   * Updates an existing document in the vector store
   *
   * @param int $id ID of the document to update
   * @param string $content New content for the document
   * @param array $metadata Updated metadata for the document
   * @return bool True if successful, false if update fails
   */
  public function updateDocument(int $id, string $content, array $metadata = []): bool
  {
    try {
      $preparedData = $this->prepareEmbeddingAndMetadata($content, $metadata);

      $metadataJson = json_encode($metadata);

      $this->connection->executeStatement(
        "UPDATE {$this->tableName}
        SET content = ?, 
            type = ?, 
            sourcetype = ?, 
            sourcename = ?,
            embedding = VEC_FromText(?), chunknumber = ?, date_modified = ?,
            entity_id = ?, 
            language_id = ?,
            metadata = ?
        WHERE id = ?",
        [
          $content,
          $preparedData['type'],
          $preparedData['sourcetype'],
          $preparedData['sourcename'],
          $preparedData['embeddingText'],
          $preparedData['chunknumber'],
          $preparedData['date_modified'],
          $preparedData['entity_id'],
          $preparedData['language_id'],
          $metadataJson,
          $id
        ]
      );

      $this->invalidateSemanticCache($preparedData);

      return true;
    } catch (\Exception $e) {
      if ($this->debug == 'True') {
        $this->securityLogger->logSecurityEvent('Error while updating the document: ' . $e->getMessage(), 'error');
      }

      return false;
    }
  }
  
  
  // ******************************************
  //  dO NOT USE BELOW
  // ******************************************
  

  /**
   * Add document with separated taxonomy
   *
   * This method stores content, metadata, and taxonomy in separate database fields.
   * The embedding is generated only from the content field (no taxonomy).
   *
   * @param Document $document Document object with content and metadata
   * @param array|null $taxonomy Structured taxonomy data (stored separately)
   * @return bool True if successful, false otherwise
   * @throws \Exception If document addition fails
   */
/*
  public function addDocumentWithTaxonomy(Document $document, ?array $taxonomy): bool
  {
    try {
      // Validate document content
      if (!isset($document->content) || $document->content === '') {
        $this->securityLogger->logSecurityEvent(
          'Document content is missing or empty',
          'warning'
        );
        return false;
      }

      // Generate embedding from content only (no taxonomy)
      $embedding = $this->embeddingGenerator->embedText($document->content);
      $this->validateEmbeddingFormat($embedding);

      // Get metadata safely
      $metadata = isset($document->metadata) ? $document->metadata : [];

      // Prepare embedding and metadata
      $preparedData = $this->prepareEmbeddingAndMetadata($document->content, $metadata);

      // Prepare metadata JSON
      $documentMetadata = [
        'id' => $document->id ?? null,
        'sourceType' => $document->sourceType ?? 'manual',
        'sourceName' => $document->sourceName ?? 'manual',
        'chunkNumber' => $document->chunkNumber ?? 0,
        'hash' => $document->hash ?? '',
      ];
      
      if (!empty($metadata) && is_array($metadata)) {
        $documentMetadata = array_merge($documentMetadata, $metadata);
      }
      
      $metadataJson = json_encode($documentMetadata);

      // Prepare taxonomy JSON (null if not provided)
      $taxonomyJson = $taxonomy !== null ? json_encode($taxonomy) : null;

      // Check if taxonomy and metadata columns exist
      $hasTaxonomyColumn = $this->hasColumn('taxonomy');
      $hasMetadataColumn = $this->hasColumn('metadata');
      $hasUserIdColumn = $this->hasColumn('user_id');
      $hasInteractionIdColumn = $this->hasColumn('interaction_id');
      $hasEntityTypeColumn = $this->hasColumn('entity_type');

      // Build INSERT query dynamically based on available columns
      $columns = "(content, type, sourcetype, sourcename, embedding, chunknumber, date_modified, entity_id, language_id";
      $values = "(?, ?, ?, ?, VEC_FromText(?), ?, ?, ?, ?";
      $params = [
        $document->content,
        $preparedData['type'],
        $preparedData['sourcetype'],
        $preparedData['sourcename'],
        $preparedData['embeddingText'],
        $preparedData['chunknumber'],
        $preparedData['date_modified'],
        $preparedData['entity_id'],
        $preparedData['language_id'],
      ];

      // Add optional columns if they exist
      if ($hasEntityTypeColumn) {
        $columns .= ", entity_type";
        $values .= ", ?";
        $params[] = $preparedData['entity_type'];
      }

      if ($hasUserIdColumn) {
        $columns .= ", user_id";
        $values .= ", ?";
        $params[] = $preparedData['user_id'];
      }

      if ($hasInteractionIdColumn) {
        $columns .= ", interaction_id";
        $values .= ", ?";
        $params[] = $preparedData['interaction_id'];
      }

      if ($hasMetadataColumn) {
        $columns .= ", metadata";
        $values .= ", ?";
        $params[] = $metadataJson;
      }

      // Add taxonomy column if it exists (Task 4.1 - separated storage)
      if ($hasTaxonomyColumn) {
        $columns .= ", taxonomy";
        $values .= ", ?";
        $params[] = $taxonomyJson;
      }

      $columns .= ")";
      $values .= ")";

      // Execute INSERT
      $this->connection->executeStatement(
        "INSERT INTO {$this->tableName} {$columns} VALUES {$values}",
        $params
      );

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          'Document added with separated taxonomy',
          'info',
          [
            'table' => $this->tableName,
            'content_length' => strlen($document->content),
            'has_taxonomy' => $taxonomy !== null,
            'taxonomy_column_exists' => $hasTaxonomyColumn
          ]
        );
      }

      return true;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        'Error adding document with taxonomy: ' . $e->getMessage(),
        'error',
        [
          'table' => $this->tableName,
          'trace' => $e->getTraceAsString()
        ]
      );
      return false;
    }
  }
*/
}
