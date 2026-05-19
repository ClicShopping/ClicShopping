<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\Rag;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\ResultFormatter;
use ClicShopping\AI\DomainsAI\Analytics\Agent\AnalyticsAgent;
use ClicShopping\AI\Config\DomainFields;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use LLPhant\Query\SemanticSearch\LLMReranker;

/**
 * MultiDBRAGManager Class
 *
 * This class manages multiple vector databases for Retrieval-Augmented Generation (RAG).
 * It provides functionality for document management, similarity search, and question answering
 * across multiple vector stores using OpenAI embeddings.
 *
 * Key features:
 * - Multiple vector store management
 * - Document embedding and storage
 * - Similarity search across multiple databases
 * - Question answering using RAG
 * - Support for different languages and entity types
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Rag
 */

class MultiDBRAGManager
{
  public mixed $app;
  public mixed $db;
  public mixed $language;
  private mixed $embeddingGenerator;
  private array $vectorStores = [];
  private mixed $securityLogger;
  private bool $debug = false;
  private static array $tableStatsCache = [];

  private mixed $resultFormatter;
  private int $userId;

  private ?LLMReranker $reranker = null;
  private bool $useReranking = false;

  /**
   * Constructor for MultiDBRAGManager
   * Initializes the RAG system with specified model and tables
   *
   * @param string|null $model OpenAI model to use (null for default configuration)
   * @param array $tableNames List of table names to use (empty for all embedding tables)
   * @param array $modelOptions Additional model options (temperature, etc.)
   * @throws \Exception If initialization fails
   */
  public function __construct(?string $model = null, array $tableNames = [], array $modelOptions = [])
  {
    // Initialisation de l'application ChatGpt via Registry
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGpt());
    }

    $this->app = Registry::get('ChatGpt');
    
    // $this->db = Registry::get('Db');
    $this->language = Registry::get('Language');
    $this->userId = AdministratorAdmin::getUserAdminId() ?? 0; // Default to 0 if no admin logged in

    // Load language definitions for ChatGpt app
    // Language definitions are now loaded from main.txt via CLICSHOPPING::getDef()
    // $this->app->loadDefinitions('Sites/ClicShoppingAdmin/rag_analytics_agent');

    if (!Registry::exists('ResultFormatter')) {
      registry::set('ResultFormatter', new ResultFormatter());
    }

    $this->resultFormatter = Registry::get('ResultFormatter');

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->securityLogger = new SecurityLogger();

    // 🔥 DEBUG CRITIQUE
    if ($this->debug) {
      error_log("====================================================");
      error_log("[info] MultiDBRAGManager::__construct() START");
      error_log("Model: " . ($model ?? 'null'));
      error_log("TableNames provided: " . print_r($tableNames, true));
      error_log("Debug enabled: " . ($this->debug ? 'YES' : 'NO'));
    }

    $parameters = null;
    $model = $model ?? (defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL') ? CLICSHOPPING_APP_CHATGPT_CH_MODEL : 'default_model');

    if (!is_null($model)) {
      $parameters['model'] = $model;
    } elseif (defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL')) {
      $parameters['model'] = CLICSHOPPING_APP_CHATGPT_CH_MODEL;
    }

    Gpt::getOpenAiGpt($parameters);

    // 🔥 APPEL CRITIQUE : Initialize vector stores
    if ($this->debug) {
      error_log("------------------------------------------------------------------------------");
      error_log("[info] About to call initializeVectorStores()...");
      error_log("TableNames param: " . (empty($tableNames) ? 'EMPTY (will auto-detect)' : implode(', ', $tableNames)));
    }

    $this->initializeVectorStores($tableNames);

    if ($this->debug) {
      error_log("------------------------------------------------------------------------------");
      error_log("[stats] After initializeVectorStores():");
      error_log("VectorStores count: " . count($this->vectorStores));
      error_log("VectorStores keys: " . implode(', ', array_keys($this->vectorStores)));

      if (empty($this->vectorStores)) {
        error_log("CRITICAL WARNING: vectorStores is EMPTY!");
      }
    }

    $this->embeddingGenerator = $this->createEmbeddingGenerator();

    // Initialize LLMReranker for better document relevance (Task 2.14.3)
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_USE_RERANKING')
      && CLICSHOPPING_APP_CHATGPT_RA_USE_RERANKING === 'True') {

      $this->useReranking = true;

      try {
        $chat = Gpt::getChatForModel();

        // Number of documents to return after reranking
        $nrOfOutputDocuments = CLICSHOPPING_APP_CHATGPT_RA_RERANKING_OUTPUT;

        $this->reranker = new LLMReranker($chat, $nrOfOutputDocuments);

        if ($this->debug) {
          error_log(" LLMReranker initialized with {$nrOfOutputDocuments} output documents");
        }
      } catch (\Exception $e) {
        error_log("[error] Failed to initialize LLMReranker: " . $e->getMessage());
        $this->useReranking = false;
        $this->reranker = null;
      }
    } else {
      $this->useReranking = false;
      if ($this->debug) {
        error_log("[info]️ Reranking disabled in configuration");
      }
    }

    if ($this->debug) {
      error_log("[info] MultiDBRAGManager::__construct() END");
      error_log("------------------------------------------");
    }
  }

  /**
   * Returns all known embedding tables (fully dynamic)
   *
   * This method dynamically detects all embedding tables in the database
   * by querying INFORMATION_SCHEMA. The method will find ANY table ending
   * with '_embedding', making it fully dynamic and extensible.
   *
   * Strategy:
   * 1. Primary: Dynamic detection via INFORMATION_SCHEMA (finds ALL *_embedding tables)
   * 2. Fallback: Returns empty array if detection fails
   *
   * No Hardcoded Lists:
   * - No hardcoded entity types in Core or Domain
   * - System automatically discovers all embedding tables
   * - New embeddings (including future parallel reads) are auto-detected
   * - If dynamic detection fails, empty array prevents stale data
   *
   * Adding New Embeddings:
   * - Simply create the embedding table in database (e.g., clic_new_entity_embedding)
   * - Dynamic detection will automatically find it
   * - No code changes needed
   *
   * @param bool $useCache Whether to use cached results (default: true)
   * @return array List of all embedding table names
   */
  public function knownEmbeddingTable(bool $useCache = true): array
  {
    // Static cache to avoid repeated database queries
    static $cachedTables = null;

    if ($useCache && $cachedTables !== null) {
      return $cachedTables;
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $dbName = CLICSHOPPING::getConfig('db_database');

    try {
      // Try to dynamically detect all *_embedding tables from database
      $sql = "SELECT TABLE_NAME 
              FROM INFORMATION_SCHEMA.TABLES 
              WHERE TABLE_SCHEMA = :dbName 
              AND TABLE_NAME LIKE :pattern 
              ORDER BY TABLE_NAME";

      $detectedTables = DoctrineOrm::select($sql, [
        'dbName' => $dbName,
        'pattern' => $prefix . '%_embedding'
      ]);

      $detectedTables = array_column($detectedTables, 'TABLE_NAME');

      if (!empty($detectedTables)) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Dynamically detected " . count($detectedTables) . " embedding tables from database",
            'info',
            ['tables' => $detectedTables]
          );
        }

        $cachedTables = $detectedTables;
        return $detectedTables;
      }

    } catch (\Exception $e) {
      // Log error but continue with fallback
      $this->securityLogger->logSecurityEvent(
        "Failed to dynamically detect embedding tables: " . $e->getMessage(),
        'warning'
      );
    }

    // Fallback: Return empty array (no hardcoded list)
    // The dynamic detection above handles ALL embedding tables automatically.
    // If dynamic detection fails, it's better to return empty than use stale hardcoded list.
    // This ensures the system adapts to new embeddings (including future parallel reads).
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "No embedding tables detected - returning empty array",
        'warning'
      );
    }

    $cachedTables = [];
    return [];
  }

  /**
   * Returns the embedding generator instance
   *
   * @return EmbeddingGeneratorInterface Instance of the embedding generator
   */
  private function getEmbeddingGenerator(): EmbeddingGeneratorInterface
  {
    if (!isset($this->embeddingGenerator)) {
      $this->embeddingGenerator = $this->createEmbeddingGenerator();
    }

    return $this->embeddingGenerator;
  }

  /**
   * Creates an embedding generator using the specified Gpt class
   *
   * @return EmbeddingGeneratorInterface Instance of the embedding generator
   */
  private function createEmbeddingGenerator(): EmbeddingGeneratorInterface
  {
    return new class(Gpt::class) implements EmbeddingGeneratorInterface {
      private $gptClass;

      /**
       * Constructor for the embedding generator
       *
       * @param string $gptClass Class name of the Gpt instance
       */
      public function __construct(string $gptClass)
      {
        $this->gptClass = $gptClass;
      }

      /**
       * Embeds a single text string
       *
       * @param string $text Text to embed
       * @return array Embedding vector
       */
      public function embedText(string $text): array
      {
        $generator = NewVector::gptEmbeddingsModel();

        if (!$generator) {
          throw new \RuntimeException('Embedding generator non initialisé');
        }

        return $generator->embedText($text);
      }

      /**
       * Embeds a single document
       *
       * @param Document $document Document object to embed
       * @return Document Embedded Document object
       */
      public function embedDocument(Document $document): Document
      {
        $document->embedding = NewVector::createEmbedding(null, $document->content);

        return $document;
      }

      /**
       * Embeds multiple documents
       *
       * @param array $documents Array of Document objects to embed
       * @return array Array of embedded Document objects
       */
      public function embedDocuments(array $documents): array
      {
        $results = [];

        foreach ($documents as $document) {
          $results[] = $this->embedDocument($document);
        }

        return $results;
      }

      /**
       * Returns the length of the embedding vector
       *
       * @return int Length of the embedding vector
       */
      public function getEmbeddingLength(): int
      {
        return NewVector::getEmbeddingLength();
      }
    };
  }

  /**
   * Initializes vector stores for the specified tables
   *
   * @param array $tableNames List of table names to initialize
   */
  private function initializeVectorStores(array $tableNames): void
  {
    $startTime = microtime(true);

    if ($this->debug) {
      error_log("=========================================================");
      error_log("[info] initializeVectorStores() CALLED");
      error_log("Input tableNames: " . (empty($tableNames) ? 'EMPTY' : implode(', ', $tableNames)));
    }

    // DECISION: Use provided tables OR auto-detection
    if (empty($tableNames)) {
      if ($this->debug) {
        error_log("[info] No tables provided, using auto-detection...");
      }

      try {
        $tableNames = DoctrineOrm::getEmbeddingTables();

        if ($this->debug) {
          error_log(" Auto-detected tables: " . implode(", ", $tableNames));
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log(" Auto-detection failed: " . $e->getMessage());
        }

        $this->securityLogger->logSecurityEvent("Error auto-detecting embedding tables: " . $e->getMessage(), 'error');

        // Fallback ultime
        $prefix = CLICSHOPPING::getConfig('db_table_prefix');
        $tableNames = [$prefix . 'pages_manager_embedding'];
      }
    }

    if ($this->debug) {
      error_log("---------------------------------------------------");
      error_log("[stats] Tables to initialize: " . implode(", ", $tableNames));
    }

    if (empty($tableNames)) {
      error_log("[error] CRITICAL: No tables to initialize!");
      return;
    }

    // Initialiser chaque VectorStore
    $successCount = 0;
    $failCount = 0;

    foreach ($tableNames as $tableName) {
      try {
        if ($this->debug) {
          error_log("---------------------------------------------------");
          error_log("[info] Creating VectorStore for: {$tableName}");
        }

        // Verify that the table exists before creating the VectorStore
        if (!DoctrineOrm::checkTableStructure($tableName)) {
          if ($this->debug) {
            error_log("Table {$tableName} does not exist, skipping");
          }

          $failCount++;
          continue;
        }

        // Create the VectorStore
        $vectorStore = new MariaDBVectorStore($this->getEmbeddingGenerator(), $tableName);

        // Store in $this->vectorStores
        $this->vectorStores[$tableName] = $vectorStore;

        $successCount++;

        if ($this->debug) {
          error_log("VectorStore created successfully");
          error_log("Current vectorStores count: " . count($this->vectorStores));
        }

      } catch (\Exception $e) {
        $failCount++;

        if ($this->debug) {
          error_log(" FAILED to create VectorStore for {$tableName}");
          error_log("Error: " . $e->getMessage());
          error_log("Trace: " . $e->getTraceAsString());
        }

        $this->securityLogger->logSecurityEvent("Error while initializing the vector store for the table {$tableName}: " . $e->getMessage(), 'error');

      }
    }

    if ($this->debug) {
      error_log("=========================================================");
      error_log("[stats] INITIALIZATION COMPLETE");
      error_log("Tables attempted: " . count($tableNames));
      error_log("Success: {$successCount}");
      error_log("Failed: {$failCount}");
      error_log("Final vectorStores count: " . count($this->vectorStores));
      error_log("VectorStores keys: " . (empty($this->vectorStores) ? 'NONE' : implode(', ', array_keys($this->vectorStores))));

      if (empty($this->vectorStores)) {
        error_log("CRITICAL: vectorStores is STILL EMPTY! ");
      } else {
        error_log("SUCCESS: vectorStores initialized with " . count($this->vectorStores) . " stores ");
      }
      error_log("[timing] initializeVectorStores() took " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
      error_log("=========================================================");
    }
  }

  /**
   * Adds a document to the specified vector store
   *
   * @param string $content Document content to add
   * @param string $tableName Name of the table to store the document
   * @param string $type Document type
   * @param string $sourceType Source type of the document
   * @param string $sourceName Name of the source
   * @param string|null $entityType Entity type (page, category, product, etc.)
   * @param int|null $entityId Entity ID
   * @param int|null $languageId Language ID
   * @return bool True if successful, false otherwise
   */
  public function addDocument(string $content, string $tableName, string $type = 'text', string $sourceType = 'manual', string $sourceName = 'manual', string|null $entityType = null, int|null $entityId = null, int|null $languageId = null, ?array $metadata = []): bool
  {
    try {
      // Check the table if the vector exist
      if (!isset($this->vectorStores[$tableName])) {
        // If the table does not exist, chack if exist inside the db
        if (!DoctrineOrm::checkTableStructure($tableName)) {
          // Id the table does not existe, create it
          if (!DoctrineOrm::createTableStructure($tableName)) {
            throw new \Exception("Unable to create the table {$tableName}");
          }
        }

        // Add the table to vector stores
        $this->vectorStores[$tableName] = new MariaDBVectorStore($this->embeddingGenerator, $tableName);
      }

      // meta data creation
      $document = new Document();
      $document->content = $content;
      $document->sourceType = $sourceType;
      $document->sourceName = $sourceName;
      $document->chunkNumber = 128;

       $array_data = [
        'type' => $type,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'language_id' => $languageId,
        'date_modified' => 'now()'
      ];
      
      
      $document->metadata = array_merge($array_data, $metadata);

      $this->vectorStores[$tableName]->addDocument($document);

      return true;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent('Error while adding the document: ' . $e->getMessage(), 'error');

      return false;
    }
  }

  /**
   * Searches for similar documents across all configured tables
   * Uses parallel search (UNION ALL) if enabled, falls back to sequential otherwise
   *
   * @param string $query Search query
   * @param int $limit Maximum number of results per table
   * @param float $minScore Minimum similarity score (0-1)
   * @param int|null $languageId Language ID for filtering results
   * @param string|null $entityType Entity type for filtering results
   * @return array Array of matching documents with similarity scores
   */

  public function searchDocuments(string $query, int $limit = 5, float $minScore = 0.5, int|null $languageId = null, string|null $entityType = null): array
  {
    try {
      // LOG COMPLET
      $array_log = [
        'limit' => $limit,
        'minScore' => $minScore,
        'languageId' => $languageId,
        'entityType' => $entityType
      ];

      $this->logSearchQuery($query, $array_log);

      // Check if parallel search is enabled
      $parallelEnabled = defined('CLICSHOPPING_APP_CHATGPT_RA_PARALLEL_ENABLED') && CLICSHOPPING_APP_CHATGPT_RA_PARALLEL_ENABLED === 'True';

      if ($parallelEnabled) {
        try {
          if ($this->debug) {
            error_log("🚀 Parallel search enabled - using UNION ALL approach");
          }
          return $this->searchDocumentsParallel($query, $limit, $minScore, $languageId, $entityType);
        } catch (\Exception $e) {
          error_log("⚠️ Parallel search failed, falling back to sequential: " . $e->getMessage());
          // Fallback to sequential
        }
      }

      // Sequential search (current implementation)
      if ($this->debug) {
        error_log("🔄 Using sequential search");
      }
      return $this->searchDocumentsSequential($query, $limit, $minScore, $languageId, $entityType);

    } catch (\Exception $e) {
      error_log("EXCEPTION in searchDocuments: " . $e->getMessage());
      error_log("Trace: " . $e->getTraceAsString());

      return [
        'documents' => [],
        'audit_metadata' => ['error' => $e->getMessage()]
      ];
    }
  }

  /**
   * Sequential search implementation (original logic)
   * Searches tables one by one
   *
   * @param string $query Search query
   * @param int $limit Maximum number of results per table
   * @param float $minScore Minimum similarity score (0-1)
   * @param int|null $languageId Language ID for filtering results
   * @param string|null $entityType Entity type for filtering results
   * @return array Array of matching documents with similarity scores
   */
  private function searchDocumentsSequential(string $query, int $limit, float $minScore, ?int $languageId, ?string $entityType): array
  {
    $allResults = [];

    if ($this->debug) {
      error_log("=== searchDocuments START (Sequential) ===");
      error_log("Query: {$query}");
      error_log("Limit: {$limit}, MinScore: {$minScore}");
      error_log("VectorStores count: " . count($this->vectorStores));
    }

    // CRITICAL CHECK
    if (empty($this->vectorStores)) {
      error_log("CRITICAL: No vector stores! Attempting to reinitialize...");

      // Attempt reinitialization
      $this->initializeVectorStores([]);

      if (empty($this->vectorStores)) {
        error_log("FAILED: Still no vector stores after reinitialization");
        return [
          'documents' => [],
          'audit_metadata' => [
            'error' => 'No vector stores initialized',
            'attempted_reinitialization' => true
          ]
        ];
      }
    }

    // Generate the embedding
    error_log("Generating embedding for query...");
    $queryEmbedding = $this->embeddingGenerator->embedText($query);
    error_log("Embedding generated, length: " . count($queryEmbedding));


    // Create filter
    $filter = null;

    if ($languageId !== null || $entityType !== null) {
      $filter = function ($metadata) use ($languageId, $entityType) {
        $match = true;

        // Only filter by language_id if it exists in metadata
        // Some tables (like orders) don't have language_id column
        if ($languageId !== null && isset($metadata['language_id'])) {
          $match = $match && ($metadata['language_id'] == $languageId);
        }
        // If language_id filter is requested but column doesn't exist, accept the document
        // This allows orders (no language_id) to appear in results

        if ($entityType !== null && isset($metadata['entity_type'])) {
          $match = $match && ($metadata['entity_type'] == $entityType);
        }

        return $match;
      };
    }

    // RECHERCHE PRIORITAIRE
    // 🔧 FIX: Increase limit before filtering to ensure we get enough results after PHP filter
    // The SQL LIMIT happens BEFORE the PHP filter, so if we request 10 results but filter by language_id,
    // we might get 0 results if the first 10 aren't in the target language
    // Solution: Request more results (limit * 5) to ensure we have enough after filtering
    $sqlLimit = $limit * 5;  // Request 5x more results to account for filtering

    foreach ($this->knownEmbeddingTable() as $priorityTable) {
      if (isset($this->vectorStores[$priorityTable])) {
        error_log("Searching in priority table: {$priorityTable}");

        try {
          $results = $this->vectorStores[$priorityTable]->similaritySearch($queryEmbedding, $sqlLimit, max(0.01, $minScore - 0.15), $filter);
          $resultsArray = is_array($results) ? $results : iterator_to_array($results);

          foreach ($resultsArray as $document) {
            // 🔧 FIX: Only apply priority boost to documents that match the language filter
            // This prevents Orders (no language_id) from getting boosted above PageManager
            if (isset($document->metadata['score'])) {
              // Only boost if document has language_id AND it matches the requested language
              // OR if no language filter was requested
              $shouldBoost = ($languageId === null) ||
                (isset($document->metadata['language_id']) && $document->metadata['language_id'] == $languageId);

              if ($shouldBoost) {
                $document->metadata['score'] = min(1.0, $document->metadata['score'] * 1.15);
                $document->metadata['priority_boost'] = true;
              }
            }
            $allResults[] = $document;
          }
        } catch (\Exception $e) {
          error_log("Priority search error in {$priorityTable}: " . $e->getMessage());
        }
      }
    }

    // 🔧 FIX: Sort all results by score BEFORE taking top N
    // This ensures PageManager (high score + boost) ranks above Categories/Manufacturers
    usort($allResults, function ($a, $b) {
      $scoreA = $a->metadata['score'] ?? 0;
      $scoreB = $b->metadata['score'] ?? 0;
      return $scoreB <=> $scoreA; // Descending order (highest score first)
    });

    if ($this->debug) {
      error_log("[stats] After sorting by score, top 5 results:");
      foreach (array_slice($allResults, 0, 5) as $i => $doc) {
        $score = $doc->metadata['score'] ?? 0;
        $entityType = $doc->metadata['entity_type'] ?? 'unknown';
        $entityId = $doc->metadata['entity_id'] ?? 'unknown';
        $boost = isset($doc->metadata['priority_boost']) ? '✓' : '✗';
        error_log("  #" . ($i + 1) . " - Score: " . number_format($score, 4) . " - Boost: {$boost} - Type: {$entityType} - ID: {$entityId}");
      }
    }

    // Prepare audit metadata
    $auditMetadata = [
      'search_mode' => 'sequential',
      'tables_searched' => count($this->vectorStores),
      'initial_results_count' => count($allResults)
    ];

    // Apply LLMReranker if enabled (Task 2.14.3)
    if ($this->debug) {
      error_log("[info] Reranking check:");
      error_log("  - useReranking: " . ($this->useReranking ? 'true' : 'false'));
      error_log("  - reranker is null: " . ($this->reranker === null ? 'true' : 'false'));
      error_log("  - allResults count: " . count($allResults));
    }

    if ($this->useReranking && $this->reranker !== null && count($allResults) > 0) {
      try {
        if ($this->debug) {
          error_log("[info] Applying LLMReranker to improve relevance...");
          error_log("Query for reranking: {$query}");
          error_log("Documents before reranking: " . count($allResults));
        }

        // Get the configured number of output documents for reranking
        // We send 2-3x more documents than we want back to give the LLM options
        $rerankingOutputCount = CLICSHOPPING_APP_CHATGPT_RA_RERANKING_OUTPUT;

        // Send 2x the output count to the reranker (but not more than available)
        $initialLimit = min(count($allResults), $rerankingOutputCount * 2);
        $documentsForReranking = array_slice($allResults, 0, $initialLimit);

        if ($this->debug) {
          error_log("Reranking {$initialLimit} documents to get top {$rerankingOutputCount}");
        }

        // Apply LLMReranker - this will reorder documents by relevance
        // transformDocuments expects: array of questions, array of documents
        $rerankedDocuments = $this->reranker->transformDocuments([$query], $documentsForReranking);

        if ($this->debug) {
          error_log(" Reranking complete: " . count($rerankedDocuments) . " documents");

          // Log reranked order for debugging
          foreach ($rerankedDocuments as $i => $doc) {
            $preview = substr($doc->content, 0, 100);
            $score = $doc->metadata['score'] ?? 0;
            error_log("Reranked #{$i} (score: {$score}): {$preview}...");
          }
        }

        // Use reranked documents
        $allResults = $rerankedDocuments;

        // Add reranking metadata
        $auditMetadata['reranking_applied'] = true;
        $auditMetadata['reranking_input_count'] = $initialLimit;
        $auditMetadata['reranking_output_count'] = count($rerankedDocuments);
        $auditMetadata['final_results_count'] = count($allResults);

      } catch (\Exception $e) {
        error_log("[error] Reranking failed: " . $e->getMessage());
        error_log("Falling back to original order");

        // Fallback: use original order, just take top N
        $allResults = array_slice($allResults, 0, $limit);
        $auditMetadata['reranking_failed'] = true;
        $auditMetadata['reranking_error'] = $e->getMessage();
        $auditMetadata['final_results_count'] = count($allResults);
      }
    } else {
      // No reranking, just take top N by similarity score
      $allResults = array_slice($allResults, 0, $limit);
      $auditMetadata['reranking_applied'] = false;
      $auditMetadata['final_results_count'] = count($allResults);

      if ($this->debug) {
        error_log("ℹ[info] Reranking disabled or not available, using top {$limit} by similarity");
      }
    }

    $result = [
      'documents' => $allResults,
      'audit_metadata' => $auditMetadata
    ];

    return $result;
  }

  /**
   * Parallel search implementation using UNION ALL
   * Searches all tables simultaneously with a single SQL query
   *
   * @param string $query Search query
   * @param int $limit Maximum number of results
   * @param float $minScore Minimum similarity score (0-1)
   * @param int|null $languageId Language ID for filtering results
   * @param string|null $entityType Entity type for filtering results
   * @return array Array of matching documents with similarity scores
   */
  private function searchDocumentsParallel(string $query, int $limit, float $minScore, ?int $languageId, ?string $entityType): array
  {
    $startTime = microtime(true);

    if ($this->debug) {
      error_log("=== searchDocuments START (Parallel - UNION ALL) ===");
      error_log("Query: {$query}");
      error_log("Limit: {$limit}, MinScore: {$minScore}");
    }

    // CRITICAL CHECK
    if (empty($this->vectorStores)) {
      error_log("CRITICAL: No vector stores! Attempting to reinitialize...");
      $this->initializeVectorStores([]);

      if (empty($this->vectorStores)) {
        error_log("FAILED: Still no vector stores after reinitialization");
        return [
          'documents' => [],
          'audit_metadata' => [
            'error' => 'No vector stores initialized',
            'attempted_reinitialization' => true,
            'search_mode' => 'parallel'
          ]
        ];
      }
    }

    // Generate query embedding
    error_log("Generating embedding for query...");
    $queryEmbedding = $this->embeddingGenerator->embedText($query);
    error_log("Embedding generated, length: " . count($queryEmbedding));

    // Convert embedding to JSON string for SQL
    $embeddingJson = json_encode($queryEmbedding);

    // Build UNION ALL query for all tables
    $unionQueries = [];
    $tables = $this->knownEmbeddingTable();
    $sqlLimit = (int)($limit * 5); // Request 5x more results to account for filtering - cast to int

    foreach ($tables as $table) {
      if (isset($this->vectorStores[$table])) {
        // Check if table has metadata column
        $hasMetadata = DoctrineOrm::columnExists($table, 'metadata');

        // Build metadata/content SELECT clauses with forced collation to prevent UNION collation errors
        $contentSelect = 'CONVERT(content USING utf8mb4) COLLATE utf8mb4_unicode_ci AS content';
        $metadataSelect = $hasMetadata
          ? 'CONVERT(metadata USING utf8mb4) COLLATE utf8mb4_unicode_ci AS metadata'
          : "CONVERT(JSON_OBJECT() USING utf8mb4) COLLATE utf8mb4_unicode_ci AS metadata";

        // Build sub-query for this table
        // Use literal value instead of parameter for LIMIT
        // Doctrine cannot bind the same parameter multiple times in UNION ALL
        $subQuery = "(
          SELECT 
            '{$table}' as source_table,
            id,
            {$contentSelect},
            embedding,
            {$metadataSelect},
            (1 / (1 + VEC_DISTANCE_COSINE(embedding, VEC_FromText(:queryEmbedding)))) as similarity_score
          FROM {$table}
          HAVING similarity_score >= :minScore";

        // Add language filter if specified (only if metadata column exists)
        if ($languageId !== null && $hasMetadata) {
          $subQuery .= " AND JSON_EXTRACT(metadata, '$.language_id') = :languageId";
        }

        // Add entity type filter if specified (only if metadata column exists)
        if ($entityType !== null && $hasMetadata) {
          $subQuery .= " AND JSON_EXTRACT(metadata, '$.entity_type') = :entityType";
        }

        // Use literal integer value for LIMIT (not quoted)
        $subQuery .= "
          ORDER BY similarity_score DESC
          LIMIT " . $sqlLimit . "
        )";

        $unionQueries[] = $subQuery;
      }
    }

    if (empty($unionQueries)) {
      error_log("No tables available for parallel search");
      return [
        'documents' => [],
        'audit_metadata' => [
          'error' => 'No tables available',
          'search_mode' => 'parallel'
        ]
      ];
    }

    // Combine all sub-queries with UNION ALL
    $sql = implode(" UNION ALL ", $unionQueries);
    // Use literal integer for final LIMIT (not parameter to avoid quoting issues)
    $sql .= " ORDER BY similarity_score DESC LIMIT " . $sqlLimit;

    // Prepare parameters (removed finalLimit parameter)
    $params = [
      'queryEmbedding' => $embeddingJson,
      'minScore' => max(0.01, $minScore - 0.15), // Same adjustment as sequential
    ];

    if ($languageId !== null) {
      $params['languageId'] = $languageId;
    }

    if ($entityType !== null) {
      $params['entityType'] = $entityType;
    }

    if ($this->debug) {
      error_log("Executing parallel search across " . count($unionQueries) . " tables");
      error_log("SQL Limit per table: {$sqlLimit}, Final limit: {$sqlLimit}");
    }

    // Execute parallel query
    try {
      $results = DoctrineOrm::select($sql, $params);

      $duration = (microtime(true) - $startTime) * 1000;
      $sequentialEstimate = count($unionQueries) * 200; // Estimate 200ms per table

      if ($this->debug) {
        error_log("✅ Parallel search completed in " . round($duration, 2) . "ms");
        error_log("   Tables searched: " . count($unionQueries));
        error_log("   Results found: " . count($results));
        error_log("   Sequential would have taken: ~{$sequentialEstimate}ms");
        error_log("   Time saved: ~" . round($sequentialEstimate - $duration, 2) . "ms");
        error_log("   Speedup: " . round($sequentialEstimate / $duration, 1) . "x faster");
      }

      // Convert results to Document objects
      $allResults = $this->convertSQLResultsToDocuments($results);

      // Apply priority boost (same logic as sequential)
      foreach ($allResults as $document) {
        if (isset($document->metadata['score'])) {
          $shouldBoost = ($languageId === null) ||
            (isset($document->metadata['language_id']) && $document->metadata['language_id'] == $languageId);

          if ($shouldBoost) {
            $document->metadata['score'] = min(1.0, $document->metadata['score'] * 1.15);
            $document->metadata['priority_boost'] = true;
          }
        }
      }

      // Sort by score
      usort($allResults, function ($a, $b) {
        $scoreA = $a->metadata['score'] ?? 0;
        $scoreB = $b->metadata['score'] ?? 0;
        return $scoreB <=> $scoreA;
      });

      if ($this->debug) {
        error_log("[stats] After sorting by score, top 5 results:");
        foreach (array_slice($allResults, 0, 5) as $i => $doc) {
          $score = $doc->metadata['score'] ?? 0;
          $entityType = $doc->metadata['entity_type'] ?? 'unknown';
          $entityId = $doc->metadata['entity_id'] ?? 'unknown';
          $boost = isset($doc->metadata['priority_boost']) ? '✓' : '✗';
          error_log("  #" . ($i + 1) . " - Score: " . number_format($score, 4) . " - Boost: {$boost} - Type: {$entityType} - ID: {$entityId}");
        }
      }

      // Prepare audit metadata
      $auditMetadata = [
        'search_mode' => 'parallel',
        'parallel_method' => 'UNION ALL',
        'tables_searched' => count($unionQueries),
        'initial_results_count' => count($allResults),
        'search_duration_ms' => round($duration, 2),
        'estimated_sequential_ms' => $sequentialEstimate,
        'time_saved_ms' => round($sequentialEstimate - $duration, 2),
        'speedup_factor' => round($sequentialEstimate / $duration, 1)
      ];

      // Apply LLMReranker if enabled (same logic as sequential)
      if ($this->useReranking && $this->reranker !== null && count($allResults) > 0) {
        try {
          if ($this->debug) {
            error_log("[info] Applying LLMReranker to improve relevance...");
          }

          $rerankingOutputCount = CLICSHOPPING_APP_CHATGPT_RA_RERANKING_OUTPUT;
          $initialLimit = min(count($allResults), $rerankingOutputCount * 2);
          $documentsForReranking = array_slice($allResults, 0, $initialLimit);

          $rerankedDocuments = $this->reranker->transformDocuments([$query], $documentsForReranking);

          if ($this->debug) {
            error_log("✅ Reranking complete: " . count($rerankedDocuments) . " documents");
          }

          $allResults = $rerankedDocuments;
          $auditMetadata['reranking_applied'] = true;
          $auditMetadata['reranking_input_count'] = $initialLimit;
          $auditMetadata['reranking_output_count'] = count($rerankedDocuments);
          $auditMetadata['final_results_count'] = count($allResults);

        } catch (\Exception $e) {
          error_log("[error] Reranking failed: " . $e->getMessage());
          $allResults = array_slice($allResults, 0, $limit);
          $auditMetadata['reranking_failed'] = true;
          $auditMetadata['reranking_error'] = $e->getMessage();
          $auditMetadata['final_results_count'] = count($allResults);
        }
      } else {
        $allResults = array_slice($allResults, 0, $limit);
        $auditMetadata['reranking_applied'] = false;
        $auditMetadata['final_results_count'] = count($allResults);
      }

      return [
        'documents' => $allResults,
        'audit_metadata' => $auditMetadata
      ];

    } catch (\Exception $e) {
      error_log("❌ Parallel search failed: " . $e->getMessage());
      error_log("Trace: " . $e->getTraceAsString());
      throw $e; // Re-throw to trigger fallback to sequential
    }
  }

  /**
   * Convert SQL results from parallel search to Document objects
   *
   * @param array $results SQL results from UNION ALL query
   * @return array Array of Document objects
   */
  private function convertSQLResultsToDocuments(array $results): array
  {
    $documents = [];

    foreach ($results as $row) {
      try {
        // Parse metadata JSON
        $metadata = json_decode($row['metadata'], true) ?? [];

        // Add similarity score to metadata
        $metadata['score'] = $row['similarity_score'];
        $metadata['source_table'] = $row['source_table'];

        // Create Document object (using LLPhant Document class)
        $document = new Document();
        $document->content = $row['content'];
        $document->metadata = $metadata;

        $documents[] = $document;

      } catch (\Exception $e) {
        error_log("Error converting SQL result to Document: " . $e->getMessage());
        continue;
      }
    }

    return $documents;
  }


  /**
   * Formats the analysis results for display
   *
   * @param array $results Analysis results
   * @return string|null Formatted results for display
   */
  public function formatResults(array $results): string|null
  {
    try {
      // Validate input
      if (empty($results)) {
        return 'No results to display';
      }

      // If results don't have a 'type' key, try to infer it or set a default
      if (!isset($results['type'])) {
        // Check if it looks like analytics results
        if (isset($results['results']) && is_array($results['results'])) {
          $results['type'] = 'analytics_results';
        } // Check if it looks like a semantic response
        else if (isset($results['response']) || isset($results['query'])) {
          $results['type'] = 'semantic_results';
        } // Default to unknown
        else {
          $results['type'] = 'unknown';
        }
      }

      $result = $this->resultFormatter->format($results);

      if (is_array($result) && array_key_exists('content', $result)) {
        $result = $result['content'];
      } else {
        $result = '<span class="alert alert-warning">Error : please change or adapt your question</span>';
      }

      return $result;
    } catch (\Exception $e) {
      // Log the error for debugging
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          'Error in formatResults: ' . $e->getMessage(),
          'error',
          ['results' => $results]
        );
      }

      return 'An error occurred while formatting the results. Please try again.';
    }
  }

  /**
   * Executes an analytical query on e-commerce data
   *
   * This method is specifically designed for analytical queries
   * that require calculations, aggregations, or precise searches
   * on numerical or structured data.
   *
   * @param string $query User\'s question or query
   * @param string|null $entityType Type of entity to analyze (products, orders, etc.)
   * @return array Analysis results with structured data
   */
  public function executeAnalyticsQuery(string $query, string|null $entityType = null): array
  {
    try {
      $analyticsAgent = new AnalyticsAgent();

      //Check the request
      if (!$analyticsAgent->isAnalyticsQuery($query)) {
        return [
          'type' => 'not_analytics',
          'message' => CLICSHOPPING::getDef('text_not_analytics')
        ];
      }

      $results = $analyticsAgent->processBusinessQuery($query);

      if ($results['type'] === 'error') {
        return [
          'type' => 'error',
          'message' => $results['message']
        ];
      }

      $matchedCategories = $analyticsAgent->getAnalyticsCategories($query);

      $response = [
        'type' => 'analytics_results',
        'query' => $query,
        'matched_categories' => $matchedCategories,
        'interpretation' => Hash::displayDecryptedDataText($results['interpretation'] ?? ''),
        'count' => $results['count'] ?? 0,
        'results' => $results['results'] ?? []
      ];

      // If we have multiple SQL query blocks
      if (isset($results[‘multi_query_results’])) {
        $response[‘multi_query_results’] = $results[‘multi_query_results’];
      } // Otherwise return the single SQL query
      else {
        // sql_query key created by processBusinessQuery
        $response[‘sql_query’] = $results[‘sql_query’] ?? ‘’;
        // if you keep the original
        if (isset($results[‘original_sql_query’])) {
          $response[‘original_sql_query’] = $results[‘original_sql_query’];
        }
        // possible corrections
        if (isset($results[‘corrections’])) {
          $response[‘corrections’] = $results[‘corrections’];
        }
      }

      return $response;

    } catch (\Exception $e) {
      return [
        'type' => 'error',
        'message' => 'Error executing analytics query: ' . $e->getMessage()
      ];
    }
  }

  /**
   * LOGGING METHOD - Logs the complete details of a search query
   */
  private function logSearchQuery(string $query, array $params): void
  {
    $logMessage = "=== SEARCH QUERY LOG ===";
    $logMessage .= "Query: {$query}\n";
    $logMessage .= "Params:\n";
    $logMessage .= "  - limit: " . ($params['limit'] ?? 'N/A') . "\n";
    $logMessage .= "  - minScore: " . ($params['minScore'] ?? 'N/A') . "\n";
    $logMessage .= "  - languageId: " . ($params['languageId'] ?? 'N/A') . "\n";
    $logMessage .= "  - entityType: " . ($params['entityType'] ?? 'N/A') . "\n";
    $logMessage .= "Vector stores available: " . count($this->vectorStores) . "\n";
    $logMessage .= "Vector stores keys: " . implode(', ', array_keys($this->vectorStores)) . "\n";

    error_log($logMessage);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent($logMessage, 'info');
    }
  }

  /**
   * Answer a question using RAG (Retrieval-Augmented Generation)
   *
   * Restored to fix semantic RAG regression
   *
   * This method:
   * 1. Searches for relevant documents using embeddings
   * 2. Builds context from retrieved documents
   * 3. Uses LLM to generate answer based on context
   *
   * @param string $question Question to answer
   * @param int $limit Maximum number of documents to retrieve
   * @param float $minScore Minimum similarity score
   * @param int|null $languageId Language ID for filtering
   * @param string|null $entityType Entity type for filtering
   * @param array $options Additional options (return_metadata, etc.)
   * @return array|string Answer with metadata or just answer string
   */
  public function answerQuestion(
    string  $question,
    int     $limit = 5,
    float   $minScore = 0.5,
    ?int    $languageId = null,
    ?string $entityType = null,
    array   $options = []
  ): array|string
  {
    try {
      if ($this->debug) {
        error_log("=== answerQuestion() START ===");
        error_log("Question: {$question}");
        error_log("Limit: {$limit}, MinScore: {$minScore}");
      }

      // Search for relevant documents
      $searchResult = $this->searchDocuments(
        $question,
        $limit,
        $minScore,
        $languageId,
        $entityType
      );

      $documents = $searchResult['documents'] ?? [];
      $auditMetadata = $searchResult['audit_metadata'] ?? [];

      if ($this->debug) {
        error_log("Found " . count($documents) . " documents");
      }

      // If no documents found, return "no information" message
      if (empty($documents)) {
        $noInfoMessage = "I don't have that information in my knowledge base.";

        if ($options['return_metadata'] ?? false) {
          return [
            'response' => $noInfoMessage,
            'audit_metadata' => $auditMetadata,
            'documents_found' => 0
          ];
        }

        return $noInfoMessage;
      }

      // Build context from documents (with priority handling)
      $context = $this->optimizeContext($documents, 3000);

      $documentNames = [];
      foreach ($documents as $doc) {
        $docName = $this->extractDocumentName($doc);
        // Only include real document names (not generic "Document" fallback)
        if ($docName !== "Document") {
          $documentNames[] = $docName;
        }
      }

      // Remove duplicates and re-index array
      $documentNames = array_values(array_unique($documentNames));

      // Generate answer using LLM with context
      $synthesisPrompt = "Based on the following information, answer this question: {$question}\n\nInformation:\n{$context}\n\n";

      // The formatter will display sources at the end in italic, so the LLM should not include them

      $synthesisPrompt .= "Answer:";

      // 🔥 CRITICAL FIX: Add language instruction with anti-hallucination rules
      // This forces the LLM to respond in the user's language and prevents hallucination
      $languageInstruction = CLICSHOPPING::getDef('text_rag_language_instruction');
      $synthesisPrompt .= "\n\n" . $languageInstruction;

      if ($this->debug) {
        error_log("[info] Prompt with language instruction: " . strlen($synthesisPrompt) . " chars");
      }

      try {
        $answer = Gpt::getGptResponse($synthesisPrompt, 300);

        if ($this->debug) {
          error_log("Generated answer length: " . strlen($answer) . " chars");
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("Error generating answer: " . $e->getMessage());
        }

        // Fallback: return first document content
        $answer = $documents[0]->content ?? "Error generating answer.";
      }

      // Return with metadata if requested
      if ($options['return_metadata'] ?? false) {
        return [
          'response' => $answer,
          'audit_metadata' => $auditMetadata,
          'documents_found' => count($documents),
          'context_length' => strlen($context)
        ];
      }

      return $answer;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("answerQuestion() exception: " . $e->getMessage());
      }

      $errorMessage = "Error answering question: " . $e->getMessage();

      if ($options['return_metadata'] ?? false) {
        return [
          'response' => $errorMessage,
          'audit_metadata' => [],
          'error' => $e->getMessage()
        ];
      }

      return $errorMessage;
    }
  }

  /**
   * Build context with priority document handling
   *
   * Priority documents (with priority_boost metadata) get full content
   * Other documents are truncated to maxCharsPerDoc
   *
   * This ensures critical information from priority sources is not lost,
   * which is essential for accurate answers (e.g., "14 days" vs "7 days")
   *
   * @param array $documents Array of Document objects
   * @param int $maxCharsPerDoc Maximum chars per non-priority document
   * @return string Formatted context string
   */
  private function optimizeContext(array $documents, int $maxCharsPerDoc = 3000): string
  {
    $context = '';
    $totalChars = 0;
    $maxTotalChars = 60000; // Global limit: ~15,000 tokens

    // Function to detect priority documents
    $isPriorityDoc = function ($doc) {
      return isset($doc->metadata['priority_boost']) && $doc->metadata['priority_boost'] === true;
    };

    foreach ($documents as $i => $doc) {
      $documentName = $this->extractDocumentName($doc);

      // Priority documents get FULL content (no truncation)
      if ($isPriorityDoc($doc)) {
        $docContent = $doc->content; //  FULL CONTENT
        $label = $documentName . " (Priority Source)";

        if ($this->debug) {
          error_log("[info] Doc #{$i} PRIORITY ({$documentName}): " . strlen($docContent) . " chars (full content)");
        }
      } else {
        // Other documents are truncated
        $docContent = $doc->content;
        if (strlen($docContent) > $maxCharsPerDoc) {
          $docContent = mb_substr($docContent, 0, $maxCharsPerDoc) . "\n[...content truncated...]";
        }
        $label = $documentName;

        if ($this->debug) {
          error_log("[info] Doc #{$i} secondary ({$documentName}): " . strlen($docContent) . " chars (truncated)");
        }
      }

      // Check global limit
      if ($totalChars + strlen($docContent) > $maxTotalChars) {
        if ($this->debug) {
          error_log("⚠[warning] Context limit reached after " . ($i + 1) . " documents");
        }
        break;
      }

      $context .= "--- {$label} ---\n";
      $context .= $docContent . "\n\n";
      $totalChars += strlen($docContent);
    }

    if ($this->debug) {
      error_log("[stats] Context built: {$totalChars} chars (~" . round($totalChars / 4) . " tokens)");
    }

    return $context;
  }

  /**
   * Extract document name from document metadata
   *
   *
   * This method extracts the document name from metadata to use in prompts
   * instead of generic "Document 1", "Document 2" labels.
   *
   * Priority order:
   * 1. title (most common)
   * 2. document_name
   * 3. brand_name (for pages_manager)
   * 4. product_name (for products)
   * 5. category_name (for categories)
   * 6. name
   * 7. page_title
   * 8. source_table (as fallback)
   * 9. "Document" (last resort - changed from "Unknown Document" to avoid polluting LLM responses)
   *
   * @param object $doc Document object with metadata
   * @return string Document name
   */
  private function extractDocumentName($doc): string
  {
    // Try to get metadata
    $metadata = null;
    if (is_object($doc) && isset($doc->metadata)) {
      $metadata = $doc->metadata;
    } elseif (is_array($doc) && isset($doc['metadata'])) {
      $metadata = $doc['metadata'];
    }

    if ($metadata === null) {
      return "Document";
    }

    // Try different metadata fields in priority order
    $possibleFields = array_values(array_unique(array_merge(
      DomainFields::getPossibleFields(),
      ['title', 'document_name', 'page_title', 'name']
    )));

    foreach ($possibleFields as $field) {
      if (isset($metadata[$field]) && !empty($metadata[$field])) {
        $name = trim($metadata[$field]);

        // Clean up the name (remove extra whitespace, limit length)
        $name = preg_replace('/\s+/', ' ', $name);

        // Limit length to 100 chars for readability
        if (strlen($name) > 100) {
          $name = substr($name, 0, 97) . '...';
        }

        return $name;
      }
    }

    // Fallback: use source_table if available
    if (isset($metadata['source_table']) && !empty($metadata['source_table'])) {
      $tableName = $metadata['source_table'];

      // Remove prefix and _embedding suffix
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      if (!empty($prefix) && str_starts_with($tableName, $prefix)) {
        $tableName = substr($tableName, strlen($prefix));
      }
      $tableName = str_replace('_embedding', '', $tableName);

      // Convert to readable format (e.g., "pages_manager_description" -> "Pages Manager Description")
      $tableName = str_replace('_', ' ', $tableName);
      $tableName = ucwords($tableName);

      return $tableName;
    }

    // Last resort: return generic name (changed from "Unknown Document" to "Document")

    return "Document";
  }

  /**
   * Converts the structured context array into a string readable by the LLM.
   * (This is the logic that was placed in MemoryRetentionService, now moved here.)
   */
  private function formatContextArrayToLlmString(array $context): string
  {
    $formatted = "";

    // Working Memory
    if (!empty($context['working_memory']) && !empty($context['working_memory']['last_message'])) {
      $formatted .= "## IMMEDIATE WORKING MEMORY";
      $formatted .= "Last Question: " . substr($context['working_memory']['last_message'], 0, 200) . "\n";
      $formatted .= "Last Answer: " . substr($context['working_memory']['last_response'], 0, 200) . "\n";
      $formatted .= "---\n\n";
    }

    // Short-term (Current Session)
    if (!empty($context['short_term']) && is_array($context['short_term'])) {
      $formatted .= "## CURRENT SESSION HISTORY\n";
      foreach ($context['short_term'] as $message) {
        if (is_array($message)) {
          $role = isset($message['role']) ? ucfirst($message['role']) : 'System';
          $content = isset($message['content']) ? trim($message['content']) : '';
          if (!empty($content)) {
            $formatted .= "{$role}: " . substr($content, 0, 150) . "\n";
          }
        }
      }
      $formatted .= "---\n\n";
    }

    // Long-term (RAG Semantic)
    if (!empty($context['long_term']) && is_array($context['long_term'])) {
      $formatted .= "## RELEVANT PREVIOUS CONTEXT (SEMANTIC RAG)\n";
      foreach ($context['long_term'] as $interaction) {
        if (is_array($interaction)) {
          $userMsg = $interaction['user_message'] ?? '';
          $sysResp = $interaction['system_response'] ?? '';
          if (!empty($userMsg) && !empty($sysResp)) {
            $formatted .= "Q: " . substr($userMsg, 0, 100) . "\n";
            $formatted .= "A: " . substr($sysResp, 0, 100) . "\n";
            $formatted .= "---\n";
          }
        }
      }
      $formatted .= "\n";
    }

    return trim($formatted);
  }


