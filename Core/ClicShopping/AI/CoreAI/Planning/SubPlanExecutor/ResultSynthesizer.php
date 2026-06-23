<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Planning\ExecutionPlan;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\ResultValidator;

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
  private ResultFormatter $formatter;

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
    $this->formatter = new ResultFormatter($this->logger, $this->debug);

    if ($this->debug) {
      $this->logger->logSecurityEvent("ResultSynthesizer initialized with ResultValidator", 'info');
    }
  }

  /**
   * Format the final result from aggregated step results.
   *
   * Public delegator kept for backward compatibility — the formatting concern
   * now lives in ResultFormatter (extracted 2026-06-20).
   *
   * @param array $aggregated Aggregated results
   * @param array $entityMetadata Entity metadata
   * @return array Final result
   */
  public function formatFinalResult(array $aggregated, array $entityMetadata): array
  {
    return $this->formatter->formatFinalResult($aggregated, $entityMetadata);
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
    $finalResult = $this->formatter->formatFinalResult($aggregated, $entityMetadata);

    // Ensure final result always has source attribution before validation
    $finalResult = $this->formatter->ensureSourceAttribution($finalResult);
    
    $finalValidation = $this->validateFinalResult($finalResult);
    if (!$finalValidation['valid']) {
      // A "missing sources and data" / empty failure is a HANDLED no-grounding outcome:
      // the query found no document match and the only candidate was an ungrounded LLM
      // fallback (e.g. "article 5 of the terms and conditions" — not indexed). We reject it
      // on purpose (never surface possibly-hallucinated content) and inform the user via a
      // friendly message below, so it is NOT a system error — log it at 'warning' to avoid
      // noise. Genuinely unexpected validation failures stay at 'error'.
      $isHandledNoGrounding = array_reduce(
        $finalValidation['errors'],
        static fn(bool $carry, string $e): bool => $carry
          && (str_contains($e, 'missing sources and data')
            || str_contains($e, 'missing data and sources')
            || stripos($e, 'empty') !== false),
        true
      );

      $this->logger->logSecurityEvent(
        ($isHandledNoGrounding
          ? "Final result rejected - no grounded sources/data (handled: user informed content is unavailable): "
          : "Final result validation failed: ")
        . implode(', ', $finalValidation['errors']),
        $isHandledNoGrounding ? 'warning' : 'error',
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
    if($this->debug) {
      error_log("[ResultSynthesizer::validateStepResults] CALLED with " . count($stepResults) . " step results");
    }

    $validatedResults = [];
    $validationFailures = 0;

    foreach ($stepResults as $stepId => $result) {
      if (!is_array($result)) {
        continue;
      }

      $type = $result['type'] ?? 'unknown';
      $isValid = false;

      if($this->debug) {
        error_log("[ResultSynthesizer::validateStepResults] Validating step {$stepId}: type={$type}");
      }

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

          if($this->debug) {
            error_log("[ResultSynthesizer::validateStepResults] validateWebResult returned: " . ($isValid ? 'TRUE' : 'FALSE'));
          }
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
        if($this->debug) {
          error_log("[ResultSynthesizer::validateStepResults] Step {$stepId} PASSED validation");
        }
      } else {
        $validationFailures++;
	
        if($this->debug) {
          error_log("[ResultSynthesizer::validateStepResults] Step {$stepId} FAILED validation");
        }

        $this->logger->logSecurityEvent(
          "Step {$stepId} validation failed for type '{$type}'",
          'warning',
          ['step_id' => $stepId, 'type' => $type]
        );
      }
    }

    if($this->debug) {
      error_log("[ResultSynthesizer::validateStepResults] Returning " . count($validatedResults) . " validated results (failures: {$validationFailures})");
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
   *  -Handle optional Analytics failures
   *
   * @param array $stepResults Array of step results
   * @return array Aggregated result
   */
  public function aggregateStepResults(array $stepResults): array
  {
    if($this->debug) {
      error_log("[ResultSynthesizer::aggregateStepResults] CALLED with " . count($stepResults) . " step results");
    }

    foreach ($stepResults as $stepId => $result) {
      if (is_array($result)) {
        error_log("[ResultSynthesizer::aggregateStepResults] Step {$stepId}: type=" . ($result['type'] ?? 'NO TYPE'));
      }
    }
    
    $aggregated = [
      'text_responses' => [],
      'data' => [],
      'sources' => [],
      'calculations' => [],
      'web_results' => [],
      'analytics_results' => [],
      'semantic_results' => [],
      'source_attributions' => [],
      'optional_failures' => [],
    ];
    
    $textResponseHashes = [];

    $failedSteps = [];
    $successfulSteps = [];

    foreach ($stepResults as $stepId => $result) {
      if (!is_array($result)) {
        continue;
      }
      
      // Check if this is an optional failure
      $isOptionalFailure = isset($result['optional']) && $result['optional'] === true;
      
      if (isset($result['failed']) && $result['failed'] === true) {
        // If it's an optional failure, track it but don't skip
        if ($isOptionalFailure) {
          $aggregated['optional_failures'][] = [
            'step_id' => $stepId,
            'reason' => $result['reason'] ?? 'unknown',
            'step_type' => $result['step_type'] ?? 'unknown',
          ];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Analytics optional failure in step {$stepId} - continuing with other steps",
              'info',
              [
                'reason' => $result['reason'] ?? 'unknown',
                'step_type' => $result['step_type'] ?? 'unknown',
              ]
            );
          }
          
          // Continue to next step without adding to failedSteps
          continue;
        }
        
        // Regular failure - skip this step
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
      $addTextResponse = function (mixed $text) use (&$aggregated, &$textResponseHashes): void {
        if (is_array($text)) {
          $parts = [];
          array_walk_recursive($text, static function ($value) use (&$parts): void {
            if (is_scalar($value)) {
              $parts[] = (string)$value;
            }
          });
          $text = implode("\n", $parts);
        }
        if (!is_string($text)) {
          return;
        }
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
      $this->aggregateResultByType($type, $result, $stepId, $aggregated, $addTextResponse);
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
   * Aggregate a single step result into the accumulator by its declared type.
   *
   * Extracted verbatim from aggregateStepResults to cut NPath. Mutates the
   * accumulator by reference and reuses the caller's deduplicating text collector.
   *
   * @param string $type Step result type discriminator
   * @param array $result Single step result
   * @param int|string $stepId Step identifier (diagnostics only)
   * @param array $aggregated Accumulator, mutated by reference
   * @param callable $addTextResponse Deduplicating text-response collector
   * @return void
   */
  private function aggregateResultByType(string $type, array $result, int|string $stepId, array &$aggregated, callable $addTextResponse): void
  {
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
        if($this->debug) {
          error_log("[ResultSynthesizer] web_search result keys: " . implode(', ', array_keys($result)));
          error_log("[ResultSynthesizer] Has 'result' key: " . (isset($result['result']) ? 'YES' : 'NO'));
          error_log("[ResultSynthesizer] Has 'results' key: " . (isset($result['results']) ? 'YES' : 'NO'));
          error_log("[ResultSynthesizer] Has 'text_response' key: " . (isset($result['text_response']) ? 'YES' : 'NO'));
        }
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
        
        if($this->debug) {
          error_log("[ResultSynthesizer] web_results count after processing: " . count($aggregated['web_results']));
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
              str_contains($sourceType, 'llm') ||
              str_contains($sourceType, 'general knowledge') ||
              str_contains($sourceType, 'conversation') ||
              str_contains($sourceType, 'memory')
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
      case 'web_search_only':
      case 'web':
        // Web search results must have sources, data, OR a pre-formatted text_response
        // (e.g., Google Trends returns a Chart.js HTML block — no separate sources/data rows)
        $hasTextContent = !empty($finalResult['text_response']) || !empty($finalResult['response']);
        if (!$hasTextContent && empty($finalResult['sources']) && empty($finalResult['data'])) {
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
      if (str_contains($error, 'Semantic result missing sources and data')) {
        return "I couldn't find any information about that in the database. The requested content (like terms and conditions) may not be available yet. Please try asking about something else or contact support to add this content.";
      }

// Pattern: "Analytics result missing data"
      if (str_contains($error, 'Analytics result missing data')) {
        return "I couldn't retrieve the requested data. This might be because there are no records matching your query, or the data hasn't been entered yet. Please try a different query or check if the data exists.";
      }

// Pattern: "Hybrid result missing"
      if (str_contains($error, 'Hybrid result missing')) {
        return "I couldn't complete your request because some of the required information is missing. Please try breaking your question into smaller parts or asking about something else.";
      }

// Pattern: "Empty response"
// Note: Using stripos here handles 'empty', 'Empty', 'EMPTY', etc., in one go
      if (stripos($error, 'empty') !== false) {
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
