<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\CoreAI\Embedding;



use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Cache;

use LLPhant\Embeddings\DataReader\FileDataReader;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3LargeEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\Mistral\MistralEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\Voyage3EmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\Voyage3LargeEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\Voyage3LiteEmbeddingGenerator;
use LLPhant\OpenAIConfig;
use LLPhant\VoyageAIConfig;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Security\InputValidator;


class NewVector
{
  /**
   * Returns the appropriate API key based on the configured model
   * 
   * @return string The API key corresponding to the configured model
   */
  private static function getApiKey(): string
  {
    $api_key = CLICSHOPPING_APP_CHATGPT_CH_API_KEY;

    if (strpos(CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL, 'mistral') === 0) {
      $api_key = CLICSHOPPING_APP_CHATGPT_CH_API_KEY_MISTRAL;
    } elseif (strpos(CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL, 'voyage') === 0) {
      $api_key = CLICSHOPPING_APP_CHATGPT_RA_API_KEY_VOYAGE_AI;
    }

    return $api_key;
  }

  /**
   * Checks if the necessary API keys are available for the selected model
   * 
   * @param string $model The embedding model to check
   * @return bool True if API keys are available, false otherwise
   */
  private static function checkApiKeys(string $model): bool
  {
    if (strpos($model, 'gpt') === 0) {
      return !empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY);
    } elseif (strpos($model, 'mistral') === 0) {
      return !empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_MISTRAL);
    } elseif (strpos($model, 'voyage') === 0) {
      return !empty(CLICSHOPPING_APP_CHATGPT_RA_API_KEY_VOYAGE_AI);
    } elseif (strpos($model, 'ollama') === 0) {
      return true;
    }

    return false;
  }

  /**
   * Returns the list of available embedding models
   * 
   * @return array Array of available embedding models
   */
  public static function getEmbeddingModel(): array
  {
    $array = [
      ['id' => 'gpt-large', 'text' => 'OpenAI Large embedding (3072 dimensions)'],
      ['id' => 'gpt-medium', 'text' => 'OpenAI Medium embedding (1536 dimensions)'],
      ['id' => 'nomic-embed-text', 'text' => 'Ollama embedding nomic-embed-text (1536 dimensions)'],
      ['id' => 'mistral', 'text' => 'Mistral embedding (1024 dimensions)'],
      ['id' => 'voyage3', 'text' => 'Voyage 3 embedding (1024 dimensions)'],
      ['id' => 'voyage3-large', 'text' => 'Voyage 3 Large embedding (4096 dimensions)'],
      ['id' => 'voyage3-lite', 'text' => 'Voyage 3 Lite embedding (384 dimensions)'],
    ];

    return $array;
  }

  /**
   * Returns the appropriate embeddings generator based on the configured model
   *
   * @return object|null Instance of the appropriate embeddings generator or null if API key is missing
   */
  public static function gptEmbeddingsModel(): object|null
  {
    Gpt::getEnvironment();

    $model = CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL;

    if (!$model) {
      return null;
    }

    if (!self::checkApiKeys($model)) {
      return null;
    }

    $api_key = self::getApiKey();

    if (strpos($model, 'gpt-large') === 0) {
      $config = new OpenAIConfig();
      $config->apiKey = $api_key;
      return new OpenAI3LargeEmbeddingGenerator($config);
    } elseif (strpos($model, 'gpt-medium') === 0) {
      $config = new OpenAIConfig();
      $config->apiKey = $api_key;
      return new OpenAI3SmallEmbeddingGenerator($config);
    } elseif (strpos($model, 'mistral') === 0) {
      $config = new OpenAIConfig();
      $config->apiKey = $api_key;
      return new MistralEmbeddingGenerator($config);
    } elseif (strpos($model, 'voyage3-large') === 0) {
      $config = new VoyageAIConfig();
      $config->apiKey = $api_key;
      return new Voyage3LargeEmbeddingGenerator($config);
    } elseif (strpos($model, 'voyage3-lite') === 0) {
      $config = new VoyageAIConfig();
      $config->apiKey = $api_key;
      return new Voyage3LiteEmbeddingGenerator($config);
    } elseif (strpos($model, 'voyage3') === 0) {
      $config = new VoyageAIConfig();
      $config->apiKey = $api_key;
      return new Voyage3EmbeddingGenerator($config);
    } else {
      return new OllamaEmbeddingGenerator($model);
    }
  }

  /**
   * Generates embeddings for a set of documents or a text description
   *
   * @param string|null $path_file_upload The file path to process
   * @param string|null $text_description The text to process
   * @param int $token_length
   * @return array|null The generated embeddings or null on error
   * @throws ClientExceptionInterface
   */
  public static function createEmbedding(string|null $path_file_upload, string|null $text_description, ?int $token_length = null)
  {
    $embeddingGenerator = self::gptEmbeddingsModel();

    if ($embeddingGenerator === null) {
      return null;
    }

    if ($token_length === null) {
      $token_length = self::getOptimalChunkSize();
    }

    $maxContextLength = self::getModelContextLength();
    $safeMaxChunkSize = (int)($maxContextLength * 0.9);

    if ($token_length > $safeMaxChunkSize) {
      error_log("Warning: chunk size {$token_length} exceeds safe limit {$safeMaxChunkSize}, adjusting...");
      $token_length = $safeMaxChunkSize;
    }

    if (!empty($path_file_upload)) {
      $baseDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/Download/Private';

      $allowedExtensions = ['txt', 'pdf', 'doc', 'docx', 'csv', 'json', 'xml'];

      $validatedPath = InputValidator::validateFilePath(
        $path_file_upload,
        [$baseDir],
        $allowedExtensions,
        true
      );

      if ($validatedPath === false) {
        throw new \RuntimeException('Invalid or unauthorized file path');
      }

      $path_file_upload = $validatedPath;
    }

    try {
      if (is_file($path_file_upload)) {
        // ============================================================================
        // FILE BRANCH - Cache embeddings for file content
        // ============================================================================
        $filePath = $path_file_upload;
        $reader = new FileDataReader($filePath);
        $documents = $reader->getDocuments();

        $totalContent = '';
        foreach ($documents as $doc) {
          $totalContent .= $doc->content;
        }

        // Generate cache key (content + model + token_length)
        $model = CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL;
        $cacheKey = md5($totalContent . $model . $token_length);

        // Check cache (namespace: Rag/Embeddings)
        $cache = new Cache($cacheKey, 'Rag/Embeddings');

        if ($cache->exists(1440)) { // 1440 minutes = 24h
          $cachedData = $cache->get();
          $hydrated = self::hydrateCachedEmbeddings($cachedData);

          if ($hydrated !== null) {
            error_log("[INFO TIME] EMBEDDING CACHE HIT (file) - Duration: < 10ms - Model: {$model}");
           return $hydrated;
          }
        }

        error_log("[error] EMBEDDING CACHE MISS (file) - Calling API - Model: {$model}");

        $estimatedTokens = self::estimateTokenCount($totalContent);

        if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
          error_log("Info : File content: ~{$estimatedTokens} tokens, will split into chunks of {$token_length} tokens");
        }

        $splitDocuments = DocumentSplitter::splitDocuments($documents, $token_length);
        $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);

        // Generate embeddings (API call 200-500ms)
        $embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);

        // Save to cache (24h)
        $cache->save(self::serializeEmbeddings($embeddedDocuments));
        error_log("[INFO TIME] EMBEDDING CACHED (file) - TTL: 24h - Model: {$model}");

        return $embeddedDocuments;

      } else {
        // ============================================================================
        // TEXT BRANCH - Cache embeddings for text content
        // ============================================================================
        $embeddingGenerator = self::gptEmbeddingsModel();

        $estimatedTokens = self::estimateTokenCount($text_description);

        if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
          error_log("Info : Text content: ~{$estimatedTokens} tokens, chunk size: {$token_length}");
        }

        if ($estimatedTokens > $token_length) {
          // Multi-chunk text - cache the entire result
          $model = CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL;
          $cacheKey = md5($text_description . $model . $token_length);

          // Check cache (namespace: Rag/Embeddings)
          $cache = new Cache($cacheKey, 'Rag/Embeddings');

          if ($cache->exists(1440)) { // 1440 minutes = 24h
            $cachedData = $cache->get();
            $hydrated = self::hydrateCachedEmbeddings($cachedData);
	    if ($cachedData !== null) {
              error_log("[INFO TIME] EMBEDDING CACHE HIT (text-multi) - Duration: < 10ms - Model: {$model}");
              return $hydrated;
            }
          }

          error_log("[error] EMBEDDING CACHE MISS (text-multi) - Calling API - Model: {$model}");

          $tempDocument = new Document();
          $tempDocument->content = $text_description;
          $tempDocument->sourceName = 'manual';
          $tempDocument->sourceType = 'manual';

          $splitDocuments = DocumentSplitter::splitDocument($tempDocument, $token_length);
          $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);

          // Generate embeddings (API call 200-500ms)
          $embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);

          // Save to cache (24h)
          $cache->save(self::serializeEmbeddings($embeddedDocuments));
          error_log("[INFO TIME] EMBEDDING CACHED (text-multi) - TTL: 24h - Model: {$model}");

          return $embeddedDocuments;

        } else {
          // Single-chunk text - cache the result
          $model = CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL;
          $cacheKey = md5($text_description . $model);

          // Check cache (namespace: Rag/Embeddings)
          $cache = new Cache($cacheKey, 'Rag/Embeddings');

          if ($cache->exists(1440)) { // 1440 minutes = 24h
            $cachedData = $cache->get();
            $hydrated = self::hydrateCachedEmbeddings($cachedData);
           if ($hydrated !== null) {
              error_log("[INFO TIME] EMBEDDING CACHE HIT (text-single) - Duration: < 10ms - Model: {$model}");
             return $hydrated;
            }
          }

          error_log("[error] EMBEDDING CACHE MISS (text-single) - Calling API - Model: {$model}");

          // Generate embedding (API call 200-500ms)
          $embedded = $embeddingGenerator->embedText($text_description);

          $document = new Document();
          $document->content = $text_description;
          $document->embedding = $embedded;
          $document->sourceName = 'manual';
          $document->sourceType = 'manual';

          $embeddedDocuments = [$document];

          // Save to cache (24h)
          $cache->save(self::serializeEmbeddings($embeddedDocuments));
          error_log("[INFO TIME] EMBEDDING CACHED (text-single) - TTL: 24h - Model: {$model}");

          return $embeddedDocuments;
        }
      }

    } catch (\Exception $e) {
      $errorMessage = 'Error generating embeddings: ' . $e->getMessage();

      if (isset($estimatedTokens)) {
        $errorMessage .= " (estimated tokens: {$estimatedTokens}, chunk size: {$token_length})";
      }

      error_log($errorMessage);

      if (strpos($e->getMessage(), 'maximum context length') !== false && $token_length > 200) {
        error_log("Retrying with smaller chunk size...");
        return self::createEmbedding($path_file_upload, $text_description, (int)($token_length / 2));
      }

      return null;
    }
  }

  /**
   * Serialize embeddings to array form to avoid caching objects (unserialize safety).
   */
  private static function serializeEmbeddings(array $embeddedDocuments): array
  {
    $serialized = [];

    foreach ($embeddedDocuments as $doc) {
      if ($doc instanceof Document) {
        $serialized[] = [
          'content' => $doc->content ?? '',
          'embedding' => $doc->embedding ?? null,
          'sourceName' => $doc->sourceName ?? null,
          'sourceType' => $doc->sourceType ?? null,
          'chunkNumber' => $doc->chunkNumber ?? null,
          'metadata' => $doc->metadata ?? null,
          'id' => $doc->id ?? null,
        ];
      }
    }

    return $serialized;
  }

  /**
   * Hydrate cached embeddings into Document objects.
   *
   * Returns null if cache is invalid or contains incomplete objects.
   */
  private static function hydrateCachedEmbeddings($cachedData): ?array
  {
    if (!is_array($cachedData)) {
      return null;
    }

    $hydrated = [];

    foreach ($cachedData as $item) {
      if ($item instanceof Document) {
        $hydrated[] = $item;
        continue;
      }

      if (is_object($item)) {
        // Incomplete objects from unserialize should invalidate the cache.
        if (get_class($item) === '__PHP_Incomplete_Class') {
          return null;
        }

        // Unknown object type -> invalidate
        return null;
      }

      if (!is_array($item) || !isset($item['embedding']) || !is_array($item['embedding'])) {
        return null;
      }

      $doc = new Document();
      $doc->content = $item['content'] ?? '';
      $doc->embedding = $item['embedding'];
      $doc->sourceName = $item['sourceName'] ?? null;
      $doc->sourceType = $item['sourceType'] ?? null;
      $doc->chunkNumber = $item['chunkNumber'] ?? null;
      $doc->metadata = $item['metadata'] ?? null;
      $doc->id = $item['id'] ?? null;

      $hydrated[] = $doc;
    }

    return $hydrated;
  }

  /**
   * Estimate the number of tokens in a given text
   *
   * @param string $text The input text to estimate token count for
   * @return int Estimated number of tokens in the text
   */
  private static function estimateTokenCount(string $text): int
  {
    $model = CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL;

    if (strpos($model, 'gpt') === 0 || strpos($model, 'voyage') === 0) {
      $avgCharsPerToken = 3.5;
      return (int)ceil(strlen($text) / $avgCharsPerToken);
    }

    return (int)ceil(strlen($text) / 4);
  }

  /**
   * Saves all embedding chunks to the specified table with proper metadata.
   * 
   * This is the centralized method for saving embeddings across all entity types in ClicShopping AI.
   * It handles the complete lifecycle of embedding storage: validation, old chunk deletion (for updates),
   * and insertion of all new chunks with consistent metadata structure.
   * 
   * The method ensures that ALL chunks generated by createEmbedding() are saved to the database,
   * fixing the previous issue where only the first chunk was saved (losing 75-95% of document content).
   * 
   * ## Key Features:
   * - Saves ALL chunks from multi-chunk documents (not just the first one)
   * - Adds consistent chunk metadata (chunk_number, total_chunks, is_chunked)
   * - Handles both insert and update operations
   * - Deletes old chunks before inserting new ones (for updates)
   * - Provides comprehensive error handling and logging
   * - Works with all entity-specific tables (pages_manager_embedding, products_embedding, etc.)
   * 
   * ## Metadata Structure:
   * Each chunk will have metadata in this format:
   * ```json
   * {
   *   "entity_id": 123,
   *   "language_id": 1,
   *   "type": "pages_manager",
   *   "chunk_number": 1,
   *   "total_chunks": 13,
   *   "is_chunked": true,
   *   "date_modified": "2024-12-26 10:30:00",
   *   ...other entity-specific metadata...
   * }
   * ```
   * 
   * ## Usage Example:
   * ```php
   * // Step 1: Generate embeddings
   * $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);
   * 
   * // Step 2: Prepare base metadata
   * $baseMetadata = [
   *   'brand_name' => $page_manager_name,
   *   'content' => $page_manager_description,
   *   'type' => 'pages_manager',  // Entity type (goes in 'type' column)
   *   'document_type' => $document_type,
   *   'tags' => $tags,
   *   'source' => ['type' => 'manual', 'name' => 'manual']  // Required!
   * ];
   * 
   * // Step 3: Save all chunks
   * $result = NewVector::saveEmbeddingsWithChunks(
   *   $embeddedDocuments,
   *   'pages_manager_embedding',  // Table name
   *   (int)$item['pages_id'],     // Entity ID
   *   (int)$item['language_id'],  // Language ID
   *   $baseMetadata,
   *   $this->app->db,
   *   $isUpdate  // true if updating existing entity
   * );
   * 
   * // Step 4: Check result
   * if (!$result['success']) {
   *   error_log("Failed to save embeddings: " . $result['error']);
   * } else {
   *   error_log("Saved {$result['chunks_saved']} chunks successfully");
   * }
   * ```
   * 
   * ## Important Notes:
   * - The 'type' field in metadata (e.g., 'pages_manager') is different from the table name (e.g., 'pages_manager_embedding')
   * - The 'type' field identifies the entity type and is stored in the database 'type' column
   * - The 'source' field is REQUIRED and must be an array with 'type' and 'name' keys
   * - For updates, set $isUpdate = true to delete old chunks before inserting new ones
   * - The method logs all operations for debugging (check error logs)
   * 
   * ## Error Handling:
   * The method returns an array with success status, chunks saved count, and error message:
   * - On success: ['success' => true, 'chunks_saved' => 13, 'error' => null]
   * - On failure: ['success' => false, 'chunks_saved' => 0, 'error' => 'Error message']
   * 
   * Common errors:
   * - "No embeddings to save": Empty $embeddedDocuments array
   * - "Invalid table name": Empty $tableName parameter
   * - "Missing 'type' in metadata": Required 'type' field not in $baseMetadata
   * - "Missing or invalid 'source' in metadata": Required 'source' field not in $baseMetadata
   * - "No valid embeddings found": No valid embedding vectors in documents
   * - "Failed to delete old chunks": Database error during old chunk deletion
   * - "Failed to save chunk X": Database error during chunk insertion
   *
   * @param array $embeddedDocuments Array of LLPhant\Embeddings\Document objects returned by createEmbedding().
   *                                 Each document must have an 'embedding' property (array of floats) and 'content' property (string).
   * 
   * @param string $tableName Name of the embedding table WITHOUT prefix (e.g., 'pages_manager_embedding', 'products_embedding').
   *                          This is the DATABASE TABLE name, not the entity type.
   * 
   * @param int $entityId ID of the source entity (e.g., pages_id, products_id, categories_id).
   *                      Used to identify which entity these embeddings belong to.
   * 
   * @param ?int $languageId Language ID for multi-language support (e.g., 1 for English, 2 for French).
   *                         Used together with entity_id to uniquely identify embeddings.
   *                         Can be NULL for entities that don't have language support (e.g., orders, return_orders).
   *                         When NULL, language_id column will not be set in the database.
   * 
   * @param array $baseMetadata Base metadata array that will be enriched with chunk information.
   *                            REQUIRED fields:
   *                            - 'type' (string): Entity type (e.g., 'pages_manager', 'products', 'categories')
   *                                              This goes in the database 'type' column
   *                            - 'source' (array): Source information with keys:
   *                                              ['type' => 'manual'|'auto'|'cron', 'name' => 'manual'|'cron'|etc]
   *                                              This goes in 'sourcetype' and 'sourcename' columns
   *                            OPTIONAL fields (entity-specific):
   *                            - 'brand_name', 'content', 'document_type', 'tags', 'legal_clauses', etc.
   * 
   * @param object $db Database connection object (typically $this->app->db in hooks).
   *                   Must support save() and delete() methods.
   * 
   * @param bool $isUpdate Whether this is an update operation (true) or insert operation (false).
   *                       If true, old chunks for this entity_id and language_id will be deleted before inserting new ones.
   *                       Default: false
   * 
   * @return array Associative array with operation result:
   *               - 'success' (bool): Whether the operation succeeded
   *               - 'chunks_saved' (int): Number of chunks successfully saved
   *               - 'error' (string|null): Error message if operation failed, null otherwise
   * 
   * @throws \Exception If JSON encoding fails or database operations fail
   * 
   * @see createEmbedding() For generating the $embeddedDocuments array
   * @see getOptimalChunkSize() For the default chunk size used by createEmbedding()
   * 
   * @since 1.0.0 Initial implementation of centralized chunk management
   */
  public static function saveEmbeddingsWithChunks(
    array $embeddedDocuments,
    string $tableName,
    int $entityId,
    ?int $languageId,
    array $baseMetadata,
    object $db,
    bool $isUpdate = false
  ): array {
    try {
      // Validation: Check if embedded documents array is empty
      if (empty($embeddedDocuments)) {
        error_log("NewVector::saveEmbeddingsWithChunks: No embeddings to save for entity {$entityId}");
        return ['success' => false, 'chunks_saved' => 0, 'error' => 'No embeddings to save'];
      }

      // Validation: Check if table name is provided
      if (empty($tableName)) {
        error_log("NewVector::saveEmbeddingsWithChunks: Invalid table name for entity {$entityId}");
        return ['success' => false, 'chunks_saved' => 0, 'error' => 'Invalid table name'];
      }

      // Validation: Check required metadata fields
      if (!isset($baseMetadata['type'])) {
        error_log("NewVector::saveEmbeddingsWithChunks: Missing 'type' in metadata for entity {$entityId}");
        return ['success' => false, 'chunks_saved' => 0, 'error' => "Missing 'type' in metadata"];
      }

      if (!isset($baseMetadata['source']) || !is_array($baseMetadata['source'])) {
        error_log("NewVector::saveEmbeddingsWithChunks: Missing or invalid 'source' in metadata for entity {$entityId}");
        return ['success' => false, 'chunks_saved' => 0, 'error' => "Missing or invalid 'source' in metadata"];
      }

      // Extract embeddings from Document objects
      $embeddings = [];
      foreach ($embeddedDocuments as $embeddedDocument) {
        if (is_object($embeddedDocument) && isset($embeddedDocument->embedding) && is_array($embeddedDocument->embedding)) {
          $embeddings[] = $embeddedDocument->embedding;
        }
      }

      // Validation: Check if we have valid embeddings
      if (empty($embeddings)) {
        error_log("NewVector::saveEmbeddingsWithChunks: No valid embeddings found for entity {$entityId}");
        return ['success' => false, 'chunks_saved' => 0, 'error' => 'No valid embeddings found'];
      }

      $totalChunks = count($embeddings);
      $isChunked = $totalChunks > 1;

      error_log("NewVector::saveEmbeddingsWithChunks: Processing {$totalChunks} chunk(s) for entity {$entityId} in table {$tableName}");

      // Delete old chunks if this is an update
      if ($isUpdate) {
        try {
          $deleteConditions = ['entity_id' => $entityId];
          
          // Only add language_id condition if it's not null
          if ($languageId !== null) {
            $deleteConditions['language_id'] = $languageId;
          }
          
          $deleteResult = $db->delete($tableName, $deleteConditions);

          $langInfo = $languageId !== null ? "language {$languageId}" : "all languages";
          error_log("NewVector::saveEmbeddingsWithChunks: Deleted old chunks for entity {$entityId}, {$langInfo}");
        } catch (\Exception $e) {
          error_log("NewVector::saveEmbeddingsWithChunks: Failed to delete old chunks for entity {$entityId}: " . $e->getMessage());
          return ['success' => false, 'chunks_saved' => 0, 'error' => 'Failed to delete old chunks: ' . $e->getMessage()];
        }
      }

      // Save all chunks
      $chunksSaved = 0;
      foreach ($embeddings as $chunkIndex => $embeddingVector) {
        try {
          // Get content from the specific chunk document
          $content = '';
          if (isset($embeddedDocuments[$chunkIndex]) && is_object($embeddedDocuments[$chunkIndex]) && isset($embeddedDocuments[$chunkIndex]->content)) {
            $content = $embeddedDocuments[$chunkIndex]->content;
          }
          
          // Convert embedding vector to JSON string
          $embeddingLiteral = json_encode($embeddingVector, JSON_THROW_ON_ERROR);

          // Prepare chunk-specific metadata
          $chunkMetadata = array_merge($baseMetadata, [
            'chunk_number' => $chunkIndex + 1,
            'total_chunks' => $totalChunks,
            'is_chunked' => $isChunked,
            'date_modified' => date('Y-m-d H:i:s')
          ]);
          
          // Only add language_id to metadata if it's not null
          if ($languageId !== null) {
            $chunkMetadata['language_id'] = $languageId;
          }

          // Prepare SQL data
          $sqlData = [
            'entity_id' => $entityId,
            'content' => $content,
            'type' => $baseMetadata['type'],
            'sourcetype' => $baseMetadata['source']['type'] ?? 'manual',
            'sourcename' => $baseMetadata['source']['name'] ?? 'manual',
            'vec_embedding' => $embeddingLiteral,
            'metadata' => json_encode($chunkMetadata, JSON_THROW_ON_ERROR),
            'date_modified' => 'now()'
          ];
          
          // Only add language_id to SQL data if it's not null
          if ($languageId !== null) {
            $sqlData['language_id'] = $languageId;
          }

          // Insert chunk into database
          $db->save($tableName, $sqlData);
          $chunksSaved++;

          if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
            error_log("NewVector::saveEmbeddingsWithChunks: Saved chunk " . ($chunkIndex + 1) . "/{$totalChunks} for entity {$entityId}");
          }

        } catch (\Exception $e) {
          error_log("NewVector::saveEmbeddingsWithChunks: Failed to save chunk " . ($chunkIndex + 1) . " for entity {$entityId}: " . $e->getMessage());
          return ['success' => false, 'chunks_saved' => $chunksSaved, 'error' => "Failed to save chunk " . ($chunkIndex + 1) . ": " . $e->getMessage()];
        }
      }

      error_log("NewVector::saveEmbeddingsWithChunks: Successfully saved all {$chunksSaved} chunk(s) for entity {$entityId}");

      return ['success' => true, 'chunks_saved' => $chunksSaved, 'error' => null];

    } catch (\Exception $e) {
      error_log("NewVector::saveEmbeddingsWithChunks: Unexpected error for entity {$entityId}: " . $e->getMessage());
      return ['success' => false, 'chunks_saved' => 0, 'error' => 'Unexpected error: ' . $e->getMessage()];
    }
  }



  /**
   * Retrieves the content of a document either from a specified file path or from a text description.
   *
   * @param string|null $path_file_upload The file path to upload and read the document from. Can be null.
   * @param string|null $text_description The text description to use if the file path is null or invalid. Can be null.
   *
   * @return string Returns the content of the document, either read from the file or taken from the text description.
   */
  public static function getDocument(string|null $path_file_upload, string|null $text_description): string
  {
    if (is_file($path_file_upload)) {
      $filePath = $path_file_upload;
      $reader = new FileDataReader($filePath);
      $documents = $reader->getDocuments();
      $documents = $documents[0]->content;
    } else {
      $documents = $text_description;
    }

    return $documents;
  }

