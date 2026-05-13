<?php
/**
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Tools;

use ClicShopping\AI\DomainsAI\WebSearch\Helper\SerpApiClient;
use ClicShopping\AI\DomainsAI\WebSearch\Logger\WebSearchLogger;
use ClicShopping\AI\InterfacesAI\WebSearchInterface;

/**
 * AmazonShoppingEngine - Mode B Executor
 *
 * Executes Google Shopping searches via SerpAPI with engine=google_shopping.
 * Provides structured price comparison with merchant data, pricing, and product links.
 *
 * Uses centralized SerpApiClient to avoid code duplication.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Executor
 */
class AmazonShoppingEngine implements WebSearchInterface
{
  private const ENGINE_NAME = 'amazon';
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
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

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
   * Check if target site is Amazon
   *
   * Detects Amazon domains (amazon.com, amazon.fr, amazon.co.uk, etc.)
   * to route to SerpAPI Amazon engine instead of traditional scraping.
   *
   * @param string|null $targetSite Target site from query
   * @return bool True if Amazon site
   */
  public function isAmazonSite(?string $targetSite): bool
  {
    if (empty($targetSite)) {
      return false;
    }

    // Normalize to lowercase
    $targetSite = strtolower(trim($targetSite));

    // Check for Amazon patterns
    $amazonPatterns = [
      'amazon',
      'amazon.com',
      'amazon.fr',
      'amazon.co.uk',
      'amazon.de',
      'amazon.es',
      'amazon.it',
      'amazon.ca',
      'amazon.com.mx',
      'amazon.co.jp',
      'amazon.in',
      'amazon.com.br',
      'amazon.com.au',
      'amazon.nl',
      'amazon.se',
      'amazon.pl',
      'amazon.sg',
      'amazon.ae',
      'amazon.sa',
    ];

    foreach ($amazonPatterns as $pattern) {
      if ($targetSite === $pattern || str_contains($targetSite, $pattern)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Map country code to Amazon domain
   *
   * @param string $countryCode ISO country code (e.g., 'fr', 'us', 'uk')
   * @return string|null Amazon domain or null
   */
  public function mapCountryToAmazonDomain(string $countryCode): ?string
  {
    $mapping = [
      'us' => 'amazon.com',
      'fr' => 'amazon.fr',
      'uk' => 'amazon.co.uk',
      'de' => 'amazon.de',
      'es' => 'amazon.es',
      'it' => 'amazon.it',
      'ca' => 'amazon.ca',
      'mx' => 'amazon.com.mx',
      'jp' => 'amazon.co.jp',
      'in' => 'amazon.in',
      'br' => 'amazon.com.br',
      'au' => 'amazon.com.au',
      'nl' => 'amazon.nl',
      'se' => 'amazon.se',
      'pl' => 'amazon.pl',
      'sg' => 'amazon.sg',
      'ae' => 'amazon.ae',
      'sa' => 'amazon.sa',
    ];

    $countryCode = strtolower($countryCode);

    return $mapping[$countryCode] ?? null;
  }
  /**
   * Execute a search query using Google Amazon engine
   *
   * @param string $query The search query string
   * @param array $options Optional parameters including:
   *                       - max_results: Maximum number of results (default: 20)
   *                       - location_params: Array with gl, hl, currency (geolocation, language, currency)
   * @return array Unified result structure with shopping_results
   */

  /**
   * Search Amazon using SerpAPI Amazon engine
   *
   * Uses engine=amazon for better results and to avoid Amazon blocking.
   * Falls back to traditional scraping if SerpAPI Amazon engine fails.
   *
   * @param string $query Search query
   * @param array $options Search options
   * @param float $startTime Start time for execution tracking
   * @return array Result structure
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

      if ($this->debug) {
        error_log(sprintf(
          '[AmazonShoppingEngine] Executing search - Query: %s, Params: %s',
          $query,
          json_encode($params)
        ));
      }

      // Execute search via centralized client
      // Note: SerpApiClient will use 'k' parameter for Amazon engine automatically
      $data = $this->client->search(self::ENGINE_NAME, $query, $params);

      // Handle search failure
      if ($data === false) {
        return $this->buildErrorResponse(
          'SerpAPI Amazon request failed',
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
          '[AmazonShoppingEngine] Search completed in %.3fs - Query: %s - Results: %d',
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
   * Amazon engine specific parameters:
   * - Does NOT support 'num' parameter (removed)
   * - Supports 'amazon_domain' for country-specific searches
   * - Supports standard location params (gl, hl)
   *
   * @param array $options Options array
   * @return array Parameters for SerpAPI
   */
  private function buildSearchParams(array $options): array
  {
    $params = [];

    // Note: Amazon engine does NOT support 'num' parameter
    // It returns a fixed number of results per page

    // Add location parameters if provided
    if (!empty($options['location_params'])) {
      $locationParams = $options['location_params'];

      // Map country code to Amazon domain
      if (!empty($locationParams['gl'])) {
        $amazonDomain = $this->mapCountryToAmazonDomain($locationParams['gl']);
        if ($amazonDomain) {
          $params['amazon_domain'] = $amazonDomain;
        }
      }

      if (!empty($locationParams['hl'])) {
        $params['hl'] = $locationParams['hl']; // Language
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
        error_log('[AmazonShoppingEngine] Configuration invalid: SerpAPI key not set');
      }
      return false;
    }

    // Basic API key format validation (SerpAPI keys are typically 64 hex characters)
    if (strlen($apiKey) < 32) {
      if ($this->debug) {
        error_log('[AmazonShoppingEngine] Configuration invalid: SerpAPI key too short');
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
   * Amazon API returns results in 'organic_results', not 'shopping_results'.
   * Each result contains: title, link, asin, price, extracted_price, rating, reviews, thumbnail, etc.
   *
   * @param array $data Decoded SerpAPI response
   * @return array Unified result structure
   */
  private function buildResultFromData(array $data): array
  {
    // 🔧 DEBUG (2026-05-10): Log response structure to diagnose parsing issues
    if ($this->debug) {
      error_log('[AmazonShoppingEngine::buildResultFromData] Response keys: ' . implode(', ', array_keys($data)));
      
      if (isset($data['search_parameters'])) {
        error_log('[AmazonShoppingEngine::buildResultFromData] Search parameters: ' . json_encode($data['search_parameters']));
      }
      
      if (isset($data['organic_results'])) {
        error_log('[AmazonShoppingEngine::buildResultFromData] organic_results count: ' . count($data['organic_results']));
      } else {
        error_log('[AmazonShoppingEngine::buildResultFromData] WARNING: No organic_results in response');
      }
      
      // Log error field if present
      if (isset($data['error'])) {
        error_log('[AmazonShoppingEngine::buildResultFromData] ERROR in response: ' . $data['error']);
      }
    }
    
    // Extract organic_results array (Amazon uses this instead of shopping_results)
    $shoppingResults = [];
    if (!empty($data['organic_results']) && is_array($data['organic_results'])) {
      foreach ($data['organic_results'] as $result) {
        $shoppingResults[] = $this->extractShoppingResult($result);
      }
    }

    // Deduplicate shopping results using normalized title + price hashing
    $shoppingResults = $this->deduplicateResults($shoppingResults);

    // Build unified result structure
    return [
      'success' => true,
      'query' => $data['search_parameters']['k'] ?? '', // Amazon uses 'k' parameter
      'ai_overview' => null, // Not supported by this engine
      'organic_results' => [], // Not supported by this engine
      'shopping_results' => $shoppingResults,
      'metadata' => [
        'mode' => 'mode_d_amazon_shopping',
        'engine' => self::ENGINE_NAME,
        'result_count' => count($shoppingResults),
        'original_count' => count($data['organic_results'] ?? []),
        'search_parameters' => $data['search_parameters'] ?? [],
      ],
    ];
  }

  /**
   * Extract shopping result from SerpAPI Amazon organic_results data
   *
   * Amazon API structure (from organic_results):
   * - position: Result position
   * - title: Product title
   * - link: Product URL
   * - asin: Amazon Standard Identification Number
   * - price: Formatted price string (e.g., "$1,299.99")
   * - extracted_price: Numeric price (e.g., 1299.99)
   * - rating: Product rating (e.g., 4.2)
   * - reviews: Number of reviews (e.g., 204)
   * - thumbnail: Product image URL
   * - bought_last_month: Purchase indicator (e.g., "200+ bought in past month")
   * - delivery: Array of delivery options
   * - stock: Stock status string
   *
   * Handles missing optional fields gracefully by setting them to null or empty string.
   * Preserves original position/ranking from SerpAPI.
   *
   * @param array $result Raw organic result from SerpAPI Amazon engine
   * @return array Normalized shopping result
   */
  private function extractShoppingResult(array $result): array
  {
    // Ensure extracted_price is float or null
    $extractedPrice = isset($result['extracted_price']) ? (float)$result['extracted_price'] : null;

    // Extract old price (may be float or null)
    $extractedOldPrice = isset($result['extracted_old_price']) ? (float)$result['extracted_old_price'] : null;

    // Extract rating (may be float or null)
    $rating = isset($result['rating']) ? (float)$result['rating'] : null;

    // Extract reviews (may be int or null)
    $reviews = isset($result['reviews']) ? (int)$result['reviews'] : null;


    // Prefer link_clean (clean product URL) over link (may be a sponsored tracking URL)
    $productLink = $result['link_clean'] ?? $result['link'] ?? '';

    return [
      'position' => $result['position'] ?? null,
      'title' => $result['title'] ?? '',
      'price' => $result['price'] ?? '',
      'extracted_price' => $extractedPrice,
      'old_price' => $result['old_price'] ?? null,
      'extracted_old_price' => $extractedOldPrice,
      'source' => 'Amazon',
      'link' => $productLink,
      'product_link' => $productLink, // Keep for backward compatibility
      'thumbnail' => $result['thumbnail'] ?? '',
      'rating' => $rating,
      'reviews' => $reviews,
      'asin' => $result['asin'] ?? null,
      'bought_last_month' => $result['bought_last_month'] ?? null,
      'delivery' => $result['delivery'] ?? null,
      'stock' => $result['stock'] ?? null,
      'data_source' => 'amazon', // 🔧 FIX (2026-05-10): Use 'amazon' for WebSearchFormatter badge detection
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
            '[AmazonShoppingEngine] Duplicate removed: %s (hash: %s)',
            $result['title'],
            $hash
          ));
        }
      }
    }

    if ($this->debug && count($results) !== count($deduplicated)) {
      error_log(sprintf(
        '[AmazonShoppingEngine] Deduplication: %d -> %d results (%d duplicates removed)',
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
      error_log('[AmazonShoppingEngine] Error: ' . $errorMessage);
    }

    return [
      'success' => false,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'metadata' => [
        'mode' => 'mode_d_amazon_shopping',
        'engine' => self::ENGINE_NAME,
        'error' => $errorMessage,
        'execution_time' => $startTime > 0 ? microtime(true) - $startTime : 0,
      ],
    ];
  }
}
