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
 * GoogleTrendsEngine - Mode E executor
 *
 * Executes Google Trends queries via SerpAPI to retrieve interest-over-time
 * timeline data for a keyword or product. Results are intended for Chart.js
 * line chart rendering in the frontend.
 *
 * SerpAPI engine: google_trends
 * Data type: TIMESERIES (interest over time, 0-100 relative scale)
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Tools
 */
class GoogleTrendsEngine implements WebSearchInterface
{
  private const ENGINE_NAME = 'google_trends';
  private const SERPAPI_ENGINE = 'google_trends';
  private const DEFAULT_DATE_RANGE = 'today 12-m';
  private const DEFAULT_DATA_TYPE = 'TIMESERIES';

  private SerpApiClient $client;
  private WebSearchLogger $logger;
  private bool $debug;

  public function __construct()
  {
    $apiKey = $this->loadApiKey();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->client = new SerpApiClient($apiKey, $this->debug);
    $this->logger = new WebSearchLogger();
  }

  private function loadApiKey(): string
  {
    if (defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI') && !empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI)) {
      return trim(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI);
    }
    return '';
  }

  /**
   * Execute a Google Trends query and return timeline data.
   *
   * @param string $query Keyword or product to analyse
   * @param array $options Optional parameters:
   *   - date_range: SerpAPI date parameter (default: "today 12-m")
   *   - location_params: Array with gl (country code) and hl (language)
   *   - tz: Timezone offset in minutes (default: 0 = UTC)
   * @return array Unified result structure with trends_data key
   */
  public function search(string $query, array $options = []): array
  {
    $startTime = microtime(true);

    if (!$this->validateConfig()) {
      return $this->buildErrorResponse('SerpAPI key not configured or invalid', $query, $startTime);
    }

    $params = $this->buildSearchParams($options);

    $data = $this->client->search(self::SERPAPI_ENGINE, $query, $params);

    if ($data === false) {
      return $this->buildErrorResponse('SerpAPI request failed', $query, $startTime);
    }

    $timelineData = $data['interest_over_time']['timeline_data'] ?? [];

    if (empty($timelineData)) {
      return $this->buildErrorResponse('No trend data returned by SerpAPI', $query, $startTime);
    }

    $normalizedData = $this->normalizeTimeline($timelineData, $query);

    $result = [
      'success' => true,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'trends_data' => [
        'keyword' => $query,
        'date_range' => $params['date'] ?? self::DEFAULT_DATE_RANGE,
        'timeline' => $normalizedData,
        'point_count' => count($normalizedData),
      ],
      'metadata' => [
        'mode' => 'mode_e_google_trends',
        'engine' => self::ENGINE_NAME,
        'execution_time' => microtime(true) - $startTime,
        'result_count' => count($normalizedData),
      ],
    ];

    if ($this->debug) {
      error_log(sprintf(
        '[GoogleTrendsEngine] Search completed in %.3fs — Query: %s — %d data points',
        $result['metadata']['execution_time'],
        $query,
        count($normalizedData)
      ));
    }

    return $result;
  }

  /**
   * Normalize timeline_data from SerpAPI into a flat array of {date, value} pairs.
   */
  private function normalizeTimeline(array $timelineData, string $query): array
  {
    $normalized = [];

    foreach ($timelineData as $point) {
      $value = null;
      foreach ($point['values'] ?? [] as $v) {
        if (strtolower($v['query'] ?? '') === strtolower($query) || count($point['values']) === 1) {
          $value = $v['extracted_value'] ?? (int)($v['value'] ?? 0);
          break;
        }
      }

      if ($value === null && !empty($point['values'])) {
        $value = $point['values'][0]['extracted_value'] ?? (int)($point['values'][0]['value'] ?? 0);
      }

      $normalized[] = [
        'date' => $point['date'] ?? '',
        'timestamp' => (int)($point['timestamp'] ?? 0),
        'value' => (int)$value,
      ];
    }

    return $normalized;
  }

  private function buildSearchParams(array $options): array
  {
    $params = [
      'data_type' => self::DEFAULT_DATA_TYPE,
      'date' => $options['date_range'] ?? self::DEFAULT_DATE_RANGE,
      'tz' => $options['tz'] ?? 0,
    ];

    if (!empty($options['location_params']['gl'])) {
      $params['geo'] = strtoupper($options['location_params']['gl']);
    }

    if (!empty($options['location_params']['hl'])) {
      $params['hl'] = $options['location_params']['hl'];
    }

    return $params;
  }

  public function buildSerpApiUrl(string $query, array $options = []): string
  {
    return $this->client->buildUrl(self::SERPAPI_ENGINE, $query, $this->buildSearchParams($options));
  }

  public function parseResponse(string $jsonResponse): array
  {
    return $this->client->parseResponse($jsonResponse) ?: $this->buildErrorResponse('JSON parse error', '', 0);
  }

  public function getName(): string
  {
    return self::ENGINE_NAME;
  }

  public function isAvailable(): bool
  {
    return $this->validateConfig();
  }

  public function validateConfig(): bool
  {
    $apiKey = $this->loadApiKey();
    if (empty($apiKey) || strlen($apiKey) < 32) {
      if ($this->debug) {
        error_log('[GoogleTrendsEngine] Configuration invalid: SerpAPI key missing or too short');
      }
      return false;
    }
    return true;
  }

  public function getCapabilities(): array
  {
    return [
      'shopping_results' => false,
      'ai_overview' => false,
      'organic_results' => false,
      'trends_data' => true,
    ];
  }

  public function getMetadata(): array
  {
    return [
      'cost_per_request' => 0.01,
      'avg_latency' => 1200.0,
      'quality_score' => 0.90,
    ];
  }

  private function buildErrorResponse(string $errorMessage, string $query, float $startTime): array
  {
    if ($this->debug) {
      error_log('[GoogleTrendsEngine] Error: ' . $errorMessage);
    }

    return [
      'success' => false,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'trends_data' => [],
      'metadata' => [
        'mode' => 'mode_e_google_trends',
        'engine' => self::ENGINE_NAME,
        'error' => $errorMessage,
        'execution_time' => $startTime > 0 ? microtime(true) - $startTime : 0,
      ],
    ];
  }
}
