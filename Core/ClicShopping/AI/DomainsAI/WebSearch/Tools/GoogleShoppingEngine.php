<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Tools;

use ClicShopping\AI\DomainsAI\WebSearch\Helper\SerpApiClient;
use ClicShopping\AI\DomainsAI\WebSearch\Logger\WebSearchLogger;
use ClicShopping\AI\InterfacesAI\WebSearchInterface;

/**
 * GoogleShoppingEngine - Mode B Executor
 *
 * Executes Google Shopping searches via SerpAPI with engine=google_shopping.
 * Provides structured price comparison with merchant data, pricing, and product links.
 *
 * Uses centralized SerpApiClient to avoid code duplication.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Executor
 */
class GoogleShoppingEngine implements WebSearchInterface
{
  private const ENGINE_NAME = 'google_shopping';
  private const DEFAULT_MAX_RESULTS = 20;

  private SerpApiClient $client;
  private WebSearchLogger $logger;
  private bool $debug;

  /**
   * Constructor
   */
  public function __construct()
  {
    $apiKey = $this->loadApiKey();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->client = new SerpApiClient($apiKey, $this->debug);
    $this->logger = new WebSearchLogger();
  }

  /**
   * Load SerpAPI key from configuration
   *
   * @return string API key
   */
  private function loadApiKey(): string
  {
    if (defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI')
        && !empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI)) {
      return trim(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI);
    }

