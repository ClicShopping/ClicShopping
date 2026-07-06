<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Schema;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Config\DomainFields;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Cache as OMCache;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Infrastructure\Prompt\PromptOptimizer;

/**
 * SchemaRetriever
 * 
 * Retrieves relevant database schema portions based on user query
 * Uses embedding-based similarity search with fallback mechanisms
 * 
 * Pure LLM Mode - pattern-based fallbacks removed
 * 
 * Responsibilities:
 * - Find relevant tables based on query similarity
 * - Build minimal schema string with only relevant tables
 * - Handle fallbacks (keyword matching, common tables)
 * - Respect model-specific context limits
 * - Cache query→tables mappings
 * 
 * @package ClicShopping\AI\Infrastructure\Schema
 */
class SchemaRetriever
{
  private mixed $db;
  private SchemaEmbedder $schemaEmbedder;
  private ColumnIndex $columnIndex;
  private PromptOptimizer $promptOptimizer;
  private bool $debug;
  private string $useCache;
  private bool $useEmbeddings;
  
  /**
   * Constructor
   * 
   * @param bool $debug Debug mode flag for logging
   * @param bool $useEmbeddings Whether to use embeddings (default: false for Pure LLM mode)
   */
  public function __construct(bool $debug = false, bool $useEmbeddings = false)
  {
    $this->db = Registry::get('Db');
    $this->schemaEmbedder = new SchemaEmbedder($debug);
    $this->columnIndex = new ColumnIndex($debug);
    $this->promptOptimizer = new PromptOptimizer();
    $this->debug = $debug;
    $this->useCache = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True' ? 'True' : 'False';
    $this->useEmbeddings = $useEmbeddings;
    
    if ($this->debug) {
      error_log("[SchemaRetriever] Initialized with embeddings: " . ($useEmbeddings ? 'ENABLED' : 'DISABLED (Pure LLM mode)'));
    }
    
    // Build column index on first use
    $this->columnIndex->build();
  }
  
  /**
   * Get relevant schema for a query
   * 
   * Main entry point - returns minimal schema based on query and model
   * Respects model-specific context limits
   * 
   * @param string $query User query
   * @param string $modelName Model name (e.g., "qwen/qwen3-4b")
   * @param int $maxTables Maximum number of tables to include
   * @return string Minimal schema text with only relevant tables
   */
  public function getRelevantSchema(string $query, string $modelName, int $maxTables = 5): string
  {
    $startTime = microtime(true);
    
    if ($this->debug) {
      error_log("[SchemaRetriever] Getting relevant schema for query: {$query}");
      error_log("[SchemaRetriever] Model: {$modelName}, Max tables: {$maxTables}");
    }
    
    // Get context limit for this model
    $contextLimit = $this->promptOptimizer->getModelLimit($modelName);
    
    // Get relevant tables
    $tables = $this->getRelevantTables($query, $maxTables);
    
    if (empty($tables)) {
      if ($this->debug) {
        error_log("[SchemaRetriever] No tables found, using common tables");
      }
      $tables = DoctrineOrm::getFallbackRelevantTables();
    }
    
    // Build schema from tables
    $schema = $this->buildSchemaFromTables($tables);
    
    // If schema exceeds limit, reduce table count
    $tokenCount = $this->promptOptimizer->estimateTokenCount($schema);
    while ($tokenCount > $contextLimit && count($tables) > 1) {
      array_pop($tables);
      $schema = $this->buildSchemaFromTables($tables);
      $tokenCount = $this->promptOptimizer->estimateTokenCount($schema);
      
      if ($this->debug) {
        error_log("[SchemaRetriever] Schema too large ({$tokenCount} tokens), reduced to " . count($tables) . " tables");
      }
    }
    
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    if ($this->debug) {
      error_log("[SchemaRetriever] Schema built with " . count($tables) . " tables");
      error_log("[SchemaRetriever] Estimated tokens: {$tokenCount} / {$contextLimit}");
      error_log("[SchemaRetriever] Duration: {$duration}ms");
      error_log("[SchemaRetriever] Tables: " . implode(', ', $tables));
    }
    
    return $schema;
  }
  
