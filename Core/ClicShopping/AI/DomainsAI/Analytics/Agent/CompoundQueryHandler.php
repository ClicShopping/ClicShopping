<?php
/**
 * CompoundQueryHandler.php
 * 
 * Handles detection, splitting, and processing of compound queries.
 * A compound query contains multiple distinct questions that should be
 * processed separately (e.g., "pending orders and total revenue").
 * 
 * Uses LLM for detection and splitting (Pure LLM Mode).
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;

/**
 * Class CompoundQueryHandler
 * 
 * Detects and processes compound queries containing multiple questions.
 * 
 * Features:
 * - LLM-based compound query detection
 * - Intelligent query splitting
 * - Independent sub-query execution
 * - Combined result formatting
 * - Partial failure handling
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6
 */

class CompoundQueryHandler
{
  private mixed $chat;
  private SecurityLogger $securityLogger;
  private mixed $language;
  private bool $debug;
  
  /**
   * Constructor
   * 
   * @param mixed $chat LLM chat instance for detection and splitting
   * @param SecurityLogger $securityLogger Security logger instance
   * @param bool $debug Enable debug mode
   */
  public function __construct($chat, SecurityLogger $securityLogger, bool $debug = false)
  {
    $this->chat = $chat;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
    $this->language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_compound_query');
  }

  
  /**
   * Detect if query is compound (multiple questions) using LLM
   * 
   * Uses LLM to analyze if the query contains multiple distinct questions
   * that should be processed separately.
   * 
   * @param string $query User query to analyze
   * @return array Detection result with structure:
   *   - is_compound: bool
   *   - confidence: float (0.0-1.0)
   *   - reasoning: string
   *   - sub_queries: array (if compound)
   *   - indicators: array (detected connectors)
   */
  public function detectCompoundQuery(string $query): array
  {
    if ($this->debug) {
      error_log(str_repeat("=", 80));
      error_log("CompoundQueryHandler: detectCompoundQuery()");
      error_log("Query: " . substr($query, 0, 100));
    }
    
    try {
      // Get detection prompt from language file with query parameter
      $prompt = $this->language->getDef('text_compound_query_detection_prompt', ['query' => $query]);
      
      // Call LLM for detection
      $response = $this->chat->generateText($prompt);
      
      // Parse LLM response
      $result = $this->parseDetectionResponse($response, $query);
      
      // Log detection result
      $this->securityLogger->logSecurityEvent(
        "Compound query detection",
        'info',
        [
          'query' => substr($query, 0, 100),
          'is_compound' => $result['is_compound'],
          'confidence' => $result['confidence'],
          'sub_query_count' => \count($result['sub_queries'] ?? [])
        ]
      );
      
      if ($this->debug) {
        error_log("Detection result: " . ($result['is_compound'] ? 'COMPOUND' : 'NOT COMPOUND'));
        error_log("Confidence: " . $result['confidence']);
        error_log(str_repeat("=", 80) . "\n");
      }
      
      return $result;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("CompoundQueryHandler: Detection error: " . $e->getMessage());
      }
      
      // Return non-compound on error
      return [
        'is_compound' => false,
        'confidence' => 0.0,
        'reasoning' => 'Detection failed: ' . $e->getMessage(),
        'sub_queries' => [],
        'indicators' => [],
        'error' => true
      ];
    }
  }
  
  /**
   * Split compound query into sub-queries using LLM
   * 
   * Takes a compound query and splits it into independent sub-queries
   * that can be processed separately.
   * 
   * @param string $query Compound query to split
   * @return array List of sub-queries with structure:
   *   - label: string (short descriptive label)
   *   - query: string (complete sub-query text)
   *   - original_part: string (part from original query)
   */
  public function splitIntoSubQueries(string $query): array
  {
    if ($this->debug) {
      error_log(str_repeat("-", 80));
      error_log("CompoundQueryHandler: splitIntoSubQueries()");
      error_log("Query: " . substr($query, 0, 100));
    }
    
    try {
      // Get split prompt from language file with query parameter
      $prompt = $this->language->getDef('text_compound_query_split_prompt', ['query' => $query]);
      
      // Call LLM for splitting
      $response = $this->chat->generateText($prompt);
      
      // Parse LLM response
      $subQueries = $this->parseSplitResponse($response);
      
      if ($this->debug) {
        error_log("Split into " . \count($subQueries) . " sub-queries");
        foreach ($subQueries as $idx => $sq) {
          error_log("  " . ($idx + 1) . ". " . $sq['label'] . ": " . substr($sq['query'], 0, 50));
        }
        error_log(str_repeat("-", 80) . "\n");
      }
      
      return $subQueries;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("CompoundQueryHandler: Split error: " . $e->getMessage());
      }
      
      // Return original query as single sub-query on error
      return [
        [
          'label' => 'Original Query',
          'query' => $query,
          'original_part' => $query,
          'error' => true
        ]
      ];
    }
  }

  
  /**
   * Execute all sub-queries and combine results
   * 
   * Executes each sub-query independently using the provided executor
   * and combines all results. Handles partial failures gracefully.
   * 
   * @param array $subQueries List of sub-queries from splitIntoSubQueries()
   * @param callable $executor Query executor function (receives query string, returns result array)
   * @return array Combined results with structure:
   *   - is_compound: true
   *   - sub_queries: array of results per sub-query
   *   - partial_success: bool (true if some but not all succeeded)
   *   - all_success: bool (true if all succeeded)
   *   - all_failed: bool (true if all failed)
   */
  public function executeAndCombine(array $subQueries, callable $executor): array
  {
    if ($this->debug) {
      error_log("\n" . str_repeat("+", 80));
      error_log("CompoundQueryHandler: executeAndCombine()");
      error_log("Sub-queries to execute: " . \count($subQueries));
    }
    
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    foreach ($subQueries as $idx => $subQuery) {
      $label = $subQuery['label'] ?? 'Query ' . ($idx + 1);
      $queryText = $subQuery['query'] ?? '';
      
      if ($this->debug) {
        error_log("\nExecuting sub-query " . ($idx + 1) . ": " . $label);
        error_log("Query: " . substr($queryText, 0, 80));
      }
      
      try {
        // Execute sub-query using provided executor
        $executionResult = $executor($queryText);
        
        // Check if execution was successful
        $success = isset($executionResult['type']) && 
                   $executionResult['type'] !== 'error' &&
                   !isset($executionResult['error']);
        
        if ($success) {
          $successCount++;
          
          $results[] = [
            'label' => $label,
            'query' => $queryText,
            'original_part' => $subQuery['original_part'] ?? $queryText,
            'success' => true,
            'results' => $executionResult['results'] ?? [],
            'sql_query' => $executionResult['sql_query'] ?? '',
            'interpretation' => $executionResult['interpretation'] ?? null,
            'count' => $executionResult['count'] ?? 0
          ];
          
          if ($this->debug) {
            error_log("  ✓ Success - " . ($executionResult['count'] ?? 0) . " results");
          }
        } else {
          $failCount++;
          
          $results[] = [
            'label' => $label,
            'query' => $queryText,
            'original_part' => $subQuery['original_part'] ?? $queryText,
            'success' => false,
            'error' => $executionResult['error'] ?? $executionResult['message'] ?? 'Unknown error',
            'sql_query' => $executionResult['sql_query'] ?? ''
          ];
          
          if ($this->debug) {
            error_log("  ✗ Failed - " . ($executionResult['error'] ?? 'Unknown error'));
          }
        }
        
      } catch (\Exception $e) {
        $failCount++;
        
        $results[] = [
          'label' => $label,
          'query' => $queryText,
          'original_part' => $subQuery['original_part'] ?? $queryText,
          'success' => false,
          'error' => $e->getMessage()
        ];
        
        if ($this->debug) {
          error_log("  ✗ Exception - " . $e->getMessage());
        }
      }
    }
    
    $totalCount = \count($subQueries);
    $allSuccess = $successCount === $totalCount;
    $allFailed = $failCount === $totalCount;
    $partialSuccess = $successCount > 0 && $failCount > 0;
    
    // Log combined execution result
    $this->securityLogger->logSecurityEvent(
      "Compound query execution completed",
      'info',
      [
        'total_sub_queries' => $totalCount,
        'success_count' => $successCount,
        'fail_count' => $failCount,
        'all_success' => $allSuccess,
        'partial_success' => $partialSuccess
      ]
    );
    
    if ($this->debug) {
      error_log("\nExecution summary: {$successCount}/{$totalCount} succeeded");
      error_log(str_repeat("+", 80) . "\n");
    }
    
    return [
      'is_compound' => true,
      'sub_queries' => $results,
      'total_count' => $totalCount,
      'success_count' => $successCount,
      'fail_count' => $failCount,
      'all_success' => $allSuccess,
      'all_failed' => $allFailed,
      'partial_success' => $partialSuccess
    ];
  }

  
  /**
   * Format combined results for display
   * 
   * Creates a user-friendly formatted response from combined results using LLM.
   * Handles partial failures and provides clear separation between sub-query results.
   * 
   * @param array $combinedResults Results from executeAndCombine()
   * @param string $originalQuery Original compound query
   * @return array Formatted response with structure:
   *   - type: 'compound_results'
   *   - query: original query
   *   - formatted_response: string (human-readable)
   *   - sub_results: array (detailed results per sub-query)
   *   - success: bool
   */
  public function formatCombinedResults(array $combinedResults, string $originalQuery = ''): array
  {
    if ($this->debug) {
      error_log("\n" . str_repeat("~", 80));
      error_log("CompoundQueryHandler: formatCombinedResults()");
    }
    
    $subResults = $combinedResults['sub_queries'] ?? [];
    $allFailed = $combinedResults['all_failed'] ?? false;
    $partialSuccess = $combinedResults['partial_success'] ?? false;
    
    // Prepare results summary for LLM
    $resultsJson = json_encode($subResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Use LLM for formatting with text_compound_format_prompt
    $formattedResponse = $this->formatWithLLM($originalQuery, $resultsJson);
    
    // Fallback to manual formatting if LLM fails
    if (empty($formattedResponse)) {
      $formattedResponse = $this->formatManually($subResults, $allFailed, $partialSuccess);
    }
    
    if ($this->debug) {
      error_log("Formatted response length: " . \strlen($formattedResponse));
      error_log(str_repeat("~", 80) . "\n");
    }
    
    return [
      'type' => 'compound_results',
      'query' => $originalQuery,
      'formatted_response' => $formattedResponse,
      'sub_results' => $subResults,
      'success' => !$allFailed,
      'partial_success' => $partialSuccess,
      'all_success' => $combinedResults['all_success'] ?? false,
      'total_count' => $combinedResults['total_count'] ?? \count($subResults),
      'success_count' => $combinedResults['success_count'] ?? 0
    ];
  }
  
  /**
   * Format results using LLM with text_compound_format_prompt
   * 
   * @param string $originalQuery Original query
   * @param string $resultsJson JSON-encoded results
   * @return string Formatted response or empty string on failure
   */
  private function formatWithLLM(string $originalQuery, string $resultsJson): string
  {
    try {
      $prompt = $this->language->getDef('text_compound_format_prompt', [
        'query' => $originalQuery,
        'results' => $resultsJson
      ]);
      
      if (empty($prompt)) {
        return '';
      }
      
      return $this->chat->generateText($prompt);
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("CompoundQueryHandler: LLM formatting failed: " . $e->getMessage());
      }
      return '';
    }
  }
  
  /**
   * Format results manually (fallback if LLM fails)
   * 
   * @param array $subResults Sub-query results
   * @param bool $allFailed All queries failed
   * @param bool $partialSuccess Some queries succeeded
   * @return string Formatted response
   */
  private function formatManually(array $subResults, bool $allFailed, bool $partialSuccess): string
  {
    $formattedParts = [];
    
    // Header
    $header = $this->language->getDef('text_compound_result_header') ?? 'Combined Results for Your Query';
    $formattedParts[] = "## {$header}";
    $formattedParts[] = "";
    
    // Status message for partial success
    if ($partialSuccess) {
      $partialMsg = $this->language->getDef('text_compound_partial_success') ?? 'Some parts of your query could not be processed. Here are the available results:';
      $formattedParts[] = "⚠️ {$partialMsg}";
      $formattedParts[] = "";
    }
    
    // All failed message
    if ($allFailed) {
      $failedMsg = $this->language->getDef('text_compound_all_failed') ?? 'Unable to process any part of your compound query. Please try rephrasing or asking each question separately.';
      $formattedParts[] = "❌ {$failedMsg}";
      $formattedParts[] = "";
    }
    
    // Format each sub-query result
    $separator = $this->language->getDef('text_compound_result_separator') ?? '---';
    $labelPrefix = $this->language->getDef('text_compound_result_label_prefix') ?? 'Result for:';
    $errorPrefix = $this->language->getDef('text_compound_sub_query_error') ?? 'Error processing this part:';
    
    foreach ($subResults as $idx => $result) {
      if ($idx > 0) {
        $formattedParts[] = "";
        $formattedParts[] = $separator;
        $formattedParts[] = "";
      }
      
      $label = $result['label'] ?? 'Query ' . ($idx + 1);
      $formattedParts[] = "### {$labelPrefix} {$label}";
      $formattedParts[] = "";
      
      if ($result['success'] ?? false) {
        if (!empty($result['interpretation'])) {
          $formattedParts[] = $result['interpretation'];
        } else {
          $count = $result['count'] ?? 0;
          $formattedParts[] = "Found {$count} result(s).";
        }
        
        if (!empty($result['sql_query'])) {
          $formattedParts[] = "";
          $formattedParts[] = "```sql";
          $formattedParts[] = $result['sql_query'];
          $formattedParts[] = "```";
        }
      } else {
        $error = $result['error'] ?? 'Unknown error';
        $formattedParts[] = "❌ {$errorPrefix} {$error}";
      }
    }
    
    return implode("\n", $formattedParts);
  }

  
  /**
   * Parse LLM detection response
   * 
   * @param string $response LLM response
   * @param string $originalQuery Original query for fallback
   * @return array Parsed detection result
   */
  private function parseDetectionResponse(string $response, string $originalQuery): array
  {
    // Try to extract JSON from response
    $json = $this->extractJson($response);
    
    if ($json !== null) {
      return [
        'is_compound' => $json['is_compound'] ?? false,
        'confidence' => (float)($json['confidence'] ?? 0.5),
        'reasoning' => $json['reasoning'] ?? '',
        'sub_queries' => $json['sub_queries'] ?? []
      ];
    }
    
    // Fallback: try to detect from text response
    $isCompound = stripos($response, 'true') !== false || 
                  stripos($response, 'compound') !== false ||
                  stripos($response, 'multiple') !== false;
    
    return [
      'is_compound' => $isCompound,
      'confidence' => 0.5,
      'reasoning' => 'Parsed from text response',
      'sub_queries' => []
    ];
  }
  
  /**
   * Parse LLM split response
   * 
   * @param string $response LLM response
   * @return array List of sub-queries
   */
  private function parseSplitResponse(string $response): array
  {
    // Try to extract JSON from response
    $json = $this->extractJson($response);
    
    if ($json !== null && isset($json['sub_queries'])) {
      return $json['sub_queries'];
    }
    
    // Fallback: try to parse numbered list
    $subQueries = [];
    $lines = explode("\n", $response);
    
    foreach ($lines as $line) {
      $line = trim($line);
      // Match patterns like "1. query" or "- query"
      if (preg_match('/^[\d\.\-\•\*]+\s*(.+)$/', $line, $matches)) {
        $query = trim($matches[1]);
        if (!empty($query) && \strlen($query) > 3) {
          $subQueries[] = [
            'label' => 'Query ' . (\count($subQueries) + 1),
            'query' => $query,
            'original_part' => $query
          ];
        }
      }
    }
    
    return $subQueries;
  }
  
  /**
   * Extract JSON from LLM response
   * 
   * @param string $response LLM response
   * @return array|null Parsed JSON or null
   */
  private function extractJson(string $response): ?array
  {
    // Try to extract JSON from markdown code blocks
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $response, $matches)) {
      $json = json_decode($matches[1], true);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
      }
    }
    
    // Try direct JSON parse
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      return $json;
    }
    
    // Try to find JSON object in response
    if (preg_match('/\{[^{}]*\}/s', $response, $matches)) {
      $json = json_decode($matches[0], true);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
      }
    }
    
    return null;
  }
  
}
