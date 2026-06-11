<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Helper;

use ClicShopping\OM\CLICSHOPPING;

/**
 * AgentResponseHelper
 *
 * Helper class to standardize agent responses according to AgentResponseInterface.
 * This class provides utility methods to create properly formatted responses
 * for all agent types (analytics, semantic, web_search, hybrid).
 *
 * MERGED: ResponseHelper methods integrated (2025-12-12)
 * - Clarification requests
 * - Error responses
 * - Success responses
 * - Ambiguous responses
 * - Multilingual support via CLICSHOPPING::getDef()
 *
 * Purpose:
 * - Centralize response formatting logic
 * - Ensure consistency across all agents
 * - Simplify agent implementation
 * - Facilitate testing and maintenance
 * - Support multilingual messages
 *
 * @package ClicShopping\AI\Helper
 * @version 2.0.0
 * @since 2025-11-14
 * @updated 2025-12-12 - Merged ResponseHelper
 */
class AgentResponseHelper
{
  /**
   * Create a standardized analytics response
   *
   * @param string $query Original query
   * @param array $result Analytics result data
   * @param bool $success Success status
   * @param array $metadata Additional metadata
   * @param string|null $error Error message if any
   * @return array Standardized response
   */
  public static function createAnalyticsResponse(
    string $query,
    array $result,
    bool $success = true,
    array $metadata = [],
    ?string $error = null
  ): array {
    // Extract SQL queries for transparency
    $sqlQueries = [];
    if (isset($result['sql'])) {
      $sqlQueries[] = $result['sql'];
    } elseif (isset($result['sql_queries'])) {
      $sqlQueries = $result['sql_queries'];
    }

    // Build source attribution
    $sourceAttribution = [
      'source_type' => 'analytics',
      'primary_source' => 'Analytics Database',
      'source_icon' => '📊',
      'details' => [
        'table' => self::extractTableName($sqlQueries[0] ?? ''),
        'query_count' => count($sqlQueries),
      ],
      'confidence' => $success ? 0.9 : 0.0,
    ];

    // Add SQL queries to details if available
    if (!empty($sqlQueries)) {
      $sourceAttribution['details']['sql_queries'] = $sqlQueries;
    }

    return [
      'success' => $success,
      'type' => 'analytics',
      'query' => $query,
      'result' => $result,
      'source_attribution' => $sourceAttribution,
      'metadata' => array_merge([
        'entity_id' => $result['entity_id'] ?? null,
        'entity_type' => $result['entity_type'] ?? null,
        'execution_time' => $metadata['execution_time'] ?? 0,
        'timestamp' => date('c'),
        'cache_hit' => $result['cached'] ?? false,
      ], $metadata),
      'error' => $error,
    ];
  }

  /**
   * Create a standardized semantic response
   *
   * @param string $query Original query
   * @param array $result Semantic search result data
   * @param bool $success Success status
   * @param array $metadata Additional metadata
   * @param string|null $error Error message if any
   * @return array Standardized response
   */
  public static function createSemanticResponse(
    string $query,
    array $result,
    bool $success = true,
    array $metadata = [],
    ?string $error = null
  ): array {
    // Determine if we have actual embedding results or LLM fallback
    $hasEmbeddings = !empty($result) && isset($result[0]['content']);
    $documentCount = $hasEmbeddings ? count($result) : 0;

    // Build source attribution
    if ($hasEmbeddings) {
      $sourceAttribution = [
        'source_type' => 'semantic',
        'primary_source' => 'RAG Knowledge Base',
        'source_icon' => '📚',
        'details' => [
          'document_count' => $documentCount,
          'similarity_threshold' => $metadata['min_score'] ?? 0.5,
          'top_documents' => array_slice(array_map(function($doc) {
            return [
              'score' => round(($doc['score'] ?? 0) * 100, 2) . '%',
              'preview' => substr($doc['content'] ?? '', 0, 100),
            ];
          }, $result), 0, 3),
        ],
        'confidence' => $documentCount > 0 ? 0.85 : 0.5,
      ];
    } else {
      // LLM fallback
      $sourceAttribution = [
        'source_type' => 'semantic',
        'primary_source' => 'LLM',
        'source_icon' => '🤖',
        'details' => [
          'fallback' => true,
          'reason' => 'No RAG data available',
        ],
        'confidence' => 0.5,
      ];
    }

    return [
      'success' => $success,
      'type' => 'semantic',
      'query' => $query,
      'result' => $result,
      'source_attribution' => $sourceAttribution,
      'metadata' => array_merge([
        'entity_id' => $metadata['entity_id'] ?? null,
        'entity_type' => $metadata['entity_type'] ?? null,
        'execution_time' => $metadata['execution_time'] ?? 0,
        'timestamp' => date('c'),
        'document_count' => $documentCount,
        'has_embeddings' => $hasEmbeddings,
      ], $metadata),
      'error' => $error,
    ];
  }