    return '';
  }

  /**
   * Execute a search query using Google Shopping engine
   *
   * @param string $query The search query string
   * @param array $options Optional parameters including:
   *                       - max_results: Maximum number of results (default: 20)
   *                       - location_params: Array with gl, hl, currency (geolocation, language, currency)
   * @return array Unified result structure with shopping_results
   */
  public function search(string $query, array $options = []): array
  {
    $startTime = microtime(true);

    try {
      // Validate configuration
      if (!$this->validateConfig()) {
        return $this->buildErrorResponse(
          'Configuration validation failed: SerpAPI key not configured',
          $query,
          $startTime
        );
      }

      // Build parameters
      $params = $this->buildSearchParams($options);

      // Execute search via centralized client
      $data = $this->client->search(self::ENGINE_NAME, $query, $params);

      // Handle search failure
      if ($data === false) {
        return $this->buildErrorResponse(
          'SerpAPI request failed',
          $query,
          $startTime
        );
      }

      // Parse and build result
      $result = $this->buildResultFromData($data);

      // Add execution metadata
      $result['metadata']['execution_time'] = microtime(true) - $startTime;
      $result['metadata']['engine'] = self::ENGINE_NAME;

      if ($this->debug) {
        error_log(sprintf(
          '[GoogleShoppingEngine] Search completed in %.3fs - Query: %s - Results: %d',
          $result['metadata']['execution_time'],
          $query,
          count($result['shopping_results'])
        ));
      }

      return $result;

    } catch (\Exception $e) {
      return $this->buildErrorResponse(
        'Exception: ' . $e->getMessage(),
        $query,
        $startTime
      );
    }
  }

  /**
   * Build search parameters from options
   *
   * @param array $options Options array
   * @return array Parameters for SerpAPI
   */
  private function buildSearchParams(array $options): array
  {
    $params = [];

    // Add max results (num parameter)
    $maxResults = $options['max_results'] ?? (defined('CLICSHOPPING_APP_CHATGPT_WEB_SHOPPING_MAX_RESULTS') ? (int)CLICSHOPPING_APP_CHATGPT_WEB_SHOPPING_MAX_RESULTS : self::DEFAULT_MAX_RESULTS);
    $params['num'] = max(10, min(100, $maxResults)); // Clamp to range 10-100

    // Add location parameters if provided
    if (!empty($options['location_params'])) {
      $locationParams = $options['location_params'];

      if (!empty($locationParams['gl'])) {
        $params['gl'] = $locationParams['gl']; // Geolocation (country code)
      }

      if (!empty($locationParams['hl'])) {
        $params['hl'] = $locationParams['hl']; // Language
      }

      if (!empty($locationParams['currency'])) {
        $params['currency'] = $locationParams['currency']; // Currency code (EUR, USD, GBP, etc.)
      }
    }

    return $params;
  }

  /**
   * Get the engine name identifier
   *
   * @return string Engine name
   */
  public function getName(): string
  {
    return self::ENGINE_NAME;
  }

  /**
   * Check if the engine is available and properly configured
   *
   * @return bool True if engine can be used
   */
  public function isAvailable(): bool
  {
    return $this->validateConfig();
  }

  /**
   * Get the capabilities supported by this engine
   *
   * @return array Capabilities structure
   */
  public function getCapabilities(): array
  {
    return [
      'shopping_results' => true,
      'ai_overview' => false,
      'organic_results' => false,
      'targeted_scraping' => false,
    ];
  }

  /**
   * Validate the engine configuration
   *
   * Validates that CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI is set and properly formatted.
   *
   * Requirements: 19.3
   *
   * @return bool True if configuration is valid
   */
  public function validateConfig(): bool
  {
    $apiKey = $this->loadApiKey();

    if (empty($apiKey)) {
      if ($this->debug) {
        error_log('[GoogleShoppingEngine] Configuration invalid: SerpAPI key not set');
      }
      return false;
    }

    // Basic API key format validation (SerpAPI keys are typically 64 hex characters)
    if (strlen($apiKey) < 32) {
      if ($this->debug) {
        error_log('[GoogleShoppingEngine] Configuration invalid: SerpAPI key too short');
      }
      return false;
    }

    return true;
  }

  /**
   * Get engine metadata for monitoring and cost tracking
   *
   * @return array Metadata structure
   */
  public function getMetadata(): array
  {
    return [
      'cost_per_request' => 0.015, // Estimated cost per API call in USD (slightly higher than AI Overview)
      'avg_latency' => 1200.0,     // Average response time in milliseconds
      'quality_score' => 0.90,     // Engine quality rating (0.0-1.0) - high quality structured data
    ];
  }

  /**
   * Build SerpAPI URL for parallel execution
   *
   * @param string $query The search query string
   * @param array $options Optional parameters
   * @return string Complete SerpAPI URL with query parameters
   */
  public function buildSerpApiUrl(string $query, array $options = []): string
  {
    $params = $this->buildSearchParams($options);
    return $this->client->buildUrl(self::ENGINE_NAME, $query, $params);
  }

  /**
   * Parse SerpAPI JSON response
   *
   * @param string $jsonResponse Raw JSON response from SerpAPI
   * @return array Parsed result structure
   */
  public function parseResponse(string $jsonResponse): array
  {
    $data = $this->client->parseResponse($jsonResponse);

    if ($data === false) {
      return $this->buildErrorResponse(
        'Failed to parse SerpAPI response',
        '',
        0
      );
    }

    return $this->buildResultFromData($data);
  }

  /**
   * Build unified result structure from SerpAPI data
   *
   * @param array $data Decoded SerpAPI response
   * @return array Unified result structure
   */
  private function buildResultFromData(array $data): array
  {
    // Extract shopping_results array
    $shoppingResults = [];
    if (!empty($data['shopping_results']) && is_array($data['shopping_results'])) {
      foreach ($data['shopping_results'] as $result) {
        $shoppingResults[] = $this->extractShoppingResult($result);
      }
    }

    // Deduplicate shopping results using normalized title + price hashing
    $shoppingResults = $this->deduplicateResults($shoppingResults);

    // Build unified result structure
    return [
      'success' => true,
      'query' => $data['search_parameters']['q'] ?? '',
      'ai_overview' => null, // Not supported by this engine
      'organic_results' => [], // Not supported by this engine
      'shopping_results' => $shoppingResults,
      'metadata' => [
        'mode' => 'mode_b_google_shopping',
        'engine' => self::ENGINE_NAME,
        'result_count' => count($shoppingResults),
        'original_count' => count($data['shopping_results'] ?? []),
        'search_parameters' => $data['search_parameters'] ?? [],
      ],
    ];
  }

  /**
   * Extract shopping result from SerpAPI data
   *
   * Handles missing optional fields gracefully by setting them to null or empty string.
   * Preserves original position/ranking from SerpAPI.
   *
   * @param array $result Raw shopping result from SerpAPI
   * @return array Normalized shopping result
   */
  private function extractShoppingResult(array $result): array
  {
    // Ensure extracted_price is float or null
    $extractedPrice = isset($result['extracted_price']) ? (float)$result['extracted_price'] : null;
    $extractedOldPrice = isset($result['extracted_old_price']) ? (float)$result['extracted_old_price'] : null;

    return [
      'position' => $result['position'] ?? null,
      'title' => $result['title'] ?? '',
      'price' => $result['price'] ?? '',
      'extracted_price' => $extractedPrice,
      'old_price' => $result['old_price'] ?? null,
      'extracted_old_price' => $extractedOldPrice,
      'source' => $result['source'] ?? '',
      'product_link' => $result['link'] ?? '',
      'thumbnail' => $result['thumbnail'] ?? '',
      'data_source' => 'shopping_results',
      'engine_type' => self::ENGINE_NAME,
    ];
  }

  /**
   * Deduplicate shopping results using normalized title + price hashing
   *
   * Algorithm:
   * 1. Normalize title (lowercase, remove punctuation, remove stop words)
   * 2. Generate hash: md5(normalized_title + '|' + extracted_price)
   * 3. Keep first occurrence of each hash
   *
   * @param array $results Shopping results array
   * @return array Deduplicated results
   */
  private function deduplicateResults(array $results): array
  {
    $seen = [];
    $deduplicated = [];

    foreach ($results as $result) {
      $hash = $this->generateResultHash($result);

      if (!isset($seen[$hash])) {
        $seen[$hash] = true;
        $deduplicated[] = $result;
      } else {
        if ($this->debug) {
          error_log(sprintf(
            '[GoogleShoppingEngine] Duplicate removed: %s (hash: %s)',
            $result['title'],
            $hash
          ));
        }
      }
    }

    if ($this->debug && count($results) !== count($deduplicated)) {
      error_log(sprintf(
        '[GoogleShoppingEngine] Deduplication: %d -> %d results (%d duplicates removed)',
        count($results),
        count($deduplicated),
        count($results) - count($deduplicated)
      ));
    }

    return $deduplicated;
  }

  /**
   * Generate hash for deduplication
   *
   * @param array $result Shopping result
   * @return string MD5 hash
   */
  private function generateResultHash(array $result): string
  {
    $normalizedTitle = $this->normalizeTitle($result['title'] ?? '');
    $price = $result['extracted_price'] ?? 0;

    return md5($normalizedTitle . '|' . $price);
  }

  /**
   * Normalize title for deduplication
   *
   * Steps:
   * 1. Convert to lowercase
   * 2. Remove punctuation
   * 3. Remove extra whitespace
   * 4. Remove common stop words (basic set)
   *
   * @param string $title Original title
   * @return string Normalized title
   */
  private function normalizeTitle(string $title): string
  {
    // Convert to lowercase
    $normalized = mb_strtolower($title, 'UTF-8');

    // Remove punctuation (keep alphanumeric and spaces)
    $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);

    // Remove extra whitespace
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = trim($normalized);

    // Remove common stop words (basic English set)
    // Note: For production, this should use ModeSelector's STOPWORDS constant
    // or be externalized to database (v2 improvement)
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'new', 'official', 'original'];
    $words = explode(' ', $normalized);
    $words = array_filter($words, function($word) use ($stopWords) {
      return !in_array($word, $stopWords) && strlen($word) > 1;
    });

    return implode(' ', $words);
  }

  /**
   * Build error response structure
   *
   * @param string $errorMessage Error message
   * @param string $query Original query
   * @param float $startTime Start time for execution time calculation
   * @return array Error response structure
   */
  private function buildErrorResponse(string $errorMessage, string $query, float $startTime): array
  {
    if ($this->debug) {
      error_log('[GoogleShoppingEngine] Error: ' . $errorMessage);
    }

    return [
      'success' => false,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'metadata' => [
        'mode' => 'mode_b_google_shopping',
        'engine' => self::ENGINE_NAME,
        'error' => $errorMessage,
        'execution_time' => $startTime > 0 ? microtime(true) - $startTime : 0,
      ],
    ];
  }
}
