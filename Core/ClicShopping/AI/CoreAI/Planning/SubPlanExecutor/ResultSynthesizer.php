<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Planning\ExecutionPlan;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ResultValidator;
use ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter\WebSearchFormatter;
use ClicShopping\OM\Hash;

/**
 * ResultSynthesizer Class
 *
 * Responsible for synthesizing and aggregating results from multiple steps.
 * Separated from PlanExecutor to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Synthesize results from execution plan
 * - Aggregate step results
 * - Validate results before synthesis (Task 4.1)
 * - Format final result
 * - Extract entity metadata
 */

class ResultSynthesizer
{
  private SecurityLogger $logger;
  private bool $debug;
  private ResultValidator $validator;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->validator = new ResultValidator($debug);

    if ($this->debug) {
      $this->logger->logSecurityEvent("ResultSynthesizer initialized with ResultValidator", 'info');
    }
  }

  /**
   * Synthesize results from execution plan
   
   *
   * @param ExecutionPlan $plan Execution plan
   * @return array Synthesized result
   */
  public function synthesizeResults(ExecutionPlan $plan): array
  {
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Synthesizing results from " . count($plan->getSteps()) . " steps",
        'info'
      );
    }

    // Get all step results
    $stepResults = $plan->getAllStepResults();

    $validatedResults = $this->validateStepResults($stepResults);

    // Aggregate results
    $aggregated = $this->aggregateStepResults($validatedResults);

    // Extract entity metadata
    $entityMetadata = $this->extractEntityMetadata($validatedResults);

    // Format final result
    $finalResult = $this->formatFinalResult($aggregated, $entityMetadata);

    // Ensure final result always has source attribution before validation
    $finalResult = $this->ensureSourceAttribution($finalResult);
    
    $finalValidation = $this->validateFinalResult($finalResult);
    if (!$finalValidation['valid']) {
      // Log validation failure
      $this->logger->logSecurityEvent(
        "Final result validation failed: " . implode(', ', $finalValidation['errors']),
        'error',
        ['result_type' => $finalResult['type'] ?? 'unknown']
      );

      // Generate user-friendly error message based on validation errors
      $errorMessage = $this->generateUserFriendlyErrorMessage($finalValidation['errors']);

      // Return error response with user-friendly message
      return [
        'success' => false,
        'type' => 'error',
        'text_response' => $errorMessage,
        'error' => 'validation_failed',
        'validation_errors' => $finalValidation['errors'],
        'data' => []
      ];
    }

    // Log validation success
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Final result validation passed",
        'info',
        ['result_type' => $finalResult['type'] ?? 'unknown']
      );
    }

    return $finalResult;
  }

  /**
   * Validate step results before aggregation
   *
   *
   * @param array $stepResults Array of step results
   * @return array Validated step results (invalid results are filtered out)
   */
  private function validateStepResults(array $stepResults): array
  {
    $validatedResults = [];
    $validationFailures = 0;

    foreach ($stepResults as $stepId => $result) {
      if (!is_array($result)) {
        continue;
      }

      $type = $result['type'] ?? 'unknown';
      $isValid = false;

      // Validate based on type
      switch ($type) {
        case 'semantic':
        case 'semantic_results':
          $isValid = $this->validator->validateSemanticResult($result);
          break;

        case 'analytics':
        case 'analytics_response':
          $isValid = $this->validator->validateAnalyticsResult($result);
          break;

        case 'web_search':
        case 'web_search_response':
        case 'web':
          $isValid = $this->validator->validateWebResult($result);
          break;

        case 'hybrid':
        case 'mixed':
          $isValid = $this->validator->validateHybridResult($result);
          break;

        case 'clarification_needed':
          // Clarification requests are always valid - they're asking for more info
          $isValid = true;
          break;

        case 'error':
          // Error results are valid (they communicate an error state)
          $isValid = true;
          break;

        default:
          // Unknown type - allow it through but log warning
          $isValid = true;
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Unknown result type '{$type}' in step {$stepId} - skipping validation",
              'warning'
            );
          }
      }

      if ($isValid) {
        $validatedResults[$stepId] = $result;
      } else {
        $validationFailures++;
        $this->logger->logSecurityEvent(
          "Step {$stepId} validation failed for type '{$type}'",
          'warning',
          ['step_id' => $stepId, 'type' => $type]
        );
      }
    }

    // Log validation summary
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Step validation complete: " . count($validatedResults) . " valid, {$validationFailures} failed",
        'info'
      );
    }

    return $validatedResults;
  }

  /**
   * Aggregate step results
   *
   * - Filters out failed steps
   * - Logs failed steps for debugging
   * - Continues aggregation with successful results only
   *
   * @param array $stepResults Array of step results
   * @return array Aggregated result
   */
  public function aggregateStepResults(array $stepResults): array
  {
    $aggregated = [
      'text_responses' => [],
      'data' => [],
      'sources' => [],
      'calculations' => [],
      'web_results' => [],
      'analytics_results' => [],
      'semantic_results' => [],
      'source_attributions' => [], // Collect source attributions
    ];
    
    $textResponseHashes = [];

    $failedSteps = [];
    $successfulSteps = [];

    foreach ($stepResults as $stepId => $result) {
      if (!is_array($result)) {
        continue;
      }
      if (isset($result['failed']) && $result['failed'] === true) {
        $failedSteps[] = [
          'step_id' => $stepId,
          'error' => $result['error'] ?? 'Unknown error',
          'step_type' => $result['step_type'] ?? 'unknown',
        ];

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Skipping failed step {$stepId} in aggregation: " . ($result['error'] ?? 'Unknown error'),
            'warning'
          );
        }

        continue;
      }

      // Track successful step
      $successfulSteps[] = $stepId;

      $type = $result['type'] ?? 'unknown';

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Step {$stepId} result structure",
          'info',
          [
            'type' => $type,
            'has_text_response' => isset($result['text_response']) && !empty($result['text_response']),
            'has_interpretation' => isset($result['interpretation']) && !empty($result['interpretation']),
            'has_source_attribution' => isset($result['source_attribution']),
            'has_results' => isset($result['results']),
            'has_data' => isset($result['data']),
            'result_keys' => array_keys($result),
          ]
        );
      }

      // 🆕 Always log step result keys for debugging
      if($this->debug) {
        error_log("ResultSynthesizer: Step {$stepId} keys: " . implode(', ', array_keys($result)));
        error_log("ResultSynthesizer: Step {$stepId} has source_attribution: " . (isset($result['source_attribution']) ? 'YES' : 'NO'));
      }

      // Aggregate text responses (dedupe identical content)
      $addTextResponse = function (string $text) use (&$aggregated, &$textResponseHashes): void {
        $normalized = trim($text);
        if ($normalized === '') {
          return;
        }
        $hash = md5($normalized);
        if (isset($textResponseHashes[$hash])) {
          return;
        }
        $textResponseHashes[$hash] = true;
        $aggregated['text_responses'][] = $text;
      };

      if (isset($result['text_response']) && !empty($result['text_response'])) {
        $addTextResponse($result['text_response']);
      } elseif (isset($result['interpretation']) && !empty($result['interpretation'])) {
        // Also collect 'interpretation' field for analytics results
        $addTextResponse($result['interpretation']);
      } elseif (isset($result['result']['interpretation']) && !empty($result['result']['interpretation'])) {
        $addTextResponse($result['result']['interpretation']);
      }

      // 🆕 Collect source attribution if present
      if (isset($result['source_attribution'])) {
        $aggregated['source_attributions'][] = $result['source_attribution'];

        if($this->debug) {
          error_log("ResultSynthesizer: ✓ Collected source_attribution from step {$stepId}: " . ($result['source_attribution']['source_type'] ?? 'unknown'));
        }
	
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "ResultSynthesizer: Collected source_attribution from step {$stepId}: " .
            ($result['source_attribution']['source_type'] ?? 'unknown'),
            'info'
          );
        }
      } else {
        if($this->debug) {
          error_log("ResultSynthesizer: ✗ No source_attribution in step {$stepId} (type: {$type})");
        }

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "ResultSynthesizer: No source_attribution in step {$stepId} (type: {$type})",
            'warning'
          );
        }
      }

      // Aggregate by type
      switch ($type) {
        case 'analytics':
        case 'analytics_response':
          // Handle analytics result structures
          if (isset($result['results'])) {
            // Standard structure: results at root level
            $aggregated['analytics_results'][] = $result;
            $aggregated['data'] = array_merge($aggregated['data'], (array)$result['results']);
          } elseif (isset($result['result']['rows'])) {
            // Nested structure: results in result.rows
            $aggregated['analytics_results'][] = $result;
            $aggregated['data'] = array_merge($aggregated['data'], (array)$result['result']['rows']);
          } elseif (isset($result['result'])) {
            // Fallback: collect entire result object
            $aggregated['analytics_results'][] = $result;
            if (is_array($result['result'])) {
              $aggregated['data'] = array_merge($aggregated['data'], [$result['result']]);
            }
          }
          break;

        case 'semantic':
        case 'semantic_results':
          // This ensures we preserve the result structure for later processing
          $aggregated['semantic_results'][] = $result;

          // Collect sources if available
          if (isset($result['audit_metadata']['sources'])) {
            $aggregated['sources'] = array_merge($aggregated['sources'], (array)$result['audit_metadata']['sources']);
          }

          // Also collect from 'sources' field directly
          if (isset($result['sources']) && is_array($result['sources'])) {
            $aggregated['sources'] = array_merge($aggregated['sources'], $result['sources']);
          }

          // Collect from 'results' field (documents)
          if (isset($result['results']) && is_array($result['results'])) {
            $aggregated['data'] = array_merge($aggregated['data'], $result['results']);
          }
          break;

        case 'calculator':
          if (isset($result['result'])) {
            $aggregated['calculations'][] = $result['result'];
          }
          break;

        case 'web_search':
        case 'web_search_response':
        case 'web':
          // Web search results have 'result' (singular) not 'results' (plural)
          if (isset($result['result'])) {
            $aggregated['web_results'][] = $result;

            // Extract items from result if available
            if (isset($result['result']['items']) && is_array($result['result']['items'])) {
              $aggregated['data'] = array_merge($aggregated['data'], $result['result']['items']);
            }

            // Extract formatted text if available (for price comparisons)
            if (isset($result['result']['formatted_text']) && !empty($result['result']['formatted_text'])) {
              $addTextResponse($result['result']['formatted_text']);
            }
          }

          // Also check for 'results' (plural) from PlanExecutor
          if (isset($result['results']) && is_array($result['results'])) {
            $aggregated['web_results'][] = $result;
            $aggregated['data'] = array_merge($aggregated['data'], $result['results']);
          }

          // Extract text_response if available
          if (isset($result['text_response']) && !empty($result['text_response'])) {
            $addTextResponse($result['text_response']);
          }
          break;

        case 'clarification_needed':
          // Clarification requests - add to a special array for type detection
          if (!isset($aggregated['clarification_results'])) {
            $aggregated['clarification_results'] = [];
          }
          $aggregated['clarification_results'][] = $result;
          break;

        case 'error':
          // Error results - add to a special array for type detection
          if (!isset($aggregated['error_results'])) {
            $aggregated['error_results'] = [];
          }
          $aggregated['error_results'][] = $result;
          break;
      }
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Aggregated results - analytics: " . count($aggregated['analytics_results']) .
        ", semantic: " . count($aggregated['semantic_results']) .
        ", web: " . count($aggregated['web_results'] ?? []) .
        ", clarification: " . count($aggregated['clarification_results'] ?? []) .
        ", error: " . count($aggregated['error_results'] ?? []) .
        ", successful: " . count($successfulSteps) .
        ", failed: " . count($failedSteps),
        'info',
        [
          'failed_steps' => $failedSteps,
        ]
      );
    }

    return $aggregated;
  }

  /**
   * Extract entity metadata from results
   *
   * @param array $stepResults Array of step results
   * @return array Entity metadata
   */
  public function extractEntityMetadata(array $stepResults): array
  {
    $metadata = [
      'entity_id' => null,
      'entity_type' => null,
    ];

    // Priority order: _step_entity_metadata > direct entity_id > _entity_metadata
    foreach ($stepResults as $result) {
      if (!is_array($result)) {
        continue;
      }

      // Check _step_entity_metadata (highest priority)
      if (isset($result['_step_entity_metadata']['entity_id']) && $result['_step_entity_metadata']['entity_id'] > 0) {
        $metadata['entity_id'] = $result['_step_entity_metadata']['entity_id'];
        $metadata['entity_type'] = $result['_step_entity_metadata']['entity_type'] ?? 'unknown';

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Found entity in _step_entity_metadata: {$metadata['entity_type']} #{$metadata['entity_id']}",
            'info'
          );
        }
        break;
      }

      // Check direct entity_id
      if (isset($result['entity_id']) && $result['entity_id'] > 0) {
        $metadata['entity_id'] = $result['entity_id'];
        $metadata['entity_type'] = $result['entity_type'] ?? 'unknown';

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Found entity in direct fields: {$metadata['entity_type']} #{$metadata['entity_id']}",
            'info'
          );
        }
        break;
      }

      // Check _entity_metadata
      if (isset($result['_entity_metadata']['entity_id']) && $result['_entity_metadata']['entity_id'] > 0) {
        $metadata['entity_id'] = $result['_entity_metadata']['entity_id'];
        $metadata['entity_type'] = $result['_entity_metadata']['entity_type'] ?? 'unknown';

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Found entity in _entity_metadata: {$metadata['entity_type']} #{$metadata['entity_id']}",
            'info'
          );
        }
        break;
      }
    }

    if ($this->debug && $metadata['entity_id'] === null) {
      $this->logger->logSecurityEvent(
        "No entity metadata found in " . count($stepResults) . " step results",
        'info'
      );
    }

    return $metadata;
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
   * @param array $aggregated Aggregated results
   * @param array $entityMetadata Entity metadata
   * @return array Final result
   */
  public function formatFinalResult(array $aggregated, array $entityMetadata): array
  {
    $hasAnalytics = !empty($aggregated['analytics_results']);
    $hasSemantic = !empty($aggregated['semantic_results']);
    $hasWeb = !empty($aggregated['web_results']);

    // ALWAYS log this (not conditional on debug) to diagnose the issue
    if($this->debug) {
      error_log("[INFO : ANALYSE] formatFinalResult: hasAnalytics=" . ($hasAnalytics ? 'YES' : 'NO') .
        ", hasSemantic=" . ($hasSemantic ? 'YES' : 'NO') .
        ", hasWeb=" . ($hasWeb ? 'YES' : 'NO') .
        ", analytics_count=" . count($aggregated['analytics_results'] ?? []) .
        ", semantic_count=" . count($aggregated['semantic_results'] ?? []));
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

    // If we have multiple analytics results (no semantic/web), keep hybrid to preserve sub-query tables
    if ($hasAnalytics && !$hasSemantic && !$hasWeb && count($aggregated['analytics_results']) > 1) {
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

    // Process analytics results
    if ($hasAnalyticsStep) {
      $firstAnalytics = $analyticsResults[0];

      $analyticsRows = $firstAnalytics['results'] ?? [];

      // Extract analytics data
      $combined['analytics_component'] = [
        'type' => 'analytics_response',
        'interpretation' => $firstAnalytics['interpretation'] ?? '',
        'results' => $analyticsRows,  // ALWAYS present, even if empty
        'sql_query' => $firstAnalytics['sql_query'] ?? '',
        'question' => $firstAnalytics['question'] ?? '',
      ];

      if (!empty($analyticsRows)) {
        // Extract column definitions from first result
        $columns = [];
        if (is_array($analyticsRows[0])) {
          $columns = array_keys($analyticsRows[0]);
        }

        $combined['analytics_component']['table_format'] = [
          'enabled' => true,
          'columns' => $columns,
          'row_count' => count($analyticsRows),
          'display_type' => 'table',
        ];

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Table display enabled for analytics results",
            'info',
            [
              'row_count' => count($analyticsResults),
              'column_count' => count($columns),
              'columns' => $columns,
              'display_type' => 'table',
            ]
          );
        }
      } else {
        // Empty results - still add metadata but disabled
        $combined['analytics_component']['table_format'] = [
          'enabled' => false,
          'columns' => [],
          'row_count' => 0,
          'display_type' => 'none',
        ];

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Table display disabled - no analytics results",
            'info'
          );
        }
      }

      // Preserve analytics source attribution
      if (isset($firstAnalytics['source_attribution'])) {
        $combined['source_attributions'][] = [
          'component' => 'analytics',
          'attribution' => $firstAnalytics['source_attribution'],
        ];
      }

      // Ensure data field contains structured table data
      if (!empty($analyticsRows)) {
        $combined['data'] = array_merge($combined['data'], $analyticsRows);
      }

      // Add analytics interpretation to text response
      if (!empty($firstAnalytics['interpretation'])) {
        $combined['text_response'] .= $firstAnalytics['interpretation'];
      } elseif (!empty($firstAnalytics['text_response'])) {
        $combined['text_response'] .= $firstAnalytics['text_response'];
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
  private function ensureSourceAttribution(array $finalResult): array
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

  /**
   * Validate final result before returning
   *
   *
   * Validation Rules by Result Type:
   *
   * 1. analytics_response:
   *    - MUST have: text_response OR response field (non-empty)
   *    - MUST have: source_attribution field
   *    - MUST have: data OR interpretation (empty data is valid if interpretation exists)
   *
   * 2. semantic_results:
   *    - MUST have: text_response OR response field (non-empty)
   *    - MUST have: source_attribution field
   *    - MUST have: sources OR data (non-empty)
   *
   * 3. mixed (hybrid queries):
   *    - MUST have: text_response OR response field (non-empty)
   *    - MUST have: source_attribution field
   *    - MUST have: data OR sources (at least one non-empty)
   *
   * 4. web_search_response:
   *    - MUST have: text_response OR response field (non-empty)
   *    - MUST have: source_attribution field
   *    - MUST have: sources (non-empty)
   *
   * @param array $finalResult Final synthesized result
   * @return array Validation result with 'valid' boolean and 'errors' array
   */
  private function validateFinalResult(array $finalResult): array
  {
    $errors = [];

    // Check if result is empty
    if (empty($finalResult)) {
      $errors[] = "Final result is empty";
      return ['valid' => false, 'errors' => $errors];
    }

    // Check for text_response or response field
    $hasTextResponse = isset($finalResult['text_response']) && !empty($finalResult['text_response']);
    $hasResponse = isset($finalResult['response']) && !empty($finalResult['response']);

    if (!$hasTextResponse && !$hasResponse) {

      $textResponseStatus = isset($finalResult['text_response']) ? 'empty' : 'not set';
      $responseStatus = isset($finalResult['response']) ? 'empty' : 'not set';
      $errors[] = "Missing text_response or response field (text_response: {$textResponseStatus}, response: {$responseStatus})";
    }

    // Check for source attribution
    if (!isset($finalResult['source_attribution']) || empty($finalResult['source_attribution'])) {

      $attributionStatus = isset($finalResult['source_attribution']) ? 'empty' : 'not set';
      $errors[] = "Missing source_attribution field (field {$attributionStatus})";
    }

    // Type-specific validation
    $type = $finalResult['type'] ?? 'unknown';
    switch ($type) {
      case 'analytics_response':
        // BUG FIX 2025-12-10: Empty data is valid if there's an interpretation/text_response
        // "No results found" is a valid analytics response
        $hasData = !empty($finalResult['results']) || !empty($finalResult['data']);
        $hasInterpretation = !empty($finalResult['interpretation']) || !empty($finalResult['text_response']);

        if (!$hasData && !$hasInterpretation) {
          $errors[] = "Analytics result missing data and interpretation";
        }
        break;

      case 'semantic_results':

        // LLM fallback is valid if it has a text_response, even without sources
        $hasTextResponse = !empty($finalResult['text_response']) || !empty($finalResult['response']);
        $hasSources = !empty($finalResult['sources']) || !empty($finalResult['data']);

        // Semantic results MUST have sources OR data, unless it's a valid LLM/memory fallback
        if (!$hasSources) {
          $sourceType = strtolower($finalResult['source_attribution']['source_type'] ?? '');
          $isLLMFallback = $hasTextResponse && (
            strpos($sourceType, 'llm') !== false ||
            strpos($sourceType, 'general knowledge') !== false ||
            strpos($sourceType, 'conversation') !== false ||
            strpos($sourceType, 'memory') !== false
          );

          if (!$isLLMFallback) {
            $sourcesStatus = isset($finalResult['sources']) ? 'empty' : 'not set';
            $dataStatus = isset($finalResult['data']) ? 'empty' : 'not set';
            $errors[] = "Semantic result missing sources and data (sources: {$sourcesStatus}, data: {$dataStatus})";
          }
        }
        break;

      case 'mixed':
      case 'hybrid':
        // Mixed/hybrid results should have data from multiple sources

        $hasData = !empty($finalResult['data']);
        $hasSources = !empty($finalResult['sources']);

        if (!$hasData && !$hasSources) {
          $dataStatus = isset($finalResult['data']) ? 'empty' : 'not set';
          $sourcesStatus = isset($finalResult['sources']) ? 'empty' : 'not set';
          $errors[] = "Mixed result missing data and sources (data: {$dataStatus}, sources: {$sourcesStatus})";
        }
        break;

      case 'clarification_needed':
        // Clarification requests are always valid - they have text_response explaining what's needed
        // No additional validation required beyond text_response check (already done above)
        break;

      case 'error':
        // Error results are always valid - they communicate an error state
        // No additional validation required beyond text_response check (already done above)
        break;

      case 'web_search_response':
      case 'web':
        // Web search results must have sources
        if (empty($finalResult['sources']) && empty($finalResult['data'])) {
          $sourcesStatus = isset($finalResult['sources']) ? 'empty' : 'not set';
          $dataStatus = isset($finalResult['data']) ? 'empty' : 'not set';
          $errors[] = "Web search result missing sources and data (sources: {$sourcesStatus}, data: {$dataStatus})";
        }
        break;
    }

    $isValid = empty($errors);

    return [
      'valid' => $isValid,
      'errors' => $errors
    ];
  }

  /**
   * Generate user-friendly error message from validation errors
   *
   * Converts technical validation errors into human-readable messages
   * that explain what went wrong and what the user can do.
   *
   * @param array $errors Array of validation error messages
   * @return string User-friendly error message
   */
  private function generateUserFriendlyErrorMessage(array $errors): string
  {
    // Check for common error patterns and generate appropriate messages
    foreach ($errors as $error) {
      // Pattern: "Semantic result missing sources and data"
      if (strpos($error, 'Semantic result missing sources and data') !== false) {
        return "I couldn't find any information about that in the database. The requested content (like terms and conditions) may not be available yet. Please try asking about something else or contact support to add this content.";
      }

      // Pattern: "Analytics result missing data"
      if (strpos($error, 'Analytics result missing data') !== false) {
        return "I couldn't retrieve the requested data. This might be because there are no records matching your query, or the data hasn't been entered yet. Please try a different query or check if the data exists.";
      }

      // Pattern: "Hybrid result missing"
      if (strpos($error, 'Hybrid result missing') !== false) {
        return "I couldn't complete your request because some of the required information is missing. Please try breaking your question into smaller parts or asking about something else.";
      }

      // Pattern: "Empty response"
      if (strpos($error, 'empty') !== false || strpos($error, 'Empty') !== false) {
        return "I couldn't find any results for your query. The information you're looking for might not be available in the system yet. Please try rephrasing your question or asking about something else.";
      }
    }

    // Default fallback message
    return "I encountered an issue processing your request. The information you're looking for might not be available, or there might be a problem with the query. Please try rephrasing your question or contact support if the issue persists.";
  }

  /**
   * Get validation metrics
   *
   *
   * @return array Validation metrics
   */
  public function getValidationMetrics(): array
  {
    return $this->validator->getValidationMetrics();
  }
}
