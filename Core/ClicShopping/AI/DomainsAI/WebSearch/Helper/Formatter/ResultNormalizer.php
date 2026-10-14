<?php
/**
 * ResultNormalizer.php
 *
 * Result normalization component for the unified websearch engine.
 * Transforms multi-source engine results into unified format with deduplication
 * and quality score calculation.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter
 * @since 2026-05-05
 *
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter;

use ClicShopping\AI\DomainsAI\WebSearch\Patterns\LocationPatterns;

/**
 * ResultNormalizer Class
 *
 * Transforms multi-source results from different search engines into a unified format.
 * Handles result merging, deduplication, and quality score calculation.
 *
 * Key Features:
 * - Merge results from multiple engines (hybrid mode)
 * - Deduplicate shopping results using normalized title + price hashing
 * - Calculate quality score based on result completeness and diversity
 * - Preserve engine-specific metadata for traceability
 * - Stopword removal for title normalization
 *
 * Quality Score Calculation:
 * - AI Overview presence: 0.3
 * - Shopping results count: 0.4 (max at 10 results)
 * - Organic results count: 0.3 (max at 10 results)
 * - Hybrid bonus: +10% if multiple engines used
 * - Cap at 1.0
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter
 */
class ResultNormalizer
{
  /**
   * @var bool Debug mode flag
   */
  private bool $debug;

  /**
   * @var array Stopwords cache for performance
   */
  private array $stopwordsCache = [];

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Normalize engine results into unified format
   *
   * Transforms engine-specific results into a unified structure with:
   * - Merged results from multiple engines (hybrid mode)
   * - Deduplicated shopping results
   * - Calculated quality score
   * - Preserved engine metadata
   *
   * @param array $engineResults Array of engine results (single or multiple)
   * @param string $query Original search query
   * @param array $metadata Additional metadata (mode_type, engines_used, routing_method)
   * @return array Unified result structure
   */
  public function normalize(array $engineResults, string $query, array $metadata = []): array
  {
    if ($this->debug) {
      error_log(sprintf(
        '[ResultNormalizer] Normalizing %d engine result(s) for query: %s',
        count($engineResults),
        $query
      ));
    }

    // Initialize unified result structure
    $unified = [
      'success' => true,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'metadata' => [
        'mode_type' => $metadata['mode_type'] ?? 'unknown',
        'engines_used' => $metadata['engines_used'] ?? [],
        'routing_method' => $metadata['routing_method'] ?? 'unknown',
        'engines' => [],
      ],
      'quality_score' => 0.0,
    ];

    // Handle single engine result (already merged by WebSearchExecutor for hybrid)
    if (isset($engineResults['success'])) {
      // This is already a merged result from WebSearchExecutor
      $unified = array_merge($unified, $engineResults);
      
      // Ensure metadata is preserved
      $unified['metadata'] = array_merge($unified['metadata'], $metadata);
      
      // Deduplicate shopping results
      if (!empty($unified['shopping_results'])) {
        $unified['shopping_results'] = $this->deduplicateShoppingResults($unified['shopping_results']);
      }
      
      // Calculate quality score
      $unified['quality_score'] = $this->calculateQualityScore($unified);
      
      if ($this->debug) {
        error_log(sprintf(
          '[ResultNormalizer] Normalized result - Quality: %.2f, Shopping: %d, Organic: %d',
          $unified['quality_score'],
          count($unified['shopping_results']),
          count($unified['organic_results'])
        ));
      }
      
      return $unified;
    }

    // Handle array of engine results (legacy path, should not occur with current WebSearchExecutor)
    foreach ($engineResults as $result) {
      if (!isset($result['success']) || !$result['success']) {
        continue;
      }

      // Merge AI overview (take first non-null)
      if (!empty($result['ai_overview']) && $unified['ai_overview'] === null) {
        $unified['ai_overview'] = $result['ai_overview'];
      }

      // Merge organic results
      if (!empty($result['organic_results'])) {
        $unified['organic_results'] = array_merge(
          $unified['organic_results'],
          $result['organic_results']
        );
      }

      // Merge shopping results
      if (!empty($result['shopping_results'])) {
        $unified['shopping_results'] = array_merge(
          $unified['shopping_results'],
          $result['shopping_results']
        );
      }

      // Collect engine metadata
      if (!empty($result['metadata'])) {
        $unified['metadata']['engines'][] = [
          'mode' => $result['metadata']['mode'] ?? 'unknown',
          'execution_time' => $result['metadata']['execution_time'] ?? 0,
          'result_count' => $result['metadata']['result_count'] ?? 0,
          'requested_count' => $result['metadata']['requested_count'] ?? 0,
          'results_available' => $result['metadata']['results_available'] ?? true,
        ];
      }
    }

    // Deduplicate shopping results
    if (!empty($unified['shopping_results'])) {
      $unified['shopping_results'] = $this->deduplicateShoppingResults($unified['shopping_results']);
    }

    // Calculate quality score
    $unified['quality_score'] = $this->calculateQualityScore($unified);

    if ($this->debug) {
      error_log(sprintf(
        '[ResultNormalizer] Normalized result - Quality: %.2f, Shopping: %d, Organic: %d',
        $unified['quality_score'],
        count($unified['shopping_results']),
        count($unified['organic_results'])
      ));
    }

    return $unified;
  }