//***********************
// not used
//***********************

  /**
   * Analyzes the actual content of a table to understand what it contains
   *
   * @param string $tableName Name of the table to analyze
   * @return array Statistics and content samples
   */
  private function analyzeTableContent(string $tableName): array
  {
    // Use cache if available
    if (isset(self::$tableStatsCache[$tableName])) {
      return self::$tableStatsCache[$tableName];
    }

    try {
      // Count documents
      $count = DoctrineOrm::selectOne("SELECT COUNT(*) as total FROM {$tableName}");

      if ($count['total'] == 0) {
        self::$tableStatsCache[$tableName] = [
          'total_documents' => 0,
          'has_content' => false,
          'types' => [],
          'sample_content' => ''
        ];
        return self::$tableStatsCache[$tableName];
      }

      // Get present document types
      $typesRows = DoctrineOrm::select("
        SELECT DISTINCT type, COUNT(*) as count 
        FROM {$tableName} 
        GROUP BY type
      ");

      $types = [];
      foreach ($typesRows as $row) {
        $types[$row['type']] = $row['count'];
      }

      // Get content sample for semantic analysis
      $sample = DoctrineOrm::selectOne("
        SELECT GROUP_CONCAT(LEFT(content, 200) SEPARATOR ' ') as sample
        FROM (
          SELECT content FROM {$tableName} 
          ORDER BY RAND() 
          LIMIT 5
        ) as samples
      ");

      $stats = [
        'total_documents' => $count['total'],
        'has_content' => true,
        'types' => $types,
        'sample_content' => $sample['sample'] ?? ''
      ];

      self::$tableStatsCache[$tableName] = $stats;

      if ($this->debug) {
        error_log("[stats] Table {$tableName} stats: " . json_encode($stats));
      }

      return $stats;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("️ Error analyzing table {$tableName}: " . $e->getMessage());
      }

      return [
        'total_documents' => 0,
        'has_content' => false,
        'types' => [],
        'sample_content' => ''
      ];
    }
  }

  /**
   * Builds the prompt with context retrieved by the MemoryService.
   *
   * @param string $prompt The original user query.
   * @param array $context The structured context (array) returned by MemoryRetentionService.
   * @return string The final prompt formatted for the LLM.
   */
  private function buildPromptWithContext(string $userQuery, array $context): string
  {
    if (empty(array_filter($context, fn($v) => !empty($v)))) {
      return $userQuery;
    }

    $contextString = $this->formatContextArrayToLlmString($context);

    if (empty(trim($contextString))) {
      return $userQuery;
    }

    return "RAG Context (Previous Interactions):\n---\n{$contextString}\n---\n\nCurrent Request: {$userQuery}";
  }


  /**
   * DIAGNOSTIC METHOD - Checks the state of all embedding tables and their content
   */
  public function performDiagnostics(): array
  {
    error_log("=== DIAGNOSTIC START ===");
    $diagnostics = [
      'timestamp' => date('Y-m-d H:i:s'),
      'vector_stores' => [],
      'tables_status' => [],
      'content_samples' => []
    ];

    // 1. Check each initialized VectorStore
    error_log("Step 1: Checking initialized VectorStores");
    foreach ($this->vectorStores as $tableName => $vectorStore) {
      error_log("  - VectorStore found: {$tableName}");
      $diagnostics['vector_stores'][] = $tableName;
    }

    if (empty($this->vectorStores)) {
      error_log("  WARNING: No VectorStores initialized!");
      $diagnostics['vector_stores'] = ['ERROR' => 'No VectorStores'];
    }

    // 2. Check embedding tables in the database
    error_log("Step 2: Checking embedding tables in database");

    foreach ($this->knownEmbeddingTable() as $tableName) {
      try {
        $result = DoctrineOrm::selectOne("SELECT COUNT(*) as cnt FROM {$tableName}");
        $count = $result['cnt'] ?? 0;

        error_log("  - Table {$tableName}: {$count} documents");
        $diagnostics['tables_status'][$tableName] = [
          'exists' => true,
          'document_count' => $count
        ];

        // If the table has content, take a sample
        if ($count > 0) {
          $sample = DoctrineOrm::selectOne(
            "SELECT content FROM {$tableName} LIMIT 1"
          );

          if ($sample) {
            $preview = substr($sample['content'], 0, 200);
            error_log("    Sample preview: {$preview}...");
            $diagnostics['content_samples'][$tableName] = $preview;
          }
        }

      } catch (\Exception $e) {
        error_log("  - Table {$tableName}: NOT FOUND - {$e->getMessage()}");
        $diagnostics['tables_status'][$tableName] = [
          'exists' => false,
          'error' => $e->getMessage()
        ];
      }
    }

    // 3. Attempt a test search
    error_log("Step 3: Testing search with simple query");
    try {
      // Very permissive search
      $testResults = $this->searchDocuments(
        "Strauss produit prix",
        limit: 10,
        minScore: 0.01  // Very low to see all results
      );

      $docCount = count($testResults['documents'] ?? []);
      error_log("  - Search returned: {$docCount} documents");
      $diagnostics['search_test'] = [
        'query' => 'Strauss produit prix',
        'results_count' => $docCount,
        'audit_metadata' => $testResults['audit_metadata'] ?? []
      ];

    } catch (\Exception $e) {
      error_log("  - Search failed: {$e->getMessage()}");
      $diagnostics['search_test'] = [
        'error' => $e->getMessage()
      ];
    }

    error_log("=== DIAGNOSTIC END ===");
    return $diagnostics;
  }
}
