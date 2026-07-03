<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services;

use ClicShopping\OM\Cache;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * LLMServiceWrapper
 *
 * Wrapper around ResponseProcessor for SEO-specific LLM interactions.
 * Provides retry logic, response caching, and structured response parsing.
 *
 * Key Features:
 * - LLM response caching (1-hour TTL)
 * - Retry logic with exponential backoff (3 retries)
 * - Structured JSON response parsing
 * - Error handling for timeout, rate limit, API errors
 * - Pure LLM mode (no pattern matching)
 *
 * Architecture:
   * - Uses Gpt::getGptResponse() for LLM calls (dynamic model)
 * - Caches all LLM responses to reduce API costs
 * - Implements exponential backoff for retries
 * - Extracts JSON from LLM responses
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services
 * @since 2026-03-02
 */
class LLMServiceWrapper
{
  private const CACHE_TTL = 3600; // 1 hour in seconds
  private const CACHE_PREFIX = 'seo_llm_';
  private const MAX_RETRIES = 3;
  private const INITIAL_BACKOFF_MS = 1000; // 1 second
  
  private bool $debug;
  private string $defaultModel;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param string $defaultModel Default LLM model to use
   */
  public function __construct(bool $debug = false, string $defaultModel = '')
  {
    $this->debug = $debug;
    if ($defaultModel !== '') {
      $this->defaultModel = $defaultModel;
    } elseif (\defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL') && CLICSHOPPING_APP_CHATGPT_CH_MODEL !== '') {
      $this->defaultModel = GPT::defaultModel();
    } else {
      $this->defaultModel = GPT::getTechnicalFallbackModel();
    }
  }

  /**
   * Generate structured JSON response from LLM
   *
   * @param string $prompt Prompt to send to LLM (should request JSON format)
   * @param array $options Options for LLM call
   * @return array Parsed JSON response
   * @throws \Exception If JSON parsing fails or all retries fail
   */
  public function generateStructuredResponse(string $prompt, array $options = []): array
  {
    // Generate response
    $response = $this->generateResponse($prompt, $options);

    // Extract and parse JSON
    $json = $this->extractJson($response);

    if ($json === null) {
      throw new \Exception("Failed to extract valid JSON from LLM response");
    }

    return $json;
  }

  /**
   * Generate LLM response with retry logic and caching
   *
   * @param string $prompt Prompt to send to LLM
   * @param array $options Options for LLM call
   *   - model: string (default: configured GPT model)
   *   - maxTokens: int (default: 500)
   *   - temperature: float (default: 0.7)
   *   - cache: bool (default: true)
   * @return string LLM response text
   * @throws \Exception If all retries fail
   */
  public function generateResponse(string $prompt, array $options = []): string
  {
    // Extract options
    $model = $options['model'] ?? $this->defaultModel;
    $maxTokens = $options['maxTokens'] ?? 500;
    $temperature = $options['temperature'] ?? 0.7;
    $useCache = $options['cache'] ?? true;

    // Check cache first
    if ($useCache) {
      $cacheKey = $this->getCacheKey($prompt, $model);
      $cached = $this->getFromCache($cacheKey);

      if ($cached !== null) {
        if ($this->debug) {
          error_log("[LLMServiceWrapper] Cache HIT for model: {$model}");
        }
        return $cached;
      }

      if ($this->debug) {
        error_log("[LLMServiceWrapper] Cache MISS for model: {$model}");
      }
    }

    // Generate response with retry logic
    $response = $this->generateWithRetry($prompt, $model, $maxTokens, $temperature);

    // Clean response
    $response = $this->cleanResponse($response);

    // Cache the result
    if ($useCache) {
      $this->saveToCache($cacheKey, $response);
    }

    return $response;
  }

  /**
   * Generate cache key for LLM response
   *
   * @param string $prompt Prompt text
   * @param string $model Model name
   * @return string Cache key
   */
  private function getCacheKey(string $prompt, string $model): string
  {
    $hash = md5($prompt . $model);
    $safeModel = preg_replace('/[^A-Za-z0-9_-]/', '-', $model);
    return self::CACHE_PREFIX . "{$safeModel}_{$hash}";
  }

  /**
   * Get LLM response from cache
   *
   * @param string $key Cache key
   * @return string|null Cached response or null if not found
   */
  private function getFromCache(string $key): ?string
  {
    $cache = new Cache($key, 'SEO');

    $expireMinutes = (int)ceil(self::CACHE_TTL / 60);
    if ($cache->exists((string)$expireMinutes)) {
      return $cache->get();
    }

    return null;
  }

