<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;


use ClicShopping\OM\Registry;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\CoreAI\Memory\SubConversationMemory\EntityMatcher;

/**
 * LongTermMemoryManager Class
 *
 * Responsible for managing long-term memory using vector embeddings.
 * Separated from ConversationMemory to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Manage MariaDBVectorStore
 * - Create embeddings for text
 * - Store interactions in vector database
 * - Search similar interactions via semantic search
 * - Handle document chunking for long texts
 */

class LongTermMemoryManager
{
  private MariaDBVectorStore $vectorStore;
  private EmbeddingGeneratorInterface $embeddingGenerator;
  private SecurityLogger $logger;
  private bool $debug;
  private float $similarityThreshold;
  private int $maxChunkSize = 2000; // Max characters per chunk (reduced chunking to avoid perceived duplicates)
  private EntityMatcher $entityMatcher;
  private MemoryDeduplicator $deduplicator;
  private MemorySimilarityRetriever $retriever;

  /**
   * Constructor
   *
   * @param MariaDBVectorStore $vectorStore Vector store instance
   * @param EmbeddingGeneratorInterface $embeddingGenerator Embedding generator
   * @param float $similarityThreshold Threshold for semantic search
   * @param bool $debug Enable debug logging
   */
  public function __construct(
    MariaDBVectorStore $vectorStore,
    EmbeddingGeneratorInterface $embeddingGenerator,
    float $similarityThreshold = 0.7,
    bool $debug = false
  ) {
    $this->vectorStore = $vectorStore;
    $this->embeddingGenerator = $embeddingGenerator;
    $this->similarityThreshold = $similarityThreshold;
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
    $this->entityMatcher = new EntityMatcher($debug);
    $this->deduplicator = new MemoryDeduplicator($debug);
    $this->retriever = new MemorySimilarityRetriever($vectorStore, $debug);

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "LongTermMemoryManager initialized with similarityThreshold={$similarityThreshold}",
        'info'
      );
    }
  }

  /**
   * Store an interaction in long-term memory
   *
   * @param string $content Content to store
   * @param array $metadata Metadata (user_id, language_id, entity_id, etc.)
   * @return bool Success of operation
   */
  public function storeInteraction(string $content, array $metadata = []): bool
  {
    try {
      // Enhanced duplicate detection - check both interaction_id AND content hash
      $tableName = $this->vectorStore->getTableName();
      
      // Calculate content hash for duplicate detection
      $contentHash = md5($content);
      $userId = (string)($metadata['user_id'] ?? 'system');
      $languageId = (int)($metadata['language_id'] ?? 1);
      
      // Create a unique signature for this interaction
      $uniqueSignature = md5($contentHash . '_' . $userId . '_' . $languageId);
      
      try {
        // Check 1: By interaction_id (if provided) - use direct column if available, else JSON
        $interactionId = $metadata['interaction_id'] ?? null;
        if ($interactionId !== null && !empty($interactionId)) {
          $existingCount = $this->deduplicator->interactionIdDuplicateCount($tableName, $interactionId);

          if ($existingCount > 0) {
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Skipping duplicate interaction_id: {$interactionId} (found {$existingCount} existing)",
                'info'
              );
            }
            return true; // Return true since it's already stored
          }
        }

        // Check 2: By exact content hash + user_id + language_id
        $existingCount = $this->deduplicator->contentHashDuplicateCount($tableName, $contentHash, $userId, $languageId);

        if ($existingCount > 0) {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Skipping duplicate content (hash: {$contentHash}) for user {$userId}, lang {$languageId}",
              'info'
            );
          }
          return true; // Return true since it's already stored
        }
      } catch (\Exception $e) {
        // If check fails, continue with insertion (don't block on duplicate check error)
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Error checking for duplicate: " . $e->getMessage() . " - continuing with insertion",
            'warning'
          );
        }
      }
      
      // Add content_hash to metadata for future duplicate detection
      $metadata['content_hash'] = $contentHash;
      $metadata['unique_signature'] = $uniqueSignature;

      // Check if content needs chunking
      if (strlen($content) > $this->maxChunkSize) {
        return $this->storeWithChunking($content, $userId, $languageId, $metadata);
      }

      // Create document
      $document = new Document();
      $document->content = $content;
      $document->sourceType = 'conversation';

      // 🔧 FIX: Don't use sourceName for user_id - keep them separate
      // sourceName should be a descriptive name, not user_id
      $document->sourceName = 'conversation_' . ($metadata['interaction_id'] ?? uniqid());

      // 🔧 FIX: Ensure user_id and interaction_id are in metadata
      // These will be extracted by prepareEmbeddingAndMetadata()
      
      // Validate user_id is present
      if (empty($metadata['user_id'])) {
        $metadata['user_id'] = $userId ?? 'system';
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "⚠️  user_id was missing in metadata, using fallback: {$metadata['user_id']}",
            'warning'
          );
        }
      }
      
      // Validate interaction_id is present
      if (empty($metadata['interaction_id'])) {
        $metadata['interaction_id'] = uniqid('interaction_', true);
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "⚠️  interaction_id was missing in metadata, generated new one: {$metadata['interaction_id']}",
            'warning'
          );
        }
      }
      
      $metadata['sourcename'] = $document->sourceName; // Keep consistent

      // Store metadata - PHP 8.4+ compatible
      // LLPhant Document uses dynamic properties, suppress warning
      if (!property_exists($document, 'metadata')) {
        // Initialize as empty array if property doesn't exist
        @$document->metadata = [];
      }
      $document->metadata = $metadata;
      
      // 🔧 FIX: Log what we're about to store
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Storing interaction with user_id={$metadata['user_id']}, interaction_id={$metadata['interaction_id']}",
          'info'
        );
      }

      // Create embedding
      $document = $this->embeddingGenerator->embedDocument($document);

      // Store in vector database
      $this->vectorStore->addDocument($document);

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Interaction stored in long-term memory (length: " . strlen($content) . ", interaction_id: {$interactionId})",
          'info'
        );
      }

      return true;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error storing interaction: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Search for similar interactions in long-term memory
   *
   * @param string $query Query text
   * @param int $limit Maximum number of results
   * @param string|null $userId Filter by user ID (optional)
   * @param int|null $languageId Filter by language ID (optional)
   * @return array Array of similar documents
   */
  public function searchSimilar(string $query, int $limit = 3, ?string $userId = null, ?int $languageId = null, string $domain = 'Ecommerce'): array
  {
    try {
      $queryEntities = $this->entityMatcher->extractEntities($query, $domain);
      
      if ($this->debug && !empty($queryEntities)) {
        $this->logger->logSecurityEvent(
          "BUG FIX: Extracted " . count($queryEntities) . " entities from query for filtering (domain: {$domain}): " . json_encode($queryEntities),
          'info'
        );
      }
      
      // Build the per-user / per-language metadata filter (null = no filter).
      $filter = $this->retriever->buildMetadataFilter($userId, $languageId);

      // Fetch + rank candidate documents (initial search, fallback cascade, score-sort, limit).
      $resultsArray = $this->retriever->fetchRanked($query, $limit, $filter, $userId, $languageId);

      // Apply entity-specific filtering to prevent context pollution
      // This ensures "article 4" doesn't return "article 3" content
      if (!empty($queryEntities)) {
        $beforeEntityFilter = count($resultsArray);
        $resultsArray = $this->entityMatcher->filterDocumentsByEntities($resultsArray, $queryEntities, $domain);
        $afterEntityFilter = count($resultsArray);
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "BUG FIX: Entity filtering applied (domain: {$domain}) - {$beforeEntityFilter} documents -> {$afterEntityFilter} documents (filtered: " . ($beforeEntityFilter - $afterEntityFilter) . ")",
            'info'
          );
        }
      }

      if ($this->debug) {
        $filterInfo = $userId !== null || $languageId !== null 
          ? " (filtered: user={$userId}, lang={$languageId})" 
          : " (no filter)";
        $avgScore = 0;
        if (!empty($resultsArray)) {
          $scores = array_filter(array_map(function($doc) {
            return (isset($doc->metadata) && isset($doc->metadata['score'])) ? $doc->metadata['score'] : 0;
          }, $resultsArray));
          $avgScore = !empty($scores) ? round(array_sum($scores) / count($scores), 3) : 0;
        }
        $this->logger->logSecurityEvent(
          "Found " . count($resultsArray) . " similar interactions (avg score: {$avgScore}){$filterInfo}",
          'info'
        );
      }

      return array_values($resultsArray);

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error searching similar interactions: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Create embedding for text
   *
   * @param string $text Text to embed
   * @return array Embedding vector
   */
  public function createEmbedding(string $text): array
  {
    try {
      return $this->embeddingGenerator->embedText($text);
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error creating embedding: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Store interaction with chunking for long texts
   *
   * @param string $content Long content to chunk and store
   * @param int|string $userId User ID
   * @param int $languageId Language ID
   * @param array $metadata Metadata
   * @return bool Success of operation
   */
  private function storeWithChunking(string $content, int|string $userId, int $languageId, array $metadata = []): bool
  {
    try {
      // Enhanced duplicate detection for chunked content
      $tableName = $this->vectorStore->getTableName();
      
      // Calculate content hash for duplicate detection
      $contentHash = md5($content);
      $userId = (string)($metadata['user_id'] ?? 'system');
      $languageId = (int)($metadata['language_id'] ?? 1);
      
      try {
        // Check by interaction_id (if provided)
        $interactionId = $metadata['interaction_id'] ?? null;
        if ($interactionId !== null && !empty($interactionId)) {
          $existingCount = $this->deduplicator->interactionIdDuplicateCount($tableName, $interactionId);

          if ($existingCount > 0) {
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Skipping duplicate interaction_id (chunked): {$interactionId} (found {$existingCount} existing)",
                'info'
              );
            }
            return true;
          }
        }
      } catch (\Exception $e) {
        // If check fails, continue with insertion
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Error checking for duplicate (chunked): " . $e->getMessage() . " - continuing with insertion",
            'warning'
          );
        }
      }
      
      // Add content_hash to metadata for future duplicate detection
      $metadata['content_hash'] = $contentHash;

      // Create base document
      $baseDocument = new Document();
      $baseDocument->content = $content;
      $baseDocument->sourceType = 'conversation';
      $baseDocument->sourceName = $metadata['user_id'] ?? 'system';

      // Store metadata in the metadata property (not as dynamic properties)
      // This avoids PHP 8.x deprecated warnings
      // Check property existence before assignment
      if (!property_exists($baseDocument, 'metadata')) {
        @$baseDocument->metadata = [];
      }
      $baseDocument->metadata = $metadata;

      // Split document with cascading separators to guarantee maxChunkSize
      $chunks = DocumentSplitter::splitDocument($baseDocument, $this->maxChunkSize, "\n\n");

      // Re-split any oversized chunks with smaller separator
      $safeChunks = [];
      foreach ($chunks as $chunk) {
        if (strlen($chunk->content) > $this->maxChunkSize) {
          $subDoc = new Document();
          $subDoc->content = $chunk->content;
          $subDoc->sourceType = $chunk->sourceType ?? 'conversation';
          $subDoc->sourceName = $chunk->sourceName ?? '';
          if (!property_exists($subDoc, 'metadata')) {
            @$subDoc->metadata = [];
          }
          $subDoc->metadata = get_object_vars($chunk)['metadata'] ?? [];
          $subChunks = DocumentSplitter::splitDocument($subDoc, $this->maxChunkSize, " ");
          foreach ($subChunks as $sub) {
            $safeChunks[] = $sub;
          }
        } else {
          $safeChunks[] = $chunk;
        }
      }
      $chunks = $safeChunks;

      // Store each chunk
      $storedCount = 0;
      foreach ($chunks as $index => $chunk) {
        // Ensure chunk inherits critical metadata (entity_id, language_id, user_id, interaction_id)
        // Use get_object_vars to safely access dynamic properties
        $chunkVars = get_object_vars($chunk);
        $chunkMeta = $chunkVars['metadata'] ?? [];
        
        // 🔧 FIX: Validate user_id and interaction_id are present
        $chunkUserId = $metadata['user_id'] ?? 'system';
        $chunkInteractionId = $metadata['interaction_id'] ?? null;
        
        if (empty($chunkInteractionId)) {
          $chunkInteractionId = uniqid('interaction_chunk_', true);
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "⚠️  interaction_id missing for chunk {$index}, generated: {$chunkInteractionId}",
              'warning'
            );
          }
        }
        
        $mergedMeta = array_merge([
          'entity_id' => $metadata['entity_id'] ?? 0,
          'language_id' => $metadata['language_id'] ?? 1,
          'user_id' => $chunkUserId,
          'interaction_id' => $chunkInteractionId,
        ], $metadata, $chunkMeta, [
          'is_chunked' => true,
          'chunk_index' => $index,
        ]);
        
        // Check property existence before assignment
        if (!property_exists($chunk, 'metadata')) {
          @$chunk->metadata = [];
        }
        $chunk->metadata = $mergedMeta;
        // Create embedding
        $chunk = $this->embeddingGenerator->embedDocument($chunk);
        
        // Store in vector database
        $this->vectorStore->addDocument($chunk);
        $storedCount++;
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Long interaction chunked and stored: {$storedCount} chunks (interaction_id: {$interactionId})",
          'info'
        );
      }

      return true;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error storing with chunking: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

   /**
   * Clean duplicate entries from the vector store
   * Removes entries with same interaction_id or very similar content
   *
   * @return array Statistics about cleaned duplicates
   */
  public function cleanDuplicates(): array
  {
    return $this->deduplicator->cleanDuplicates($this->vectorStore->getTableName());
  }
}
