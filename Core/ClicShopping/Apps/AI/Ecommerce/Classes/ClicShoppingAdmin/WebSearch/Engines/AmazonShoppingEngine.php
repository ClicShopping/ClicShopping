<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Engines;

use ClicShopping\AI\DomainsAI\WebSearch\Helper\SerpApiClient;
use ClicShopping\AI\DomainsAI\WebSearch\Logger\WebSearchLogger;
use ClicShopping\AI\InterfacesAI\WebSearchInterface;

/**
 * AmazonShoppingEngine — Mode D Executor
 *
 * Executes Amazon marketplace product searches via the SerpAPI `engine=amazon`
 * endpoint. Returns structured product data (price, rating, reviews, ASIN, ...)
 * for downstream rendering by the agnostic WebSearchFormatter.
 *
 * MIGRATION NOTE (2026-05-24, VIOLATION-002):
 *   This engine previously lived at
 *   `Core/ClicShopping/AI/DomainsAI/WebSearch/Tools/AmazonShoppingEngine.php`,
 *   violating AGENTS.md's "no domain-specific code in DomainsAI/" rule. It was
 *   relocated to the Ecommerce App and is now registered with the Core
 *   {@see \ClicShopping\AI\RegistryAI\WebSearchEngineRegistry} via
 *   {@see \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Registration\WebSearchRegistration}.
 *
 * The duplicated Amazon-domain list previously embedded in `isAmazonSite()`
 * has been removed — that knowledge now lives in {@see AmazonSiteRouter},
 * the single source of truth for the Ecommerce domain.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Engines
 * @since 2026-05-24 (relocated from Core)
 */
class AmazonShoppingEngine implements WebSearchInterface
{
    private const ENGINE_NAME = 'amazon';
    private const DEFAULT_MAX_RESULTS = 20;

    private SerpApiClient $client;
    private WebSearchLogger $logger;
    private bool $debug;

    public function __construct()
    {
        $apiKey = $this->loadApiKey();
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
            && \CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

        $this->client = new SerpApiClient($apiKey, $this->debug);
        $this->logger = new WebSearchLogger();
    }

    private function loadApiKey(): string
    {
        if (\defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI')
            && !empty(\CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI)) {
            return \trim(\CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI);
        }

        return '';
    }

    /**
     * Map ISO country code to its corresponding Amazon marketplace domain.
     *
     * Used to set the `amazon_domain` SerpAPI parameter when location-aware
     * searches are requested.
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

        return $mapping[\strtolower($countryCode)] ?? null;
    }

    public function search(string $query, array $options = []): array
    {
        $startTime = \microtime(true);

        try {
            if (!$this->validateConfig()) {
                return $this->buildErrorResponse(
                    'Configuration validation failed: SerpAPI key not configured',
                    $query,
                    $startTime
                );
            }

            $params = $this->buildSearchParams($options);

            if ($this->debug) {
                \error_log(\sprintf(
                    '[AmazonShoppingEngine] Executing search - Query: %s, Params: %s',
                    $query,
                    \json_encode($params)
                ));
            }

            // SerpApiClient picks the right query param ('k' for Amazon) via the
            // agnostic WebSearchEngineRegistry — no hard-coded engine name here.
            $data = $this->client->search(self::ENGINE_NAME, $query, $params);

            if ($data === false) {
                return $this->buildErrorResponse(
                    'SerpAPI Amazon request failed',
                    $query,
                    $startTime
                );
            }

            $result = $this->buildResultFromData($data);
            $result['metadata']['execution_time'] = \microtime(true) - $startTime;
            $result['metadata']['engine'] = self::ENGINE_NAME;

            if ($this->debug) {
                \error_log(\sprintf(
                    '[AmazonShoppingEngine] Search completed in %.3fs - Query: %s - Results: %d',
                    $result['metadata']['execution_time'],
                    $query,
                    \count($result['shopping_results'])
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
     * Amazon engine specifics:
     * - Does NOT support the `num` parameter
     * - Supports `amazon_domain` for country-specific searches
     * - Supports standard `gl`/`hl` location params
     */
    private function buildSearchParams(array $options): array
    {
        $params = [];

        if (!empty($options['location_params'])) {
            $locationParams = $options['location_params'];

            if (!empty($locationParams['gl'])) {
                $amazonDomain = $this->mapCountryToAmazonDomain($locationParams['gl']);
                if ($amazonDomain) {
                    $params['amazon_domain'] = $amazonDomain;
                }
            }

            if (!empty($locationParams['hl'])) {
                $params['hl'] = $locationParams['hl'];
            }
        }

        return $params;
    }

    public function getName(): string
    {
        return self::ENGINE_NAME;
    }

    public function isAvailable(): bool
    {
        return $this->validateConfig();
    }

    public function getCapabilities(): array
    {
        return [
            'shopping_results' => true,
            'ai_overview' => false,
            'organic_results' => false,
            'targeted_scraping' => false,
        ];
    }

    public function validateConfig(): bool
    {
        $apiKey = $this->loadApiKey();

        if (empty($apiKey)) {
            if ($this->debug) {
                \error_log('[AmazonShoppingEngine] Configuration invalid: SerpAPI key not set');
            }
            return false;
        }

        if (\strlen($apiKey) < 32) {
            if ($this->debug) {
                \error_log('[AmazonShoppingEngine] Configuration invalid: SerpAPI key too short');
            }
            return false;
        }

        return true;
    }

