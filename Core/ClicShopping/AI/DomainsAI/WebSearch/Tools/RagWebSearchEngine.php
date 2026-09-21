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
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\InterfacesAI\BatchableWebSearchInterface;
use ClicShopping\AI\InterfacesAI\WebSearchInterface;
use ClicShopping\OM\CLICSHOPPING;


/**
 * RagWebSearchEngine - Mode C Executor
 *
 * Executes targeted searches on specific competitor sites configured in rag_websearch table.
 * Provides deep product extraction from configured e-commerce sites using site-specific patterns.
 *
 * Uses centralized SerpApiClient to avoid code duplication.
 * Queries rag_websearch table via Doctrine ORM (agnostic layer requirement).
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Executor
 */
class RagWebSearchEngine implements WebSearchInterface, BatchableWebSearchInterface
{
  private const ENGINE_NAME = 'rag_websearch';
  private const DEFAULT_MAX_RESULTS_PER_SITE = 5;
  private const SERPAPI_ENGINE = 'google'; // Use standard Google search with site: operator
  private SerpApiClient $client;
  private WebSearchLogger $logger;
  private bool $debug;
  private string $prefixDb;
  /** @var array<string,array> Site row per batch key, so the return leg keeps its patterns */
  private array $batchSites = [];
  
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
    $this->prefixDb = CLICSHOPPING::getConfig('db_table_prefix');
  }

  /**
   * Load SerpAPI key from configuration
   *
   * @return string API key
   */
  private function loadApiKey(): string
  {
    if (defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI') && !empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI)) {
      return trim(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI);
    }

    return '';
  }

  /**
   * Execute a search query using RAG WebSearch engine
   *
   * Queries rag_websearch table for active sites, then executes
   * site-specific searches via SerpAPI using site:domain.com operator.
   *
   * @param string $query The search query string
   * @param array $options Optional parameters including:
   *                       - max_results_per_site: Maximum results per site (default: 5)
   *                       - location_params: Array with gl, hl (geolocation, language)
   *                       - target_site: Specific site to search (optional filter)
   * @return array Unified result structure with rag_results array
   */
  public function search(string $query, array $options = []): array
  {
    $startTime = microtime(true);

    $requests = $this->prepareBatchRequests($query, $options);

    if ($requests === []) {
      return $this->buildErrorResponse($this->lastPreparationError(), $query, $startTime);
    }

    // One round for every site: this engine's own worst case is its slowest site, not their sum.
    $result = $this->buildResultFromBatch($this->client->runBatch($requests), $query, $options);
    $result['metadata']['execution_time'] = microtime(true) - $startTime;

    return $result;
  }

  /**
   * Declare one call per active site so they all run in the same round.
   *
   * The site row is kept in PHP under the same key; nothing about it travels through the URL.
   *
   * @param string $query Search query
   * @param array $options Options array with optional target_site filter
   * @return array<string,array> One request per site domain
   */
  public function prepareBatchRequests(string $query, array $options = []): array
  {
    $this->batchSites = [];

    if (!$this->validateConfig()) {
      return [];
    }

    $activeSites = $this->getActiveSites($options);

    if (empty($activeSites)) {
      return [];
    }

    $params = $this->buildSearchParams($options);
    $requests = [];

    foreach ($activeSites as $site) {
      $key = (string)$site['site_domain'];
      $request = $this->client->buildHttpRequest(
        self::SERPAPI_ENGINE,
        $query . ' site:' . $site['site_domain'],
        $params,
        $key
      );

      if ($request === false) {
        continue;
      }

      $requests[$key] = $request;
      $this->batchSites[$key] = $site;
    }

    if ($this->debug) {
      error_log(sprintf('[RagWebSearchEngine] Declared %d site searches', count($requests)));
    }

    return $requests;
  }

  /**
   * Build the unified result from the responses of the declared site searches.
   *
   * @param array<string,string|false> $responses Raw body per site domain
   * @param string $query Search query
   * @param array $options Unused, the extraction reads its patterns from the site row
   * @return array Same structure search() returns
   */
  public function buildResultFromBatch(array $responses, string $query, array $options = []): array
  {
    $startTime = microtime(true);
    $ragResults = [];
    $totalResults = 0;
    $totalPricesExtracted = 0;

    foreach ($responses as $key => $body) {
      $site = $this->batchSites[$key] ?? null;

      if ($site === null) {
        continue;
      }

      $siteResults = $this->extractSiteResults($body, $site, (string)$key);

      if (empty($siteResults)) {
        continue;
      }

      $pricesFound = count(array_filter($siteResults, fn($r) => $r['extracted_price'] !== null));
      $totalPricesExtracted += $pricesFound;

      $ragResults[] = [
        'site' => $site['site_domain'],
        'site_name' => $site['site_domain'],
        'results' => $siteResults,
        'result_count' => count($siteResults),
        'prices_extracted' => $pricesFound,
      ];

      $totalResults += count($siteResults);

      $this->logScrapingActivity($query, $site, count($siteResults));
    }

    $siteCount = count($this->batchSites);

    $result = [
      'success' => true,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'rag_results' => $ragResults,
      'metadata' => [
        'mode' => 'mode_c_rag_websearch',
        'engine' => self::ENGINE_NAME,
        'execution_method' => 'db_scraping',
        'sites_searched' => $siteCount,
        'total_results' => $totalResults,
        'prices_extracted' => $totalPricesExtracted,
        'execution_time' => microtime(true) - $startTime,
      ],
    ];

    $siteNames = implode(', ', array_column($this->batchSites, 'site_domain'));

    if ($totalResults === 0) {
      // Case 1: site was searched but returned no organic results at all
      $result['metadata']['user_notification'] = [
        'type' => 'warning',
        'message' => sprintf(
          'No results were found on %s for "%s". Showing Google Shopping results as alternative.',
          $siteNames,
          $query
        ),
        'fallback' => 'google_shopping',
      ];

      if ($this->debug) {
        error_log(sprintf('[RagWebSearchEngine] No results scraped from %s — notifying user', $siteNames));
      }
    } elseif ($totalPricesExtracted === 0) {
      // Case 2: pages found but the price regex did not match — scraping pattern may be outdated
      $result['metadata']['user_notification'] = [
        'type' => 'info',
        'message' => sprintf(
          'Products were found on %s but prices could not be extracted (scraping pattern may need updating). Prices from Google Shopping are shown as alternative.',
          $siteNames
        ),
        'fallback' => 'google_shopping',
      ];

      if ($this->debug) {
        error_log(sprintf('[RagWebSearchEngine] %d results from %s but 0 prices extracted — pattern mismatch', $totalResults, $siteNames));
      }
    }

    if ($this->debug) {
      error_log(sprintf(
        '[RagWebSearchEngine] Search completed in %.3fs - Query: %s - Sites: %d - Results: %d',
        $result['metadata']['execution_time'],
        $query,
        $siteCount,
        $totalResults
      ));
    }

    return $result;
  }

  /**
   * Why no call could be declared — the three causes must not collapse into one message.
   *
   * @return string Reason, phrased for the user-facing error response
   */
  private function lastPreparationError(): string
  {
    if (!$this->validateConfig()) {
      return 'Configuration validation failed: No active sites configured or SerpAPI key missing';
    }

    return 'No active competitor sites found in ' . $this->prefixDb . 'rag_websearch table';
  }

  /**
   * Get active competitor sites from rag_websearch table
   *
   * Queries via Doctrine ORM (agnostic layer requirement).
   * Filters by status = 1 (active) and optional target_site.
   *
   * @param array $options Options array with optional target_site filter
   * @return array Active sites array
   */
  private function getActiveSites(array $options): array
  {
    try {
      $tableName = $this->prefixDb . 'rag_websearch';

      // Build query — only columns that exist in rag_websearch
      $sql = "SELECT id,
                     site_domain,
                     search_pattern,
                     status
              FROM {$tableName}
              WHERE status = 1";

      $params = [];

      // Optional target_site filter: use LIKE when no TLD (e.g. "<site>" → "<site>.com")
      if (!empty($options['target_site'])) {
        $targetSite = $options['target_site'];
        if ($this->hasTLD($targetSite)) {
          $sql .= " AND site_domain = :target_site";
          $params['target_site'] = $targetSite;
        } else {
          $sql .= " AND site_domain LIKE :target_site";
          $params['target_site'] = $targetSite . '%';
        }
      }

      $sql .= " ORDER BY site_domain ASC";

      // Execute query via Doctrine ORM
      $sites = DoctrineOrm::select($sql, $params);

      if ($this->debug) {
        error_log(sprintf(
          '[RagWebSearchEngine] Query: %s, Params: %s, Results: %d',
          $sql,
          json_encode($params),
          count($sites)
        ));
      }

      return $sites;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log('[RagWebSearchEngine] Error querying active sites: ' . $e->getMessage());
      }

      $this->logger->logError('Error querying ' . $this->prefixDb . 'rag_websearch table: ' . $e->getMessage(), [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return [];
    }
  }

  /**
   * Turn one site's raw response into its extracted products.
   *
   * @param string|false $body Raw body for that site, false when the call failed
   * @param array $site Site configuration with search_pattern
   * @param string $key Batch key, so the failure cause can be read back
   * @return array Extracted products, empty when nothing usable came back
   */
  private function extractSiteResults(string|false $body, array $site, string $key): array
  {
    try {
      $data = $this->client->decodeResponse(self::SERPAPI_ENGINE, $body, $key);

      if ($data === false) {
        if ($this->debug) {
          error_log(sprintf(
            '[RagWebSearchEngine] SerpAPI request failed for site %s: %s',
            $site['site_domain'],
            $this->client->lastError($key)
          ));
        }

        return [];
      }

      $organicResults = $data['organic_results'] ?? [];

      if (empty($organicResults)) {
        if ($this->debug) {
          error_log(sprintf('[RagWebSearchEngine] No results found for site: %s', $site['site_domain']));
        }

        return [];
      }

      $extractedResults = [];

      foreach ($organicResults as $result) {
        $extracted = $this->extractProductInfo($result, $site);

        if ($extracted !== null) {
          $extractedResults[] = $extracted;
        }
      }

      if ($this->debug) {
        error_log(sprintf(
          '[RagWebSearchEngine] Site: %s - Raw results: %d - Extracted: %d',
          $site['site_domain'],
          count($organicResults),
          count($extractedResults)
        ));
      }

      return $extractedResults;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log(sprintf(
          '[RagWebSearchEngine] Error extracting site %s: %s',
          $site['site_domain'],
          $e->getMessage()
        ));
      }

      return [];
    }
  }

  /**
   * Extract product information from organic result using site-specific patterns
   *
   * Uses search_pattern column from rag_websearch table to extract
   * product data (title, price, URL) from organic search results.
   *
   * @param array $result Organic result from SerpAPI
   * @param array $site Site configuration with search_pattern
   * @return array|null Extracted product info or null if extraction fails
   */
  private function extractProductInfo(array $result, array $site): ?array
  {
    // Basic extraction (always available)
    $extracted = [
      'position' => $result['position'] ?? null,
      'title' => $result['title'] ?? '',
      'link' => $result['link'] ?? '',
      'snippet' => $result['snippet'] ?? '',
      'source' => $site['site_domain'],
      'price' => null,
      'extracted_price' => null,
      'data_source' => 'rag_websearch',
      'engine_type' => self::ENGINE_NAME,
    ];

    // Apply site-specific pattern if available
    if (!empty($site['search_pattern'])) {
      $pattern = json_decode($site['search_pattern'], true);

      if ($pattern !== null && is_array($pattern)) {
        // Extract price using pattern
        if (!empty($pattern['price_regex'])) {
          $priceMatch = $this->extractPriceWithPattern(
            $result['snippet'] ?? '',
            $pattern['price_regex']
          );

          if ($priceMatch !== null) {
            $extracted['price'] = $priceMatch['formatted'];
            $extracted['extracted_price'] = $priceMatch['numeric'];
          }
        }

        // Extract additional fields if pattern specifies
        if (!empty($pattern['title_selector'])) {
          // Note: title_selector would require HTML parsing
          // For now, we use the title from SerpAPI organic result
          // Future enhancement: fetch and parse HTML if needed
        }
      }
    }

    // Fallback: attempt generic price extraction from multiple SerpAPI fields.
    if ($extracted['extracted_price'] === null) {
      $candidates = [
        $result['snippet']                            ?? '',
        $result['title']                              ?? '',
        $result['rich_snippet']['top']['detected_extensions']['price'] ?? '',
        is_array($result['rich_snippet']['top']['extensions'] ?? null)
          ? implode(' ', $result['rich_snippet']['top']['extensions'])
          : '',
        is_array($result['rich_snippet']['extensions'] ?? null)
          ? implode(' ', $result['rich_snippet']['extensions'])
          : '',
        is_array($result['extensions'] ?? null)
          ? implode(' ', $result['extensions'])
          : '',
      ];

      foreach ($candidates as $text) {
        if (!is_string($text) || trim($text) === '') {
          continue;
        }
        $priceMatch = $this->extractPriceGeneric($text);
        if ($priceMatch !== null) {
          $extracted['price'] = $priceMatch['formatted'];
          $extracted['extracted_price'] = $priceMatch['numeric'];
          break;
        }
      }
    }

    return $extracted;
  }

  /**
   * Extract price using site-specific regex pattern
   *
   * @param string $text Text to search
   * @param string $pattern Regex pattern
   * @return array|null Price data or null
   */
  private function extractPriceWithPattern(string $text, string $pattern): ?array
  {
    if (preg_match($pattern, $text, $matches)) {
      // Assume first capture group is the numeric price
      $numericPrice = isset($matches[1]) ? (float)str_replace(',', '.', $matches[1]) : null;

      if ($numericPrice !== null) {
        return [
          'formatted' => $matches[0],
          'numeric' => $numericPrice,
        ];
      }
    }

    return null;
  }

  /**
   * Extract price using generic patterns
   *
   * Fallback method when site-specific pattern is not available.
   * Attempts to match common price formats (EUR, USD, GBP, etc.).
   *
   * @param string $text Text to search
   * @return array|null Price data or null
   */
  private function extractPriceGeneric(string $text): ?array
  {
    if ($text === '') {
      return null;
    }

    // Patterns ordered from most specific (best precision) to most permissive.
    $patterns = [
      // -- EUR --
      // 1 099,99 € / 1.099,99 € / 1 099.99 € (thousand sep + 2 decimals + €)
      '/(\d{1,3}(?:[\s\.\x{00A0}\x{202F}]\d{3})+)[,.](\d{2})\s*(?:€|EUR(?!\w))/iu',
      // 1 099 € / 1.099 € (thousand sep, no decimals, + €)
      '/(\d{1,3}(?:[\s\.\x{00A0}\x{202F}]\d{3})+)\s*(?:€|EUR(?!\w))/iu',
      // 899,99 € / 899.99 € (no thousand sep, 2 decimals, + €)
      '/(\d{2,5})[,.](\d{2})\s*(?:€|EUR(?!\w))/iu',
      // 899 € / 899€ / 899 EUR (no thousand sep, no decimals, + €)
      '/(\d{2,6})\s*(?:€|EUR(?!\w))/iu',
      // € 899,99 / € 899 (currency BEFORE amount)
      '/(?:€|EUR)\s*(\d{1,3}(?:[\s\.\x{00A0}\x{202F}]\d{3})+)(?:[,.](\d{2}))?\b/iu',
      '/(?:€|EUR)\s*(\d{2,6})(?:[,.](\d{2}))?\b/iu',

      // -- USD --
      '/\$\s*(\d{1,3}(?:,\d{3})+)(?:\.(\d{2}))?\b/i',     // $1,099.99 / $1,099
      '/\$\s*(\d{2,6})(?:\.(\d{2}))?\b/i',                 // $899.99 / $899
      '/(\d{2,6})(?:\.(\d{2}))?\s*USD\b/i',

      // -- GBP --
      '/£\s*(\d{1,3}(?:,\d{3})+)(?:\.(\d{2}))?\b/i',
      '/£\s*(\d{2,6})(?:\.(\d{2}))?\b/i',
      '/(\d{2,6})(?:\.(\d{2}))?\s*GBP\b/i',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $text, $matches)) {
        // Strip every kind of thousand separator from the integer part,
        $integerPart = preg_replace('/[\s\.\x{00A0}\x{202F},]/u', '', $matches[1]);
        $decimalPart = $matches[2] ?? '';

        $normalized  = $decimalPart !== ''
          ? $integerPart . '.' . $decimalPart
          : $integerPart;

        $numericPrice = (float) $normalized;

        // Sanity guard: prices outside [1, 1_000_000] are almost certainly
        // false positives (product IDs, SKUs, years, screen sizes, ...).
        if ($numericPrice < 1.0 || $numericPrice > 1_000_000) {
          continue;
        }

        return [
          'formatted' => trim($matches[0]),
          'numeric'   => $numericPrice,
        ];
      }
    }

    return null;
  }

  /**
   * Check if a domain string includes a TLD
   *
   * @param string $domain Domain string to check
   * @return bool True if domain has a TLD
   */
  private function hasTLD(string $domain): bool
  {
    $commonTLDs = [
      'com', 'fr', 'de', 'uk', 'es', 'it', 'nl', 'be', 'ch', 'ca', 'au', 'jp', 'cn', 'in', 'br', 'mx',
      'ru', 'pl', 'se', 'no', 'dk', 'fi', 'at', 'ie', 'pt', 'gr', 'cz', 'hu', 'ro', 'bg', 'hr', 'sk',
      'si', 'ee', 'lv', 'lt', 'lu', 'mt', 'cy', 'co.uk', 'co.jp', 'co.nz', 'co.za', 'com.au', 'com.br',
    ];

    foreach ($commonTLDs as $tld) {
      if (preg_match('/\.' . preg_quote($tld, '/') . '$/i', $domain)) {
        return true;
      }
    }

    return false;
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

    // Add max results per site (num parameter)
    $maxResults = $options['max_results_per_site'] ?? (defined('CLICSHOPPING_APP_CHATGPT_WEB_RAG_MAX_RESULTS_PER_SITE') ? (int)CLICSHOPPING_APP_CHATGPT_WEB_RAG_MAX_RESULTS_PER_SITE : self::DEFAULT_MAX_RESULTS_PER_SITE);
    $params['num'] = max(3, min(20, $maxResults)); // Clamp to range 3-20

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
   * Log scraping activity to rag_web_search_requests table
   *
   * Logs each site search for audit trail and monitoring.
   *
   * @param string $query Original query
   * @param array $site Site configuration
   * @param int $resultCount Number of results extracted
   */
  private function logScrapingActivity(string $query, array $site, int $resultCount): void
  {
    try {
      // Note: WebSearchLogger expects a specific format
      // For now, we log via error_log for audit trail
      // Future enhancement: extend WebSearchLogger to support Mode C logging

      if ($this->debug) {
        error_log(sprintf(
          '[RagWebSearchEngine] Scraping activity - Query: %s, Site: %s, Results: %d',
          $query,
          $site['site_domain'],
          $resultCount
        ));
      }

      // TODO: Implement proper logging to rag_web_search_requests
      // This requires extending WebSearchLogger or creating a dedicated method
      // for Mode C scraping activity logging

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log('[RagWebSearchEngine] Error logging scraping activity: ' . $e->getMessage());
      }
    }
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
   * Validates:
   * 1. SerpAPI key is configured
   * 2. rag_websearch table exists
   * 3. At least one active site (status = 1) is configured
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
      'ai_overview' => false,
      'organic_results' => true,
      'targeted_scraping' => true,
    ];
  }

  /**
   * Validate the engine configuration
   *
   * Checks:
   * 1. SerpAPI key is set and valid
   * 2. rag_websearch table exists
   * 3. At least one active site is configured
   *
   * Requirements: 19.4
   *
   * @return bool True if configuration is valid
   */
  public function validateConfig(): bool
  {
    // Check API key
    $apiKey = $this->loadApiKey();

    if (empty($apiKey)) {
      if ($this->debug) {
        error_log('[RagWebSearchEngine] Configuration invalid: SerpAPI key not set');
      }
      return false;
    }

    if (strlen($apiKey) < 32) {
      if ($this->debug) {
        error_log('[RagWebSearchEngine] Configuration invalid: SerpAPI key too short');
      }
      return false;
    }

    // Check if rag_websearch table exists and has active sites
    try {
      $tableName = $this->prefixDb . 'rag_websearch';

      $sql = "SELECT COUNT(*) as count 
              FROM {$tableName} 
              WHERE status = 1";

      $result = DoctrineOrm::selectOne($sql);

      if (!$result || $result['count'] == 0) {
        if ($this->debug) {
          error_log('[RagWebSearchEngine] Configuration invalid: No active sites in ' . $this->prefixDb . 'rag_websearch');
        }
        return false;
      }

      if ($this->debug) {
        error_log(sprintf(
          '[RagWebSearchEngine] Configuration valid: %d active sites found',
          $result['count']
        ));
      }

      return true;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log('[RagWebSearchEngine] Configuration validation error: ' . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Get engine metadata for monitoring and cost tracking
   *
   * @return array Metadata structure
   */
  public function getMetadata(): array
  {
    return [
      'cost_per_request' => 0.02, // Estimated cost per API call (higher due to multiple site searches)
      'avg_latency' => 3000.0,    // Average response time in milliseconds (higher due to multiple requests)
      'quality_score' => 0.75,    // Engine quality rating (0.0-1.0) - depends on site-specific patterns
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
      error_log('[RagWebSearchEngine] Error: ' . $errorMessage);
    }

    return [
      'success' => false,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'rag_results' => [],
      'metadata' => [
        'mode' => 'mode_c_rag_websearch',
        'engine' => self::ENGINE_NAME,
        'error' => $errorMessage,
        'execution_time' => $startTime > 0 ? microtime(true) - $startTime : 0,
      ],
    ];
  }
}
