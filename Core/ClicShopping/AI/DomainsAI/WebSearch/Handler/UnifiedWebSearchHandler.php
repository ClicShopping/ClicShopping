<?php
/**
 * UnifiedWebSearchHandler
 * 
 * Feature flag wrapper for gradual rollout of unified websearch engine.
 * Routes to either legacy WebSearchTool or unified WebSearchFacade based on configuration.
 *
 * Requirements: 29.1, 29.2, 29.3, 29.4, 29.5
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Handler;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\WebSearch\WebSearchFacade;

/**
 * UnifiedWebSearchHandler Class
 *
 * This handler provides a unified interface for web search operations.
 * Legacy WebSearchTool has been removed - only WebSearchFacade is used.
 *
 * All operations are logged for monitoring and debugging.
 *
 * Architecture:
 * - Delegates all operations to WebSearchFacade (unified engine)
 * - Logs all operations for monitoring
 * - Handles errors gracefully
 */
class UnifiedWebSearchHandler
{
  /**
   * @var SecurityLogger Security and audit logger
   */
  private SecurityLogger $logger;

  /**
   * @var WebSearchFacade Unified web search facade (always used)
   */
  private WebSearchFacade $facade;

  /**
   * @var bool Debug mode
   */
  private bool $debug;

  /**
   * Constructor
   *
   * Initializes the handler with WebSearchFacade (unified engine only).
   * Legacy WebSearchTool has been removed.
   *
   * @param string $userId User ID (for compatibility)
   * @param int $languageId Language ID (for compatibility)
   * @param bool $debug Enable debug logging
   */
  public function __construct(string $userId = 'system', int $languageId = 1, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;

    // Initialize unified facade (only implementation)
    try {
      $this->facade = new WebSearchFacade();
      
      if ($this->debug) {
        $this->logger->logStructured(
          'info',
          'UnifiedWebSearchHandler',
          'initialization',
          [
            'implementation' => 'unified',
            'facade_initialized' => true,
            'user_id' => $userId,
            'language_id' => $languageId,
            'timestamp' => date('Y-m-d H:i:s')
          ]
        );
      }
    } catch (\Exception $e) {
      $this->logger->logError(
        'UnifiedWebSearchHandler initialization failed: ' . $e->getMessage(),
        [
          'error_type' => get_class($e),
          'stack_trace' => $e->getTraceAsString(),
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );
      
      throw $e; // Re-throw as there's no fallback
    }
  }

  /**
   * Main search method
   *
   * Routes search request to WebSearchFacade (unified engine only).
   * Legacy WebSearchTool has been removed.
   *
   * @param string $query Search query
   * @param array $options Search options:
   *                       - max_results: Maximum results per engine
   *                       - mode_hint: Explicit mode override
   *                       - location: Geographic location
   *                       - target_site: Specific site to search
   *
   * @return array Search results with metadata
   */
  public function search(string $query, array $options = []): array
  {
    $startTime = microtime(true);

    try {
      // Log search start
      $this->logger->logStructured(
        'info',
        'UnifiedWebSearchHandler',
        'search_start',
        [
          'query' => $query,
          'implementation' => 'unified',
          'options' => $options,
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      // Call unified facade
      $result = $this->facade->search($query, $options);

      // Calculate execution time
      $executionTime = microtime(true) - $startTime;

      // Add implementation metadata
      $result['metadata'] = $result['metadata'] ?? [];
      $result['metadata']['implementation_type'] = 'unified';
      $result['metadata']['execution_time'] = round($executionTime, 3);

      // Log search success
      $this->logger->logStructured(
        'info',
        'UnifiedWebSearchHandler',
        'search_success',
        [
          'query' => $query,
          'implementation' => 'unified',
          'execution_time' => round($executionTime, 3),
          'success' => $result['success'] ?? false,
          'result_count' => $this->getResultCount($result),
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      return $result;

    } catch (\Exception $e) {
      // Calculate execution time even on failure
      $executionTime = microtime(true) - $startTime;

      // Log error
      $this->logger->logError(
        'UnifiedWebSearchHandler search failed: ' . $e->getMessage(),
        [
          'query' => $query,
          'implementation' => 'unified',
          'execution_time' => round($executionTime, 3),
          'error_type' => get_class($e),
          'stack_trace' => $e->getTraceAsString(),
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      // Return error result
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'text_response' => 'Une erreur est survenue lors de la recherche web.',
        'metadata' => [
          'implementation_type' => 'unified',
          'execution_time' => round($executionTime, 3),
          'error_type' => get_class($e)
        ]
      ];
    }
  }

  /**
   * Compare product prices
   *
   * Routes price comparison request to WebSearchFacade (unified engine only).
   *
   * @param array $product Product data structure
   * @param string $query Search query
   *
   * @return array Price comparison result
   */
  public function comparePrice(array $product, string $query): array
  {
    $startTime = microtime(true);

    try {
      // Log price comparison start
      $this->logger->logStructured(
        'info',
        'UnifiedWebSearchHandler',
        'price_comparison_start',
        [
          'product_id' => $product['products_id'] ?? null,
          'query' => $query,
          'implementation' => 'unified',
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      // Call unified facade
      $result = $this->facade->comparePrice($product, $query);

      // Calculate execution time
      $executionTime = microtime(true) - $startTime;

      // Add implementation metadata
      $result['metadata'] = $result['metadata'] ?? [];
      $result['metadata']['implementation_type'] = 'unified';
      $result['metadata']['execution_time'] = round($executionTime, 3);

      // Log price comparison success
      $this->logger->logStructured(
        'info',
        'UnifiedWebSearchHandler',
        'price_comparison_success',
        [
          'product_id' => $product['products_id'] ?? null,
          'implementation' => 'unified',
          'execution_time' => round($executionTime, 3),
          'competitor_count' => count($result['competitors'] ?? []),
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      return $result;

    } catch (\Exception $e) {
      // Calculate execution time even on failure
      $executionTime = microtime(true) - $startTime;

      // Log error
      $this->logger->logError(
        'UnifiedWebSearchHandler price comparison failed: ' . $e->getMessage(),
        [
          'product_id' => $product['products_id'] ?? null,
          'query' => $query,
          'implementation' => 'unified',
          'execution_time' => round($executionTime, 3),
          'error_type' => get_class($e),
          'stack_trace' => $e->getTraceAsString(),
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );

      // Return error result
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'product' => $product,
        'competitors' => [],
        'metadata' => [
          'implementation_type' => 'unified',
          'execution_time' => round($executionTime, 3),
          'error_type' => get_class($e)
        ]
      ];
    }
  }

  /**
   * Get result count from search result
   *
   * @param array $result Search result
   *
   * @return int Result count
   */
  private function getResultCount(array $result): int
  {
    $count = 0;

    // Count items (legacy format)
    if (isset($result['items'])) {
      $count += count($result['items']);
    }

    // Count organic results (unified format)
    if (isset($result['organic_results'])) {
      $count += count($result['organic_results']);
    }

    // Count shopping results (unified format)
    if (isset($result['shopping_results'])) {
      $count += count($result['shopping_results']);
    }

    return $count;
  }
}
