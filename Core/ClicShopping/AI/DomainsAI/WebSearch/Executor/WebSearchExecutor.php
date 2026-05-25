<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Executor;

use ClicShopping\AI\DomainsAI\WebSearch\Exception\ConfigurationException;
use ClicShopping\AI\DomainsAI\WebSearch\Logger\WebSearchLogger;
use ClicShopping\AI\DomainsAI\WebSearch\Processor\RoutingDecision;
use ClicShopping\AI\InterfacesAI\WebSearchInterface;
use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;

/**
 * WebSearchExecutor - Orchestrator for multi-mode execution
 *
 * This component orchestrates the execution of one or more search engines based on
 * the routing decision from IntentRouter. It supports both single-mode and hybrid-mode
 * execution with parallel HTTP requests using Guzzle for optimal performance.
 *
 * Key Features:
 * - Single-mode execution: Execute one engine and return results
 * - Hybrid-mode execution: Execute multiple engines sequentially via search() calls,
 *   preserving full context (target_site, location_params) across all engines
 * - Graceful error handling: Continue if at least one engine succeeds
 * - Configuration validation: Ensure engines are properly configured
 * - Execution metrics logging: Track performance and result counts
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Executor
 */
class WebSearchExecutor
{
  private const DEFAULT_TIMEOUT = 10; // seconds

  private WebSearchLogger $logger;
  private WebSearchEngineRegistry $registry;
  private bool $debug;

  /**
   * Constructor
   *
   * Acquires the shared {@see WebSearchEngineRegistry} so engine lookups go
   * through the agnostic registration system. Built-in Core providers
   * (Mode A/B/C/E) and any Apps/AI/{Domain}/-registered providers are both
   * resolved transparently by mode identifier.
   */
  public function __construct()
  {
    $this->logger = new WebSearchLogger();
    $this->registry = WebSearchEngineRegistry::getInstance();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Execute search based on routing decision
   *
   * This method orchestrates the execution of one or more search engines based on
   * the routing decision. For single mode, it executes the engine directly. For
   * hybrid mode, it executes multiple engines in parallel using Guzzle promises.
   *
   * @param RoutingDecision $routing Routing decision from IntentRouter
   * @param string $query Original search query
   * @param array $options Optional parameters including:
   *                       - max_results: Maximum number of results per engine
   *                       - location_params: Location parameters (gl, hl, currency)
   * @return array Unified result structure with results from all engines
   * @throws \RuntimeException If all engines fail or configuration is invalid
   */
  public function execute(RoutingDecision $routing, string $query, array $options = []): array
  {
    $startTime = microtime(true);

    try {
      // Validate configuration
      $this->validateConfiguration($routing);

      // Get selected modes
      $selectedModes = $routing->getSelectedModes();

      if ($this->debug) {
        error_log(sprintf(
          '[WebSearchExecutor] Executing %d mode(s): %s',
          count($selectedModes),
          implode(', ', $selectedModes)
        ));
      }

      // Execute based on mode count
      if (count($selectedModes) === 1) {
        // Single mode execution
        $results = $this->executeSingleMode($selectedModes[0], $query, $options, $routing);
      } else {
        // Hybrid mode execution (parallel)
        $results = $this->executeHybridMode($selectedModes, $query, $options, $routing);
      }

      // Add execution metadata
      $results['metadata']['routing_decision'] = $routing->toArray();
      $results['metadata']['total_execution_time'] = microtime(true) - $startTime;
      $results['metadata']['is_hybrid_mode'] = $routing->isHybridMode();

      // Propagate user_notification from routing decision (e.g. site not in DB from ModeSelector)
      // Only set if not already populated by an engine (engine notifications take precedence)
      $routingNotification = $routing->getMetadata()['user_notification'] ?? null;
      if ($routingNotification !== null && empty($results['metadata']['user_notification'])) {
        $results['metadata']['user_notification'] = $routingNotification;
      }

      // Log execution metrics
      $this->logExecutionMetrics($query, $routing, $results, microtime(true) - $startTime);

      return $results;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log('[WebSearchExecutor] Execution error: ' . $e->getMessage());
      }

      throw new \RuntimeException(
        'WebSearchExecutor execution failed: ' . $e->getMessage(),
        0,
        $e
      );
    }
  }

