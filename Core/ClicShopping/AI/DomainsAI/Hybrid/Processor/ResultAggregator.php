<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Processor;


use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\ResultFormatter;

/**
 * ResultAggregator - Aggregates results from different query type combinations (AGNOSTIC)
 *
 * This class provides generic aggregation logic that is framework and domain-agnostic.
 * Domain-specific aggregation logic (e.g., e-commerce price comparison) should be
 * implemented in subclasses in Apps/AI/{Domain}/ directories.
 *
 * Responsibilities:
 * - Provide generic aggregation for semantic + analytics combinations
 * - Provide default aggregation for other query combinations
 * - Collect and deduplicate sources from all sub-queries
 * - Handle failed sub-queries gracefully with warnings
 * - Provide hooks for domain-specific aggregation
 *
 * Requirements:
 * - REQ-5.2: Specialized aggregation for semantic + analytics
 * - REQ-5.3: Default aggregation for other combinations
 * - REQ-5.4: Source deduplication
 * - REQ-5.5: Failed sub-query handling
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubHybridQueryProcessor
 * @since 2025-12-14
 * @updated 2026-04-28 - Refactored to be domain-agnostic
 */

class ResultAggregator extends BaseQueryProcessor
{
  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    parent::__construct($debug, 'ResultAggregator');
  }

  /**
   * Process: Aggregate results based on query type combination
   *
   * @param array $input Array with 'successful' and 'failed' results
   * @param array $context Context information (query_type, etc.)
   * @return array Aggregated result
   */
  public function process($input, array $context = []): array
  {
    if (!$this->validate($input)) {
      return $this->handleError(
        "Invalid input for aggregation",
        null,
        ['success' => false, 'text_response' => 'Invalid input for result aggregation']
      );
    }

    $successfulResults = $input['successful'] ?? [];
    $failedResults = $input['failed'] ?? [];

    try {
      if ($this->debug) {
        $this->logInfo("Aggregating results", [
          'successful' => count($successfulResults),
          'failed' => count($failedResults)
        ]);
      }

      // Determine aggregation strategy based on query types
      $queryTypes = $this->extractQueryTypes($successfulResults);
      $aggregationType = $this->determineAggregationType($queryTypes);

      if ($this->debug) {
        $this->logInfo("Aggregation type determined: {$aggregationType}", ['types' => $queryTypes]);
      }

      // Aggregate based on type
      return match($aggregationType) {
        'semantic_analytics' => $this->aggregateSemanticWithAnalytics($successfulResults, $failedResults),
        default => $this->aggregateDomainSpecific($aggregationType, $successfulResults, $failedResults)
      };

    } catch (\Exception $e) {
      return $this->handleError(
        "Error aggregating results",
        $e,
        ['success' => false, 'text_response' => 'Failed to aggregate results: ' . $e->getMessage()]
      );
    }
  }

  /**
   * Validate input
   *
   * @param mixed $input Input to validate
   * @return bool True if valid
   */
  public function validate($input): bool
  {
    return is_array($input) && isset($input['successful']) && is_array($input['successful']);
  }

  /**
   * Extract query types from successful results
   *
   * @param array $successfulResults Successful sub-query results
   * @return array Array of query types
   */
  private function extractQueryTypes(array $successfulResults): array
  {
    $types = [];
    foreach ($successfulResults as $result) {
      if (isset($result['type'])) {
        $types[] = $result['type'];
      }
    }
    return array_unique($types);
  }

  /**
   * Determine aggregation type based on query types
   *
   * @param array $queryTypes Array of query types
   * @return string Aggregation type
   */
  private function determineAggregationType(array $queryTypes): string
  {
    sort($queryTypes);
    $typeKey = implode('_', $queryTypes);

    // Semantic + analytics
    if (in_array('semantic', $queryTypes, true) && in_array('analytics', $queryTypes, true)) {
      return 'semantic_analytics';
    }

    // Price comparison: analytics + web_search (domain-specific, handled by subclasses)
    if (in_array('analytics', $queryTypes, true) && in_array('web_search', $queryTypes, true)) {
      return 'price_comparison';
    }

    // Default for all other combinations
    return 'default';
  }

  /**
   * Hook for domain-specific aggregation
   *
   * Subclasses can override this method to provide domain-specific aggregation logic.
   * Default implementation falls back to aggregateDefault().
   *
   * @param string $aggregationType Aggregation type
   * @param array $successfulResults Successful results
   * @param array $failedResults Failed results
   * @return array Aggregated result
   */
  protected function aggregateDomainSpecific(string $aggregationType, array $successfulResults, array $failedResults): array
  {
    // Default: fallback to generic aggregation
    return $this->aggregateDefault($successfulResults, $failedResults);
  }

  /**
   * Aggregate semantic + analytics results
   * REQ-5.2: Specialized aggregation for semantic + analytics
   */
  private function aggregateSemanticWithAnalytics(array $successfulResults, array $failedResults): array
  {
    $semanticResponse = "";
    $analyticsData = [];
    $sources = [];

    foreach ($successfulResults as $subResult) {
      $type = $subResult['type'] ?? '';
      $result = $subResult['result'] ?? [];

      if ($type === 'semantic') {
        $semanticResponse = $result['result']['response'] ?? $result['response'] ?? '';
      } elseif ($type === 'analytics') {
        $interpretation = $result['result']['interpretation'] ?? '';
        if (!empty($interpretation)) $analyticsData[] = $interpretation;
      }
      $sources = $this->collectSources($result, $sources);
    }

    $aggregatedText = $semanticResponse;
    
    if (!empty($analyticsData)) {
      $aggregatedText .= "\n\n📊 Related Data:\n- " . implode("\n- ", $analyticsData) . "\n";
    }
    
    $aggregatedText = $this->addFailedQueryWarning($aggregatedText, $failedResults);

    if ($this->debug) {
      $this->logInfo("Semantic + analytics aggregation complete", [
        'has_semantic' => !empty($semanticResponse),
        'analytics_count' => count($analyticsData)
      ]);
    }

    return $this->formatAggregatedResult(
      'semantic_analytics',
      trim($aggregatedText),
      $analyticsData,
      $sources,
      $successfulResults,
      $failedResults
    );
  }

  /**
   * Default aggregation for other query combinations
   * REQ-5.3: Default aggregation for other combinations
   */
  private function aggregateDefault(array $successfulResults, array $failedResults): array
  {
    $aggregatedText = "";
    $aggregatedData = [];
    $sources = [];

    foreach ($successfulResults as $index => $subResult) {
      $result = $subResult['result'] ?? [];

      // Extract text response
      $response = $result['result']['response'] ?? $result['result']['interpretation'] ?? $result['response'] ?? '';
      if (!empty($response)) {
        $aggregatedText .= ($index + 1) . ". " . $response . "\n\n";
      }

      // Collect data
      if (!empty($result['result']['data'])) {
        $aggregatedData[] = ['sub_query' => $subResult['query'], 'data' => $result['result']['data']];
      }
      $sources = $this->collectSources($result, $sources);
    }

    $aggregatedText = $this->addFailedQueryWarning($aggregatedText, $failedResults);

    if ($this->debug) {
      $this->logInfo("Default aggregation complete", [
        'results' => count($successfulResults),
        'data_items' => count($aggregatedData)
      ]);
    }

    return $this->formatAggregatedResult(
      'complex_query',
      trim($aggregatedText),
      $aggregatedData,
      $sources,
      $successfulResults,
      $failedResults
    );
  }

  /**
   * Collect sources from result - REQ-5.4: Source collection
   */
  protected function collectSources(array $result, array $sources): array
  {
    if (!empty($result['result']['sources'])) {
      return array_merge($sources, $result['result']['sources']);
    }
    if (!empty($result['sources'])) {
      return array_merge($sources, $result['sources']);
    }
    return $sources;
  }

  /**
   * Deduplicate sources - REQ-5.4: Source deduplication
   * Converts Document objects to strings and removes duplicates
   */
  private function deduplicateSources(array $sources): array
  {
    if (empty($sources)) return [];

    $uniqueSources = array_unique(array_map(function($doc) {
      if (is_object($doc) && method_exists($doc, 'getContent')) return $doc->getContent();
      if (is_object($doc)) return method_exists($doc, '__toString') ? (string)$doc : get_class($doc);
      return (string)$doc;
    }, $sources));

    if ($this->debug) {
      $this->logInfo("Sources deduplicated", ['original' => count($sources), 'unique' => count($uniqueSources)]);
    }
    return $uniqueSources;
  }

  /**
   * Add failed sub-query warning to text
   * REQ-5.5: Failed sub-query handling
   * 
   * Adds a user-friendly warning message when some sub-queries fail.
   * The message provides clear information about partial results.
   * 
   * Note: Subclasses should override this method to provide localized messages
   * using their domain's language system.
   */
  protected function addFailedQueryWarning(string $text, array $failedResults): string
  {
    if (!empty($failedResults)) {
      $failedCount = count($failedResults);
      
      // Generic English message (subclasses should override for localization)
      $warningMessage = $failedCount === 1
        ? "\n⚠️ Note: Some information could not be retrieved. Results shown are partial.\n"
        : "\n⚠️ Note: Some information could not be retrieved ({$failedCount} queries failed). Results shown are partial.\n";
      
      $text .= $warningMessage;
      
      if ($this->debug) {
        $this->logWarning("Failed sub-queries detected", [
          'count' => $failedCount,
          'failed_queries' => array_map(fn($r) => $r['query'] ?? 'unknown', $failedResults)
        ]);
      }
    }
    return $text;
  }

  /**
   * Format aggregated result with standard structure
   */
  protected function formatAggregatedResult(string $type, string $textResponse, $data, array $sources, array $successfulResults, array $failedResults): array
  {
    return [
      'success' => !empty($successfulResults),
      'text_response' => $textResponse,
      'result' => [
        'type' => $type,
        'response' => $textResponse,
        'text_response' => $textResponse,
        'data' => $data,
        'sources' => $this->deduplicateSources($sources),
        'sub_results' => array_merge($successfulResults, $failedResults),
        'successful_count' => count($successfulResults),
        'failed_count' => count($failedResults)
      ]
    ];
  }
}
