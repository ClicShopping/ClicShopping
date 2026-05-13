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
 * GoogleAIOverviewEngine - Mode A Executor
 *
 * Executes AI Overview searches via SerpAPI with engine=google.
 * Provides AI-synthesized product research for broad market understanding.
 *
 * Note: Changed from 'google_ai_overview' to 'google' engine because:
 * - 'google_ai_overview' requires a page_token from a previous search (two-step process)
 * - 'google' engine includes ai_overview data directly when available
 * - Simpler, more reliable, and avoids the page_token requirement
 *
 * Uses centralized SerpApiClient to avoid code duplication.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Executor
 */
class GoogleAIOverviewEngine implements WebSearchInterface
{
  private const ENGINE_NAME = 'google';
  private const DEFAULT_MAX_RESULTS = 10;

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
   * Execute a search query using Google AI Overview engine
   *
   * @param string $query The search query string
   * @param array $options Optional parameters including:
   *                       - max_results: Maximum number of results (default: 10)
   *                       - location_params: Array with gl, hl (geolocation, language)
   * @return array Unified result structure with ai_overview and organic_results
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
          '[GoogleAIOverviewEngine] Search completed in %.3fs - Query: %s',
          $result['metadata']['execution_time'],
          $query
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
    $maxResults = $options['max_results'] 
      ?? (defined('CLICSHOPPING_APP_AI_WEBSEARCH_OVERVIEW_MAX_RESULTS') 
          ? (int)CLICSHOPPING_APP_AI_WEBSEARCH_OVERVIEW_MAX_RESULTS 
          : self::DEFAULT_MAX_RESULTS);
    $params['num'] = max(5, min(50, $maxResults)); // Clamp to range 5-50

    // Add location parameters if provided
    if (!empty($options['location_params'])) {
      $locationParams = $options['location_params'];
      
      if (!empty($locationParams['gl'])) {
        $params['gl'] = $locationParams['gl']; // Geolocation (country code)
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
      'shopping_results' => false,
      'ai_overview' => true,
      'organic_results' => true,
      'targeted_scraping' => false,
    ];
  }

  /**
   * Validate the engine configuration
   *
   * Validates that CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI is set and properly formatted.
   *
   * Requirements: 19.2
   *
   * @return bool True if configuration is valid
   */
  public function validateConfig(): bool
  {
    $apiKey = $this->loadApiKey();
    
    if (empty($apiKey)) {
      if ($this->debug) {
        error_log('[GoogleAIOverviewEngine] Configuration invalid: SerpAPI key not set');
      }
      return false;
    }

    // Basic API key format validation (SerpAPI keys are typically 64 hex characters)
    if (strlen($apiKey) < 32) {
      if ($this->debug) {
        error_log('[GoogleAIOverviewEngine] Configuration invalid: SerpAPI key too short');
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
      'cost_per_request' => 0.01, // Estimated cost per API call in USD
      'avg_latency' => 1500.0,    // Average response time in milliseconds
      'quality_score' => 0.85,    // Engine quality rating (0.0-1.0)
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
    // Extract ai_overview field (may be null or missing)
    $aiOverview = $data['ai_overview'] ?? null;

    // Extract organic_results for supplementary information
    $organicResults = [];
    if (!empty($data['organic_results']) && is_array($data['organic_results'])) {
      foreach ($data['organic_results'] as $result) {
        $organicResults[] = [
          'position' => $result['position'] ?? null,
          'title' => $result['title'] ?? '',
          'link' => $result['link'] ?? '',
          'snippet' => $result['snippet'] ?? '',
          'source' => $result['source'] ?? '',
        ];
      }
    }

    // Build unified result structure
    return [
      'success' => true,
      'query' => $data['search_parameters']['q'] ?? '',
      'ai_overview' => $aiOverview,
      'organic_results' => $organicResults,
      'shopping_results' => [], // Not supported by this engine
      'metadata' => [
        'mode' => 'mode_a_ai_overview',
        'engine' => self::ENGINE_NAME,
        'result_count' => count($organicResults),
        'has_ai_overview' => !empty($aiOverview),
        'search_parameters' => $data['search_parameters'] ?? [],
      ],
    ];
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
      error_log('[GoogleAIOverviewEngine] Error: ' . $errorMessage);
    }

    return [
      'success' => false,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'metadata' => [
        'mode' => 'mode_a_ai_overview',
        'engine' => self::ENGINE_NAME,
        'error' => $errorMessage,
        'execution_time' => $startTime > 0 ? microtime(true) - $startTime : 0,
      ],
    ];
  }
}