//***********
// Statistics
//***********
  /**
   * Calculates the mean (average) value of the given array of numbers.
   *
   * @param array $values The array of numerical values to calculate the mean from.
   * @return float The calculated mean value of the array.
   * @throws DivisionByZeroError If the array is empty, causing a division by zero.
   */
  private function calculateMean(array $values)
  {
    return array_sum($values) / count($values);
  }

  /**
   * Calculates the variance of a given array of numeric values.
   *
   * @param array $values An array of numeric values for which to calculate the variance.
   * @return float The calculated variance of the provided values.
   * @throws \InvalidArgumentException If the input array is empty.
   */
   private function calculateVariance(array $values): float
  {
    $mean = $this->calculateMean($values);
    $sum_of_squared_diff = 0;

    foreach ($values as $value) {
      $sum_of_squared_diff += pow($value - $mean, 2);
    }

    if (empty($values)) {
      throw new \InvalidArgumentException('The array should not be empty.');
    }

    return $sum_of_squared_diff / count($values);
  }


  /**
   * Returns the embedding length for the configured embedding model
   *
   * @return int The embedding length in dimensions for the selected model
   */
  public static function getEmbeddingLength(): int
  {
    if (strpos(CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL, 'gpt-large') === 0) {
      return 3072;
    } elseif (strpos(CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL, 'gpt-medium') === 0) {
      return 1536;
    } elseif (strpos(CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL, 'mistral') === 0) {
      return 1024;
    } elseif (strpos(CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL, 'voyage3-large') === 0) {
      return 4096;
    } elseif (strpos(CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL, 'voyage3-lite') === 0) {
      return 384;
    } elseif (strpos(CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL, 'voyage3') === 0) {
      return 1024;
    } else {
      return 1536;
    }
  }

  /**
   * Returns the maximum context length for the configured embedding model
   *
   * @return int The maximum context length in tokens for the selected model
   */
  public static function getModelContextLength(): int
  {
    $model = CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL;

    $contextLengths = [
      'gpt-large' => 8192,
      'gpt-medium' => 8192,
      'mistral' => 4096,
      'voyage3-large' => 16000,
      'voyage3' => 8000,
      'voyage3-lite' => 4000,
      'nomic-embed-text' => 8192,
    ];

    foreach ($contextLengths as $modelPrefix => $length) {
      if (strpos($model, $modelPrefix) === 0) {
        return $length;
      }
    }

    return 4096;
  }
  
  /**
   * Returns the optimal chunk size for the configured embedding model
   *
   * @return int The optimal chunk size in tokens for the selected model
   */
  public static function getOptimalChunkSize(): int
  {
    $model = CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL;

    $chunkSizes = [
      'gpt-large' => 800,
      'gpt-medium' => 800,
      'mistral' => 500,
      'voyage3-large' => 1000,
      'voyage3' => 800,
      'voyage3-lite' => 300,
      'nomic-embed-text' => 800,
    ];

    foreach ($chunkSizes as $modelPrefix => $size) {
      if (strpos($model, $modelPrefix) === 0) {
        return $size;
      }
    }

    return 500;
  }

  /**
   * Identifies the embedding model provider based on its name.
   * @param string $model
   * @return string
   */
  private static function getModelProvider(string $model): string
  {
    if (strpos($model, 'gpt') === 0) return 'openai';
    if (strpos($model, 'mistral') === 0) return 'mistral';
    if (strpos($model, 'voyage') === 0) return 'voyageai';
    if (strpos($model, 'nomic') === 0) return 'ollama';

    return 'unknown';
  }

  //*********************
  // Not Used
  //*********************


  /**
   * Returns the configuration details for the current embedding model.
   *
   * @return array An associative array containing model configuration details such as model name, embedding length,
   *               context length, optimal chunk size, safe maximum chunk size, and provider.
   */
  public static function getModelConfiguration(): array
  {
    $model = CLICSHOPPING_APP_CHATGPT_RA_EMBEDDING_MODEL;

    return [
      'model' => $model,
      'embedding_length' => self::getEmbeddingLength(),
      'context_length' => self::getModelContextLength(),
      'optimal_chunk_size' => self::getOptimalChunkSize(),
      'safe_max_chunk_size' => (int)(self::getModelContextLength() * 0.9),
      'provider' => self::getModelProvider($model),
    ];
  }
  
  
   /**
   * Validates whether a document can be embedded based on its estimated token count and the specified maximum token limit.
   *
   * @param string $content The content of the document to validate.
   * @param int|null $maxTokens The maximum number of tokens allowed. If null, the optimal chunk size for the model will be used.
   * @return array An associative array containing:
   *               - 'valid' (bool): Whether the document is valid for embedding.
   *               - 'estimated_tokens' (int): The estimated number of tokens in the document.
   *               - 'max_tokens' (int): The maximum token limit used for validation.
   *               - 'chunks_needed' (int): The number of chunks needed if the document exceeds the max token limit.
   *               - 'should_split' (bool): Whether the document should be split into multiple chunks.
   */
  public static function validateDocumentSize(string $content, ?int $maxTokens = null): array
  {
    if ($maxTokens === null) {
      $maxTokens = self::getOptimalChunkSize();
    }

    $estimatedTokens = self::estimateTokenCount($content);
    $chunksNeeded = (int)ceil($estimatedTokens / $maxTokens);

    return [
      'valid' => $estimatedTokens <= $maxTokens,
      'estimated_tokens' => $estimatedTokens,
      'max_tokens' => $maxTokens,
      'chunks_needed' => $chunksNeeded,
      'should_split' => $chunksNeeded > 1,
    ];
  }

  /**
   * Calculates the standard deviation of the given array of values.
   *
   * @param array $values The array of numerical values to calculate the standard deviation for.
   * @return float The calculated standard deviation of the values.
   * @throws \InvalidArgumentException If the provided array is empty.
   */
  public function calculateStandardDeviation(array $values): float
  {
    $variance = $this->calculateVariance($values);

    if (empty($values)) {
      throw new \InvalidArgumentException('The array should not be empty.');
    }

    return sqrt($variance);
  }

  /**
   * Calculates the cosine similarity between two vectors.
   *
   * @param array $vec1 An array representing the first vector.
   * @param array $vec2 An array representing the second vector. Must have the same length as $vec1.
   * @return float The cosine similarity value, which ranges from -1 to 1. Returns 0.0 if either vector has zero magnitude.
   * @throws InvalidArgumentException If the input vectors do not have the same length.
   */
  public static function cosineSimilarity(array $vec1, array $vec2) :float
  {
    if (count($vec1) !== count($vec2)) {
      throw new InvalidArgumentException('Vectors must have the same length.');
    }

    $dot_product = 0;
    $magnitude_vec1 = 0;
    $magnitude_vec2 = 0;

    foreach ($vec1 as $i => $value) {
      $dot_product += $value * $vec2[$i];
      $magnitude_vec1 += $value * $value;
      $magnitude_vec2 += $vec2[$i] * $vec2[$i];
    }

    if ($magnitude_vec1 == 0 || $magnitude_vec2 == 0) {
      return 0.0; // Return 0 for vectors with no magnitude
    }

    return $dot_product / (sqrt($magnitude_vec1) * sqrt($magnitude_vec2));
  }
}
