<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * WebSearchInterface - Contract for all search engine implementations
 *
 * This interface defines the standard contract that all search engines must implement
 * to ensure consistent behavior across different search modes (AI Overview, Shopping, RAG).
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Executor
 */
interface WebSearchInterface
{
  /**
   * Execute a search query using this engine
   *
   * @param string $query The search query string
   * @param array $options Optional parameters including:
   *                       - max_results: Maximum number of results to return
   *                       - location_params: Array with gl, hl, currency
   *                       - target_site: Specific site to search (for RAG mode)
   * @return array Unified result structure with engine-specific data
   */
  public function search(string $query, array $options = []): array;

  /**
   * Get the engine name identifier
   *
   * @return string Engine name (e.g., 'google_ai_overview', 'google_shopping', 'rag_websearch')
   */
  public function getName(): string;

  /**
   * Check if the engine is available and properly configured
   *
   * @return bool True if engine can be used, false otherwise
   */
  public function isAvailable(): bool;

  /**
   * Get the capabilities supported by this engine
   *
   * @return array Capabilities structure with boolean flags:
   *               - shopping_results: Supports structured shopping data
   *               - ai_overview: Supports AI-generated summaries
   *               - organic_results: Supports standard web results
   *               - targeted_scraping: Supports site-specific scraping
   */
  public function getCapabilities(): array;

  /**
   * Validate the engine configuration
   *
   * @return bool True if configuration is valid, false otherwise
   */
  public function validateConfig(): bool;

  /**
   * Get engine metadata for monitoring and cost tracking
   *
   * @return array Metadata structure with:
   *               - cost_per_request: Estimated cost per API call
   *               - avg_latency: Average response time in milliseconds
   *               - quality_score: Engine quality rating (0.0-1.0)
   */
  public function getMetadata(): array;
}
