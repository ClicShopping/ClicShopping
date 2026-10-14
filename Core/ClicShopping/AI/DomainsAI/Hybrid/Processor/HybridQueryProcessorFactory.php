<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Processor;

/**
 * HybridQueryProcessorFactory - Factory for creating and orchestrating components
 *
 * Initializes all SubHybridQueryProcessor components with dependency injection
 * and provides unified interface for query processing orchestration.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubHybridQueryProcessor
 * @since 2025-12-14
 */

class HybridQueryProcessorFactory
{
  /**
   * @var QueryClassifier Query classifier component
   */
  private QueryClassifier $queryClassifier;

  /**
   * @var QuerySplitter Query splitter component
   */
  private QuerySplitter $querySplitter;

  /**
   * @var ResultSynthesizer Result synthesizer component
   */
  private ResultSynthesizer $resultSynthesizer;

  /**
   * @var ResultAggregator Result aggregator component
   */
  private ResultAggregator $resultAggregator;

  /**
   * @var PromptValidator Prompt validator component
   */
  private PromptValidator $promptValidator;

  /**
   * @var bool Debug mode flag
   */
  private bool $debug;

  /**
   * @var array Component metadata
   */
  private array $metadata;

  /**
   * Constructor - Initialize all components with dependency injection
   *
   * @param bool $debug Enable debug logging for all components
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->initializeComponents();
    $this->metadata = ['factory_version' => '1.0.0', 'components_initialized' => date('c'), 'debug_enabled' => $debug];
  }

  /**
   * Initialize all components with dependency injection
   * Order: PromptValidator, QueryClassifier, QuerySplitter, ResultSynthesizer, ResultAggregator
   * 
   * Pure LLM mode: No circular dependencies needed - all components are independent.
   * QueryClassifier and QuerySplitter both use LLM directly without cross-dependencies.
   * 
   * @since 2025-12-29 (Task 5.1.6.7: Removed circular dependency injection)
   */
  private function initializeComponents(): void
  {
    $this->promptValidator = new PromptValidator($this->debug);
    $this->queryClassifier = new QueryClassifier($this->debug);
    $this->querySplitter = new QuerySplitter($this->debug, $this->queryClassifier);
    $this->resultSynthesizer = new ResultSynthesizer($this->debug);
    $this->resultAggregator = new ResultAggregator($this->debug);
  }

  // Component getters
  public function getQueryClassifier(): QueryClassifier { return $this->queryClassifier; }
  public function getQuerySplitter(): QuerySplitter { return $this->querySplitter; }
  public function getResultSynthesizer(): ResultSynthesizer { return $this->resultSynthesizer; }
  public function getResultAggregator(): ResultAggregator { return $this->resultAggregator; }
  public function getPromptValidator(): PromptValidator { return $this->promptValidator; }

  /**
   * Get factory metadata including all component information
   */
  public function getMetadata(): array
  {
    return array_merge($this->metadata, ['components' => [
      'QueryClassifier' => $this->queryClassifier->getMetadata(),
      'QuerySplitter' => $this->querySplitter->getMetadata(),
      'ResultSynthesizer' => $this->resultSynthesizer->getMetadata(),
      'ResultAggregator' => $this->resultAggregator->getMetadata(),
      'PromptValidator' => $this->promptValidator->getMetadata(),
    ]]);
  }

  /**
   * Process query through classification and splitting pipeline
   *
   * @param string $query Query to process
   * @param array $context Additional context
   * @return array Processing result with classification and sub-queries
   */
  public function processQuery(string $query, array $context = []): array
  {
    try {
      $classification = $this->queryClassifier->process($query);
      $needsSplitting = $classification['type'] === 'hybrid' || 
                       $this->querySplitter->detectMultipleIntents($query, $classification);

      if ($needsSplitting) {
        $subQueries = $this->querySplitter->process(['query' => $query, 'intent' => $classification]);
        return ['classification' => $classification, 'sub_queries' => $subQueries, 'requires_splitting' => true];
      }

      return [
        'classification' => $classification,
        'sub_queries' => [['query' => $query, 'type' => $classification['type'], 
                          'confidence' => $classification['confidence'], 'priority' => 1]],
        'requires_splitting' => false,
      ];
    } catch (\Exception $e) {
      return [
        'error' => true,
        'message' => 'Query processing failed: ' . $e->getMessage(),
        'classification' => ['type' => 'semantic', 'confidence' => 0.3],
        'sub_queries' => [['query' => $query, 'type' => 'semantic', 'confidence' => 0.3]],
      ];
    }
  }

  /**
   * Synthesize results from sub-query executions
   *
   * @param array $subQueryResults Results from sub-query executions
   * @param string $originalQuery Original query string
   * @param array $context Additional context
   * @return array Synthesized result
   */
  public function synthesizeResults(array $subQueryResults, string $originalQuery, array $context = []): array
  {
    try {
      $context['original_query'] = $originalQuery;
      $context['start_time'] = $context['start_time'] ?? microtime(true);
      return $this->resultSynthesizer->process($subQueryResults, $context);
    } catch (\Exception $e) {
      return ['type' => 'error', 'message' => 'Result synthesis failed: ' . $e->getMessage(),
              'result' => 'Failed to synthesize results from sub-queries.'];
    }
  }

  /**
   * Aggregate results from different query types
   *
   * @param array $successfulResults Successful sub-query results
   * @param array $failedResults Failed sub-query results
   * @param array $context Additional context
   * @return array Aggregated result
   */
  public function aggregateResults(array $successfulResults, array $failedResults = [], array $context = []): array
  {
    try {
      return $this->resultAggregator->process(['successful' => $successfulResults, 'failed' => $failedResults], $context);
    } catch (\Exception $e) {
      return ['success' => false, 'text_response' => 'Result aggregation failed: ' . $e->getMessage(),
              'result' => ['type' => 'error', 'response' => 'Failed to aggregate results.']];
    }
  }

  /**
   * Validate prompt before LLM call
   *
   * @param string $prompt Prompt to validate
   * @param string $context Context where validation is performed
   * @return string Validated prompt
   */
  public function validatePrompt(string $prompt, string $context = 'unknown'): string
  {
    return $this->promptValidator->process($prompt, ['context' => $context]);
  }
}
