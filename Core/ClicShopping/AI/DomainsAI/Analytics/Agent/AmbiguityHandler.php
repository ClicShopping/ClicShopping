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
  private bool $debug;
  
  /**
   * Constructor
   * 
   * @param AmbiguousQueryDetector $ambiguityDetector Ambiguity detection component
   * @param mixed $queryProcessor SQL query processor
   * @param mixed $queryExecutor Query executor
   * @param bool $debug Debug mode flag
   */
  public function __construct(
    AmbiguousQueryDetector $ambiguityDetector,
    $queryProcessor,
    $queryExecutor,
    bool $debug = false
  ) {
    $this->ambiguityDetector = $ambiguityDetector;
    $this->queryProcessor = $queryProcessor;
    $this->queryExecutor = $queryExecutor;
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
        
        // Execute query
        $executionResult = $this->queryExecutor->execute($resolvedQuery);
        
        if (!$executionResult['success']) {
          if ($this->debug) {
            error_log("  Execution failed: " . ($executionResult['error'] ?? 'Unknown error'));
          }
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
    
    if ($this->debug) {
      error_log("*" . str_repeat("*", 99) . "\n");
    }
    
    // Use ResponseHelper for standardized ambiguous response
    return AgentResponseHelper::buildAmbiguousResponse(
      $question,
      $ambiguityAnalysis['ambiguity_type'],
      $interpretationResults
    );
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