  /**
   * Create a standardized web search response
   *
   * @param string $query Original query
   * @param array $result Web search result data
   * @param bool $success Success status
   * @param array $metadata Additional metadata
   * @param string|null $error Error message if any
   * @return array Standardized response
   */
  public static function createWebSearchResponse(
    string $query,
    array $result,
    bool $success = true,
    array $metadata = [],
    ?string $error = null
  ): array {
    // Extract URLs from results
    $urls = [];
    if (isset($result['items'])) {
      $urls = array_slice(array_map(function($item) {
        return $item['url'] ?? $item['link'] ?? '';
      }, $result['items']), 0, 5);
    } elseif (is_array($result)) {
      foreach ($result as $item) {
        if (isset($item['url'])) {
          $urls[] = $item['url'];
        }
      }
      $urls = array_slice($urls, 0, 5);
    }

    // Check if this is a price comparison
    $isPriceComparison = isset($result['is_price_comparison']) && $result['is_price_comparison'];

    // Build source attribution
    if ($isPriceComparison) {
      $sourceAttribution = [
        'source_type' => 'web_search',
        'primary_source' => 'Mixed',
        'source_icon' => '🔀',
        'details' => [
          'sources' => ['Analytics Database', 'Web Search'],
          'comparison_type' => 'price',
          'url_count' => count($urls),
        ],
        'confidence' => 0.8,
      ];
    } else {
      $sourceAttribution = [
        'source_type' => 'web_search',
        'primary_source' => 'Web Search',
        'source_icon' => '🌐',
        'details' => [
          'url_count' => count($urls),
          'urls' => $urls,
          'cache_source' => $metadata['cache_source'] ?? 'none',
        ],
        'confidence' => 0.7,
      ];
    }

    return [
      'success' => $success,
      'type' => 'web_search',
      'query' => $query,
      'result' => $result,
      'source_attribution' => $sourceAttribution,
      'metadata' => array_merge([
        'entity_id' => $result['product']['product_id'] ?? null,
        'entity_type' => isset($result['product']) ? 'product' : null,
        'execution_time' => $metadata['execution_time'] ?? 0,
        'timestamp' => date('c'),
        'cache_hit' => $metadata['cached'] ?? false,
        'is_price_comparison' => $isPriceComparison,
      ], $metadata),
      'error' => $error,
    ];
  }

