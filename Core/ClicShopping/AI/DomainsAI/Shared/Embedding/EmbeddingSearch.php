<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Embedding;


use ClicShopping\OM\Cache;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

/**
 * EmbeddingSearch Class
 *
 * 🔧 PRIORITY 3 - PHASE 3.2: Optimized embedding search with caching and limits
 *
 * Responsible for performing efficient vector similarity searches with:
 * - Top-k limiting to reduce result set size
 * - Two-level caching (embedding cache + search result cache)
 * - Graceful error handling
 * - Performance monitoring
 *
 * Performance Targets:
 * - Embedding search: <50ms (REQ-2.1)
 * - Cache hits: <10ms (REQ-2.4)
 * - Result limiting: top-k matches only (REQ-2.3)
 *
 * Caching Strategy:
 * - Query embeddings: 1 hour TTL (embeddings don't change)
 * - Search results: 10 minutes TTL (balance freshness vs performance)
 * - Cache key: MD5(query + limit + filters)
 *
 * @package ClicShopping\AI\Domain\Embedding
 */

class EmbeddingSearch
{
  private SecurityLogger $logger;
  private MariaDBVectorStore $vectorStore;
  private EmbeddingGeneratorInterface $embeddingGenerator;
  private bool $debug;
  
  // Cache configuration
  private bool $cacheEnabled = true;
  private int $embeddingCacheTTL = 60; // 1 hour in minutes
  private int $searchCacheTTL = 10; // 10 minutes
  
  // Performance configuration
  private int $defaultLimit = 5;
  private float $defaultMinScore = 0.7;
  
  /**
   * Constructor
   *
   * @param MariaDBVectorStore $vectorStore Vector store for similarity search
   * @param EmbeddingGeneratorInterface $embeddingGenerator For generating query embeddings
   * @param bool $debug Enable debug logging
   */
  public function __construct(
    MariaDBVectorStore $vectorStore,
    EmbeddingGeneratorInterface $embeddingGenerator,
    bool $debug = false
  ) {
    $this->vectorStore = $vectorStore;
    $this->embeddingGenerator = $embeddingGenerator;
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
    
    if ($this->debug) {
      $this->logger->logSecurityEvent("EmbeddingSearch initialized", 'info');
    }
  }
  
