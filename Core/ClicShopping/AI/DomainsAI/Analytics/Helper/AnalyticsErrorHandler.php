<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Helper;


use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * AnalyticsErrorHandler
 * 
 * Handles error recovery and user-friendly error messaging for AnalyticsAgent
 * Manages intelligent query correction and empty result handling
 * 
 * Responsibilities:
 * - Attempt intelligent correction of failed queries
 * - Generate user-friendly error suggestions
 * - Handle empty results gracefully
 * - Coordinate with CorrectionAgent for learning
 * 
 * @package ClicShopping\AI\Handler\Error
 */

class AnalyticsErrorHandler
{
  private mixed $db;
  private mixed $correctionAgent;
  private mixed $queryExecutor;
  private array $correctionLog = [];
  private bool $debug;
  
  /**
   * Constructor
   * 
   * @param mixed $db Database connection
   * @param mixed $correctionAgent Correction agent for intelligent fixes
   * @param mixed $queryExecutor Query executor for deduplication
   * @param bool $debug Debug mode flag
   */
  public function __construct(
    $db,
    $correctionAgent,
    $queryExecutor,
    bool $debug = false
  ) {
    $this->db = $db;
    $this->correctionAgent = $correctionAgent;
    $this->queryExecutor = $queryExecutor;
    $this->debug = $debug;
  }
  