  /**
   * Create a standardized hybrid response
   *
   * @param string $query Original query
   * @param array $subResults Array of sub-query results
   * @param string $synthesis Synthesized text response
   * @param array $metadata Additional metadata
   * @param string|null $error Error message if any
   * @return array Standardized response
   */
  public static function createHybridResponse(
    string $query,
    array $subResults,
    string $synthesis,
    array $metadata = [],
    ?string $error = null
  ): array {
    // Collect source attributions from sub-results
    $sourceAttributions = [];
    $sourcesUsed = [];
    
    foreach ($subResults as $subResult) {
      if (isset($subResult['source_attribution'])) {
        $sourceAttributions[] = $subResult['source_attribution'];
        $sourcesUsed[] = $subResult['source_attribution']['primary_source'] ?? 'Unknown';
      }
    }

    $sourcesUsed = array_unique($sourcesUsed);

    // Build combined source attribution
    $sourceAttribution = [
      'source_type' => 'hybrid',
      'primary_source' => 'Mixed',
      'source_icon' => '🔀',
      'details' => [
        'sources' => $sourcesUsed,
        'source_count' => count($sourceAttributions),
        'sub_query_count' => count($subResults),
      ],
      'confidence' => 0.8,
    ];

    // Determine success based on sub-results
    $success = !empty(array_filter($subResults, fn($r) => $r['success'] ?? true));

    return [
      'success' => $success,
      'type' => 'hybrid',
      'query' => $query,
      'text_response' => $synthesis, // Add text_response at top level for consistency
      'result' => [
        'sub_queries' => $subResults,
        'synthesis' => $synthesis,
        'sources_used' => $sourcesUsed,
      ],
      'source_attribution' => $sourceAttribution,
      'metadata' => array_merge([
        'execution_time' => $metadata['execution_time'] ?? 0,
        'timestamp' => date('c'),
        'sub_query_count' => count($subResults),
        'successful_count' => count(array_filter($subResults, fn($r) => $r['success'] ?? true)),
        'failed_count' => count(array_filter($subResults, fn($r) => !($r['success'] ?? true))),
      ], $metadata),
      'error' => $error,
    ];
  }

  /**
   * Create an error response
   *
   * @param string $query Original query
   * @param string $error Error message
   * @param string $type Query type (analytics, semantic, web_search, hybrid)
   * @param array $metadata Additional metadata
   * @return array Standardized error response
   */
  public static function createErrorResponse(
    string $query,
    string $error,
    string $type = 'semantic',
    array $metadata = []
  ): array {
    return [
      'success' => false,
      'type' => $type,
      'query' => $query,
      'result' => [],
      'source_attribution' => [
        'source_type' => 'error',
        'primary_source' => 'Error',
        'source_icon' => '❌',
        'details' => [
          'error_type' => $metadata['error_type'] ?? 'execution_error',
          'component' => $metadata['component'] ?? 'Unknown',
        ],
        'confidence' => 0.0,
      ],
      'metadata' => array_merge([
        'execution_time' => 0,
        'timestamp' => date('c'),
        'error_id' => $metadata['error_id'] ?? uniqid('err_', true),
      ], $metadata),
      'error' => $error,
    ];
  }

  /**
   * Extract table name from SQL query
   *
   * @param string $sql SQL query
   * @return string Table name or 'database'
   */
  private static function extractTableName(string $sql): string
  {
    if (empty($sql)) {
      return 'database';
    }

    // Extract table name from FROM clause
    if (preg_match('/FROM\s+([a-zA-Z0-9_]+)/i', $sql, $matches)) {
      return $matches[1];
    }

    return 'database';
  }

  /**
   * Validate response structure
   *
   * Ensures the response has all required fields according to AgentResponseInterface.
   *
   * @param array $response Response to validate
   * @return bool True if valid
   */
  public static function validateResponse(array $response): bool
  {
    $requiredFields = ['success', 'type', 'query', 'result', 'source_attribution', 'metadata'];
    
    foreach ($requiredFields as $field) {
      if (!isset($response[$field])) {
        return false;
      }
    }

    // Validate source_attribution structure
    if (!isset($response['source_attribution']['primary_source']) ||
        !isset($response['source_attribution']['source_icon']) ||
        !isset($response['source_attribution']['confidence'])) {
      return false;
    }

    // Validate metadata structure
    if (!isset($response['metadata']['timestamp'])) {
      return false;
    }

    return true;
  }

  // ============================================================================
  // MERGED METHODS FROM ResponseHelper (2025-12-12)
  // ============================================================================

