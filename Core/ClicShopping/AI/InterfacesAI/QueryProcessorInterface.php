<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * QueryProcessorInterface
 *
 * Interface for orchestration-level query processing operations.
 * Defines the contract for the QueryProcessor class that handles:
 * - Query validation and retry logic
 * - Parallel execution of operations
 * - Context decision and enrichment
 * - Query-context relation analysis
 *
 * This interface is distinct from HybridQueryProcessorInterface which is used
 * for hybrid query processing components (QueryClassifier, QuerySplitter, etc.).
 *
 * Requirements:
 * - REQ-8: Query Processing Extraction
 * - REQ-17: Backward Compatibility Guarantee
 * - REQ-25: Code Quality and Standards Compliance

 */
interface QueryProcessorInterface
{
  /**
   * Process query with retry logic for temporary errors
   *
   * Executes the provided callback with automatic retry on temporary errors.
   * Distinguishes between temporary errors (network issues, timeouts) and
   * permanent errors (validation failures, logic errors).
   *
   * @param string $query User query to process
   * @param array $options Processing options (max_retries, retry_delay, etc.)
   * @param callable $processCallback Callback function to execute
   * @return array Processing result with status and data
   * @throws \Exception If max retries exceeded or permanent error occurs
   */
  public function processWithRetry(string $query, array $options, callable $processCallback): array;

  /**
   * Execute parallel operations for query processing
   *
   * Runs multiple operations in parallel to improve performance:
   * - Context retrieval from memory
   * - Query translation to English
   * - Entity extraction
   *
   * @param string $query User query to process
   * @return array Results from parallel operations with timing metrics
   */
  public function executeParallelOperations(string $query): array;

  /**
   * Process context decision for query
   *
   * Determines whether to use context for the query based on:
   * - Query-context relevance
   * - Context freshness
   * - Query complexity
   *
   * @param string $query User query
   * @param array $rawContext Raw context from memory
   * @return array Processed context decision with filtered context
   */
  public function processContextDecision(string $query, array $rawContext): array;

  /**
   * Analyze query-context relation
   *
   * Analyzes the relationship between the query and available context:
   * - Semantic similarity
   * - Entity overlap
   * - Temporal relevance
   *
   * @param string $query User query
   * @param array $context Available context
   * @return array Analysis result with relevance scores
   */
  public function analyzeQueryContextRelation(string $query, array $context): array;

  /**
   * Build enriched context from analysis
   *
   * Enriches context with additional information based on analysis:
   * - Related entities
   * - Historical patterns
   * - Domain-specific metadata
   *
   * @param array $context Base context
   * @param array $contextAnalysis Context analysis results
   * @return array Enriched context with additional metadata
   */
  public function buildEnrichedContext(array $context, array $contextAnalysis): array;
}