  /**
   * Execute single mode
   *
   * @param string $mode Mode identifier
   * @param string $query Search query
   * @param array $options Options array
   * @param RoutingDecision $routing Routing decision
   * @return array Result structure
   */
  private function executeSingleMode(
    string $mode,
    string $query,
    array $options,
    RoutingDecision $routing
  ): array {
    $startTime = microtime(true);

    // Instantiate engine
    $engine = $this->instantiateEngine($mode);

    // Prepare options with location params
    $engineOptions = $this->prepareEngineOptions($options, $routing);

    // Per-engine query-type preference is declared by the provider, so Core
    // never needs to hard-code which mode IDs prefer the canonical product query.
    $provider = $this->registry->getProvider($mode);
    $usesProductQuery = $provider !== null && $provider->usesProductQuery();
    $productQuery = $routing->getProduct();
    $engineQuery = ($usesProductQuery && $productQuery !== null) ? $productQuery : $query;

    // Execute search
    $result = $engine->search($engineQuery, $engineOptions);

    // Add execution metadata
    $result['metadata']['mode_type'] = $mode;
    $result['metadata']['engines_used'] = [$mode];
    $result['metadata']['routing_method'] = $routing->getRoutingMethod();
    $result['metadata']['execution_time'] = microtime(true) - $startTime;

    if ($this->debug) {
      error_log(sprintf(
        '[WebSearchExecutor] Single mode completed: %s in %.3fs',
        $mode,
        $result['metadata']['execution_time']
      ));
    }

    return $result;
  }

  /**
   * Execute hybrid mode using direct search() calls per engine
   *
   * Each engine's search() method is called sequentially with the full options context.
   * This preserves all context variables (target_site, location_params, etc.) throughout
   * the execution chain, avoiding the URL serialization/deserialization that caused
   * variable transmission breaks with the previous parallel HTTP approach.
   *
   * Mode C (RagWebSearchEngine) is incompatible with single-URL parallel execution
   * because it makes N requests (one per active site). Using search() directly handles
   * this correctly without requiring buildSerpApiUrl() / parseResponse().
   *
   * @param array $modes Array of mode identifiers
   * @param string $query Search query
   * @param array $options Options array
   * @param RoutingDecision $routing Routing decision
   * @return array Merged result structure
   */
  private function executeHybridMode(
    array $modes,
    string $query,
    array $options,
    RoutingDecision $routing
  ): array {
    $startTime = microtime(true);
    $engineResults = [];
    $successCount = 0;

    // Prepare engine options ONCE — all context preserved for every engine
    $engineOptions = $this->prepareEngineOptions($options, $routing);

    // For shopping/scraping engines, use only the product name (stripped of comparison language)
    // Mode A (AI Overview) needs the full query for context
    $productQuery = $routing->getProduct();

    foreach ($modes as $mode) {
      $engineStartTime = microtime(true);

      try {
        $engine = $this->instantiateEngine($mode);

        // Per-engine query-type preference is declared by the provider
        $provider = $this->registry->getProvider($mode);
        $usesProductQuery = $provider !== null && $provider->usesProductQuery();
        $engineQuery = ($usesProductQuery && $productQuery !== null) ? $productQuery : $query;

        if ($this->debug) {
          error_log(sprintf(
            '[WebSearchExecutor] Executing engine %s via search() — query: "%s"',
            $mode,
            $engineQuery
          ));
        }

        // Direct search() call — full context preserved, no URL serialization
        $result = $engine->search($engineQuery, $engineOptions);

        if ($result['success']) {
          $result['metadata']['execution_time'] = microtime(true) - $engineStartTime;
          $result['metadata']['mode'] = $mode;
          $engineResults[] = $result;
          $successCount++;

          if ($this->debug) {
            error_log(sprintf(
              '[WebSearchExecutor] Engine %s succeeded in %.3fs — %d shopping, %d rag',
              $mode,
              $result['metadata']['execution_time'],
              count($result['shopping_results'] ?? []),
              count($result['rag_results'] ?? [])
            ));
          }
        } else {
          if ($this->debug) {
            error_log(sprintf(
              '[WebSearchExecutor] Engine %s returned error: %s',
              $mode,
              $result['metadata']['error'] ?? 'unknown'
            ));
          }
        }
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log(sprintf(
            '[WebSearchExecutor] Engine %s failed with exception: %s',
            $mode,
            $e->getMessage()
          ));
        }
      }
    }

    if ($successCount === 0) {
      throw new \RuntimeException('All engines failed in hybrid mode');
    }

    $mergedResult = $this->mergeEngineResults($engineResults, $query);

    $mergedResult['metadata']['mode_type'] = 'hybrid';
    $mergedResult['metadata']['engines_used'] = $modes;
    $mergedResult['metadata']['routing_method'] = $routing->getRoutingMethod();
    $mergedResult['metadata']['total_execution_time'] = microtime(true) - $startTime;
    $mergedResult['metadata']['successful_engines'] = $successCount;
    $mergedResult['metadata']['failed_engines'] = count($modes) - $successCount;