  /**
   * Get relevant tables based on query similarity
   * 
   * Uses embedding-based similarity search with fallback mechanisms
   * In Pure LLM mode (useEmbeddings=false), skips embeddings and uses only column matching
   * 
   * 
   * @param string $query User query
   * @param int $maxTables Maximum number of tables to return
   * @return array Array of table names
   */
  public function getRelevantTables(string $query, int $maxTables = 5): array
  {
    // Check cache first
    if ($this->useCache === 'True') {
      // This creates cache files in Work/Cache/Rag/SchemaQuery/*.cache
      $cacheKey = md5($query . '_' . $maxTables . '_' . ($this->useEmbeddings ? 'emb' : 'pure'));
      $cache = new OMCache($cacheKey, 'Rag/SchemaQuery');  // Namespace creates subdirectory structure
      $cached = $cache->get();
      
      if (!empty($cached)) {
        if ($this->debug) {
          error_log("[SchemaRetriever] Using cached table list from Rag/SchemaQuery/");
        }
        return $cached;
      }
    }
    
    // Pure LLM mode: skip embeddings, use only column matching
    if (!$this->useEmbeddings) {
      if ($this->debug) {
        error_log("[SchemaRetriever] Pure LLM mode: using column matching only (embeddings disabled)");
      }
      
      $tables = $this->fallbackKeywordMatching($query);
      
      if (!empty($tables)) {
        $tables = array_slice($tables, 0, $maxTables);
        
        // Cache the result
        if ($this->useCache === 'True') {
          $cache->save($tables);
        }
        
        return $tables;
      }
      
      // If keyword matching fails, use common tables
      if ($this->debug) {
        error_log("[SchemaRetriever] Keyword matching returned no results, using common tables");
      }
      
      return DoctrineOrm::getFallbackRelevantTables();
    }
    
    // Embedding mode: try embeddings first, then fallback
    try {
      // Try embedding-based retrieval (primary method)
      $tables = $this->getTablesBySimilarity($query, $maxTables);
      
      if (!empty($tables)) {
        // Cache the result
        if ($this->useCache === 'True') {
          $cache->save($tables); // Cache without TTL metadata
        }
        
        return $tables;
      }
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[SchemaRetriever] Embedding-based retrieval failed: " . $e->getMessage());
      }
    }
    
    // Fallback to keyword matching
    if ($this->debug) {
      error_log("[SchemaRetriever] Falling back to keyword matching");
    }
    
    $tables = $this->fallbackKeywordMatching($query);
    
    if (!empty($tables)) {
      return array_slice($tables, 0, $maxTables);
    }
    
    // Final fallback to common tables
    if ($this->debug) {
      error_log("[SchemaRetriever] Falling back to common tables");
    }
    
