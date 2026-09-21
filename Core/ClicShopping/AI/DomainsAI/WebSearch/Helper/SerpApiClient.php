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
  private const SERPAPI_HOST = 'serpapi.com';
  private const DEFAULT_MAX_CONCURRENT = 5;

  private string $apiKey;
  private bool $debug;
  private string $lastError = '';
  /** @var array<string,string> Failure cause per batch key */
  private array $batchErrors = [];

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

    $request = $this->buildHttpRequest($engine, $query, $params);

    if ($request === false) {
      return false;
    }

    $response = HTTP::getResponse($request, [self::SERPAPI_HOST]);

    return $this->decodeResponse($engine, $response);
  }

  /**
   * Run several already-declared requests in ONE round instead of one after the other.
   *
   * The caller's keys index the result, so the engine context stays in PHP and never transits
   * through the URL — that transmission is what sank the previous parallel path.
   *
   * @param array<string,array> $requests Requests from buildHttpRequest(), keyed by the caller
   * @param int $maxConcurrent Sockets opened at once
   * @return array<string,string|false> Raw body per key, false for the ones that failed
   */
  public function runBatch(array $requests, int $maxConcurrent = self::DEFAULT_MAX_CONCURRENT): array
  {
    $bodies = [];

    foreach (HTTP::getParallelResponses($requests, [self::SERPAPI_HOST], $maxConcurrent) as $key => $response) {
      $bodies[$key] = ($response['success'] ?? false) ? $response['data'] : false;
    }

    return $bodies;
  }

  /**
   * Build the HTTP request for one SerpAPI search, policy check included.
   *
   * Public so an engine can DECLARE its calls to the executor instead of running them itself
   * ({@see \ClicShopping\AI\InterfacesAI\BatchableWebSearchInterface}).
   *
   * @param string $engine Engine type (google_ai_overview, google_shopping, google, ...)
   * @param string $query Search query
   * @param array $params Additional parameters (gl, hl, num, currency, ...)
   * @param string $key Batch key the failure belongs to, empty for a single call
   * @return array|false Request array for HTTP::getResponse(), false when the policy refuses it
   */
  public function buildHttpRequest(string $engine, string $query, array $params, string $key = ''): array|false
  {
    // Per-engine query param key is declared by the registered provider
    // (default 'q'; some providers return 'k'). Core stays brand-free.
    $queryParamKey = WebSearchEngineRegistry::getInstance()->getSerpApiQueryParam($engine);

    $requestParams = array_merge([
      'engine' => $engine,
      $queryParamKey => $query,
      'api_key' => $this->apiKey,
    ], $params);

    $url = self::SERPAPI_BASE_URL . '?' . http_build_query($requestParams);

    if ($this->debug) {
      error_log(sprintf(
        '[SerpApiClient] Request: engine=%s, query=%s, params=%s',
        $engine,
        $query,
        json_encode($params)
      ));
    }

    // The deployment policy decides before the connection, not after — per URL, and
    // before the batch is constituted: the primitive's allowlist does not replace it.
    try {
      OutboundPolicy::assertAllowed($url, 'websearch');
    } catch (OutboundBlockedException $e) {
      $this->fail($engine, $e->getMessage(), $key);

      return false;
    }

    return [
      'url' => $url,
      'method' => 'get',
      'timeout' => TechnicalDefaults::int('CLICSHOPPING_APP_CHATGPT_WEB_SERPAPI_TIMEOUT'),
      'header' => [
        'User-Agent: ClicShoppingAI/1.0'
      ]
    ];
  }

  /**
   * Turn a raw SerpAPI body into data, naming why it is not data when it is not.
   *
   * Public for the return leg of a declared batch: the engine gets raw bodies back.
   *
   * @param string $engine Engine the response belongs to
   * @param mixed $response Raw body, or false when nothing came back
   * @param string $key Batch key the failure belongs to, empty for a single call
   * @return array|false Decoded payload, false on timeout, malformed body or provider error
   */
  public function decodeResponse(string $engine, mixed $response, string $key = ''): array|false
  {
    $timeout = TechnicalDefaults::int('CLICSHOPPING_APP_CHATGPT_WEB_SERPAPI_TIMEOUT');

    // No response at all is a timeout or a network refusal, not an API verdict: naming it so is
    // what separates "the provider said no" from "we did not wait".
    if ($response === false || empty($response)) {
      return $this->fail($engine, sprintf('no response within %ds (timeout or network failure)', $timeout), $key);
    }

    $data = is_string($response) ? json_decode($response, true) : $response;

    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->fail($engine, 'malformed response: ' . json_last_error_msg(), $key);
    }

    if (isset($data['error'])) {
      return $this->fail($engine, 'provider error: ' . (string)$data['error'], $key);
    }

    return $data;
  }

  /**
   * Record a failure cause and log it. Logged unconditionally: the three causes collapse into one
   * `false`, and behind a debug flag the reason is unreadable on the server that produced it.
   *
   * @param string $engine The engine that failed
   * @param string $reason Why the call produced no data
   * @param string $key Batch key the failure belongs to, empty for a single call
   * @return false Always, so a caller can return it directly
   */
  private function fail(string $engine, string $reason, string $key = ''): false
  {
    if ($key === '') {
      $this->lastError = $reason;
    } else {
      $this->batchErrors[$key] = $reason;
    }

    error_log(sprintf('[SerpApiClient] %s failed - %s', $engine, $reason));

    return false;
  }

  /**
   * Why the last call returned false, empty when it succeeded.
   *
   * @param string|null $key Batch key to read, null for the last single search()
   * @return string The failure cause
   */
  public function lastError(?string $key = null): string
  {
    if ($key !== null) {
      return $this->batchErrors[$key] ?? '';
    }

    return $this->lastError;
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
