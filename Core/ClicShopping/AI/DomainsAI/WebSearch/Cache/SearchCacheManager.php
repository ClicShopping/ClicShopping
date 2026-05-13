<?php
/**
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Cache;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;

/**
 * SearchCacheManager Class
 *
 * Manages intelligent cache (Learning RAG) for web search results.
 * Stores high-quality results in `rag_web_cache_vectors` for:
 * - Reducing API costs
 * - Accelerating future queries
 * - Learning from past searches (self-healing)
 */

class SearchCacheManager
{
  private MariaDBVectorStore $vectorStore;
  private $embeddingGenerator;
  private SecurityLogger $logger;
  private bool $debug;
  private string $tableName;

  // Configuration
  private int $maxChunkSize = 800;
  private int $chunkOverlap = 50;
  private float $qualityThreshold = 0.7;

  /**
   * Constructor
   *
   * @param string $tableName Table for cache (default: rag_web_cache_vectors)
   */
   public function __construct(string $tableName = 'rag_web_cache_embedding')
  {
    $this->logger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->embeddingGenerator = NewVector::gptEmbeddingsModel();

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $fullTableName = $prefix . $tableName;

    $this->tableName = $fullTableName;

    $this->vectorStore = new MariaDBVectorStore($this->embeddingGenerator, $fullTableName);

    if ($this->debug) {
      $this->logger->logSecurityEvent("SearchCacheManager initialized with table: {$fullTableName}", 'info');
    }
  }

  /**
   * Return the table name used for storage
   *
   * @return string Table name
   */
  public function getTableName(): string
  {
    return $this->tableName;
  }