    return DoctrineOrm::getFallbackRelevantTables();
  }
  
  /**
   * Get tables by embedding similarity with dynamic column matching
   * 
   * @param string $query User query
   * @param int $maxTables Maximum number of tables
   * @return array Array of table names
   */
  private function getTablesBySimilarity(string $query, int $maxTables): array
  {
    // Create query embedding using NewVector directly
    $embeddedDocs = NewVector::createEmbedding(null, $query);
    
    if (empty($embeddedDocs) || !isset($embeddedDocs[0]->embedding)) {
      throw new \Exception("Failed to create query embedding");
    }
    
    $queryEmbedding = $embeddedDocs[0]->embedding;
    
    if ($this->debug) {
      error_log("[SchemaRetriever] Query embedding created: " . count($queryEmbedding) . " dimensions");
    }
    
    // Load all table embeddings
    $tableEmbeddings = $this->schemaEmbedder->getAllTableEmbeddings();
    
    if (empty($tableEmbeddings)) {
      throw new \Exception("No table embeddings found in database");
    }
    
    if ($this->debug) {
      error_log("[SchemaRetriever] Loaded " . count($tableEmbeddings) . " table embeddings");
    }
    
    // Calculate embedding similarity scores
    $embeddingScores = [];
    foreach ($tableEmbeddings as $tableName => $embedding) {
      $embeddingScores[$tableName] = $this->cosineSimilarity($queryEmbedding, $embedding);
    }
    
    // Apply dynamic column matching
    $finalScores = $this->applyDynamicScoring($query, $embeddingScores);
    
    // Sort by final score (descending)
    arsort($finalScores);
    
    if ($this->debug) {
      error_log("[SchemaRetriever] Top 5 final scores:");
      $count = 0;
      foreach ($finalScores as $table => $score) {
        if ($count++ >= 5) break;
        error_log("  - {$table}: " . round($score, 4));
      }
    }
    
    // Return top N tables
    return array_slice(array_keys($finalScores), 0, $maxTables);
  }
  
  /**
   * Apply dynamic scoring based on column matching
   * 
   * @param string $query User query
   * @param array $embeddingScores Embedding similarity scores
   * @return array Final scores
   */
  private function applyDynamicScoring(string $query, array $embeddingScores): array
  {
    // Pure LLM Mode - pattern-based fallbacks removed
    // Extract query keywords using simple word extraction
    $queryKeywords = $this->extractSimpleKeywords($query);
    
    // Find column matches
    $columnMatchScores = [];
    foreach ($queryKeywords as $keyword) {
      $matches = $this->columnIndex->find($keyword);
      foreach ($matches as $match) {
        $table = $match['table'];
        $columnMatchScores[$table] = ($columnMatchScores[$table] ?? 0) + 1;
      }
    }
    
    // Normalize column scores
    $maxColumnScore = !empty($columnMatchScores) ? max($columnMatchScores) : 1;
    foreach ($columnMatchScores as $table => $score) {
      $columnMatchScores[$table] = $score / $maxColumnScore;
    }
    
    // Combine scores
    $finalScores = [];
    foreach ($embeddingScores as $table => $embScore) {
      $colScore = $columnMatchScores[$table] ?? 0;
      
      // Composite score: 30% embedding + 70% column matching
      $finalScores[$table] = ($embScore * 0.3) + ($colScore * 0.7);
      
      // Penalty for reference tables
      if ($this->isReferenceTable($table)) {
        $finalScores[$table] *= 0.5;
      }
      
      // Penalty for junction tables
      if ($this->isJunctionTable($table)) {
        $finalScores[$table] *= 0.6;
      }
    }
    
    return $finalScores;
  }
  
  /**
   * Extract simple keywords from query (Pure LLM mode)
   * 
   * @param string $query User query
   * @return array Array of keywords
   */
  private function extractSimpleKeywords(string $query): array
  {
    $query = strtolower($query);
    
    // Remove special chars and split
    $query = preg_replace('/[^a-z0-9\s]/', ' ', $query);
    $words = preg_split('/\s+/', $query);
    
    // Filter words longer than 3 characters
    $keywords = [];
    foreach ($words as $word) {
      if (strlen($word) > 3) {
        $keywords[] = $word;
      }
    }
    
    return array_unique($keywords);
  }
  
  /**
   * Check if table is a reference table
   * 
   * @param string $tableName Table name
   * @return bool True if reference table
   */
  private function isReferenceTable(string $tableName): bool
  {
    return preg_match('/_classes$|_rules$|_format$|_status$/', $tableName) === 1;
  }
  
  /**
   * Check if table is a junction table
   * 
   * @param string $tableName Table name
   * @return bool True if junction table
   */
  private function isJunctionTable(string $tableName): bool
  {
    return preg_match('/_to_/', $tableName) === 1;
  }
  
  /**
   * Calculate cosine similarity between two vectors
   * 
   * @param array $vec1 First vector
   * @param array $vec2 Second vector
   * @return float Similarity score (0-1)
   */
  private function cosineSimilarity(array $vec1, array $vec2): float
  {
    if (count($vec1) !== count($vec2)) {
      return 0.0;
    }
    
    $dotProduct = 0.0;
    $magnitude1 = 0.0;
    $magnitude2 = 0.0;
    
    for ($i = 0, $iMax = count($vec1); $i < $iMax; $i++) {
      $dotProduct += $vec1[$i] * $vec2[$i];
      $magnitude1 += $vec1[$i] * $vec1[$i];
      $magnitude2 += $vec2[$i] * $vec2[$i];
    }
    
    $magnitude1 = sqrt($magnitude1);
    $magnitude2 = sqrt($magnitude2);
    
    if ($magnitude1 == 0 || $magnitude2 == 0) {
      return 0.0;
    }
    
    return $dotProduct / ($magnitude1 * $magnitude2);
  }
  

  /**
   * Rank candidate tables for a query by keyword SPECIFICITY, most relevant first.
   *
   * Pure LLM Mode (no schema embeddings, NO stopword/synonym lists): the only signal is ColumnIndex
   * keyword hits, scored purely by statistics + structure. The previous version concatenated hits
   * in query-word order and let getRelevantTables() truncate at maxTables — so a generic word
   * ("customer" → 100+ tables) flooded the head of the list and evicted the discriminating business
   * table (products_favorites / products_recommendations). Three data-driven signals fix this
   * without hardcoding any word list:
   *  - IDF: a keyword matching many tables is generic and contributes little; a rare one, more.
   *  - Concept coverage: a keyword scores once per table (not per column), so a table covering two
   *    distinct query concepts beats one that repeats a single concept across many columns.
   *  - Table-name match: a keyword found in the table NAME (favorites -> products_favorites) is far
   *    more discriminating than a column-only hit, so it earns a strong boost.
   * Reference/junction tables keep the same structural penalties as the embedding path. Result: the
   * specific table ranks into the top-N before truncation, and generic filler words (list, current,
   * …) can no longer out-rank it — no curated stopword list required.
   *
   * @param string $query User query
   * @return array Table names ordered by descending relevance
   */
  private function fallbackKeywordMatching(string $query): array
  {
    $keywords = $this->extractSimpleKeywords($query);

    $scores = [];
    foreach ($keywords as $keyword) {
      $matches = $this->columnIndex->find($keyword);
      if (empty($matches)) {
        continue;
      }
      
      // Score by CONCEPT COVERAGE, not column count: a keyword contributes once per table, so a
      // table matching two distinct query concepts (e.g. "recommendations" + "customer") beats a
      // table that merely repeats one concept across many columns (e.g. customers_* everywhere).
      $distinctTables = array_unique(array_map(static fn($m) => $m['table'], $matches));

      $idf = 1.0 / log(count($distinctTables) + M_E);
      foreach ($distinctTables as $table) {
        $scores[$table] = ($scores[$table] ?? 0.0) + $idf;

        if (str_contains($table, $keyword)) {
          $scores[$table] += 2.0;
        }
      }
    }

    // Same structural penalties as the embedding path (applyDynamicScoring).
    foreach ($scores as $table => $score) {
      if ($this->isReferenceTable($table)) {
        $scores[$table] *= 0.5;
      }
      if ($this->isJunctionTable($table)) {
        $scores[$table] *= 0.6;
      }
    }

    arsort($scores);
    $ranked = array_keys($scores);

    if ($this->debug && !empty($ranked)) {
      error_log("[SchemaRetriever] Ranked column-index matches: " . implode(', ', array_slice($ranked, 0, 8)));
    }

    return $ranked;
  }
  
  /**
   * Build schema text from table names
   * 
   * Reads schema directly from database using SHOW FULL COLUMNS
   * to include column comments. This approach is database-agnostic
   * and doesn't require embedding tables.
   *
   * 
   * @param array $tableNames Array of table names
   * @return string Schema text
   */
  private function buildSchemaFromTables(array $tableNames): string
  {
    $schemaParts = [];
    
    $schemaRules = $this->loadSchemaRules();
    if (!empty($schemaRules)) {
      $schemaParts[] = $schemaRules;
    }
    
    $sectionNumber = 1;
    
    foreach ($tableNames as $tableName) {
      try {
        // Build schema directly from database with column comments
        $schemaText = $this->buildSchemaWithComments($tableName);
        
        if (!empty($schemaText)) {
          $schemaParts[] = "{$sectionNumber}. {$schemaText}";
          $sectionNumber++;
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("[SchemaRetriever] Error building schema for {$tableName}: " . $e->getMessage());
        }
        // Continue with next table
      }
    }
    
    $schemaParts[] = "\nImportant: When generating SQL queries, strictly follow these table structures and do not invent columns.\n";
    
    return implode("\n", $schemaParts);
  }
  
  /**
   * Load schema rules from domain configuration
   * 
   * Uses generic rules if no domain is configured
   * 
   * @return string Schema rules text
   */
  private function loadSchemaRules(): string
  {
    $activeDomain = DomainConfig::getActivities();
    $schemaConfig = DomainFields::resolveAppClass($activeDomain, 'SchemaConfig');
    if ($schemaConfig !== null) {
      try {
        if (is_subclass_of($schemaConfig, \ClicShopping\AI\InterfacesAI\SchemaConfigInterface::class)
          && method_exists($schemaConfig, 'getSchemaRulesString')) {
          return $schemaConfig::getSchemaRulesString();
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("[SchemaRetriever] Failed to load schema rules: " . $e->getMessage());
        }
      }
    }
    
    // Use generic rules if no domain or domain not found
    return "IMPORTANT: Please follow the database schema structure carefully when generating SQL queries.\n";
  }
  
  /**
   * Build schema text with column comments from database
   * 
   * Uses SHOW FULL COLUMNS to get column information including comments.
   * This is database-agnostic and works on MariaDB, MySQL, and compatible databases.
   * 
   * @param string $tableName Table name
   * @return string Schema text with column descriptions
   */
  private function buildSchemaWithComments(string $tableName): string
  {
    $schemaText = "Table {$tableName}:\n";
    
    // Use SHOW FULL COLUMNS to get column info with comments
    $Qcolumns = $this->db->query("SHOW FULL COLUMNS FROM {$tableName}");
    
    $columns = [];
    while ($Qcolumns->fetch()) {
      $field = $Qcolumns->value('Field');
      $type = $Qcolumns->value('Type');
      $comment = $Qcolumns->value('Comment');
      
      if (!empty($comment)) {
        // Include comment for better LLM understanding
        $columns[] = "  - {$field} ({$type}): {$comment}";
      } else {
        // No comment, just show field and type
        $columns[] = "  - {$field} ({$type})";
      }
    }
    
    if (!empty($columns)) {
      $schemaText .= implode("\n", $columns);
    }
    
    return $schemaText;
  }
}
