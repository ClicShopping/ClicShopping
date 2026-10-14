<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Helper;

use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;
use ClicShopping\OM\HTTP;

/**
 * SerpApiClient - Centralized SerpAPI client
 *
 * Provides a reusable interface for making SerpAPI requests.
 * Avoids code duplication across different engine implementations.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Helper
 */
class SerpApiClient
{
  private const SERPAPI_BASE_URL = 'https://serpapi.com/search';
  private const DEFAULT_TIMEOUT = 10;

  private string $apiKey;
  private bool $debug;

  /**
   * Constructor
   *
   * @param string $apiKey SerpAPI key
   * @param bool $debug Enable debug logging
   */
  public function __construct(string $apiKey, bool $debug = false)
  {
    $this->apiKey = $apiKey;
    $this->debug = $debug;
  }

  /**
   * Execute a SerpAPI search request
   *
   * @param string $engine Engine type (google_ai_overview, google_shopping, google, etc.)
   * @param string $query Search query
   * @param array $params Additional parameters (gl, hl, num, currency, etc.)
   * @return array|false Decoded JSON response or false on failure
   */
  public function search(string $engine, string $query, array $params = []): array|false
  {
    // Per-engine query param key is declared by the registered provider
    // (default 'q'; some providers return 'k'). Core stays brand-free.
    $queryParamKey = WebSearchEngineRegistry::getInstance()->getSerpApiQueryParam($engine);

    // Build base parameters
    $requestParams = [
      'engine' => $engine,
      $queryParamKey => $query,
      'api_key' => $this->apiKey,
    ];

    // Merge additional parameters
    $requestParams = array_merge($requestParams, $params);

    // Build URL
    $url = self::SERPAPI_BASE_URL . '?' . http_build_query($requestParams);

    if ($this->debug) {
      error_log(sprintf(
        '[SerpApiClient] Request: engine=%s, query=%s, params=%s',
        $engine,
        $query,
        json_encode($params)
      ));
    }

    // Execute HTTP request
    $response = HTTP::getResponse([
      'url' => $url,
      'method' => 'get',
      'timeout' => self::DEFAULT_TIMEOUT,
      'header' => [
        'User-Agent: ClicShoppingAI/1.0'
      ]
    ], ['serpapi.com']);

    // Handle HTTP failure
    if ($response === false || empty($response)) {
      if ($this->debug) {
        error_log('[SerpApiClient] HTTP request failed');
      }
      return false;
    }

    // Decode JSON response
    $data = is_string($response) ? json_decode($response, true) : $response;

    // Handle JSON decode error
    if (json_last_error() !== JSON_ERROR_NONE) {
      if ($this->debug) {
        error_log('[SerpApiClient] JSON decode error: ' . json_last_error_msg());
      }
      return false;
    }

    // Handle SerpAPI error response
    if (isset($data['error'])) {
      if ($this->debug) {
        error_log('[SerpApiClient] SerpAPI error: ' . $data['error']);
      }
      return false;
    }

    return $data;
  }

  /**
   * Build SerpAPI URL for parallel execution
   *
   * @param string $engine Engine type
   * @param string $query Search query
   * @param array $params Additional parameters
   * @return string Complete SerpAPI URL
   */
  public function buildUrl(string $engine, string $query, array $params = []): string
  {
    $queryParamKey = WebSearchEngineRegistry::getInstance()->getSerpApiQueryParam($engine);

    $requestParams = [
      'engine' => $engine,
      $queryParamKey => $query,
      'api_key' => $this->apiKey,
    ];

    $requestParams = array_merge($requestParams, $params);

    return self::SERPAPI_BASE_URL . '?' . http_build_query($requestParams);
  }

  /**
   * Parse SerpAPI JSON response
   *
   * @param string $jsonResponse Raw JSON response
   * @return array|false Decoded response or false on error
   */
  public function parseResponse(string $jsonResponse): array|false
  {
    $data = json_decode($jsonResponse, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      if ($this->debug) {
        error_log('[SerpApiClient] JSON decode error: ' . json_last_error_msg());
      }
      return false;
    }

    if (isset($data['error'])) {
      if ($this->debug) {
        error_log('[SerpApiClient] SerpAPI error: ' . $data['error']);
      }
      return false;
    }

    return $data;
  }

  /**
   * Get the base URL for SerpAPI
   *
   * @return string Base URL
   */
  public static function getBaseUrl(): string
  {
    return self::SERPAPI_BASE_URL;
  }
}