  /**
   * Attempt intelligent correction of a failed query
   * 
   * Uses CorrectionAgent to analyze the error and suggest a corrected query.
   * If correction succeeds, executes the corrected query and returns results.
   * Maintains correction log for learning purposes.
   * 
   * @param \Exception $originalError The exception that occurred
   * @param string $failedQuery The SQL query that failed
   * @param string $originalQuery The original SQL before modifications
   * @param string $userQuestion The user's natural language question
   * @return array Result with success flag and data or error information
   */
  public function attemptIntelligentCorrection(
    \Exception $originalError,
    string $failedQuery,
    string $originalQuery,
    string $userQuestion
  ): array {
    // Load language definitions
    $CLICSHOPPING_Language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_error_handler');
      
    try {
      // Prepare error context for CorrectionAgent
      $errorContext = [
        'error_message' => $originalError->getMessage(),
        'failed_query' => $failedQuery,
        'original_query' => $userQuestion,
        'previous_corrections' => $this->correctionLog,
      ];

      // Ask CorrectionAgent to correct
      $correctionResult = $this->correctionAgent->attemptCorrection($errorContext);

      if (!$correctionResult['success']) {
        return [
          'success' => false,
          'error' => $correctionResult['error'] ?? 'Correction failed',
          'suggestions' => $correctionResult['suggestions'] ?? [],
        ];
      }

      // Get corrected query
      $correctedQuery = $correctionResult['corrected_query'];

      // Log the correction
      $this->correctionLog[] = [
        'original_error' => $originalError->getMessage(),
        'correction_method' => $correctionResult['correction_method'],
        'confidence' => $correctionResult['confidence'],
        'learned_from_history' => $correctionResult['learned_from_history'] ?? false,
      ];

      // Attempt to execute corrected query
      $query = $this->db->prepare($correctedQuery);

      if (!$query) {
        throw new \Exception("Failed to prepare corrected query");
      }

      $query->execute();
      $rows = $query->fetchAll(\PDO::FETCH_ASSOC);
      $queryResults = $this->queryExecutor->deduplicateRows($rows);
    
      // Success!
      return [
        'success' => true,
        'data' => [
          'original_query' => $originalQuery,
          'failed_query' => $failedQuery,
          'executed_query' => $correctedQuery,
          'results' => $queryResults,
          'count' => count($queryResults),
          'corrections' => $this->correctionLog,
          'correction_details' => [
            'method' => $correctionResult['correction_method'],
            'confidence' => $correctionResult['confidence'],
            'learned_from_history' => $correctionResult['learned_from_history'] ?? false,
            'similar_cases_found' => $correctionResult['similar_cases_found'] ?? 0,
          ],
          'message' => CLICSHOPPING::getDef('text_correction_success'),
        ],
      ];
    } catch (\Exception $e) {
      // Correction failed or corrected query also failed
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'correction_attempted' => true,
      ];
    }
  }
  
  /**
   * Generate user-friendly error suggestion based on error type
   * 
   * Analyzes the database error message and returns a helpful suggestion
   * for the user. Handles common error types like unknown columns, syntax
   * errors, and missing tables.
   * 
   * @param string $errorMessage Error message from database
   * @param string $question Original question that generated the query
   * @return string User-friendly suggestion for fixing the error
   */
  public function generateErrorSuggestion(string $errorMessage, string $question): string
  {
    // Load language definitions
    $CLICSHOPPING_Language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_error_handler');
    
    // Suggestions based on the type of error
    if (str_contains($errorMessage, 'Unknown column')) {
      return CLICSHOPPING::getDef('text_column_reference_does_not_exist');
    }

    if (str_contains($errorMessage, 'syntax error')) {
      return CLICSHOPPING::getDef('text_sql_query_generated_error');
    }

    if (str_contains($errorMessage, 'Table') && str_contains($errorMessage, 'doesn\'t exist')) {
      return CLICSHOPPING::getDef('text_table_referenced_does_not_exist');
    }

    // Generic suggestion
    return CLICSHOPPING::getDef('text_error_executing_query');
  }
  
  /**
   * Generate user-friendly message for empty results
   * 
   * Creates a helpful message when a query returns no results. Analyzes
   * the SQL query to provide context-specific suggestions (e.g., check
   * spelling for product searches, try wider date range for time queries).
   * 
   * @param string $question The user's question
   * @param array $results The results array (with sql_query if available)
   * @param bool $debug Whether to include debug information
   * @return string User-friendly message explaining why no results were found
   */
  public function generateEmptyResultsMessage(string $question, array $results, bool $debug = false): string
  {
    // Load language definitions
    $CLICSHOPPING_Language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_error_handler');
    
    // Base message - more conversational and asking for clarification
    $message = CLICSHOPPING::getDef('text_empty_results_base');

    // Add helpful suggestions based on query type
    if (isset($results['sql_query'])) {
      $sql = $results['sql_query'];

      // Product search
      if (stripos($sql, 'products_name') !== false || stripos($sql, 'products_description') !== false) {
        $message .= CLICSHOPPING::getDef('text_empty_results_product_title');
        $message .= CLICSHOPPING::getDef('text_empty_results_product_spelling');
        $message .= CLICSHOPPING::getDef('text_empty_results_product_shorter');
        $message .= CLICSHOPPING::getDef('text_empty_results_product_generic');
        $message .= CLICSHOPPING::getDef('text_empty_results_product_list_all');
      }
      // Date/time queries
      elseif (stripos($sql, 'date') !== false || stripos($sql, 'datetime') !== false) {
        $message .= CLICSHOPPING::getDef('text_empty_results_date_title');
        $message .= CLICSHOPPING::getDef('text_empty_results_date_period');
        $message .= CLICSHOPPING::getDef('text_empty_results_date_wider');
        $message .= CLICSHOPPING::getDef('text_empty_results_date_recent');
      }
      // Category queries
      elseif (stripos($sql, 'categories') !== false) {
        $message .= CLICSHOPPING::getDef('text_empty_results_category_title');
        $message .= CLICSHOPPING::getDef('text_empty_results_category_name');
        $message .= CLICSHOPPING::getDef('text_empty_results_category_parent');
        $message .= CLICSHOPPING::getDef('text_empty_results_category_list_all');
      }
      // Customer/order queries
      elseif (stripos($sql, 'customers') !== false || stripos($sql, 'orders') !== false) {
        $message .= CLICSHOPPING::getDef('text_empty_results_customer_title');
        $message .= CLICSHOPPING::getDef('text_empty_results_customer_criteria');
        $message .= CLICSHOPPING::getDef('text_empty_results_customer_period');
        $message .= CLICSHOPPING::getDef('text_empty_results_customer_specific');
      }
      elseif (stripos($sql, 'products_model') !== false || 
              stripos($question, 'référence') !== false || 
              stripos($question, 'sku') !== false ||
              stripos($question, 'modèle') !== false) {
        $message .= CLICSHOPPING::getDef('text_empty_results_reference_title');
        $message .= CLICSHOPPING::getDef('text_empty_results_reference_exists');
        $message .= CLICSHOPPING::getDef('text_empty_results_reference_by_name');
        $message .= CLICSHOPPING::getDef('text_empty_results_reference_list_all');
        $message .= CLICSHOPPING::getDef('text_empty_results_reference_exact_name');
      }
      // Generic suggestion
      else {
        $message .= CLICSHOPPING::getDef('text_empty_results_generic_title');
        $message .= CLICSHOPPING::getDef('text_empty_results_generic_rephrase');
        $message .= CLICSHOPPING::getDef('text_empty_results_generic_criteria');
        $message .= CLICSHOPPING::getDef('text_empty_results_generic_simpler');
      }
    } else {
      // No SQL query available, generic message
      $message .= CLICSHOPPING::getDef('text_empty_results_no_sql_title');
      $message .= CLICSHOPPING::getDef('text_empty_results_no_sql_precision');
      $message .= CLICSHOPPING::getDef('text_empty_results_no_sql_data_exists');
      $message .= CLICSHOPPING::getDef('text_empty_results_no_sql_simpler');
    }

    // Add debug info if enabled
    if ($debug && isset($results['sql_query'])) {
      $message .= CLICSHOPPING::getDef('text_empty_results_debug_title');
      $message .= CLICSHOPPING::getDef('text_empty_results_debug_query', ['sql_query' => $results['sql_query']]);
    }

    return $message;
  }
  
  /**
   * Get correction log
   * 
   * Returns the log of all corrections attempted during this session.
   * Useful for debugging and learning purposes.
   * 
   * @return array Array of correction log entries
   */
  public function getCorrectionLog(): array
  {
    return $this->correctionLog;
  }
}
