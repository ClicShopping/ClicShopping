<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;


use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\DomainsAI\Shared\Helper\AgentResponseHelper;
use ClicShopping\AI\DomainsAI\Analytics\Helper\AnalyticsErrorHandler;
use ClicShopping\AI\DomainsAI\Analytics\Helper\Detection\AmbiguousQueryDetector;

/**
 * AmbiguityHandler
 * 
 * Handles ambiguous query detection and resolution for AnalyticsAgent
 * Manages multiple interpretation generation and clarification requests
 * 
 * Responsibilities:
 * - Generate multiple interpretations for ambiguous queries
 * - Execute each interpretation and collect results
 * - Request clarification from users when needed
 * - Build standardized ambiguous/clarification responses
 */

class AmbiguityHandler
{
  private AmbiguousQueryDetector $ambiguityDetector;
  private mixed $queryProcessor;
  private mixed $queryExecutor;
  private AnalyticsErrorHandler $errorHandler;
  private bool $debug;

  /**
   * Constructor
   *
   * @param AmbiguousQueryDetector $ambiguityDetector Ambiguity detection component
   * @param mixed $queryProcessor SQL query processor
   * @param mixed $queryExecutor Query executor
   * @param AnalyticsErrorHandler $errorHandler Self-heal, shared with the normal path
   * @param bool $debug Debug mode flag
   */
  public function __construct(
    AmbiguousQueryDetector $ambiguityDetector,
    $queryProcessor,
    $queryExecutor,
    AnalyticsErrorHandler $errorHandler,
    bool $debug = false
  ) {
    $this->ambiguityDetector = $ambiguityDetector;
    $this->queryProcessor = $queryProcessor;
    $this->queryExecutor = $queryExecutor;
    $this->errorHandler = $errorHandler;
    $this->debug = $debug;
  }
  
  /**
   * Handle ambiguous query by generating multiple interpretations
   * 
   * Generates SQL for each interpretation, executes them, and returns
   * results for all interpretations. Uses AgentResponseHelper for standardized
   * response format.
   * 
   * @param string $question Original ambiguous question
   * @param array $ambiguityAnalysis Ambiguity analysis result from AmbiguousQueryDetector
   * @param callable $sqlGenerator Function to generate SQL from modified query
   * @return array Results with multiple interpretations
   */
  public function handleAmbiguousQuery(
    string $question,
    array $ambiguityAnalysis,
    callable $sqlGenerator
  ): array {
    if ($this->debug) {
      error_log(str_repeat("*", 100));
      error_log("DEBUG: AmbiguityHandler.handleAmbiguousQuery() - START");
      error_log("*" . str_repeat("*", 99));
    }
    
    $interpretationResults = [];
    $failures = [];

    // Generate multiple interpretations using the detector
    $interpretations = $this->ambiguityDetector->generateMultipleInterpretations(
      $question,
      $ambiguityAnalysis,
      $sqlGenerator
    );
    
    if ($this->debug) {
      error_log("Generated " . count($interpretations) . " interpretations");
    }
    
    // Execute each interpretation
    foreach ($interpretations as $interpretation) {
      if ($this->debug) {
        error_log("Executing interpretation: " . $interpretation['type']);
        error_log("SQL: " . substr($interpretation['sql'], 0, 200));
      }
      
      try {
        // Resolve placeholders
        $resolvedQuery = $this->queryProcessor->resolvePlaceholders($interpretation['sql']);
        
        // Validate SQL
        $validation = InputValidator::validateSqlQuery($resolvedQuery);
        
        if (!$validation['valid']) {
          if ($this->debug) {
            error_log("  Validation failed: " . implode(', ', $validation['issues']));
          }
          continue;
        }
        
        // Same schema-level guards as the normal path (AnalyticsAgent::executeSqlQueries):
        // an interpretation is a query like any other and must not skip them.
        $resolvedQuery = $this->queryProcessor->fixDateFilters($resolvedQuery);
        $resolvedQuery = $this->queryProcessor->fixEncryptedGroupBy($resolvedQuery);

        // Execute query
        $executionResult = $this->queryExecutor->execute($resolvedQuery);
        
        if (!$executionResult['success']) {
          if ($this->debug) {
            error_log("  Execution failed: " . ($executionResult['error'] ?? 'Unknown error'));
          }

          $failures[] = [
            'interpretation' => $interpretation,
            'executed_sql' => $resolvedQuery,
            'error' => $executionResult['error'] ?? 'Unknown error',
          ];

          continue;
        }

        $queryResults = $executionResult['data'];
        
        if ($this->debug) {
          error_log("  Success! Rows returned: " . count($queryResults));
        }
        
        // Store interpretation result
        $interpretationResults[] = [
          'type' => $interpretation['type'],
          'label' => $interpretation['label'],
          'description' => $interpretation['description'],
          'sql_query' => $resolvedQuery,
          'results' => $queryResults,
          'count' => count($queryResults)
        ];
        
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("  Exception: " . $e->getMessage());
        }
        continue;
      }
    }
    
    // Nothing ran: give the failures the same second chance the normal path gives every query.
    if (empty($interpretationResults) && !empty($failures)) {
      $healed = $this->healFailedInterpretations($question, $failures);

      if ($healed !== null) {
        $interpretationResults[] = $healed;
      }
    }