  /**
   * Stores web results in the learning RAG
   *
   * @param string $query Original query
   * @param array $results Formatted results from WebSearchTool
   * @param array $metadata Additional metadata
   * @return bool True if success
   */
  public function storeInLearningRAG(string $query, array $results, array $metadata = []): bool
  {
    if ($this->vectorStore === null) {
      return false;
    }
    
    try {
      $qualityScore = $this->calculateQualityScore($results);

      if ($qualityScore < $this->qualityThreshold) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Quality too low ({$qualityScore}), skipping storage",
            'info'
          );
        }
        return false;
      }

      // Deduplicate shopping results before caching
      if (isset($results['shopping_results']) && is_array($results['shopping_results'])) {
        $results['shopping_results'] = $this->deduplicateShoppingResults($results['shopping_results']);
      }

      // Format content using JSON v2.0 format
      $content = $this->formatContentForStorageV2($query, $results, $qualityScore);

      if (empty(trim($content))) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Empty content generated, cannot store",
            'warning'
          );
        }
        return false;
      }

      $document = new Document();
      $document->content = $content;
      $document->sourceType = 'web_search';
      $document->sourceName = $results['metadata']['search_engine'] ?? 'serpapi';

      $document->metadata = [
        'type' => 'web_search_cache',
        'original_query' => $query,
        'search_engine' => $results['metadata']['search_engine'] ?? 'serpapi',
        'quality_score' => $qualityScore,
        'usage_count' => 0,
        'last_used' => null,
        'total_results' => $results['total_results'] ?? 0,
        'date_cached' => date('Y-m-d H:i:s'),
        'has_ai_overview' => $results['metadata']['has_ai_overview'] ?? false,
        'cache_format' => 'json_v2.0',
        'engine_type' => $results['metadata']['mode_type'] ?? ($results['metadata']['search_engine'] ?? 'serpapi'),
      ];

      // Preserve AI Overview data in metadata
      if (isset($results['ai_overview']) && !empty($results['ai_overview'])) {
        $document->metadata['ai_overview'] = $results['ai_overview'];
        $document->metadata['is_generative'] = $results['ai_overview']['is_generative'] ?? true;
      }

      if (!empty($metadata)) {
        $document->metadata = array_merge($document->metadata, $metadata);
      }

      // Log cache format used
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Storing cache in JSON v2.0 format (engine_type: {$document->metadata['engine_type']})",
          'info'
        );
      }

      $estimatedTokens = $this->estimateTokenCount($content);

      if ($estimatedTokens > $this->maxChunkSize) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Content large ({$estimatedTokens} tokens), splitting into chunks",
            'info'
          );
        }

        $splitDocs = DocumentSplitter::splitDocument($document, $this->maxChunkSize, $this->chunkOverlap);

        $storedCount = 0;

        foreach ($splitDocs as $chunk) {
          if (empty($chunk->content)) {
            continue;
          }

          $chunk->metadata['is_chunked'] = true;
          $chunk->metadata['chunk_parent_query'] = $query;

          $this->vectorStore->addDocument($chunk);
          $storedCount++;
        }

        if ($this->debug) {
          $this->logger->logSecurityEvent("Stored {$storedCount} chunks in learning RAG", 'info' );
        }

      } else {
        $this->vectorStore->addDocument($document);

        if ($this->debug) {
          $this->logger->logSecurityEvent( "Stored single document in learning RAG", 'info'
          );
        }
      }

      return true;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent("Error storing in learning RAG: " . $e->getMessage(), 'error');
      return false;
    }
  }

  /**
   * Search in RAG cache
   *
   * @param string $query Search query
   * @param int $limit Max number of results
   * @return array|null Cache results or null if nothing found
   */
  public function searchInCache(string $query, int $limit = 3): ?array
  {
    if ($this->vectorStore === null) {
      return null;
    }
    
    try {
      $results = $this->vectorStore->similaritySearch(
        $query,
        $limit,
        0.75,
        function($metadata) {
          return isset($metadata['type'])
            && $metadata['type'] === 'web_search_cache';
        }
      );

      if (empty($results)) {
        return null;
      }

      foreach ($results as $doc) {
        $this->incrementUsageCount($doc->id);
      }

      $formatted = [];

      foreach ($results as $doc) {
        // Detect cache format
        $cacheFormat = $doc->metadata['cache_format'] ?? 'legacy_text';
        
        // Parse content based on format
        if ($cacheFormat === 'json_v2.0') {
          $parsedContent = $this->parseJsonV2Content($doc->content);
        } else {
          $parsedContent = $this->parseLegacyTextContent($doc->content);
        }

        $result = [
          'content' => $doc->content,
          'original_query' => $doc->metadata['original_query'] ?? '',
          'quality_score' => $doc->metadata['quality_score'] ?? 0,
          'similarity_score' => $doc->metadata['score'] ?? 0,
          'usage_count' => $doc->metadata['usage_count'] ?? 0,
          'cache_format' => $cacheFormat,
          'engine_type' => $doc->metadata['engine_type'] ?? 'unknown',
        ];

        // Merge parsed content
        if ($parsedContent) {
          $result = array_merge($result, $parsedContent);
        }

        // Include AI Overview data if present
        if (isset($doc->metadata['has_ai_overview']) && $doc->metadata['has_ai_overview'] === true) {
          $result['has_ai_overview'] = true;
          
          if (isset($doc->metadata['ai_overview'])) {
            $result['ai_overview'] = $doc->metadata['ai_overview'];
          }
          
          if (isset($doc->metadata['is_generative'])) {
            $result['is_generative'] = $doc->metadata['is_generative'];
          }
        }

        $formatted[] = $result;
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent("Cache hit: Found " . count($formatted) . " results for query", 'info');
        
        // Log cache format distribution
        $formatCounts = array_count_values(array_column($formatted, 'cache_format'));
        foreach ($formatCounts as $format => $count) {
          $this->logger->logSecurityEvent("Cache format used: {$format} ({$count} results)", 'info');
        }
      }

      return $formatted;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent("Error searching cache: " . $e->getMessage(), 'error');
      return null;
    }
  }

  /**
   * Formats content for storage using JSON v2.0 format
   *
   * @param string $query Original query
   * @param array $results Search results
   * @param float $qualityScore Calculated quality score
   * @return string JSON formatted content
   */
  private function formatContentForStorageV2(string $query, array $results, float $qualityScore): string
  {
    $jsonData = [
      'version' => '2.0',
      'query' => $query,
      'ai_overview' => $results['ai_overview'] ?? null,
      'organic_results' => $results['items'] ?? [],
      'shopping_results' => $results['shopping_results'] ?? [],
      'metadata' => [
        'mode_type' => $results['metadata']['mode_type'] ?? 'unknown',
        'engines_used' => $results['metadata']['engines_used'] ?? [],
        'routing_method' => $results['metadata']['routing_method'] ?? 'unknown',
        'search_engine' => $results['metadata']['search_engine'] ?? 'serpapi',
        'has_ai_overview' => $results['metadata']['has_ai_overview'] ?? false,
        'total_results' => $results['total_results'] ?? 0,
      ],
      'quality_score' => $qualityScore,
      'cached_at' => date('c'), // ISO 8601 format
    ];

    return json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  }

  /**
   * Parses JSON v2.0 format content
   *
   * @param string $content JSON content
   * @return array|null Parsed data or null on failure
   */
  private function parseJsonV2Content(string $content): ?array
  {
    try {
      $data = json_decode($content, true);
      
      if (!$data || !isset($data['version']) || $data['version'] !== '2.0') {
        return null;
      }

      return [
        'query' => $data['query'] ?? '',
        'ai_overview' => $data['ai_overview'] ?? null,
        'organic_results' => $data['organic_results'] ?? [],
        'shopping_results' => $data['shopping_results'] ?? [],
        'metadata' => $data['metadata'] ?? [],
        'cached_quality_score' => $data['quality_score'] ?? 0,
        'cached_at' => $data['cached_at'] ?? null,
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Error parsing JSON v2.0 content: " . $e->getMessage(),
          'warning'
        );
      }
      return null;
    }
  }

  /**
   * Parses legacy text format content
   *
   * @param string $content Text content
   * @return array|null Parsed data or null on failure
   */
  private function parseLegacyTextContent(string $content): ?array
  {
    try {
      // Extract query
      $query = '';
      if (preg_match('/^Query:\s*(.+?)$/m', $content, $matches)) {
        $query = trim($matches[1]);
      }

      // Extract AI Overview
      $aiOverview = null;
      if (preg_match('/AI Overview:\s*\n(.+?)(?=\n\n|$)/s', $content, $matches)) {
        $aiOverview = [
          'full_summary' => trim($matches[1]),
        ];
      }

      // Extract organic results (simplified)
      $organicResults = [];
      if (preg_match_all('/\d+\.\s+(.+?)\n\s+(.+?)\n\s+Source:\s+(.+?)(?=\n\n|\n\d+\.|\z)/s', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          $organicResults[] = [
            'title' => trim($match[1]),
            'snippet' => trim($match[2]),
            'source' => trim($match[3]),
          ];
        }
      }

      return [
        'query' => $query,
        'ai_overview' => $aiOverview,
        'organic_results' => $organicResults,
        'shopping_results' => [], // Legacy format doesn't have shopping results
        'metadata' => [
          'mode_type' => 'legacy',
          'engines_used' => ['legacy'],
        ],
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Error parsing legacy text content: " . $e->getMessage(),
          'warning'
        );
      }
      return null;
    }
  }

  /**
   * Deduplicates shopping results using normalized title + price hashing
   *
   * @param array $shoppingResults Shopping results array
   * @return array Deduplicated results
   */
  private function deduplicateShoppingResults(array $shoppingResults): array
  {
    $seen = [];
    $deduplicated = [];

    foreach ($shoppingResults as $result) {
      $title = $result['title'] ?? '';
      $price = $result['extracted_price'] ?? $result['price'] ?? '';

      // Normalize title: lowercase, remove punctuation, remove extra spaces
      $normalizedTitle = strtolower($title);
      $normalizedTitle = preg_replace('/[^\w\s]/', '', $normalizedTitle);
      $normalizedTitle = preg_replace('/\s+/', ' ', $normalizedTitle);
      $normalizedTitle = trim($normalizedTitle);

      // Generate hash
      $hash = md5($normalizedTitle . '|' . $price);

      if (!isset($seen[$hash])) {
        $seen[$hash] = true;
        $deduplicated[] = $result;
      }
    }

    if ($this->debug && count($shoppingResults) > count($deduplicated)) {
      $removed = count($shoppingResults) - count($deduplicated);
      $this->logger->logSecurityEvent(
        "Deduplicated shopping results: removed {$removed} duplicates",
        'info'
      );
    }

    return $deduplicated;
  }

  /**
   * Formats content for storage (legacy method - kept for backward compatibility)
   *
   * @param string $query Original query
   * @param array $results Search results
   * @return string Formatted content
   */
  private function formatContentForStorage(string $query, array $results): string
  {
    $parts = [];

    $parts[] = "Query: {$query}";
    $parts[] = "";

    // AI Overview section (if present)
    if (isset($results['ai_overview']) && !empty($results['ai_overview']['full_summary'])) {
      $parts[] = "AI Overview:";
      $parts[] = $results['ai_overview']['full_summary'];
      $parts[] = "";

      if (!empty($results['ai_overview']['sources'])) {
        $parts[] = "AI Overview Sources:";
        foreach ($results['ai_overview']['sources'] as $source) {
          if (is_array($source)) {
            $title = $source['title'] ?? 'Source';
            $url = $source['url'] ?? '';
            $parts[] = "- {$title}" . (!empty($url) ? " ({$url})" : "");
          } else {
            $parts[] = "- {$source}";
          }
        }
        $parts[] = "";
      }
    }

    if (isset($results['featured_snippet'])) {
      $parts[] = "Featured Answer:";
      $parts[] = $results['featured_snippet']['answer'];
      $parts[] = "Source: " . $results['featured_snippet']['source'];
      $parts[] = "";
    }

    $parts[] = "Top Results:";
    $items = array_slice($results['items'] ?? [], 0, 5);

    foreach ($items as $i => $item) {
      $parts[] = ($i + 1) . ". " . $item['title'];
      $parts[] = "   " . $item['snippet'];
      
      if (!empty($item['link'])) {
        $parts[] = "   Source: " . $item['link'];
      } else {
        $parts[] = "   Source: " . $item['source'];
      }
      
      $parts[] = "";
    }

    return implode("\n", $parts);
  }

  /**
   * Calculates quality score for results
   *
   * @param array $results Search results
   * @return float Quality score (0-1)
   */
  private function calculateQualityScore(array $results): float
  {
    $score = 0.5;

    // AI Overview bonus (30%)
    if (isset($results['metadata']['has_ai_overview']) && $results['metadata']['has_ai_overview'] === true) {
      $score += 0.3;
    }

    // Shopping results bonus (40% - scaled by count)
    if (isset($results['shopping_results']) && is_array($results['shopping_results'])) {
      $shoppingCount = count($results['shopping_results']);
      $score += min($shoppingCount / 10, 1.0) * 0.4;
    }

    if (isset($results['featured_snippet']) && !empty($results['featured_snippet']['answer'])) {
      $score += 0.2;
    }

    $relevantCount = 0;
    foreach ($results['items'] ?? [] as $item) {
      if (($item['relevance_score'] ?? 0) > 0.7) {
        $relevantCount++;
      }
    }
    $score += min($relevantCount / 10, 1.0) * 0.3;

    // Hybrid mode bonus (10%)
    if (isset($results['metadata']['engines_used']) && is_array($results['metadata']['engines_used']) && count($results['metadata']['engines_used']) > 1) {
      $score += 0.1;
    }

    // High result count bonus
    if (($results['total_results'] ?? 0) > 100) {
      $score += 0.1;
    }

    return min($score, 1.0);
  }

  /**
   * Estimates token count in text
   *
   * @param string $text Text
   * @return int Estimated token count
   */
  private function estimateTokenCount(string $text): int
  {
    return (int)ceil(strlen($text) / 4);
  }

  /**
   * Increments usage count of a document
   *
   * @param int $documentId Document ID
   */
  private function incrementUsageCount(int $documentId): void
  {
    try {
      $tableName = $this->tableName;

      $sql = "UPDATE {$tableName}
              SET usage_count = COALESCE(usage_count, 0) + 1,
                  last_used = NOW()
              WHERE id = :id";

      DoctrineOrm::execute($sql, ['id' => $documentId]);

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Failed to increment usage count: " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  /**
   * Cleans old unused documents
   *
   * @param int $daysOld Age in days
   * @param int $minUsageCount Minimum usage to keep
   * @return int Number of documents deleted
   */
  public function cleanOldCache(int $daysOld = 90, int $minUsageCount = 2): int
  {
    try {
      $tableName = $this->tableName;

      $sql = "DELETE FROM {$tableName} 
              WHERE type = 'web_search_cache'
                AND date_modified < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND COALESCE(usage_count, 0) < :min_usage";

      $deleted = DoctrineOrm::execute($sql, [
        'days' => $daysOld,
        'min_usage' => $minUsageCount
      ]);

      if ($this->debug && $deleted > 0) {
        $this->logger->logSecurityEvent( "Cleaned {$deleted} old cache entries (>{$daysOld} days, usage<{$minUsageCount})",'info');
      }

      return $deleted;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent("Error cleaning cache: " . $e->getMessage(),'error');
      return 0;
    }
  }

  /**
   * Gets cache statistics
   *
   * @return array Statistics
   */
  public function getCacheStats(): array
  {
    try {
      $tableName = $this->tableName;

      $sql = "SELECT 
                COUNT(*) as total_entries,
                AVG(quality_score) as avg_quality,
                SUM(usage_count) as total_usage,
                MAX(usage_count) as max_usage,
                COUNT(DISTINCT original_query) as unique_queries
              FROM {$tableName}
              WHERE type = 'web_search_cache'";

      $stats = DoctrineOrm::selectOne($sql);

      if (!$stats) {
        return [
          'total_cached_entries' => 0,
          'average_quality_score' => 0,
          'total_reuses' => 0,
          'max_reuses' => 0,
          'unique_queries_cached' => 0,
        ];
      }

      return [
        'total_cached_entries' => (int)$stats['total_entries'],
        'average_quality_score' => round((float)$stats['avg_quality'], 2),
        'total_reuses' => (int)$stats['total_usage'],
        'max_reuses' => (int)$stats['max_usage'],
        'unique_queries_cached' => (int)$stats['unique_queries'],
      ];

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error getting cache stats: " . $e->getMessage(),
        'error'
      );

      return [
        'total_cached_entries' => 0,
        'average_quality_score' => 0,
        'total_reuses' => 0,
        'max_reuses' => 0,
        'unique_queries_cached' => 0,
      ];
    }
  }

  /**
   * Sets quality threshold
   *
   * @param float $threshold Threshold (0-1)
   */
  public function setQualityThreshold(float $threshold): void
  {
    $this->qualityThreshold = max(0.0, min(1.0, $threshold));
  }

  /**
   * Sets chunk size
   *
   * @param int $size Size in tokens
   */
  public function setMaxChunkSize(int $size): void
  {
    $this->maxChunkSize = max(100, $size);
  }

  /**
   * Gets current quality threshold
   *
   * @return float Threshold (0-1)
   */
  public function getQualityThreshold(): float
  {
    return $this->qualityThreshold ?? 0.75;
  }
}