  /**
   * Deduplicate shopping results using normalized title + price hashing
   *
   * Implements requirement 11.3: Deduplication using normalized title + price hashing (md5).
   * Removes stopwords from titles before hashing to improve matching accuracy.
   *
   * Algorithm:
   * 1. Normalize title (lowercase, remove punctuation, remove stopwords)
   * 2. Generate hash: md5(normalized_title + '|' + extracted_price)
   * 3. Keep first occurrence of each hash
   *
   * @param array $shoppingResults Array of shopping results
   * @return array Deduplicated shopping results
   */
  private function deduplicateShoppingResults(array $shoppingResults): array
  {
    if (empty($shoppingResults)) {
      return [];
    }

    $seen = [];
    $deduplicated = [];
    $duplicateCount = 0;

    foreach ($shoppingResults as $result) {
      // Generate hash for deduplication
      $hash = $this->generateDeduplicationHash($result);

      if (!isset($seen[$hash])) {
        $seen[$hash] = true;
        $deduplicated[] = $result;
      } else {
        $duplicateCount++;
        
        if ($this->debug) {
          error_log(sprintf(
            '[ResultNormalizer] Duplicate detected: %s (hash: %s)',
            $result['title'] ?? 'unknown',
            substr($hash, 0, 8)
          ));
        }
      }
    }

    if ($this->debug && $duplicateCount > 0) {
      error_log(sprintf(
        '[ResultNormalizer] Deduplication: %d duplicates removed, %d unique results',
        $duplicateCount,
        count($deduplicated)
      ));
    }

    return $deduplicated;
  }

  /**
   * Generate deduplication hash for a shopping result
   *
   * Creates a hash based on normalized title and price for deduplication.
   * Stopwords are removed from the title to improve matching accuracy.
   *
   * @param array $result Shopping result
   * @return string MD5 hash for deduplication
   */
  private function generateDeduplicationHash(array $result): string
  {
    // Extract title and price
    $title = $result['title'] ?? '';
    $price = $result['extracted_price'] ?? 0.0;

    // Normalize title
    $normalizedTitle = $this->normalizeTitle($title);

    // Generate hash: md5(normalized_title + '|' + price)
    $hashInput = $normalizedTitle . '|' . number_format($price, 2, '.', '');
    
    return md5($hashInput);
  }

  /**
   * Normalize title for deduplication
   *
   * Implements requirement 11.3: Stopword removal for title normalization.
   *
   * Normalization steps:
   * 1. Convert to lowercase
   * 2. Remove punctuation
   * 3. Remove stopwords (English and French)
   * 4. Trim whitespace
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

    // Remove stopwords
    $normalized = $this->removeStopwords($normalized);

    // Normalize whitespace
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = trim($normalized);

    return $normalized;
  }

  /**
   * Remove stopwords from text
   *
   * Removes common stopwords (English and French) to improve matching accuracy.
   * Uses LocationPatterns for stopword configuration.
   *
   * @param string $text Text to process
   * @return string Text with stopwords removed
   */
  private function removeStopwords(string $text): string
  {
    // Get stopwords for both English and French
    if (empty($this->stopwordsCache)) {
      $this->stopwordsCache = array_merge(
        LocationPatterns::getStopwords('en'),
        LocationPatterns::getStopwords('fr')
      );
    }

    // Split text into words
    $words = explode(' ', $text);

    // Filter out stopwords
    $filtered = array_filter($words, function($word) {
      return !in_array($word, $this->stopwordsCache, true);
    });

    return implode(' ', $filtered);
  }

