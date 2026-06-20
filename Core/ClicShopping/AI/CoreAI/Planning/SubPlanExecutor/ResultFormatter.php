<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter\WebSearchFormatter;

/**
 * ResultFormatter Class
 *
 * Final-result presentation concern extracted verbatim from ResultSynthesizer
 * (2026-06-20) to break up the repository's heaviest-NPath method
 * (formatFinalResult). Owns only the formatting/attribution of an already
 * aggregated result; dependencies (logger, debug) are injected by
 * ResultSynthesizer.
 *
 * Responsibilities:
 * - Format the final result from aggregated step results
 * - Combine analytics + semantic sub-results for hybrid queries
 * - Ensure every final result carries source attribution
 */
class ResultFormatter
{
  private SecurityLogger $logger;
  private bool $debug;

  public function __construct(SecurityLogger $logger, bool $debug = false)
  {
    $this->logger = $logger;
    $this->debug = $debug;
  }

  /**
   * Format final result
   *
   * This method combines aggregated sub-query results into a single coherent response.
   *
   * Key Features:
   * 1. Text Response Fallback: If no sub-queries provide text responses, generates
   *    a fallback based on available data, sources, or query types. This ensures
   *    hybrid queries always have a text response for validation.
   *
   * 2. Source Attribution Merging: When multiple sub-queries have source attributions,
   *    creates a "Mixed" attribution that includes all source types and counts.
   *
   * 3. Type Determination: Determines the primary result type based on the mix of
   *    sub-query types (analytics_response, semantic_results, mixed, web_search_response).
   *
   * Handle optional Analytics failures
   * 4. Optional Failure Handling: When Analytics fails optionally (target_site specified),
   *    uses WebSearch results as primary response instead of showing error.
   *
   * @param array $aggregated Aggregated results
   * @param array $entityMetadata Entity metadata
   * @return array Final result
   */
  public function formatFinalResult(array $aggregated, array $entityMetadata): array
  {
    $hasAnalytics = !empty($aggregated['analytics_results']);
    $hasSemantic = !empty($aggregated['semantic_results']);
    $hasWeb = !empty($aggregated['web_results']);
    $hasOptionalFailures = !empty($aggregated['optional_failures']);

    // ALWAYS log this (not conditional on debug) to diagnose the issue
    if($this->debug) {
      error_log("[INFO : ANALYSE] formatFinalResult: hasAnalytics=" . ($hasAnalytics ? 'YES' : 'NO') .
        ", hasSemantic=" . ($hasSemantic ? 'YES' : 'NO') .
        ", hasWeb=" . ($hasWeb ? 'YES' : 'NO') .
        ", hasOptionalFailures=" . ($hasOptionalFailures ? 'YES' : 'NO') .
        ", analytics_count=" . count($aggregated['analytics_results'] ?? []) .
        ", semantic_count=" . count($aggregated['semantic_results'] ?? []));
    }
    
    // Handle optional Analytics failure with WebSearch results
    // If Analytics failed optionally AND we have WebSearch results, use WebSearch as primary
    if ($hasOptionalFailures && $hasWeb && !$hasAnalytics) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Analytics optional failure - using WebSearch result only",
          'info',
          [
            'optional_failures' => $aggregated['optional_failures'],
            'web_results_count' => count($aggregated['web_results']),
          ]
        );
      }
      
      // Extract WebSearch result
      $firstWebResult = $aggregated['web_results'][0];
      
      // Build result with WebSearch data
      $finalResult = [
        'type' => 'web_search_only',
        'text_response' => $firstWebResult['text_response'] ?? '',
        'data' => $firstWebResult['results'] ?? [],
        'sources' => [],
        'analytics_status' => 'optional_failure',
        'analytics_reason' => $aggregated['optional_failures'][0]['reason'] ?? 'unknown',
        'optional_failures' => $aggregated['optional_failures'],
      ];
      
      // Add source attribution from WebSearch
      if (isset($firstWebResult['source_attribution'])) {
        $finalResult['source_attribution'] = $firstWebResult['source_attribution'];
      }
      
      // Add response field for compatibility
      if (!empty($finalResult['text_response'])) {
        $finalResult['response'] = $finalResult['text_response'];
      }
      
      // Add web results metadata
      if (isset($firstWebResult['metadata'])) {
        $finalResult['metadata'] = $firstWebResult['metadata'];
      }
      
      return $finalResult;
    }

    // If we have both analytics and semantic results, use intelligent combination
    if ($hasAnalytics && $hasSemantic && !$hasWeb) {
      if($this->debug) {
        error_log("✅ CALLING combineAnalyticsAndSemantic()");
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "TASK 9: Detected hybrid query with analytics + semantic, using combineAnalyticsAndSemantic()",
          'info'
        );
      }

      $finalResult = $this->combineAnalyticsAndSemantic(
        $aggregated['analytics_results'],
        $aggregated['semantic_results']
      );

      // Add entity metadata if present
      if (!empty($entityMetadata['entity_id'])) {
        $finalResult['entity_id'] = $entityMetadata['entity_id'];
        $finalResult['entity_type'] = $entityMetadata['entity_type'];
        $finalResult['_entity_metadata'] = [
          'entity_id' => $entityMetadata['entity_id'],
          'entity_type' => $entityMetadata['entity_type'],
        ];
      }

      return $finalResult;
    }

    // If we have one or more analytics results (no semantic/web), keep hybrid to preserve
    // sub-query tables (a single analytics sub-query, e.g. product attributes, must still render
    // as a table, not a text-only synthesis).
    if ($hasAnalytics && !$hasSemantic && !$hasWeb && count($aggregated['analytics_results']) >= 1) {
      $textResponse = implode("\n\n", array_filter($aggregated['text_responses']));

      $subQueries = array_map(function ($result) {
        if (!is_array($result)) {
          return $result;
        }
        $normalized = $result;
        if (($normalized['type'] ?? '') === 'analytics_response') {
          $normalized['type'] = 'analytics';
        }
        return $normalized;
      }, $aggregated['analytics_results']);

      // Build merged source attribution if available
      $sourceAttribution = null;
      if (!empty($aggregated['source_attributions'])) {
        if (count($aggregated['source_attributions']) === 1) {
          $sourceAttribution = $aggregated['source_attributions'][0];
        } else {
          $sourceTypes = array_unique(array_column($aggregated['source_attributions'], 'source_type'));
          $sourceAttribution = [
            'source_type' => 'Hybrid',
            'source_icon' => '🔀',
            'source_details' => 'Information combined from multiple sources',
            'sources' => $sourceTypes,
            'source_count' => count($aggregated['source_attributions']),
          ];
        }
      }

      $finalResult = [
        'type' => 'hybrid',
        'text_response' => $textResponse,
        // Provide structured data at root for ResponseFormatter / HybridFormatter
        'data' => [
          'sub_queries' => $subQueries,
          'synthesis' => $textResponse,
          'sources_used' => array_unique(array_map(function ($a) {
            return $a['source_type'] ?? 'Unknown';
          }, $aggregated['source_attributions'] ?? [])),
        ],
        // Keep legacy 'result' for backward compatibility
        'result' => [
          'sub_queries' => $subQueries,
          'synthesis' => $textResponse,
          'sources_used' => array_unique(array_map(function ($a) {
            return $a['source_type'] ?? 'Unknown';
          }, $aggregated['source_attributions'] ?? [])),
        ],
        // Also expose sub_queries at top-level for hybrid formatter
        'sub_queries' => $subQueries,
      ];

      if (!empty($textResponse)) {
        $finalResult['response'] = $textResponse;
      }

      if ($sourceAttribution !== null) {
        $finalResult['source_attribution'] = $sourceAttribution;
      }

      return $finalResult;
    }

    // Otherwise, use standard aggregation logic
    // Combine text responses
    $textResponse = implode("\n\n", array_filter($aggregated['text_responses']));

    // This is critical for hybrid query validation - ensures every result has a text response
    // even when sub-queries don't provide interpretations or text_response fields.
    // Fallback priority: data count > sources count > generic success message
    if (empty($textResponse)) {
      // Generate fallback based on available data
      if (!empty($aggregated['data'])) {
        $dataCount = count($aggregated['data']);
        $textResponse = "Retrieved {$dataCount} result(s) successfully.";
      } elseif (!empty($aggregated['sources'])) {
        $sourcesCount = count($aggregated['sources']);
        $textResponse = "Found {$sourcesCount} relevant source(s).";
      } elseif (!empty($aggregated['analytics_results']) || !empty($aggregated['semantic_results'])) {
        $textResponse = "Query executed successfully.";
      }

      if ($this->debug && !empty($textResponse)) {
        $this->logger->logSecurityEvent(
          "TASK 2.1: Generated fallback text_response (original was empty)",
          'info'
        );
      }
    }

    // Determine primary result type
    $primaryType = 'mixed';

    // Check for clarification_needed first (all steps need clarification)
    $allClarification = true;
    $clarificationCount = 0;
    $totalResultCount = 0;
    
    // Check all result arrays including clarification_results
    foreach ($aggregated as $key => $value) {
      if (str_ends_with($key, '_results') && !empty($value)) {
        foreach ($value as $result) {
          $totalResultCount++;
          $resultType = $result['type'] ?? 'unknown';
          
          if ($this->debug) {
            error_log("[ResultSynthesizer] Checking result in {$key}: type={$resultType}");
          }
          
          if ($resultType === 'clarification_needed') {
            $clarificationCount++;
          } else {
            $allClarification = false;
          }
        }
      }
    }

    if ($this->debug) {
      error_log("[ResultSynthesizer] Type detection: allClarification={$allClarification}, clarificationCount={$clarificationCount}, totalResultCount={$totalResultCount}");
    }

    // If ALL results are clarification_needed, set type to clarification_needed
    if ($allClarification && $totalResultCount > 0) {
      $primaryType = 'clarification_needed';
      
      if ($this->debug) {
        error_log("[ResultSynthesizer] ✅ Setting primaryType to 'clarification_needed' (all {$totalResultCount} results need clarification)");
      }
    }
    // If we have ONLY clarification results (no other result types)
    elseif (!empty($aggregated['clarification_results']) && 
            empty($aggregated['analytics_results']) && 
            empty($aggregated['semantic_results']) && 
            empty($aggregated['web_results'])) {
      $primaryType = 'clarification_needed';
      
      if ($this->debug) {
        error_log("[ResultSynthesizer] ✅ Setting primaryType to 'clarification_needed' (only clarification results present)");
      }
    }
    // Check for web_results first (highest priority for display)
    elseif (!empty($aggregated['web_results'])) {
      $primaryType = 'web_search_response';
    } elseif (!empty($aggregated['analytics_results']) && empty($aggregated['semantic_results'])) {
      $primaryType = 'analytics_response';
    } elseif (!empty($aggregated['semantic_results']) && empty($aggregated['analytics_results'])) {
      $primaryType = 'semantic_results';
    }

    $finalResult = [
      'type' => $primaryType,
      'text_response' => $textResponse,
      'data' => $aggregated['data'],
      'sources' => $aggregated['sources'],
    ];


    // This ensures extractFinalResponse() can find the answer
    if (!empty($textResponse)) {
      $finalResult['response'] = $textResponse;
    }

    // 🆕 Add source attribution (merge if multiple, or use single)
    // For hybrid queries with multiple sub-queries, this creates a "Mixed" attribution
    // that preserves information about all data sources used. This is required for
    // validation and provides transparency to users about where data originated.
    if (!empty($aggregated['source_attributions'])) {
      if (count($aggregated['source_attributions']) === 1) {
        // Single source - use as-is
        $finalResult['source_attribution'] = $aggregated['source_attributions'][0];
      } else {
        // Multiple sources - create merged attribution with all source types
        $sourceTypes = array_unique(array_column($aggregated['source_attributions'], 'source_type'));
        $finalResult['source_attribution'] = [
          'source_type' => 'Hybrid',
          'source_icon' => '🔀',
          'source_details' => 'Information combined from multiple sources',
          'sources' => $sourceTypes,
          'source_count' => count($aggregated['source_attributions']),
        ];
      }
    }

    // Add analytics-specific fields if present
    if (!empty($aggregated['analytics_results'])) {
      $firstAnalytics = $aggregated['analytics_results'][0];
      $finalResult['question'] = $firstAnalytics['question'] ?? '';
      $finalResult['interpretation'] = $firstAnalytics['interpretation'] ?? '';
      $finalResult['results'] = $firstAnalytics['results'] ?? [];
      $finalResult['sql_query'] = $firstAnalytics['sql_query'] ?? '';

      // 🆕 Preserve source attribution from analytics result if not already set
      if (!isset($finalResult['source_attribution']) && isset($firstAnalytics['source_attribution'])) {
        $finalResult['source_attribution'] = $firstAnalytics['source_attribution'];
      }
    }

    // Add semantic-specific fields if present
    if (!empty($aggregated['semantic_results'])) {
      $firstSemantic = $aggregated['semantic_results'][0];


      if (isset($firstSemantic['response']) && !empty($firstSemantic['response'])) {
        $finalResult['response'] = $firstSemantic['response'];
      }

      $finalResult['audit_metadata'] = $firstSemantic['audit_metadata'] ?? [];

      // 🆕 Preserve source attribution from semantic result if not already set
      if (!isset($finalResult['source_attribution']) && isset($firstSemantic['source_attribution'])) {
        $finalResult['source_attribution'] = $firstSemantic['source_attribution'];
      }
    }

    // Add entity metadata if present
    if (!empty($entityMetadata['entity_id'])) {
      $finalResult['entity_id'] = $entityMetadata['entity_id'];
      $finalResult['entity_type'] = $entityMetadata['entity_type'];

      $finalResult['_entity_metadata'] = [
        'entity_id' => $entityMetadata['entity_id'],
        'entity_type' => $entityMetadata['entity_type'],
      ];

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Final result includes entity metadata: {$entityMetadata['entity_type']} #{$entityMetadata['entity_id']}",
          'info'
        );
      }
    }

    // Add calculations if present
    if (!empty($aggregated['calculations'])) {
      $finalResult['calculations'] = $aggregated['calculations'];
    }

    // Add web results if present
    if (!empty($aggregated['web_results'])) {
      $finalResult['web_results'] = $aggregated['web_results'];

      // Generate text_response for web search results if not already present
      if (empty($textResponse)) {
        $firstWebResult = $aggregated['web_results'][0];

        // Check if this is a price comparison
        if (isset($firstWebResult['result']['is_price_comparison']) && $firstWebResult['result']['is_price_comparison']) {
          // Price comparison - use formatted text
          if (isset($firstWebResult['result']['formatted_text'])) {
            $finalResult['text_response'] = $firstWebResult['result']['formatted_text'];
            $finalResult['response'] = $firstWebResult['result']['formatted_text'];
          }
        } else {
          // Standard web search - format items using WebSearchResultFormatter
          if (isset($firstWebResult['result']['items']) && is_array($firstWebResult['result']['items'])) {
            $items = $firstWebResult['result']['items'];
            $query = $firstWebResult['query'] ?? 'votre recherche';

            $formatter = new WebSearchFormatter($this->debug);
            $formatted = $formatter->format([
              'type' => 'web_search_response',
              'query' => $query,
              'results' => $items,
            ]);

            $formattedText = $formatted['content'] ?? '';

            $finalResult['text_response'] = $formattedText;
            $finalResult['response'] = $formattedText;
          }
        }

        // Update primary type to web_search_response
        $finalResult['type'] = 'web_search_response';
      }
    }

    // 🆕 Debug: Log if source_attribution is in final result
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Final result structure before validation",
        'info',
        [
          'type' => $finalResult['type'] ?? 'unknown',
          'has_text_response' => isset($finalResult['text_response']) && !empty($finalResult['text_response']),
          'has_response' => isset($finalResult['response']) && !empty($finalResult['response']),
          'has_source_attribution' => isset($finalResult['source_attribution']),
          'has_data' => isset($finalResult['data']) && !empty($finalResult['data']),
          'has_sources' => isset($finalResult['sources']) && !empty($finalResult['sources']),
          'text_response_length' => isset($finalResult['text_response']) ? strlen($finalResult['text_response']) : 0,
          'data_count' => isset($finalResult['data']) ? count($finalResult['data']) : 0,
          'sources_count' => isset($finalResult['sources']) ? count($finalResult['sources']) : 0,
        ]
      );

      // Original debug logging
      $this->logger->logSecurityEvent(
        "ResultSynthesizer: Final result has source_attribution: " .
        (isset($finalResult['source_attribution']) ? 'YES (' . ($finalResult['source_attribution']['source_type'] ?? 'unknown') . ')' : 'NO'),
        'info'
      );
      $this->logger->logSecurityEvent(
        "ResultSynthesizer: Collected " . count($aggregated['source_attributions']) . " source attributions",
        'info'
      );
    }

    return $finalResult;
  }

  /**
   * Combine analytics and semantic results intelligently
   *
   * This method is critical for hybrid mode queries to display tables correctly.
   * It merges analytics (structured data) and semantic (text/documents) results
   * into a unified response that preserves the strengths of both:
   * - Analytics: Precise numerical data, calculations, aggregations
   * - Semantic: Contextual information, documents, explanations
   *
   *    - The 'results' array is ALWAYS present in analytics_component
   *    - Even when empty (no matching data), the array structure is preserved
   *    - This prevents "No results found" messages from replacing table structures
   *
   * 2. ADD TABLE FORMAT METADATA (Task 3.2):
   *    - Extracts column definitions from first result row
   *    - Adds row count and display type information
   *    - Sets table_format.enabled = true for non-empty results
   *    - This metadata tells the frontend to render a table
   *
   * 3. ENSURE DATA FIELD CONTAINS STRUCTURED TABLE DATA (Task 3.1):
   *    - The 'data' field at root level contains the actual table rows
   *    - This is used by ResultFormatter to generate HTML/JSON tables
   *    - Preserves all columns and values from SQL query results
   *
   * 4. MAINTAIN COMPONENT SEPARATION (Task 3.3):
   *    - analytics_component: Contains SQL query, results, table metadata
   *    - semantic_component: Contains text response, sources, documents
   *    - Both components are preserved in the final result
   *    - This allows the frontend to display both table and text
   *
   * The combination strategy:
   * 1. Prioritizes analytics data for numerical/factual information
   * 2. Enriches with semantic context and explanations
   * 3. Preserves source attribution for each component (Requirement 5.3)
   * 4. Creates a coherent narrative that answers the user's hybrid query
   *
   * EXAMPLE RESULT STRUCTURE:
   * {
   *   "type": "hybrid",
   *   "text_response": "Combined narrative...",
   *   "data": [{...}],  // Table rows from analytics
   *   "analytics_component": {
   *     "results": [{...}],  // ALWAYS present
   *     "table_format": {
   *       "enabled": true,
   *       "columns": ["ean", "price"],
   *       "row_count": 1,
   *       "display_type": "table"
   *     }
   *   },
   *   "semantic_component": {...}
   * }
   *
   * @param array $analyticsResults Array of analytics results
   * @param array $semanticResults Array of semantic results
   * @return array Combined result with unified structure
   */
  private function combineAnalyticsAndSemantic(array $analyticsResults, array $semanticResults): array
  {
    $hasAnalyticsStep = !empty($analyticsResults);
    $hasSemanticStep = !empty($semanticResults);

    $combined = [
      'type' => 'hybrid', // Changed from 'mixed' to 'hybrid' for proper hybrid query identification
      'text_response' => '',
      'data' => [],
      'sources' => [],
      'source_attributions' => [],
      'analytics_component' => null,
      'semantic_component' => null,
    ];

    // Process analytics results — aggregate ALL analytics sub-queries (not just the first),
    // so a hybrid query like "price + last 3 orders" renders BOTH tables (§R maillon C).
    if ($hasAnalyticsStep) {
      $combined['analytics_components'] = [];

      foreach ($analyticsResults as $analytics) {
        $analyticsRows = $analytics['results'] ?? [];

        $component = [
          'type' => 'analytics_response',
          'interpretation' => $analytics['interpretation'] ?? '',
          'results' => $analyticsRows,  // ALWAYS present, even if empty
          'sql_query' => $analytics['sql_query'] ?? '',
          'question' => $analytics['question'] ?? '',
        ];

        if (!empty($analyticsRows)) {
          $columns = is_array($analyticsRows[0]) ? array_keys($analyticsRows[0]) : [];
          $component['table_format'] = [
            'enabled' => true,
            'columns' => $columns,
            'row_count' => count($analyticsRows),
            'display_type' => 'table',
          ];
          // Ensure data field contains structured table data from every analytics sub-query
          $combined['data'] = array_merge($combined['data'], $analyticsRows);
        } else {
          $component['table_format'] = [
            'enabled' => false,
            'columns' => [],
            'row_count' => 0,
            'display_type' => 'none',
          ];
        }

        $combined['analytics_components'][] = $component;

        // Preserve analytics source attribution (one per sub-query)
        if (isset($analytics['source_attribution'])) {
          $combined['source_attributions'][] = [
            'component' => 'analytics',
            'attribution' => $analytics['source_attribution'],
          ];
        }

        // Concatenate each analytics interpretation into the text response
        $interp = $analytics['interpretation'] ?? ($analytics['text_response'] ?? '');
        if (!empty($interp)) {
          if (!empty($combined['text_response'])) {
            $combined['text_response'] .= "\n\n";
          }
          $combined['text_response'] .= $interp;
        }
      }

      // Backward compatibility: expose the first analytics as analytics_component (singular).
      // The loop above runs at least once ($hasAnalyticsStep), so offset 0 always exists.
      $combined['analytics_component'] = $combined['analytics_components'][0];

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Aggregated " . count($combined['analytics_components']) . " analytics component(s)",
          'info'
        );
      }
    }

    // Process semantic results
    if ($hasSemanticStep) {
      $firstSemantic = $semanticResults[0];

      // Extract semantic data
      $combined['semantic_component'] = [
        'type' => 'semantic_results',
        'response' => $firstSemantic['response'] ?? '',
        'text_response' => $firstSemantic['text_response'] ?? '',
        'sources' => $firstSemantic['sources'] ?? [],
        'audit_metadata' => $firstSemantic['audit_metadata'] ?? [],
      ];

      // Preserve semantic source attribution
      if (isset($firstSemantic['source_attribution'])) {
        $combined['source_attributions'][] = [
          'component' => 'semantic',
          'attribution' => $firstSemantic['source_attribution'],
        ];
      }

      // Add semantic sources to combined sources
      if (isset($firstSemantic['sources']) && is_array($firstSemantic['sources'])) {
        $combined['sources'] = array_merge($combined['sources'], $firstSemantic['sources']);
      }

      // Add semantic documents to combined data
      if (isset($firstSemantic['results']) && is_array($firstSemantic['results'])) {
        $combined['data'] = array_merge($combined['data'], $firstSemantic['results']);
      }

      // Add semantic response to text response
      $semanticText = $firstSemantic['response'] ?? $firstSemantic['text_response'] ?? '';
      if (!empty($semanticText)) {
        // Add separator if analytics text exists
        if (!empty($combined['text_response'])) {
          $combined['text_response'] .= "\n\n";
        }
        $combined['text_response'] .= $semanticText;
      }
    }


    // If only one component has results, adjust type accordingly
    // BUT: Keep 'hybrid' type if we have multiple results (even if same type)
    // Determine type based on component presence, not row counts
    if ($hasAnalyticsStep && $hasSemanticStep) {
      $combined['type'] = 'hybrid';
    } elseif ($hasSemanticStep) {
      $combined['type'] = 'semantic_results';
    } elseif ($hasAnalyticsStep) {
      $combined['type'] = 'analytics_response';
    }

    // Create unified source attribution
    if (count($combined['source_attributions']) === 1) {
      // Single source - use component's attribution directly
      $combined['source_attribution'] = $combined['source_attributions'][0]['attribution'];
    } elseif (count($combined['source_attributions']) > 1) {
      // Multiple sources - create merged attribution
      $sourceTypes = [];
      foreach ($combined['source_attributions'] as $attr) {
        $sourceTypes[] = $attr['attribution']['source_type'] ?? 'Unknown';
      }

      $combined['source_attribution'] = [
        'source_type' => 'Hybrid',
        'source_icon' => '🔀',
        'source_details' => 'Combined from: ' . implode(' + ', array_unique($sourceTypes)),
        'components' => $combined['source_attributions'],
        'source_count' => count($combined['source_attributions']),
      ];
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "TASK 9: Combined analytics and semantic results",
        'info',
        [
          'has_analytics' => !empty($analyticsResults),
          'has_semantic' => !empty($semanticResults),
          'combined_type' => $combined['type'],
          'source_attributions_count' => count($combined['source_attributions']),
          'text_response_length' => strlen($combined['text_response']),
          'data_count' => count($combined['data']),
          'sources_count' => count($combined['sources']),
        ]
      );
    }

    return $combined;
  }

  /**
   * Ensure final result has source attribution.
   *
   * This prevents validation failures when upstream components
   * omit source attribution due to edge cases or partial failures.
   *
   * @param array $finalResult Final synthesized result
   * @return array Final result with source_attribution guaranteed
   */
  public function ensureSourceAttribution(array $finalResult): array
  {
    if (isset($finalResult['source_attribution']) && !empty($finalResult['source_attribution'])) {
      return $finalResult;
    }

    // Try to build from source_attributions if present (hybrid combine path)
    if (isset($finalResult['source_attributions']) && is_array($finalResult['source_attributions'])) {
      $normalized = [];
      foreach ($finalResult['source_attributions'] as $item) {
        if (is_array($item) && isset($item['attribution']) && is_array($item['attribution'])) {
          $normalized[] = $item['attribution'];
        } elseif (is_array($item) && isset($item['source_type'])) {
          $normalized[] = $item;
        }
      }

      if (count($normalized) === 1) {
        $finalResult['source_attribution'] = $normalized[0];
        return $finalResult;
      }

      if (count($normalized) > 1) {
        $sourceTypes = array_filter(array_map(function ($attr) {
          return is_array($attr) ? ($attr['source_type'] ?? null) : null;
        }, $normalized));

        $finalResult['source_attribution'] = [
          'source_type' => 'Hybrid',
          'source_icon' => 'i',
          'source_details' => 'Combined from multiple sources',
          'sources' => array_values(array_unique($sourceTypes)),
          'source_count' => count($normalized),
          'fallback' => true,
        ];

        return $finalResult;
      }
    }

    // Fallback attribution based on result structure
    $type = $finalResult['type'] ?? 'unknown';
    $sourceType = 'System';
    $details = 'Fallback source attribution added by ResultSynthesizer';

    if ($type === 'web_search_response' || !empty($finalResult['web_results'])) {
      $sourceType = 'Web Search';
      $details = 'Information retrieved from external web search';
    } elseif ($type === 'analytics_response' || isset($finalResult['sql_query']) || isset($finalResult['results'])) {
      $sourceType = 'Analytics Database';
      $details = 'Information retrieved from internal database';
    } elseif ($type === 'semantic_results' || !empty($finalResult['sources'])) {
      $sourceType = 'RAG Knowledge Base';
      $details = 'Information retrieved from knowledge base';
    }

    $finalResult['source_attribution'] = [
      'source_type' => $sourceType,
      'source_icon' => 'i',
      'source_details' => $details,
      'fallback' => true,
    ];

    return $finalResult;
  }
}
