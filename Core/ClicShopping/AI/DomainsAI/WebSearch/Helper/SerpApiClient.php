<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Helper;

use ClicShopping\AI\Config\TechnicalDefaults;
use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;
use ClicShopping\AI\Security\OutboundBlockedException;
use ClicShopping\AI\Security\OutboundPolicy;
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

  private string $apiKey;
  private bool $debug;
  private string $lastError = '';

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
    $this->lastError = '';
    $timeout = TechnicalDefaults::int('CLICSHOPPING_APP_CHATGPT_WEB_SERPAPI_TIMEOUT');
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

    // The deployment policy decides before the connection, not after.
    try {
      OutboundPolicy::assertAllowed($url, 'websearch');
    } catch (OutboundBlockedException $e) {
      return $this->fail($engine, $e->getMessage());
    }

    // Execute HTTP request
    $response = HTTP::getResponse([
      'url' => $url,
      'method' => 'get',
      'timeout' => $timeout,
      'header' => [
        'User-Agent: ClicShoppingAI/1.0'
      ]
    ], ['serpapi.com']);

    // Handle HTTP failure. No response at all is a timeout or a network refusal, not an API
    // verdict: naming it so is what separates "the provider said no" from "we did not wait".
    if ($response === false || empty($response)) {
      return $this->fail($engine, sprintf('no response within %ds (timeout or network failure)', $timeout));
    }

    // Decode JSON response
    $data = is_string($response) ? json_decode($response, true) : $response;

    // Handle JSON decode error
    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->fail($engine, 'malformed response: ' . json_last_error_msg());
    }

    // Handle SerpAPI error response
    if (isset($data['error'])) {
      return $this->fail($engine, 'provider error: ' . (string)$data['error']);
    }

    return $data;
  }

  /**
   * Record a failure cause and log it. Logged unconditionally: the three causes collapse into one
   * `false`, and behind a debug flag the reason is unreadable on the server that produced it.
   *
   * @param string $engine The engine that failed
   * @param string $reason Why the call produced no data
   * @return false Always, so a caller can return it directly
   */
  private function fail(string $engine, string $reason): false
  {
    $this->lastError = $reason;
    error_log(sprintf('[SerpApiClient] %s failed - %s', $engine, $reason));

    return false;
  }

  /**
   * Why the last search() returned false, empty when it succeeded.
   *
   * @return string The failure cause
   */
  public function lastError(): string
  {
    return $this->lastError;
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
      return $this->fail('parseResponse', 'malformed response: ' . json_last_error_msg());
    }

    if (isset($data['error'])) {
      return $this->fail('parseResponse', 'provider error: ' . (string)$data['error']);
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