    public function getMetadata(): array
    {
        return [
            'cost_per_request' => 0.015,
            'avg_latency' => 1200.0,
            'quality_score' => 0.90,
        ];
    }

    public function buildSerpApiUrl(string $query, array $options = []): string
    {
        $params = $this->buildSearchParams($options);
        return $this->client->buildUrl(self::ENGINE_NAME, $query, $params);
    }

    public function parseResponse(string $jsonResponse): array
    {
        $data = $this->client->parseResponse($jsonResponse);

        if ($data === false) {
            return $this->buildErrorResponse('Failed to parse SerpAPI response', '', 0);
        }

        return $this->buildResultFromData($data);
    }

    /**
     * Amazon returns results in `organic_results` (not `shopping_results`).
     */
    private function buildResultFromData(array $data): array
    {
        if ($this->debug) {
            \error_log('[AmazonShoppingEngine::buildResultFromData] Response keys: '
                . \implode(', ', \array_keys($data)));

            if (isset($data['search_parameters'])) {
                \error_log('[AmazonShoppingEngine::buildResultFromData] Search parameters: '
                    . \json_encode($data['search_parameters']));
            }

            if (isset($data['organic_results'])) {
                \error_log('[AmazonShoppingEngine::buildResultFromData] organic_results count: '
                    . \count($data['organic_results']));
            } else {
                \error_log('[AmazonShoppingEngine::buildResultFromData] WARNING: No organic_results in response');
            }

            if (isset($data['error'])) {
                \error_log('[AmazonShoppingEngine::buildResultFromData] ERROR in response: ' . $data['error']);
            }
        }

        $shoppingResults = [];
        if (!empty($data['organic_results']) && \is_array($data['organic_results'])) {
            foreach ($data['organic_results'] as $result) {
                $shoppingResults[] = $this->extractShoppingResult($result);
            }
        }

        $shoppingResults = $this->deduplicateResults($shoppingResults);

        return [
            'success' => true,
            'query' => $data['search_parameters']['k'] ?? '',
            'ai_overview' => null,
            'organic_results' => [],
            'shopping_results' => $shoppingResults,
            'metadata' => [
                'mode' => 'mode_d_amazon_shopping',
                'engine' => self::ENGINE_NAME,
                'result_count' => \count($shoppingResults),
                'original_count' => \count($data['organic_results'] ?? []),
                'search_parameters' => $data['search_parameters'] ?? [],
            ],
        ];
    }

    private function extractShoppingResult(array $result): array
    {
        $extractedPrice = isset($result['extracted_price']) ? (float) $result['extracted_price'] : null;
        $extractedOldPrice = isset($result['extracted_old_price']) ? (float) $result['extracted_old_price'] : null;
        $rating = isset($result['rating']) ? (float) $result['rating'] : null;
        $reviews = isset($result['reviews']) ? (int) $result['reviews'] : null;

        // Prefer link_clean over link (which may be a sponsored tracking URL)
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
            'product_link' => $productLink,
            'thumbnail' => $result['thumbnail'] ?? '',
            'rating' => $rating,
            'reviews' => $reviews,
            'asin' => $result['asin'] ?? null,
            'bought_last_month' => $result['bought_last_month'] ?? null,
            'delivery' => $result['delivery'] ?? null,
            'stock' => $result['stock'] ?? null,
            'data_source' => 'amazon',
            'engine_type' => self::ENGINE_NAME,
        ];
    }

    private function deduplicateResults(array $results): array
    {
        $seen = [];
        $deduplicated = [];

        foreach ($results as $result) {
            $hash = $this->generateResultHash($result);

            if (!isset($seen[$hash])) {
                $seen[$hash] = true;
                $deduplicated[] = $result;
            } elseif ($this->debug) {
                \error_log(\sprintf(
                    '[AmazonShoppingEngine] Duplicate removed: %s (hash: %s)',
                    $result['title'],
                    $hash
                ));
            }
        }

        if ($this->debug && \count($results) !== \count($deduplicated)) {
            \error_log(\sprintf(
                '[AmazonShoppingEngine] Deduplication: %d -> %d results (%d duplicates removed)',
                \count($results),
                \count($deduplicated),
                \count($results) - \count($deduplicated)
            ));
        }

        return $deduplicated;
    }

    private function generateResultHash(array $result): string
    {
        $normalizedTitle = $this->normalizeTitle($result['title'] ?? '');
        $price = $result['extracted_price'] ?? 0;

        return \md5($normalizedTitle . '|' . $price);
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = \mb_strtolower($title, 'UTF-8');
        $normalized = \preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);
        $normalized = \preg_replace('/\s+/', ' ', $normalized);
        $normalized = \trim($normalized);

        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'new', 'official', 'original'];
        $words = \explode(' ', $normalized);
        $words = \array_filter($words, static fn(string $word): bool =>
            !\in_array($word, $stopWords, true) && \strlen($word) > 1
        );

        return \implode(' ', $words);
    }

    private function buildErrorResponse(string $errorMessage, string $query, float $startTime): array
    {
        if ($this->debug) {
            \error_log('[AmazonShoppingEngine] Error: ' . $errorMessage);
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
                'execution_time' => $startTime > 0 ? \microtime(true) - $startTime : 0,
            ],
        ];
    }
}