  /**
   * Generate response with exponential backoff retry logic
   *
   * @param string $prompt Prompt text
   * @param string $model Model name
   * @param int $maxTokens Max tokens
   * @param float $temperature Temperature
   * @return string LLM response
   * @throws \Exception If all retries fail
   */
  private function generateWithRetry(
    string $prompt,
    string $model,
    int $maxTokens,
    float $temperature
  ): string
  {
    $attempt = 0;
    $lastException = null;

    while ($attempt < self::MAX_RETRIES) {
      try {
        if ($this->debug && $attempt > 0) {
          error_log("[LLMServiceWrapper] Retry attempt {$attempt} for model: {$model}");
        }

        $response = Gpt::getGptResponse($prompt, $maxTokens, $temperature, $model, 1);
        if ($response === false) {
          throw new \Exception('LLM response empty or failed');
        }

        if ($this->debug) {
          error_log("[LLMServiceWrapper] LLM call SUCCESS on attempt {$attempt}");
        }

        return $response;

      } catch (\Exception $e) {
        $lastException = $e;
        $attempt++;

        if ($this->debug) {
          error_log("[LLMServiceWrapper] LLM call FAILED on attempt {$attempt}: " . $e->getMessage());
        }

        // Check if we should retry
        if ($attempt >= self::MAX_RETRIES) {
          break;
        }

        // Exponential backoff
        $backoffMs = self::INITIAL_BACKOFF_MS * pow(2, $attempt - 1);

        if ($this->debug) {
          error_log("[LLMServiceWrapper] Backing off for {$backoffMs}ms before retry");
        }

        usleep($backoffMs * 1000); // Convert to microseconds
      }
    }

    // All retries failed
    error_log("[LLMServiceWrapper] All {$attempt} retries FAILED for model: {$model}");
    throw new \Exception(
      "LLM generation failed after " . self::MAX_RETRIES . " retries: " .
      ($lastException ? $lastException->getMessage() : "Unknown error")
    );
  }

  /**
   * Clean LLM response by removing prefixes and extra whitespace
   *
   * @param string $text Raw LLM response
   * @return string Clean response
   */
  private function cleanResponse(string $text): string
  {
    // Remove common LLM prefixes
    $patterns = [
      '/^(Response:|Answer:|Result:|Output:)\s*/i',
      '/^(Here is|Here\'s|This is)\s+(the|a|an)\s+(response|answer|result):\s*/i',
    ];

    $clean = $text;
    foreach ($patterns as $pattern) {
      $clean = preg_replace($pattern, '', $clean);
    }

    // Trim whitespace
    $clean = trim($clean);

    // Clean HTML
    $clean = HTMLOverrideCommon::cleanHtmlForEmbedding($clean);

    return $clean;
  }

  /**
   * Save LLM response to cache
   *
   * @param string $key Cache key
   * @param string $value Response to cache
   * @return void
   */
  private function saveToCache(string $key, string $value): void
  {
    $cache = new Cache($key, 'SEO');
    $cache->save($value, ['ttl_seconds' => self::CACHE_TTL]);
  }

  /**
   * Extract JSON from LLM response.
   *
   * The LLM sometimes wraps its output in markdown fences (```json ... ```)
   * and the JSON object can be deeply nested (e.g. quality response with
   * dimension_scores, issues arrays, etc.).  The previous non-greedy regex
   * (\{.*?\}) stopped at the first closing brace it found, producing a
   * truncated/invalid match.
   *
   * Strategy (in order):
   *  1. Direct parse — works when the model returns raw JSON.
   *  2. Strip markdown fence, then parse — handles ```json … ``` wrapping.
   *  3. Balanced-brace scan — finds the outermost { … } or [ … ] in
   *     mixed text, respecting nesting depth.
   *
   * @param string $response LLM response text
   * @return array|null Parsed JSON or null if extraction fails
   */
  private function extractJson(string $response): ?array
  {
    // 1. Direct parse (fastest path — no fences, clean JSON)
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
      return $json;
    }

    // 2. Strip markdown code fences then try again
    $stripped = preg_replace('/^```(?:json)?\s*/i', '', trim($response));
    $stripped = preg_replace('/\s*```\s*$/i', '', $stripped);
    $json = json_decode($stripped, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
      return $json;
    }

    // 3. Balanced-brace / balanced-bracket scan
    //    Walk the string looking for the first { or [ and track depth.
    //    This handles multi-line, deeply nested structures.
    $openers  = ['{' => '}', '[' => ']'];
    $response = $stripped ?: $response; // prefer already-stripped version

    for ($i = 0, $len = strlen($response); $i < $len; $i++) {
      $ch = $response[$i];

      if (!isset($openers[$ch])) {
        continue;
      }

      // Found a potential JSON start — scan for the matching closer
      $closer = $openers[$ch];
      $depth  = 0;
      $inStr  = false;
      $escape = false;

      for ($j = $i; $j < $len; $j++) {
        $c = $response[$j];

        if ($escape) {
          $escape = false;
          continue;
        }

        if ($c === '\\' && $inStr) {
          $escape = true;
          continue;
        }

        if ($c === '"') {
          $inStr = !$inStr;
          continue;
        }

        if ($inStr) {
          continue;
        }

        if ($c === $ch) {
          $depth++;
        } elseif ($c === $closer) {
          $depth--;
          if ($depth === 0) {
            $candidate = substr($response, $i, $j - $i + 1);
            $json      = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
              return $json;
            }
            // Not valid — keep scanning for the next opener
            break;
          }
        }
      }
    }

    // All strategies exhausted
    if ($this->debug) {
      error_log('[LLMServiceWrapper] Failed to extract JSON from response: ' . substr($response, 0, 200));
    }

    return null;
  }
}