    if ($this->debug) {
      error_log(sprintf(
        '[WebSearchExecutor] Hybrid mode completed: %d/%d engines succeeded in %.3fs',
        $successCount,
        count($modes),
        $mergedResult['metadata']['total_execution_time']
      ));
    }

    return $mergedResult;
  }

  /**
   * Merge results from multiple engines
   *
   * @param array $engineResults Array of engine results
   * @param string $query Original query
   * @return array Merged result structure
   */
  private function mergeEngineResults(array $engineResults, string $query): array
  {
    $merged = [
      'success' => true,
      'query' => $query,
      'ai_overview' => null,
      'organic_results' => [],
      'shopping_results' => [],
      'rag_results' => [],
      'trends_data' => [],
      'metadata' => [
        'engines' => [],
      ],
    ];

    // **DEBUG: Log merge start**
    if ($this->debug) {
      error_log(sprintf(
        '[WebSearchExecutor::mergeEngineResults] Merging %d engine results',
        count($engineResults)
      ));
    }

    foreach ($engineResults as $result) {
      // Merge AI overview (take first non-null)
      if (!empty($result['ai_overview']) && $merged['ai_overview'] === null) {
        $merged['ai_overview'] = $result['ai_overview'];
      }

      // Merge organic results
      if (!empty($result['organic_results'])) {
        $merged['organic_results'] = array_merge(
          $merged['organic_results'],
          $result['organic_results']
        );
      }

      // Merge shopping results
      if (!empty($result['shopping_results'])) {
        $merged['shopping_results'] = array_merge(
          $merged['shopping_results'],
          $result['shopping_results']
        );
      }

      // **NEW: Merge rag_results (Mode C - Amazon and other competitor sites)**
      if (!empty($result['rag_results'])) {
        // **DEBUG: Log rag_results detection**
        if ($this->debug) {
          error_log(sprintf(
            '[WebSearchExecutor::mergeEngineResults] Found rag_results with %d sites',
            count($result['rag_results'])
          ));
        }
        
        // rag_results is an array of site results, each with 'site', 'site_name', 'results'
        // We need to flatten the results from all sites into shopping_results format
        foreach ($result['rag_results'] as $siteResult) {
          if (!empty($siteResult['results'])) {
            // **DEBUG: Log site results**
            if ($this->debug) {
              error_log(sprintf(
                '[WebSearchExecutor::mergeEngineResults] Processing site %s with %d results',
                $siteResult['site'] ?? 'unknown',
                count($siteResult['results'])
              ));
            }
            
            // Convert RAG results to shopping_results format for display
            foreach ($siteResult['results'] as $ragItem) {
              // **DEBUG: Log what we're converting**
              if ($this->debug) {
                error_log(sprintf(
                  '[WebSearchExecutor] Converting RAG item: title=%s, price=%s, extracted_price=%s, thumbnail=%s, rating=%s',
                  $ragItem['title'] ?? 'NULL',
                  $ragItem['price'] ?? 'NULL',
                  $ragItem['extracted_price'] ?? 'NULL',
                  $ragItem['thumbnail'] ?? 'NULL',
                  $ragItem['rating'] ?? 'NULL'
                ));
              }
              
              $merged['shopping_results'][] = [
                'position' => $ragItem['position'] ?? count($merged['shopping_results']) + 1,
                'title' => $ragItem['title'] ?? '',
                'link' => $ragItem['link'] ?? '',
                'product_link' => $ragItem['link'] ?? '',
                'source' => $ragItem['source'] ?? $siteResult['site'],
                'price' => $ragItem['price'] ?? null,
                'extracted_price' => $ragItem['extracted_price'] ?? null,
                'rating' => $ragItem['rating'] ?? null,
                'reviews' => $ragItem['reviews'] ?? null,
                'thumbnail' => $ragItem['thumbnail'] ?? null,
                'snippet' => $ragItem['snippet'] ?? '',
                'data_source' => $ragItem['data_source'] ?? 'rag_websearch',
              ];
            }
          }
        }
        
        // Also keep original rag_results for reference
        $merged['rag_results'] = array_merge(
          $merged['rag_results'],
          $result['rag_results']
        );
      }

      // Merge trends_data (Mode E — take first non-empty result)
      if (!empty($result['trends_data']) && empty($merged['trends_data'])) {
        $merged['trends_data'] = $result['trends_data'];
      }

      // Propagate user_notification from any engine (e.g. Mode C found 0 results)
      if (!empty($result['metadata']['user_notification'])) {
        $merged['metadata']['user_notification'] = $result['metadata']['user_notification'];
      }

      // Collect engine metadata
      $merged['metadata']['engines'][] = [
        'mode' => $result['metadata']['mode'] ?? 'unknown',
        'execution_time' => $result['metadata']['execution_time'] ?? 0,
        'result_count' => $result['metadata']['result_count'] ?? 0,
        'requested_count' => $result['metadata']['requested_count'] ?? 0,
        'results_available' => $result['metadata']['results_available'] ?? true,
      ];
    }

    if (!empty($merged['shopping_results'])) {
      $priorityMap = [
        'rag_websearch'    => 0,
        'amazon'           => 1,
        'shopping_results' => 2,
      ];
      usort($merged['shopping_results'], static function (array $a, array $b) use ($priorityMap): int {
        $pa = $priorityMap[$a['data_source'] ?? ''] ?? 3;
        $pb = $priorityMap[$b['data_source'] ?? ''] ?? 3;
        if ($pa === $pb) {
          // Preserve original ordering within the same source by falling
          // back to position (lower position = earlier in the original list).
          return ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX);
        }
        return $pa <=> $pb;
      });
    }

    // **DEBUG: Log merge completion**
    if ($this->debug) {
      error_log(sprintf(
        '[WebSearchExecutor::mergeEngineResults] Merge complete: %d shopping_results, %d organic_results, %d rag_results',
        count($merged['shopping_results']),
        count($merged['organic_results']),
        count($merged['rag_results'])
      ));
    }

    return $merged;
  }

  /**
   * Prepare engine options with location params and max_results
   *
   * @param array $options User-provided options
   * @param RoutingDecision $routing Routing decision
   * @return array Prepared options for engine
   */
  private function prepareEngineOptions(array $options, RoutingDecision $routing): array
  {
    $engineOptions = $options;

    // Add location parameters from routing decision
    $engineOptions['location_params'] = $routing->getLocationParams();

    // **NEW: Add target_site from routing decision for Amazon engine support**
    $targetSite = $routing->getTargetSite();
    if ($targetSite !== null) {
      $engineOptions['target_site'] = $targetSite;
    }

    // Pass max_results from options (engines will use their defaults if not specified)
    // This allows per-engine configuration via constants:
    // - CLICSHOPPING_APP_CHATGPT_WEB_SHOPPING_MAX_RESULTS
    // - CLICSHOPPING_APP_CHATGPT_WEB_OVERVIEW_MAX_RESULTS
    // - CLICSHOPPING_APP_CHATGPT_WEB_RAG_MAX_RESULTS_PER_SITE

    return $engineOptions;
  }

  /**
   * Instantiate engine based on mode identifier
   *
   * Resolves the mode through {@see WebSearchEngineRegistry}. Both built-in
   * Core providers and any Apps/AI/{Domain}/-registered providers are looked
   * up through the same path — Core never owns a per-mode class map.
   *
   * @param string $mode Mode identifier
   * @return WebSearchInterface Engine instance
   * @throws \RuntimeException If no provider is registered for the mode
   */
  private function instantiateEngine(string $mode): WebSearchInterface
  {
    $provider = $this->registry->getProvider($mode);
    if ($provider === null) {
      throw new \RuntimeException("Unknown mode: {$mode}");
    }

    $engineClass = $provider->getEngineClass();
    if (!class_exists($engineClass)) {
      throw new \RuntimeException("Engine class not found: {$engineClass}");
    }

    return new $engineClass();
  }

  /**
   * Validate configuration for selected engines
   *
   * Ensures at least one search engine is properly configured before accepting queries.
   * This method validates:
   * - At least one mode is selected
   * - Each selected engine can be instantiated
   * - Each selected engine passes its validateConfig() check
   * - At least one engine is fully operational
   *
   * @param RoutingDecision $routing Routing decision
   * @throws ConfigurationException If configuration validation fails with detailed error message
   */
  private function validateConfiguration(RoutingDecision $routing): void
  {
    $selectedModes = $routing->getSelectedModes();

    // Requirement 19.1: Validate that at least one search engine is selected
    if (empty($selectedModes)) {
      throw ConfigurationException::noModesSelected();
    }

    $validEngines = 0;
    $validationErrors = [];

    foreach ($selectedModes as $mode) {
      try {
        $engine = $this->instantiateEngine($mode);

        // Requirement 19.2, 19.3, 19.4: Validate engine-specific configuration
        if ($engine->validateConfig()) {
          $validEngines++;
          
          if ($this->debug) {
            error_log(sprintf(
              '[WebSearchExecutor] Engine %s configuration valid',
              $mode
            ));
          }
        } else {
          $validationErrors[$mode] = 'Configuration validation failed';
          
          if ($this->debug) {
            error_log(sprintf(
              '[WebSearchExecutor] Engine %s configuration invalid',
              $mode
            ));
          }
        }
      } catch (\Exception $e) {
        $validationErrors[$mode] = $e->getMessage();
        
        if ($this->debug) {
          error_log(sprintf(
            '[WebSearchExecutor] Failed to instantiate engine %s: %s',
            $mode,
            $e->getMessage()
          ));
        }
      }
    }

    // Requirement 19.1: Ensure at least one engine is properly configured
    if ($validEngines === 0) {
      // Requirement 19.5: Throw ConfigurationException with detailed error message
      if ($this->debug) {
        error_log(sprintf(
          '[WebSearchExecutor] Configuration validation failed. Errors: %s',
          json_encode($validationErrors)
        ));
      }
      
      throw ConfigurationException::noAvailableEngines($selectedModes);
    }

    if ($this->debug) {
      error_log(sprintf(
        '[WebSearchExecutor] Configuration validated: %d/%d engines available',
        $validEngines,
        count($selectedModes)
      ));
    }
  }

  /**
   * Log execution metrics via WebSearchLogger
   *
   * Logs execution metrics including:
   * - engine_name: Name of engine(s) used
   * - requested_max_results: Requested result count
   * - actual_results_returned: Actual result count
   * - truncated: Whether results were truncated
   *
   * @param string $query Original query
   * @param RoutingDecision $routing Routing decision
   * @param array $results Result structure
   * @param float $executionTime Total execution time
   */
  private function logExecutionMetrics(
    string $query,
    RoutingDecision $routing,
    array $results,
    float $executionTime
  ): void {
    try {
      // Build metadata for logging
      $metadata = [
        'mode_type' => $results['metadata']['mode_type'] ?? 'unknown',
        'engines_used' => $results['metadata']['engines_used'] ?? [],
        'routing_method' => $routing->getRoutingMethod(),
        'execution_time' => $executionTime,
        'is_hybrid_mode' => $routing->isHybridMode(),
      ];

      // Add per-engine metrics
      if (!empty($results['metadata']['engines'])) {
        foreach ($results['metadata']['engines'] as $engineMeta) {
          $metadata['engine_metrics'][] = [
            'mode' => $engineMeta['mode'],
            'execution_time' => $engineMeta['execution_time'],
            'result_count' => $engineMeta['result_count'],
            'requested_count' => $engineMeta['requested_count'] ?? 0,
            'results_available' => $engineMeta['results_available'] ?? true,
            'truncated' => ($engineMeta['result_count'] ?? 0) < ($engineMeta['requested_count'] ?? 0),
          ];
        }
      }

      if ($this->debug) {
        error_log(sprintf(
          '[WebSearchExecutor] Execution metrics: %s',
          json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ));
      }

    } catch (\Exception $e) {
      // Log but don't fail
      if ($this->debug) {
        error_log('[WebSearchExecutor] Failed to log execution metrics: ' . $e->getMessage());
      }
    }
  }

  /**
   * Get available engines
   *
   * Iterates every mode registered with {@see WebSearchEngineRegistry},
   * built-in or domain-provided, and returns those whose engine reports
   * itself available (engine class instantiable + `isAvailable()` true).
   *
   * @return array Array of available engine modes
   */
  public function getAvailableEngines(): array
  {
    $available = [];

    foreach ($this->registry->getRegisteredModes() as $mode) {
      try {
        $engine = $this->instantiateEngine($mode);
        if ($engine->isAvailable()) {
          $available[] = $mode;
        }
      } catch (\Exception $e) {
        // Skip unavailable engines
      }
    }

    return $available;
  }

  /**
   * Get engine metadata for all available engines
   *
   * @return array Array of engine metadata indexed by mode
   */
  public function getEngineMetadata(): array
  {
    $metadata = [];

    foreach ($this->registry->getRegisteredModes() as $mode) {
      try {
        $engine = $this->instantiateEngine($mode);
        if ($engine->isAvailable()) {
          $metadata[$mode] = $engine->getMetadata();
        }
      } catch (\Exception $e) {
        // Skip unavailable engines
      }
    }

    return $metadata;
  }
}
