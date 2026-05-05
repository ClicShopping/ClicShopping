<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\Infrastructure\Schema;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * SchemaEmbedder
 * 
 * Parses database schema into individual table definitions and generates embeddings
 * for efficient retrieval in Schema RAG optimization
 * 
 * Responsibilities:
 * - Parse full schema text into individual table schemas
 * - Generate embeddings for each table using EmbeddingService
 * - Store embeddings in database with caching
 * - Provide invalidation mechanism when schema changes
 * 
 * @package ClicShopping\AI\Infrastructure\Schema
 */
class SchemaEmbedder
{
  private mixed $db;
  private mixed $embeddingService;
  private bool $debug;
  
  /**
   * Constructor
   * 
   * @param bool $debug Debug mode flag for logging
   */
  public function __construct(bool $debug = false)
  {
    $this->db = Registry::get('Db');
    
    // Try to get embedding service, but don't fail if it's not available
    try {
      $this->embeddingService = Registry::exists('EmbeddingService') ? Registry::get('EmbeddingService') : null;
    } catch (\Exception $e) {
      $this->embeddingService = null;
      if ($debug) {
        error_log("[SchemaEmbedder] EmbeddingService not available: " . $e->getMessage());
      }
    }
    
    $this->debug = $debug;
  }
  
  /**
   * Embed all tables from the full schema text
   * 
   * Parses the schema, generates embeddings, and stores them in database
   * This is typically run once during installation or when schema changes
   * 
   * @param string $fullSchemaText Full schema text from language file
   * @return array Statistics about embedding generation
   */
  public function embedAllTables(string $fullSchemaText): array
  {
    $startTime = microtime(true);
    
    if ($this->debug) {
      error_log("[SchemaEmbedder] Starting embedding generation for all tables");
      error_log("[SchemaEmbedder] Full schema length: " . strlen($fullSchemaText) . " chars");
    }
    
    // Parse schema into individual tables
    $tables = $this->parseSchemaIntoTables($fullSchemaText);
    
    if ($this->debug) {
      error_log("[SchemaEmbedder] Parsed " . count($tables) . " tables");
    }
    
    $stats = [
      'total_tables' => count($tables),
      'embedded' => 0,
      'failed' => 0,
      'duration_seconds' => 0
    ];
    
    // Generate and store embeddings for each table
    foreach ($tables as $tableName => $tableData) {
      try {
        $this->embedTable($tableName, $tableData);
        $stats['embedded']++;
        
        if ($this->debug) {
          error_log("[SchemaEmbedder] ✓ Embedded table: {$tableName}");
        }
      } catch (\Exception $e) {
        $stats['failed']++;
        error_log("[SchemaEmbedder] ✗ Failed to embed table {$tableName}: " . $e->getMessage());
      }
    }
    
    $stats['duration_seconds'] = round(microtime(true) - $startTime, 2);
    
    if ($this->debug) {
      error_log("[SchemaEmbedder] Embedding complete: {$stats['embedded']} succeeded, {$stats['failed']} failed");
      error_log("[SchemaEmbedder] Duration: {$stats['duration_seconds']} seconds");
    }
    
    return $stats;
  }
  