  /**
   * Build a clarification request response
   * Used when query is too ambiguous to process
   *
   * @param string $query Original query
   * @param string|null $ambiguityType Type of ambiguity detected
   * @param string|null $customMessage Custom clarification message (optional)
   * @return array Clarification response structure
   */
  public static function buildClarificationRequest(
    string $query,
    ?string $ambiguityType = null,
    ?string $customMessage = null
  ): array {
    // Use language definition or custom message
    $message = $customMessage ?? CLICSHOPPING::getDef('text_query_too_ambiguous');
    
    // Fallback if translation key is returned (not translated)
    if ($message === 'text_query_too_ambiguous') {
      $message = 'Votre requête est trop ambiguë. Veuillez être plus précis sur ce que vous voulez savoir.';
    }

    return [
      'type' => 'clarification_needed',
      'query' => $query,
      'ambiguous' => true,
      'ambiguity_type' => $ambiguityType,
      'message' => $message
    ];
  }

  /**
   * Build an error response (legacy method - use createErrorResponse instead)
   * Standardized error structure across all agents
   *
   * @deprecated Use createErrorResponse() instead
   * @param string $query Original query
   * @param string $errorMessage Error message (can be language key or direct message)
   * @param string|null $errorType Type of error (optional)
   * @param array $context Additional error context (optional)
   * @return array Error response structure
   */
  public static function buildErrorResponse(
    string $query,
    string $errorMessage,
    ?string $errorType = null,
    array $context = []
  ): array {
    // Try to get language definition, fallback to direct message
    $message = CLICSHOPPING::getDef($errorMessage, null, $errorMessage);

    return [
      'type' => 'error',
      'query' => $query,
      'message' => $message,
      'error_type' => $errorType,
      'context' => $context,
      'timestamp' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Build a success response with results
   * Standardized success structure
   *
   * @param string $query Original query
   * @param string $resultType Type of result (analytics, semantic, web_search, etc.)
   * @param array $results Result data
   * @param array $metadata Additional metadata (optional)
   * @return array Success response structure
   */
  public static function buildSuccessResponse(
    string $query,
    string $resultType,
    array $results,
    array $metadata = []
  ): array {
    return [
      'type' => $resultType,
      'query' => $query,
      'success' => true,
      'results' => $results,
      'count' => count($results),
      'metadata' => $metadata,
      'timestamp' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Build an ambiguous query response with multiple interpretations
   * Used when query has multiple valid interpretations
   *
   * @param string $query Original query
   * @param string $ambiguityType Type of ambiguity
   * @param array $interpretationResults Results for each interpretation
   * @param string|null $recommendation Recommendation message (optional)
   * @return array Ambiguous response structure
   */
  public static function buildAmbiguousResponse(
    string $query,
    string $ambiguityType,
    array $interpretationResults,
    ?string $recommendation = null
  ): array {
    $interpretations = array_map(function($result) {
      return $result['type'] ?? 'unknown';
    }, $interpretationResults);

    // Use language definition for recommendation
    $defaultRecommendation = CLICSHOPPING::getDef('text_multiple_interpretations_review');

    return [
      'type' => 'analytics_results_ambiguous',
      'query' => $query,
      'ambiguous' => true,
      'ambiguity_type' => $ambiguityType,
      'interpretations' => $interpretations,
      'interpretation_results' => $interpretationResults,
      'count' => count($interpretationResults),
      'recommendation' => $recommendation ?? $defaultRecommendation
    ];
  }

  /**
   * Add ambiguity metadata to existing response
   * Enriches response with ambiguity information
   *
   * @param array $response Existing response
   * @param bool $isAmbiguous Whether query was ambiguous
   * @param string|null $ambiguityType Type of ambiguity (optional)
   * @param array $interpretations List of interpretations (optional)
   * @return array Response with ambiguity metadata
   */
  public static function addAmbiguityMetadata(
    array $response,
    bool $isAmbiguous,
    ?string $ambiguityType = null,
    array $interpretations = []
  ): array {
    $response['ambiguous'] = $isAmbiguous;

    if ($isAmbiguous) {
      $response['ambiguity_type'] = $ambiguityType;
      $response['interpretations'] = $interpretations;
    }

    return $response;
  }
}
