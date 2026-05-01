<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\InterfacesAI;



/**
 * HybridQueryProcessorInterface
 *
 * Base interface for all HybridQueryProcessor components.
 * Defines the common contract that all specialized processors must implement.
 *
 * This interface ensures consistency across all Hybrid query processor components
 * (QueryClassifier, QuerySplitter, ResultSynthesizer, ResultAggregator, PromptValidator)
 * and provides a unified API for query processing operations.
 *
 * Note: This interface was renamed from QueryProcessorInterface to clarify its purpose
 * for hybrid query processing components, distinct from the orchestration-level QueryProcessor.
 */
 
interface HybridQueryProcessorInterface
{
  /**
   * Process the input data and return the result
   *
   * This is the main processing method that each component must implement.
   * The exact behavior depends on the specific component:
   * - QueryClassifier: Classifies query type and returns classification result
   * - QuerySplitter: Splits complex queries into sub-queries
   * - ResultSynthesizer: Synthesizes results from multiple sources
   * - ResultAggregator: Aggregates results from different query types
   * - PromptValidator: Validates and sanitizes prompts
   *
   * @param mixed $input The input data to process (type varies by component)
   * @param array $context Additional context for processing (optional)
   * @return mixed The processed result (type varies by component)
   * @throws \Exception If processing fails
   */
  public function process($input, array $context = []);

  /**
   * Validate the input data before processing
   *
   * Performs validation checks on the input to ensure it meets the requirements
   * for processing. This method should be called before process() to prevent
   * invalid data from being processed.
   *
   * @param mixed $input The input data to validate
   * @return bool True if input is valid, false otherwise
   */
  public function validate($input): bool;

  /**
   * Get metadata about the processor
   *
   * Returns metadata information about the processor component, including:
   * - Component name
   * - Version
   * - Supported operations
   * - Configuration options
   * - Performance metrics (if available)
   *
   * This method is useful for debugging, monitoring, and documentation purposes.
   *
   * @return array Metadata information about the processor
   */
  public function getMetadata(): array;
}