  /**
   * Get embedding for a specific table
   * 
   * @param string $tableName Table name (e.g., "clic_products")
   * @return array|null Embedding vector or null if not found
   */
  public function getTableEmbedding(string $tableName): ?array
  {
    $Qembedding = $this->db->prepare('
      SELECT embedding_vector
      FROM :table_rag_schema_embedding
      WHERE table_name = :table_name
    ');
    
    $Qembedding->bindValue(':table_name', $tableName);
    $Qembedding->execute();
    
    if ($Qembedding->fetch()) {
      $embeddingJson = $Qembedding->value('embedding_vector');
      return json_decode($embeddingJson, true);
    }
    
    return null;
  }
  
  /**
   * Get all table embeddings
   * 
   * @return array Associative array of table_name => embedding_vector
   */
  public function getAllTableEmbeddings(): array
  {
    $Qembeddings = $this->db->query('
      SELECT table_name, VEC_ToText(embedding_vector) as embedding_text
      FROM :table_rag_schema_embedding
      ORDER BY table_name
    ');
    
    $embeddings = [];
    
    while ($Qembeddings->fetch()) {
      $tableName = $Qembeddings->value('table_name');
      
      // Skip technical tables (should not be in embeddings, but filter just in case)
      if (strpos($tableName, '_embedding') !== false || strpos($tableName, 'clic_rag_') === 0) {
        continue;
      }
      
      $embeddingText = $Qembeddings->value('embedding_text');
      
      // Parse VECTOR text format: [val1,val2,...]
      $embeddingText = trim($embeddingText, '[]');
      $embeddings[$tableName] = array_map('floatval', explode(',', $embeddingText));
    }
    
    return $embeddings;
  }
  
  /**
   * Invalidate cache and regenerate embeddings
   * 
   * Call this when database schema changes
   * 
   * @param string $fullSchemaText Full schema text from language file
   * @return array Statistics about regeneration
   */
  public function invalidateAndRegenerate(string $fullSchemaText): array
  {
    if ($this->debug) {
      error_log("[SchemaEmbedder] Invalidating cache and regenerating embeddings");
    }
    
    // Delete all existing embeddings
    $this->db->query('DELETE FROM :table_rag_schema_embedding');
    
    // Regenerate
    return $this->embedAllTables($fullSchemaText);
  }
  
  /**
   * Parse full schema text into individual table definitions
   * 
   * Extracts numbered sections like:
   * 1. Table clic_orders:
   *    - Contains orders...
   * 2. Table clic_customers:
   *    - Contains customer...
   * 
   * @param string $fullSchema Full schema text
   * @return array Associative array of table_name => table_data
   */
  private function parseSchemaIntoTables(string $fullSchema): array
  {
    $tables = [];
    
    // Pattern to match numbered table sections
    // Example: "1. Table clic_orders:\n   - Contains orders..."
    $pattern = '/(\d+)\.\s+Table\s+(clic_\w+):\s*\\n(.*?)(?=\d+\.\s+Table|\z)/s';
    
    preg_match_all($pattern, $fullSchema, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
      $sectionNumber = $match[1];
      $tableName = $match[2];
      $description = trim($match[3]);
      
      $tables[$tableName] = [
        'name' => $tableName,
        'description' => $description,
        'section_number' => $sectionNumber
      ];
      
      if ($this->debug) {
        error_log("[SchemaEmbedder] Parsed table: {$tableName} (section {$sectionNumber})");
      }
    }
    
    return $tables;
  }
  
  /**
   * Generate embedding for a single table and store in database
   * 
   * @param string $tableName Table name
   * @param array $tableData Table data from parsing
   * @return void
   * @throws \Exception If embedding generation fails
   */
  private function embedTable(string $tableName, array $tableData): void
  {
    // Build schema text for embedding
    $schemaText = "Table: {$tableName}\n{$tableData['description']}";
    
    // Generate embedding using existing EmbeddingService
    $embedding = $this->embeddingService->createEmbedding($schemaText);
    
    if (empty($embedding)) {
      throw new \Exception("Embedding generation returned empty result");
    }
    
    // Estimate token count (rough estimate: 4 chars per token)
    $tokenCount = (int)ceil(strlen($schemaText) / 4);
    
    // Store in database
    $this->storeEmbedding($tableName, $schemaText, $embedding, $tokenCount);
  }
  
  /**
   * Store embedding in database
   * 
   * @param string $tableName Table name
   * @param string $schemaText Schema text
   * @param array $embedding Embedding vector
   * @param int $tokenCount Estimated token count
   * @return void
   */
  private function storeEmbedding(string $tableName, string $schemaText, array $embedding, int $tokenCount): void
  {
    $embeddingJson = json_encode($embedding);
    $now = date('Y-m-d H:i:s');
    
    // Check if embedding already exists
    $Qcheck = $this->db->prepare('
      SELECT id
      FROM :table_rag_schema_embedding
      WHERE table_name = :table_name
    ');
    
    $Qcheck->bindValue(':table_name', $tableName);
    $Qcheck->execute();
    
    if ($Qcheck->fetch()) {
      // Update existing
      $Qupdate = $this->db->prepare('
        UPDATE :table_rag_schema_embedding
        SET schema_text = :schema_text,
            embedding_vector = :embedding_vector,
            token_count = :token_count,
            updated_at = :updated_at
        WHERE table_name = :table_name
      ');
      
      $Qupdate->bindValue(':schema_text', $schemaText);
      $Qupdate->bindValue(':embedding_vector', $embeddingJson);
      $Qupdate->bindInt(':token_count', $tokenCount);
      $Qupdate->bindValue(':updated_at', $now);
      $Qupdate->bindValue(':table_name', $tableName);
      $Qupdate->execute();
    } else {
      // Insert new
      $Qinsert = $this->db->prepare('
        INSERT INTO :table_rag_schema_embedding
        (table_name, schema_text, embedding_vector, token_count, created_at)
        VALUES
        (:table_name, :schema_text, :embedding_vector, :token_count, :created_at)
      ');
      
      $Qinsert->bindValue(':table_name', $tableName);
      $Qinsert->bindValue(':schema_text', $schemaText);
      $Qinsert->bindValue(':embedding_vector', $embeddingJson);
      $Qinsert->bindInt(':token_count', $tokenCount);
      $Qinsert->bindValue(':created_at', $now);
      $Qinsert->execute();
    }
  }
}