  /**
   * Calculate quality score based on result completeness and diversity
   *
   * Implements requirement 11.4: Quality score calculation.
   *
   * Formula:
   * - AI Overview presence: 0.3
   * - Shopping results count: 0.4 (max at 10 results)
   * - Organic results count: 0.3 (max at 10 results)
   * - Hybrid bonus: +10% if multiple engines used
   * - Cap at 1.0
   *
   * @param array $unified Unified result structure
   * @return float Quality score (0.0 - 1.0)
   */
  private function calculateQualityScore(array $unified): float
  {
    $score = 0.0;

    // AI Overview presence: 0.3
    if (!empty($unified['ai_overview'])) {
      $score += 0.3;
    }

    // Shopping results count: 0.4 (max at 10 results)
    $shoppingCount = count($unified['shopping_results'] ?? []);
    $shoppingScore = min($shoppingCount / 10.0, 1.0) * 0.4;
    $score += $shoppingScore;

    // Organic results count: 0.3 (max at 10 results)
    $organicCount = count($unified['organic_results'] ?? []);
    $organicScore = min($organicCount / 10.0, 1.0) * 0.3;
    $score += $organicScore;

    // Hybrid bonus: +10% if multiple engines used
    $enginesUsed = $unified['metadata']['engines_used'] ?? [];
    if (count($enginesUsed) > 1) {
      $score *= 1.1; // 10% bonus
      
      if ($this->debug) {
        error_log('[ResultNormalizer] Hybrid bonus applied: +10%');
      }
    }

    // Cap at 1.0
    $score = min($score, 1.0);

    if ($this->debug) {
      error_log(sprintf(
        '[ResultNormalizer] Quality score breakdown - AI: %s, Shopping: %.2f, Organic: %.2f, Total: %.2f',
        !empty($unified['ai_overview']) ? '0.30' : '0.00',
        $shoppingScore,
        $organicScore,
        $score
      ));
    }

    return round($score, 2);
  }

  /**
   * Get deduplication statistics
   *
   * Returns statistics about deduplication for monitoring and debugging.
   *
   * @param array $originalResults Original shopping results
   * @param array $deduplicatedResults Deduplicated shopping results
   * @return array Statistics with keys: original_count, deduplicated_count, duplicates_removed, deduplication_rate
   */
  public function getDeduplicationStats(array $originalResults, array $deduplicatedResults): array
  {
    $originalCount = count($originalResults);
    $deduplicatedCount = count($deduplicatedResults);
    $duplicatesRemoved = $originalCount - $deduplicatedCount;
    $deduplicationRate = $originalCount > 0 
      ? round(($duplicatesRemoved / $originalCount) * 100, 2) 
      : 0.0;

    return [
      'original_count' => $originalCount,
      'deduplicated_count' => $deduplicatedCount,
      'duplicates_removed' => $duplicatesRemoved,
      'deduplication_rate' => $deduplicationRate,
    ];
  }

  /**
   * Validate unified result structure
   *
   * Validates that the unified result has all required fields.
   * Useful for testing and debugging.
   *
   * @param array $unified Unified result structure
   * @return bool True if valid, false otherwise
   */
  public function validateUnifiedResult(array $unified): bool
  {
    $requiredFields = [
      'success',
      'query',
      'ai_overview',
      'organic_results',
      'shopping_results',
      'metadata',
      'quality_score',
    ];

    foreach ($requiredFields as $field) {
      if (!array_key_exists($field, $unified)) {
        if ($this->debug) {
          error_log("[ResultNormalizer] Validation failed: missing field '{$field}'");
        }
        return false;
      }
    }

    // Validate metadata structure
    $requiredMetadataFields = [
      'mode_type',
      'engines_used',
      'routing_method',
      'engines',
    ];

    foreach ($requiredMetadataFields as $field) {
      if (!array_key_exists($field, $unified['metadata'])) {
        if ($this->debug) {
          error_log("[ResultNormalizer] Validation failed: missing metadata field '{$field}'");
        }
        return false;
      }
    }

    // Validate quality score range
    if ($unified['quality_score'] < 0.0 || $unified['quality_score'] > 1.0) {
      if ($this->debug) {
        error_log(sprintf(
          "[ResultNormalizer] Validation failed: quality_score out of range (%.2f)",
          $unified['quality_score']
        ));
      }
      return false;
    }

    return true;
  }
}