    if ($this->debug) {
      error_log("*" . str_repeat("*", 99) . "\n");
    }

    if (empty($interpretationResults) && !empty($failures)) {
      throw new \Exception(
        'Every interpretation of the ambiguous query failed: ' . $failures[0]['error']
      );
    }

    // One survivor is not an ambiguity: the reading the user gets was never in competition.
    // A reading that ran but returned nothing is not one — it answers no question.
    $viable = array_values(array_filter($interpretationResults, static fn(array $r): bool => !empty($r['results'])));

    if (count($viable) === 1) {
      return $this->buildSingleInterpretationResult($question, $ambiguityAnalysis, $viable[0]);
    }

    // Use ResponseHelper for standardized ambiguous response
    return AgentResponseHelper::buildAmbiguousResponse(
      $question,
      $ambiguityAnalysis['ambiguity_type'],
      $interpretationResults,
      null,
      $ambiguityAnalysis['default_interpretation'] ?? null
    );
  }

  /**
   * Run the shared self-heal over interpretations whose SQL errored.
   *
   * Same CorrectionAgent the normal path uses (AnalyticsErrorHandler::attemptIntelligentCorrection).
   * Stops on the first repair that returns rows: a correction yielding nothing is not a success,
   * it is the same failure with a different query.
   *
   * @param string $question Original question
   * @param array $failures Recorded execution failures
   * @return array|null A repaired interpretation result, or null when none could be repaired
   */
  private function healFailedInterpretations(string $question, array $failures): ?array
  {
    foreach ($failures as $failure) {
      $interpretation = $failure['interpretation'];

      if ($this->debug) {
        error_log("Attempting self-heal for interpretation '{$interpretation['type']}'");
      }

      try {
        $correction = $this->errorHandler->attemptIntelligentCorrection(
          new \Exception($failure['error']),
          $failure['executed_sql'],
          $interpretation['sql'],
          $question
        );
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("  Self-heal raised: " . $e->getMessage());
        }

        continue;
      }

      if (empty($correction['success']) || empty($correction['data']['results'])) {
        continue;
      }

      return [
        'type' => $interpretation['type'],
        'label' => $interpretation['label'],
        'description' => $interpretation['description'],
        'sql_query' => $correction['data']['executed_query'] ?? $failure['executed_sql'],
        'results' => $correction['data']['results'],
        'count' => count($correction['data']['results']),
        'corrections' => $correction['data']['corrections'] ?? [],
      ];
    }

    return null;
  }

  /**
   * Reconverge a lone surviving interpretation onto the standard analytics result shape.
   *
   * `analytics_results_ambiguous` is short-circuited by AnalyticsAgent::resolveEarlyResultReturn:
   * it skips the interpretation LLM and the entity extraction, leaving AnalyticsExecutor to guess
   * an answer from column names. When only one interpretation executed, that degradation buys
   * nothing — there is no second reading to arbitrate — so the result goes back through the
   * normal path instead. Ambiguity metadata is preserved for observability.
   *
   * @param string $question Original question
   * @param array $ambiguityAnalysis Ambiguity analysis result
   * @param array $winner The single interpretation that produced rows
   * @return array Standard analytics_results payload
   */
  private function buildSingleInterpretationResult(
    string $question,
    array $ambiguityAnalysis,
    array $winner
  ): array {
    if ($this->debug) {
      error_log("Single surviving interpretation '{$winner['type']}' - returning as a standard result");
    }

    $entityInfo = $this->queryExecutor->extractEntityIdFromResults($winner['results']);

    return [
      'type' => 'analytics_results',
      'query' => $question,
      'sql_query' => $winner['sql_query'],
      'original_sql_query' => $winner['sql_query'],
      'corrections' => $winner['corrections'] ?? [],
      'results' => $winner['results'],
      'count' => $winner['count'],
      'entity_id' => $entityInfo['entity_id'],
      'entity_type' => $entityInfo['entity_type'],
      'ambiguous' => $ambiguityAnalysis['is_ambiguous'] ?? false,
      'ambiguity_type' => $ambiguityAnalysis['ambiguity_type'] ?? null,
      'interpretations' => [$winner['type']],
    ];
  }

  /**
   * Request clarification from user for ambiguous query
   * 
   * Builds a standardized clarification request response using AgentResponseHelper.
   * Returns a user-friendly message explaining the ambiguity and requesting
   * clarification.
   * 
   * @param string $question Original ambiguous question
   * @param array $ambiguityAnalysis Ambiguity analysis result from AmbiguousQueryDetector
   * @return array Clarification request result with standardized format
   */
  public function requestClarification(string $question, array $ambiguityAnalysis): array
  {
    if ($this->debug) {
      error_log("\n" . str_repeat("?", 100));
      error_log("DEBUG: AmbiguityHandler.requestClarification()");
      error_log("?" . str_repeat("?", 99));
    }
    
    // Use ResponseHelper for standardized clarification response
    $response = AgentResponseHelper::buildClarificationRequest(
      $question,
      $ambiguityAnalysis['ambiguity_type'] ?? null
    );
    
    if ($this->debug) {
      $message = $response['message'] ?? 'No message';
      error_log("Clarification message: " . $message);
      error_log("?" . str_repeat("?", 99) . "\n");
    }
    
    return $response;
  }
}
