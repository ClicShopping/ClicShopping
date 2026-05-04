<?php
  /**
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @Info : https://www.clicshopping.org/forum/trademark/
   */

  namespace ClicShopping\AI\DomainsAI\WebSearch\Tool;

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\HTTP;
  use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\IntentAnalyzer;
  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\AI\Infrastructure\Cache\Cache;
  use ClicShopping\AI\DomainsAI\WebSearch\Cache\SearchCacheManager;
  use ClicShopping\AI\Security\SecurityLogger;
  use ClicShopping\AI\DomainsAI\WebSearch\Logger\WebSearchLogger;
  use ClicShopping\AI\InterfacesAI\EntityHelperInterface;


  /**
   * WebSearchTool Class
   *
   * Intelligent wrapper for SerpApi API with:
   * - Cache management (cost reduction)
   * - Rate limiting (API protection)
   * - Result formatting
   * - Integration with SearchCacheManager (Learning RAG)
   * - Multi-engine support (Google, Bing, DuckDuckGo)
   * - Google AI Overview support (always enabled)
   *
   * When an EntityHelper is provided, it will be used for product lookups instead of
   * direct SQL queries. This makes WebSearchTool work with any domain (Ecommerce, HR, etc.)
   * while maintaining backward compatibility with direct SQL fallback.
   */
  class WebSearchTool
  {
    private string $apiKey;
    private SecurityLogger $logger;
    private Cache $cache;
    private ?object $processor ;
    private SearchCacheManager $cacheManager;
    private WebSearchLogger $searchLogger;
    private bool $debug;
    private mixed $db;
    private mixed $language;
    private ?EntityHelperInterface $entityHelper = null;

    // Configuration
    private int $maxResults = 20;
    private int $rateLimitPerHour = 100;
    private int $cacheExpiration = 86400; // 24h
    private string $defaultEngine = 'google';
    private int $requestTimeout = 30;

    // Statistics
    private array $stats = [
      'total_requests' => 0,
      'cache_hits' => 0,
      'cache_hits_short_term' => 0,
      'cache_hits_rag_learning' => 0,
      'api_calls' => 0,
      'errors' => 0,
      'api_cost_saved' => 0.0,
      'api_cost_spent' => 0.0,
    ];

    /**
     * Constructor
     *
     * @param EntityHelperInterface|null $entityHelper Optional entity helper for domain-specific product lookups
     * @throws \RuntimeException If API key is not configured
     */
    public function __construct(?EntityHelperInterface $entityHelper = null)
    {
      $key = Gpt::getSerpApiKey();
      $this->apiKey = $key;
      

      if (empty($this->apiKey)) {
         error_log('SerpApi key not configured. Set CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI in configuration.');
      }

      $this->logger = new SecurityLogger();
      $this->cache = new Cache(true);
      $this->processor = null;
      $this->cacheManager = new SearchCacheManager('rag_web_cache_embedding');
      $this->db = Registry::get('Db');

      try {
        $this->language = Registry::get('Language');
      } catch (\Exception $e) {
        $this->language = null;
      }

      $this->searchLogger = new WebSearchLogger();
      $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
      $this->entityHelper = $entityHelper;

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "WebSearchTool initialized with engine: {$this->defaultEngine} (key: " . substr($this->apiKey, 0, 10) . "...)" .
          ($this->entityHelper !== null ? " with EntityHelper" : " without EntityHelper"),
          'info'
        );
      }
    }

    /**
     * Get comprehensive cache statistics including both memory and DB cache
     */
    public function getComprehensiveCacheStats(): array
    {
      $stats = $this->getStats();
      $ragStats = $this->cacheManager->getCacheStats();

      return [
        'memory_cache' => [
          'total_requests' => $stats['total_requests'],
          'cache_hits' => $stats['cache_hits'],
          'cache_hit_rate' => $stats['cache_hit_rate'],
          'short_term_hits' => $stats['cache_hits_short_term'],
          'rag_learning_hits' => $stats['cache_hits_rag_learning'],
        ],
        'rag_learning_cache' => $ragStats,
        'api_costs' => [
          'total_spent' => $stats['api_cost_spent'],
          'total_saved' => $stats['api_cost_saved'],
          'total_cost' => $stats['total_api_cost'],
          'savings_rate' => $stats['cost_savings_rate'],
        ],
        'performance' => [
          'api_calls' => $stats['api_calls'],
          'errors' => $stats['errors'],
          'error_rate' => $stats['error_rate'],
        ],
      ];
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
      $cacheHitRate = $this->stats['total_requests'] > 0 ? ($this->stats['cache_hits'] / $this->stats['total_requests']) * 100 : 0;
      $totalApiCost = $this->stats['api_cost_saved'] + $this->stats['api_cost_spent'];
      $costSavingsRate = $totalApiCost > 0 ? ($this->stats['api_cost_saved'] / $totalApiCost) * 100 : 0;

      return array_merge($this->stats, [
        'cache_hit_rate' => round($cacheHitRate, 2) . '%',
        'api_call_rate' => ($this->stats['total_requests'] - $this->stats['cache_hits']),
        'error_rate' => $this->stats['total_requests'] > 0 ? round(($this->stats['errors'] / $this->stats['total_requests']) * 100, 2) . '%' : '0%',
        'total_api_cost' => round($totalApiCost, 4),
        'cost_savings_rate' => round($costSavingsRate, 2) . '%',
        'cache_breakdown' => [
          'short_term' => $this->stats['cache_hits_short_term'],
          'rag_learning' => $this->stats['cache_hits_rag_learning'],
        ],
      ]);
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
      $this->stats = [
        'total_requests' => 0,
        'cache_hits' => 0,
        'cache_hits_short_term' => 0,
        'cache_hits_rag_learning' => 0,
        'api_calls' => 0,
        'errors' => 0,
        'api_cost_saved' => 0.0,
        'api_cost_spent' => 0.0,
      ];
    }

    /**
     * Set default search engine
     */
    public function setDefaultEngine(string $engine): void
    {
      $allowed = ['google', 'bing', 'duckduckgo', 'yahoo'];

      if (!in_array($engine, $allowed, true)) {
        throw new \InvalidArgumentException("Engine must be one of: " . implode(', ', $allowed));
      }

      $this->defaultEngine = $engine;
    }

    /**
     * Set max results
     */
    public function setMaxResults(int $max): void
    {
      $this->maxResults = max(1, min(20, $max));
    }

    /**
     * Set rate limit per hour
     */
    public function setRateLimitPerHour(int $limit): void
    {
      $this->rateLimitPerHour = max(10, $limit);
    }

    /**
     * Helper for price comparison logic using AI Overview insights
     */
    public function comparePriceWithAI(string $productName): array
    {
      $query = "What is the current market price for {$productName} and how does it compare to competitors?";

      $results = $this->search($query, [
        'gl' => 'us'
      ]);

      if ($results['metadata']['has_ai_overview']) {
        return [
          'summary' => $results['ai_overview']['full_summary'],
          'sources' => $results['ai_overview']['sources']
        ];
      }

      return ['error' => 'AI Overview not available for this comparison'];
    }

    /**
     * Main entry point: Intelligent web search
     *
     * Workflow:
     * 1. Check short-term cache (Cache.php)
     * 2. Check RAG learning cache (SearchCacheManager)
     * 3. If cache miss: Call SerpApi
     * 4. Format results
     * 5. Cache + Log + Store in RAG if high quality
     *
     * @param string $query Search query
     * @param array $options Options (max_results, engine, location, etc.)
     * @return array Formatted results with metadata
     */
    public function search(string $query, array $options = []): array
    {
      $startTime = microtime(true);
      $this->stats['total_requests']++;

      try {
        // Validation
        if (empty(trim($query))) {
          throw new \InvalidArgumentException('Query cannot be empty');
        }

        if (strlen($query) > 500) {
          throw new \InvalidArgumentException('Query too long (max 500 characters)');
        }

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Web search request: {$query}",
            'info'
          );
        }

        // STEP 1: Check short-term cache (24h)
        $cacheKey = $this->generateCacheKey($query, $options);
        $cached = $this->cache->getCachedResponse($cacheKey);

        if ($cached !== null) {
          $this->stats['cache_hits']++;
          $this->stats['cache_hits_short_term']++;

          $apiCostSaved = $this->estimateApiCost([]);
          $this->stats['api_cost_saved'] += $apiCostSaved;

          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "✅ Cache hit (short-term): {$query} - API cost saved: \${$apiCostSaved}",
              'info'
            );
          }

          $result = json_decode($cached, true);
          $result['cached'] = true;
          $result['cache_source'] = 'short_term';
          $result['execution_time'] = microtime(true) - $startTime;
          $result['api_cost_saved'] = $apiCostSaved;

          return $result;
        }

        // STEP 2: Check RAG learning cache
        $ragCached = $this->cacheManager->searchInCache($query, 3);

        if ($ragCached !== null && !empty($ragCached)) {
          $result = $this->convertRagCacheToStandardFormat($ragCached);

          if (!empty($result['items'])) {
            $this->stats['cache_hits']++;
            $this->stats['cache_hits_rag_learning']++;

            $apiCostSaved = $this->estimateApiCost([]);
            $this->stats['api_cost_saved'] += $apiCostSaved;

            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "✅ Cache hit (RAG learning): {$query} with " . count($result['items']) . " items - API cost saved: \${$apiCostSaved}",
                'info'
              );
            }

            $result['cached'] = true;
            $result['cache_source'] = 'rag_learning';
            $result['execution_time'] = microtime(true) - $startTime;
            $result['api_cost_saved'] = $apiCostSaved;

            return $result;
          } else {
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Cache found but empty, will call API: {$query}",
                'info'
              );
            }
          }
        }

        // STEP 3: Cache miss → API call
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "⚠️ Cache miss, calling SerpApi for: {$query}",
            'info'
          );
        }

        // Check rate limiting
        $this->checkRateLimit();

        // Merge default options
        $options = array_merge([
          'engine' => 'google',
          'google_domain' => 'google.com',
          'gl' => 'us',
          'hl' => 'en',
          'num' => 10,
        ], $options);

        // Call SerpApi
        $rawResults = $this->callSerpApi($query, $options);
        $this->stats['api_calls']++;

        $apiCost = $this->estimateApiCost($rawResults);
        $this->stats['api_cost_spent'] += $apiCost;

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "💰 SerpAPI call completed - Cost: \${$apiCost}",
            'info'
          );
        }

        // STEP 4: Process and format results
        $processed = $this->processResults($rawResults, $query, $options);

        // STEP 4b: Regional fallback for AI Overview
        if (empty($processed['ai_overview']) && $options['gl'] !== 'us') {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "No AI Overview for region {$options['gl']}, retrying with 'us'",
              'info'
            );
          }

          $fallbackOptions = $options;
          $fallbackOptions['gl'] = 'us';
          $fallbackResults = $this->callSerpApi($query, $fallbackOptions);
          $fallbackProcessed = $this->processResults($fallbackResults, $query, $fallbackOptions);

          if (!empty($fallbackProcessed['ai_overview'])) {
            $processed['ai_overview'] = $fallbackProcessed['ai_overview'];
            $processed['metadata']['has_ai_overview'] = true;
            $processed['metadata']['region_fallback'] = true;
            $processed['metadata']['original_region'] = $options['gl'];
          }
        }

        // Enrich with metadata
        $processed['cached'] = false;
        $processed['cache_source'] = 'none';
        $processed['execution_time'] = microtime(true) - $startTime;
        $processed['api_cost'] = $apiCost;

        // STEP 5: Cache results
        $ttl = $options['cache_ttl'] ?? $this->cacheExpiration;
        $this->cache->cacheResponse($cacheKey, json_encode($processed), $ttl);

        // STEP 6: Store in RAG learning if high quality
        $qualityScore = $this->calculateQualityScore($processed);
        $processed['quality_score'] = $qualityScore;

        if ($qualityScore >= 0.7) {
          $stored = $this->cacheManager->storeInLearningRAG($query, $processed);
          $processed['stored_in_rag'] = $stored;

          if ($this->debug) {
            if ($stored) {
              $this->logger->logSecurityEvent(
                "✅ High quality result stored in RAG learning DB (score: {$qualityScore})",
                'info'
              );
            } else {
              $this->logger->logSecurityEvent(
                "⚠️ Failed to store result in RAG learning DB (score: {$qualityScore})",
                'warning'
              );
            }
          }
        } else {
          $processed['stored_in_rag'] = false;

          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Quality score too low ({$qualityScore}), not storing in RAG learning DB",
              'info'
            );
          }
        }

        // STEP 7: Log complete request
        $this->searchLogger->saveWebSearchRequest(
          $query,
          $query,
          ['intent' => 'EXTERNAL_WEB', 'confidence' => 1.0, 'method' => 'direct'],
          $processed,
          '',
          [
            'execution_time' => $processed['execution_time'],
            'api_cost' => $processed['api_cost'],
            'cached' => false,
            'cached_in_rag' => $processed['stored_in_rag'],
            'quality_score' => $qualityScore,
          ]
        );

        return $processed;

      } catch (\Exception $e) {
        $this->stats['errors']++;

        $this->logger->logSecurityEvent(
          "Web search error: " . $e->getMessage(),
          'error'
        );

        return [
          'success' => false,
          'error' => $e->getMessage(),
          'query' => $query,
          'execution_time' => microtime(true) - $startTime,
        ];
      }
    }

    /**
     * Generate unique cache key
     */
    private function generateCacheKey(string $query, array $options): string
    {
      $normalized = strtolower(trim($query));
      $optionsHash = md5(json_encode([
        'engine' => $options['engine'] ?? $this->defaultEngine,
        'max_results' => $options['max_results'] ?? $this->maxResults,
        'language' => $options['language'] ?? 'en',
        'location' => $options['location'] ?? '',
      ]));

      return "websearch_" . md5($normalized) . "_{$optionsHash}";
    }

    /**
     * Estimate API cost
     */
    private function estimateApiCost(array $results): float
    {
      return 0.002; // SerpApi average cost per request
    }

    /**
     * Convert RAG cache format to standard format
     */
    private function convertRagCacheToStandardFormat(array $ragCached): array
    {
      $firstResult = $ragCached[0] ?? [];
      $content = $firstResult['content'] ?? '';

      return [
        'success' => true,
        'query' => $firstResult['original_query'] ?? '',
        'items' => $this->parseRagContentToItems($content),
        'total_results' => 0,
        'metadata' => [
          'search_engine' => 'rag_cache',
          'timestamp' => time(),
          'quality_score' => $firstResult['quality_score'] ?? 0,
          'usage_count' => $firstResult['usage_count'] ?? 0,
        ],
      ];
    }

    /**
     * Parse RAG content to extract items with URLs
     */
    private function parseRagContentToItems(string $content): array
    {
      $items = [];
      $pattern = '/(\d+)\.\s+(.+?)\n\s+(.+?)\n\s+Source:\s+(https?:\/\/[^\s\n]+|[^\s\n]+?)(?:\n|$)/s';

      if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          $sourceOrLink = trim($match[4]);
          $link = '';
          $source = $sourceOrLink;

          if (preg_match('/^https?:\/\//', $sourceOrLink)) {
            $link = $sourceOrLink;
            $parsed = parse_url($sourceOrLink);
            $source = $parsed['host'] ?? $sourceOrLink;
            $source = preg_replace('/^www\./', '', $source);
          }

          $items[] = [
            'position' => (int)$match[1],
            'title' => trim($match[2]),
            'snippet' => trim($match[3]),
            'source' => $source,
            'link' => $link,
            'relevance_score' => 0.8,
          ];
        }
      }

      return $items;
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(): void
    {
      $key = 'serpapi_rate_limit_' . date('YmdH');
      $count = (int)($this->cache->getCachedResponse($key) ?? 0);

      if ($count >= $this->rateLimitPerHour) {
        throw new \RuntimeException(
          "SerpApi rate limit exceeded ({$this->rateLimitPerHour} requests/hour). Try again later."
        );
      }

      $this->cache->cacheResponse($key, (string)($count + 1), 3600);

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Rate limit check: {$count}/{$this->rateLimitPerHour} requests this hour",
          'info'
        );
      }
    }

    /**
     * Wrapper for SerpApi calls
     */
    private function callSerpApi(string $query, array $options): array
    {
      $engine = $options['engine'] ?? $this->defaultEngine;
      $maxResults = $options['max_results'] ?? $this->maxResults;

      $params = [
        'api_key' => $this->apiKey,
        'engine' => $engine,
      ];

      if ($engine !== 'google_ai_overview') {
        $params['q'] = $query;
        $params['num'] = $maxResults;
      } else {
        $pageToken = $options['page_token'] ?? $options['ai_overview_page_token'] ?? null;
        if (empty($pageToken)) {
          throw new \InvalidArgumentException(
            'SerpApi google_ai_overview requires page_token (ai_overview.page_token).'
          );
        }
        $params['page_token'] = $pageToken;
        if (!empty($query)) {
          $params['q'] = $query;
        }
      }

      if (isset($options['language'])) {
        $params['hl'] = $options['language'];
      }

      if (isset($options['location'])) {
        $params['location'] = $options['location'];
      }

      if (isset($options['country'])) {
        $params['gl'] = $options['country'];
      }

      if (isset($options['gl'])) {
        $params['gl'] = $options['gl'];
      }

      if (isset($options['hl'])) {
        $params['hl'] = $options['hl'];
      }

      $params['safe'] = 'active';
      $params['output'] = $options['output'] ?? 'json';

      if (isset($options['no_cache'])) {
        $params['no_cache'] = $options['no_cache'];
      }
      if (isset($options['async'])) {
        $params['async'] = $options['async'];
      }
      if (isset($options['zero_trace'])) {
        $params['zero_trace'] = $options['zero_trace'];
      }
      if (isset($options['json_restrictor'])) {
        $params['json_restrictor'] = $options['json_restrictor'];
      }

      // Single SerpApi endpoint for all engines (including google_ai_overview)
      $url = 'https://serpapi.com/search?' . http_build_query($params);

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "SerpApi URL: " . preg_replace('/api_key=[^&]+/', 'api_key=***', $url),
          'info'
        );
      }

      $response = HTTP::getResponse([
        'url' => $url,
        'method' => 'get',
        'header' => [
          'User-Agent: ClicShoppingAI/1.0'
        ]
      ], ['serpapi.com']);

      if ($response === false) {
        throw new \RuntimeException("Failed to get response from SerpApi");
      }

      $decoded = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \RuntimeException("Invalid JSON response from SerpApi: " . json_last_error_msg());
      }

      if (isset($decoded['error'])) {
        throw new \RuntimeException("SerpApi error: " . $decoded['error']);
      }

      return $decoded;
    }

    /**
     * Processes raw JSON from SerpApi into structured internal format
     * Extracts AI Overview and organic results
     */
    private function processResults(array $rawResults, string $query, array $options): array
    {
      $items = [];
      $aiOverview = null;

      // Extract AI Overview data (always enabled)
      if (isset($rawResults['ai_overview'])) {
        try {
          $aiOverview = [
            'text' => $rawResults['ai_overview']['text_blocks'] ?? [],
            'full_summary' => $rawResults['ai_overview']['text'] ?? '',
            'sources' => $rawResults['ai_overview']['references'] ?? [],
            'is_generative' => true
          ];
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            'AI Overview extraction error: ' . $e->getMessage(),
            'warning'
          );
          $aiOverview = null;
        }
      }

      // Extract organic results
      if (isset($rawResults['organic_results']) && is_array($rawResults['organic_results'])) {
        foreach ($rawResults['organic_results'] as $index => $result) {
          $items[] = [
            'position' => $index + 1,
            'title' => $result['title'] ?? '',
            'snippet' => $result['snippet'] ?? '',
            'link' => $result['link'] ?? '',
            'source' => $result['displayed_link'] ?? parse_url($result['link'] ?? '', PHP_URL_HOST) ?? '',
            'relevance_score' => 1.0 - ($index * 0.05),
          ];
        }
      }

      return [
        'success' => true,
        'query' => $query,
        'ai_overview' => $aiOverview,
        'items' => $items,
        'total_results' => $rawResults['search_information']['total_results'] ?? count($items),
        'metadata' => [
          'search_engine' => $rawResults['search_metadata']['engine'] ?? 'google',
          'timestamp' => time(),
          'has_ai_overview' => !empty($aiOverview),
          'processing_method' => 'enhanced',
        ],
      ];
    }

    /**
     * Calculate a quality score based on richness of data
     */
    private function calculateQualityScore(array $processed): float
    {
      $score = 0.5;

      // AI Overview bonus
      if ($processed['metadata']['has_ai_overview'] ?? false) {
        $score += 0.3;
      }

      // Number of results
      if (!empty($processed['items'])) {
        $itemCount = count($processed['items']);
        $score += min(0.2, $itemCount * 0.02);
      }

      return min(1.0, $score);
    }

    /**
     * Compare internal product price with web search results
     */
    public function comparePrice(array $product, array $webResults): array
    {
      try {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Comparing price for product: {$product['name']} (Internal price: {$product['price']})",
            'info'
          );
        }

        $internalPrice = (float)$product['price'];
        $productName = $product['name'];
        $competitorPrices = [];

        // Extract prices from web search results
        if (isset($webResults['items']) && is_array($webResults['items'])) {
          foreach ($webResults['items'] as $item) {
            // Skip non-array items (defensive check for data integrity)
            if (!is_array($item)) {
              if ($this->debug) {
                $this->logger->logSecurityEvent(
                  "Skipping non-array item in web results: " . gettype($item),
                  'warning'
                );
              }
              continue;
            }
            
            $extractedPrice = $this->extractPriceFromResult($item);

            if ($extractedPrice !== null) {
              $competitorPrices[] = [
                'source' => $item['source'] ?? 'Unknown',
                'url' => $item['link'] ?? '',
                'price' => $extractedPrice,
                'title' => $item['title'] ?? '',
                'snippet' => $item['snippet'] ?? '',
              ];
            }
          }
        }

        if (empty($competitorPrices)) {
          return [
            'success' => true,
            'product_name' => $productName,
            'internal_price' => $internalPrice,
            'competitor_prices' => [],
            'comparison' => [
              'cheapest' => null,
              'most_expensive' => null,
              'average_competitor_price' => null,
              'price_differences' => [],
            ],
            'recommendation' => 'No competitor prices found for comparison.',
            'competitive_status' => 'unknown',
          ];
        }

        // Calculate price differences
        $priceDifferences = [];
        $competitorPriceValues = [];

        foreach ($competitorPrices as $competitor) {
          $competitorPrice = $competitor['price'];
          $competitorPriceValues[] = $competitorPrice;

          $difference = $internalPrice - $competitorPrice;
          $percentageDiff = $competitorPrice > 0 ? ($difference / $competitorPrice) * 100 : 0;

          $priceDifferences[] = [
            'source' => $competitor['source'],
            'url' => $competitor['url'],
            'competitor_price' => $competitorPrice,
            'difference' => round($difference, 2),
            'percentage_difference' => round($percentageDiff, 2),
            'status' => $difference < 0 ? 'cheaper' : ($difference > 0 ? 'more_expensive' : 'same'),
          ];
        }

        // Find cheapest and most expensive
        $cheapest = null;
        $mostExpensive = null;
        $minPrice = PHP_FLOAT_MAX;
        $maxPrice = 0;

        foreach ($priceDifferences as $diff) {
          if ($diff['competitor_price'] < $minPrice) {
            $minPrice = $diff['competitor_price'];
            $cheapest = $diff;
          }
          if ($diff['competitor_price'] > $maxPrice) {
            $maxPrice = $diff['competitor_price'];
            $mostExpensive = $diff;
          }
        }

        // Calculate average
        $avgCompetitorPrice = count($competitorPriceValues) > 0
          ? array_sum($competitorPriceValues) / count($competitorPriceValues)
          : 0;

        // Determine competitive status
        $competitiveStatus = 'competitive';
        $recommendation = '';

        $avgDifference = $internalPrice - $avgCompetitorPrice;
        $avgPercentageDiff = $avgCompetitorPrice > 0 ? ($avgDifference / $avgCompetitorPrice) * 100 : 0;

        if ($avgPercentageDiff > 10) {
          $competitiveStatus = 'not_competitive';
          $recommendation = sprintf(
            "Your price (%.2f) is %.1f%% higher than the average competitor price (%.2f). Consider reducing the price to remain competitive.",
            $internalPrice,
            abs($avgPercentageDiff),
            $avgCompetitorPrice
          );
        } elseif ($avgPercentageDiff < -10) {
          $competitiveStatus = 'very_competitive';
          $recommendation = sprintf(
            "Your price (%.2f) is %.1f%% lower than the average competitor price (%.2f). You have a strong competitive advantage.",
            $internalPrice,
            abs($avgPercentageDiff),
            $avgCompetitorPrice
          );
        } else {
          $competitiveStatus = 'competitive';
          $recommendation = sprintf(
            "Your price (%.2f) is competitive, within %.1f%% of the average competitor price (%.2f).",
            $internalPrice,
            abs($avgPercentageDiff),
            $avgCompetitorPrice
          );
        }

        return [
          'success' => true,
          'product_name' => $productName,
          'internal_price' => $internalPrice,
          'competitor_prices' => $competitorPrices,
          'comparison' => [
            'cheapest' => $cheapest,
            'most_expensive' => $mostExpensive,
            'average_competitor_price' => round($avgCompetitorPrice, 2),
            'price_differences' => $priceDifferences,
            'average_percentage_difference' => round($avgPercentageDiff, 2),
          ],
          'recommendation' => $recommendation,
          'competitive_status' => $competitiveStatus,
          'total_competitors_found' => count($competitorPrices),
        ];

      } catch (\Exception $e) {
        $this->logger->logSecurityEvent(
          "Error comparing prices: " . $e->getMessage(),
          'error'
        );

        return [
          'success' => false,
          'error' => 'Unable to compare prices: ' . $e->getMessage(),
          'product_name' => $product['name'] ?? 'Unknown',
          'internal_price' => $product['price'] ?? 0,
        ];
      }
    }

    /**
     * Extract price from web search result
     */
    private function extractPriceFromResult(array $result): ?float
    {
      $text = '';

      if (isset($result['title'])) {
        $text .= ' ' . $result['title'];
      }
      if (isset($result['snippet'])) {
        $text .= ' ' . $result['snippet'];
      }

      if (empty($text)) {
        return null;
      }

      $patterns = [
        ['pattern' => '/[\$€£]\s*(\d{1,3}(?:,\d{3})+\.\d{2})/', 'thousand_sep' => ',', 'decimal_sep' => '.'],
        ['pattern' => '/[\$€£]\s*(\d{1,6}\.\d{2})/', 'thousand_sep' => '', 'decimal_sep' => '.'],
        ['pattern' => '/(\d{1,6},\d{2})\s*[€£\$]/', 'thousand_sep' => '', 'decimal_sep' => ','],
        ['pattern' => '/(\d{1,3}(?:,\d{3})+\.\d{2})\s*[\$€£]/', 'thousand_sep' => ',', 'decimal_sep' => '.'],
        ['pattern' => '/(\d{1,6}\.\d{2})\s*[\$€£]/', 'thousand_sep' => '', 'decimal_sep' => '.'],
      ];

      foreach ($patterns as $patternConfig) {
        if (preg_match($patternConfig['pattern'], $text, $matches)) {
          $priceStr = $matches[1];

          if (!empty($patternConfig['thousand_sep'])) {
            $priceStr = str_replace($patternConfig['thousand_sep'], '', $priceStr);
          }

          if ($patternConfig['decimal_sep'] === ',') {
            $priceStr = str_replace(',', '.', $priceStr);
          }

          $price = (float)$priceStr;

          if ($price > 0 && $price < 1000000) {
            return $price;
          }
        }
      }

      return null;
    }

    /**
     * Find product in database before web search
     */
    public function findProductInDatabase(string $query, ?int $languageId = null): ?array
    {
      try {
        if ($languageId === null) {
          $languageId = $this->language !== null ? $this->language->getId() : 1;
        }

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Searching for product in database: {$query} (language_id: {$languageId})",
            'info'
          );
        }

        // Try embedding search using IntentAnalyzer
        $intentAnalyzer = new IntentAnalyzer(null, $this->debug);
        $entityResult = $intentAnalyzer->detectEntityFromEmbeddings($query, 'product');

        if ($entityResult !== null && isset($entityResult['entity_id'])) {
          $product = null;
          if ($this->entityHelper !== null) {
            $product = $this->entityHelper::getEntityById($entityResult['entity_id'], $languageId);
          } else {
            $product = $this->getProductById($entityResult['entity_id'], $languageId);
          }

          if ($product !== null) {
            $product['detection_method'] = 'embedding';
            $product['confidence'] = $entityResult['confidence'];
            $product['entity_id'] = $product['product_id'];
            $product['entity_type'] = 'product';
            return $product;
          }
        }

        // Fallback to SQL LIKE search
        $product = null;
        if ($this->entityHelper !== null && method_exists($this->entityHelper, 'searchProductByName')) {
          $product = $this->entityHelper->searchProductByName($query, $languageId);
        } else {
          $product = $this->searchProductByName($query, $languageId);
        }

        if ($product !== null) {
          $product['detection_method'] = 'sql_like';
          $product['confidence'] = 0.6;
          $product['entity_id'] = $product['product_id'];
          $product['entity_type'] = 'product';
          return $product;
        }

        return null;

      } catch (\Exception $e) {
        $this->logger->logSecurityEvent(
          "Error finding product in database: " . $e->getMessage(),
          'error'
        );
        return null;
      }
    }

    /**
     * Get product details by ID
     */
    private function getProductById(int $productId, ?int $languageId = null): ?array
    {
      try {
        if ($languageId === null) {
          $languageId = $this->language !== null ? $this->language->getId() : 1;
        }

        $Qproduct = $this->db->prepare('SELECT p.products_id as product_id,
                                               pd.products_name as name,
                                               p.products_price as price,
                                               p.products_model as model
                                        FROM :table_products p
                                        INNER JOIN :table_products_description pd ON p.products_id = pd.products_id
                                        WHERE p.products_id = :product_id
                                          AND pd.language_id = :language_id
                                        LIMIT 1
                                      ');

        $Qproduct->bindInt(':product_id', $productId);
        $Qproduct->bindInt(':language_id', $languageId);
        $Qproduct->execute();

        if ($Qproduct->fetch()) {
          return [
            'product_id' => $Qproduct->valueInt('product_id'),
            'name' => $Qproduct->value('name'),
            'price' => $Qproduct->valueDecimal('price'),
            'model' => $Qproduct->value('model'),
          ];
        }

        return null;

      } catch (\Exception $e) {
        $this->logger->logSecurityEvent(
          "Error getting product by ID: " . $e->getMessage(),
          'error'
        );
        return null;
      }
    }

    /**
     * Search product by name using SQL LIKE
     */
    private function searchProductByName(string $query, ?int $languageId = null): ?array
    {
      try {
        if ($languageId === null) {
          $languageId = $this->language !== null ? $this->language->getId() : 1;
        }

        $cleanQuery = preg_replace('/\b(stock|price|compare|competitors?|show|give|display|of|the|a|an)\b/i', '', $query);
        $cleanQuery = trim($cleanQuery);

        if (empty($cleanQuery)) {
          return null;
        }

        $Qproduct = $this->db->prepare(' SELECT p.products_id as product_id,
                                                 pd.products_name as name,
                                                 p.products_price as price,
                                                 p.products_model as model
                                          FROM :table_products p
                                          INNER JOIN :table_products_description pd ON p.products_id = pd.products_id
                                          WHERE (pd.products_name LIKE :search_term
                                             OR p.products_model LIKE :search_term)
                                            AND pd.language_id = :language_id
                                            AND p.products_status = 1
                                          ORDER BY 
                                            CASE 
                                              WHEN pd.products_name = :exact_term THEN 1
                                              WHEN pd.products_name LIKE :starts_with THEN 2
                                              ELSE 3
                                            END
                                          LIMIT 1
                                        ');

        $searchTerm = '%' . $cleanQuery . '%';
        $startsWith = $cleanQuery . '%';

        $Qproduct->bindValue(':search_term', $searchTerm);
        $Qproduct->bindValue(':exact_term', $cleanQuery);
        $Qproduct->bindValue(':starts_with', $startsWith);
        $Qproduct->bindInt(':language_id', $languageId);
        $Qproduct->execute();

        if ($Qproduct->fetch()) {
          return [
            'product_id' => $Qproduct->valueInt('product_id'),
            'name' => $Qproduct->value('name'),
            'price' => $Qproduct->valueDecimal('price'),
            'model' => $Qproduct->value('model'),
          ];
        }

        return null;

      } catch (\Exception $e) {
        $this->logger->logSecurityEvent(
          "Error searching product by name: " . $e->getMessage(),
          'error'
        );
        return null;
      }
    }
  }