  /**
   * Search for similar documents using vector similarity
   *
   * 🔧 PRIORITY 3 - PHASE 3.2: Main entry point for optimized embedding search
   *
   * This method implements the complete optimization strategy:
   * 1. Check search result cache (10 min TTL)
   * 2. Generate/retrieve embedding (with 1 hour cache)
   * 3. Perform vector search with top-k limit
   * 4. Cache results for future queries
   *
   * Performance optimizations:
   * - Cache hit: <10ms (REQ-2.4)
   * - Cache miss: <50ms (REQ-2.1)
   * - Top-k limiting reduces result processing (REQ-2.3)
   *
   * @param string $query Search query text
   * @param int $limit Maximum number of results (default: 5)
   * @param float $minScore Minimum similarity score (0-1, default: 0.7)
   * @param callable|null $filter Optional filter function
   * @return array Array of matching documents with similarity scores
   */
  public function searchSimilar(
    string $query,
    int $limit = 5,
    float $minScore = 0.7,
    ?callable $filter = null
  ): array {
    $startTime = microtime(true);
    
    // Use default limit if not specified
    if ($limit <= 0) {
      $limit = $this->defaultLimit;
    }
    
    // Use default min score if not specified
    if ($minScore <= 0) {
      $minScore = $this->defaultMinScore;
    }
    
    if ($this->debug) {
      error_log("\n--- EMBEDDING SEARCH START ---");
      error_log("Query: '{$query}'");
      error_log("Limit: {$limit}");
      error_log("Min Score: {$minScore}");
    }
    
    try {
      // 1. Check search result cache first (10 min TTL)
      if ($this->cacheEnabled) {
        $searchCacheKey = $this->generateSearchCacheKey($query, $limit, $minScore, $filter);
        $searchCache = new Cache($searchCacheKey, 'Rag/EmbeddingSearch');
        
        if ($searchCache->exists($this->searchCacheTTL)) {
          $cached = $searchCache->get();
          
          if ($cached !== null && is_array($cached)) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($this->debug) {
              error_log("✅ SEARCH CACHE HIT - Returning cached results");
              error_log("Results: " . count($cached));
              error_log("Duration: " . round($duration, 2) . " ms");
              error_log("--- EMBEDDING SEARCH END ---\n");
            }
            
            $this->logger->logStructured(
              'info',
              'Rag/EmbeddingSearch',
              'search_cache_hit',
              [
                'query' => substr($query, 0, 100),
                'limit' => $limit,
                'results' => count($cached),
                'duration_ms' => round($duration, 2)
              ]
            );
            
            return $cached;
          }
        }
        
        if ($this->debug) {
          error_log("[error] SEARCH CACHE MISS - Performing vector search");
        }
      }
      
      // 2. Generate or retrieve embedding (with 1 hour cache)
      $embedding = $this->getEmbedding($query);
      
      if (empty($embedding)) {
        $this->logger->logSecurityEvent(
          "EmbeddingSearch: Failed to generate embedding for query",
          'error'
        );
        return [];
      }
      
      // 3. Perform vector search with top-k limit
      $searchStartTime = microtime(true);
      $results = $this->vectorStore->similaritySearch(
        $embedding,
        $limit,
        $minScore,
        $filter
      );
      $searchDuration = (microtime(true) - $searchStartTime) * 1000;
      
      // Convert iterable to array if needed
      $resultsArray = is_array($results) ? $results : iterator_to_array($results);
      
      // 4. Limit results to top-k (safety check)
      $resultsArray = array_slice($resultsArray, 0, $limit);
      
      $totalDuration = (microtime(true) - $startTime) * 1000;
      
      if ($this->debug) {
        error_log("[INFO] Vector search completed");
        error_log("Results: " . count($resultsArray));
        error_log("Search duration: " . round($searchDuration, 2) . " ms");
        error_log("Total duration: " . round($totalDuration, 2) . " ms");
      }
      
      $this->logger->logStructured(
        'info',
        'Rag/EmbeddingSearch',
        'search_completed',
        [
          'query' => substr($query, 0, 100),
          'limit' => $limit,
          'min_score' => $minScore,
          'results' => count($resultsArray),
          'search_duration_ms' => round($searchDuration, 2),
          'total_duration_ms' => round($totalDuration, 2)
        ]
      );
      
      // 5. Cache results for future queries (10 min TTL)
      if ($this->cacheEnabled && !empty($resultsArray)) {
        try {
          $searchCacheKey = $this->generateSearchCacheKey($query, $limit, $minScore, $filter);
          $searchCache = new Cache($searchCacheKey, 'Rag/EmbeddingSearch');
          $searchCache->save($resultsArray);
          
          if ($this->debug) {
            error_log("✅ Search results cached for {$this->searchCacheTTL} minutes");
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "EmbeddingSearch: Failed to cache search results: " . $e->getMessage(),
            'warning'
          );
        }
      }
      
      if ($this->debug) {
        error_log("--- EMBEDDING SEARCH END ---\n");
      }
      
      return $resultsArray;
      
    } catch (\Exception $e) {
      $duration = (microtime(true) - $startTime) * 1000;
      
      $this->logger->logSecurityEvent(
        "EmbeddingSearch: Search failed: " . $e->getMessage(),
        'error'
      );
      
      if ($this->debug) {
        error_log("[error] SEARCH FAILED");
        error_log("Error: " . $e->getMessage());
        error_log("Duration: " . round($duration, 2) . " ms");
        error_log("--- EMBEDDING SEARCH END ---\n");
      }
      
      // Graceful degradation: return empty array (REQ-2.5)
      return [];
    }
  }
  
  /**
   * Get embedding for query with caching
   *
   * 🔧 PRIORITY 3 - PHASE 3.2: Embedding generation with 1 hour cache
   *
   * Embeddings are expensive to generate but don't change for the same query.
   * We cache them for 1 hour to avoid repeated API calls.
   *
   * @param string $query Query text
   * @return array Embedding vector
   */
  private function getEmbedding(string $query): array
  {
    $startTime = microtime(true);
    
    try {
      // 1. Check embedding cache (1 hour TTL)
      if ($this->cacheEnabled) {
        $embeddingCacheKey = $this->generateEmbeddingCacheKey($query);
        $embeddingCache = new Cache($embeddingCacheKey, 'Rag/Embedding');
        
        if ($embeddingCache->exists($this->embeddingCacheTTL)) {
          $cached = $embeddingCache->get();
          
          if ($cached !== null && is_array($cached)) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($this->debug) {
              error_log("✅ EMBEDDING CACHE HIT");
              error_log("Duration: " . round($duration, 2) . " ms");
            }
            
            return $cached;
          }
        }
        
        if ($this->debug) {
          error_log("[error] EMBEDDING CACHE MISS - Generating embedding");
        }
      }
      
      // 2. Generate embedding
      $embedding = $this->embeddingGenerator->embedText($query);
      
      // Validate embedding format
      if (!is_array($embedding) || empty($embedding)) {
        throw new \RuntimeException("Invalid embedding format");
      }
      
      $duration = (microtime(true) - $startTime) * 1000;
      
      if ($this->debug) {
        error_log("✅ Embedding generated");
        error_log("Dimensions: " . count($embedding));
        error_log("Duration: " . round($duration, 2) . " ms");
      }
      
      // 3. Cache embedding (1 hour TTL)
      if ($this->cacheEnabled) {
        try {
          $embeddingCacheKey = $this->generateEmbeddingCacheKey($query);
          $embeddingCache = new Cache($embeddingCacheKey, 'Rag/Embedding');
          $embeddingCache->save($embedding);
          
          if ($this->debug) {
            error_log("✅ Embedding cached for {$this->embeddingCacheTTL} minutes");
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "EmbeddingSearch: Failed to cache embedding: " . $e->getMessage(),
            'warning'
          );
        }
      }
      
      return $embedding;
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "EmbeddingSearch: Failed to generate embedding: " . $e->getMessage(),
        'error'
      );
      
      if ($this->debug) {
        error_log("[error] EMBEDDING GENERATION FAILED");
        error_log("Error: " . $e->getMessage());
      }
      
      return [];
    }
  }
  
  /**
   * Generate cache key for search results
   *
   * @param string $query Query text
   * @param int $limit Result limit
   * @param float $minScore Minimum score
   * @param callable|null $filter Filter function
   * @return string Cache key
   */
  private function generateSearchCacheKey(
    string $query,
    int $limit,
    float $minScore,
    ?callable $filter
  ): string {
    $keyData = [
      'query' => strtolower(trim($query)),
      'limit' => $limit,
      'min_score' => round($minScore, 2),
      'filter' => $filter !== null ? 'filtered' : 'unfiltered',
      'table' => $this->vectorStore->getTableName(),
    ];
    
    return md5(json_encode($keyData));
  }
  
  /**
   * Generate cache key for embedding
   *
   * @param string $query Query text
   * @return string Cache key
   */
  private function generateEmbeddingCacheKey(string $query): string
  {
    // Normalize query for consistent caching
    $normalizedQuery = strtolower(trim($query));
    return md5($normalizedQuery);
  }
  
  /**
   * Enable or disable caching
   *
   * @param bool $enabled Enable caching
   * @return void
   */
  public function setCacheEnabled(bool $enabled): void
  {
    $this->cacheEnabled = $enabled;
  }
  
  /**
   * Set embedding cache TTL in minutes
   *
   * @param int $minutes Cache TTL in minutes
   * @return void
   */
  public function setEmbeddingCacheTTL(int $minutes): void
  {
    $this->embeddingCacheTTL = $minutes;
  }
  
  /**
   * Set search result cache TTL in minutes
   *
   * @param int $minutes Cache TTL in minutes
   * @return void
   */
  public function setSearchCacheTTL(int $minutes): void
  {
    $this->searchCacheTTL = $minutes;
  }
  
  /**
   * Set default limit for search results
   *
   * @param int $limit Default limit
   * @return void
   */
  public function setDefaultLimit(int $limit): void
  {
    $this->defaultLimit = $limit;
  }
  
  /**
   * Set default minimum score
   *
   * @param float $minScore Default minimum score
   * @return void
   */
  public function setDefaultMinScore(float $minScore): void
  {
    $this->defaultMinScore = $minScore;
  }
  
  /**
   * Clear all embedding and search caches
   *
   * @return void
   */
  public function clearCache(): void
  {
    try {
      Cache::clear('Rag/Embedding', 'Rag/Embedding');
      Cache::clear('Rag/EmbeddingSearch', 'Rag/EmbeddingSearch');
      
      if ($this->debug) {
        $this->logger->logSecurityEvent("EmbeddingSearch: Cache cleared", 'info');
      }
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "EmbeddingSearch: Failed to clear cache: " . $e->getMessage(),
        'warning'
      );
    }
  }
  
  /**
   * Get cache statistics
   *
   * @return array Cache statistics
   */
  public function getCacheStats(): array
  {
    try {
      $embeddingStats = Cache::getStats();
      $searchStats = Cache::getStats();
      
      return [
        'embedding_cache' => $embeddingStats,
        'search_cache' => $searchStats,
        'embedding_ttl_minutes' => $this->embeddingCacheTTL,
        'search_ttl_minutes' => $this->searchCacheTTL,
        'cache_enabled' => $this->cacheEnabled,
      ];
    } catch (\Exception $e) {
      return [
        'error' => $e->getMessage()
      ];
    }
  }
}
