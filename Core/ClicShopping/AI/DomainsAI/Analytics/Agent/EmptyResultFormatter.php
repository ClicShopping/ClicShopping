<?php
/**
 * EmptyResultFormatter.php
 * 
 * Handles formatting of empty SQL query results with helpful context.
 * Uses LLM to generate possible reasons and alternative suggestions.
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;


use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Registry;

/**
 * Class EmptyResultFormatter
 * 
 * Formats empty query results with:
 * - Clear message indicating no results found
 * - The SQL query that was executed
 * - LLM-generated possible reasons for empty results
 * - LLM-generated alternative query suggestions
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5
 */

class EmptyResultFormatter
{
  private mixed $chat;
  private SecurityLogger $securityLogger;
  private mixed $language;
  private bool $debug;
  
  /**
   * Constructor
   * 
   * @param mixed $chat LLM chat instance for generating reasons/suggestions
   * @param SecurityLogger $securityLogger Security logger instance
   * @param bool $debug Enable debug mode
   */
  public function __construct($chat, SecurityLogger $securityLogger, bool $debug = false)
  {
    $this->chat = $chat;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
    $this->language = Registry::get('Language');
    DomainConfig::loadAgnosticLanguageFile('rag_empty_results');
  }
  
  /**
   * Format empty result with helpful context
   * 
   * Generates a structured response for queries that return no data,
   * including possible reasons and alternative suggestions.
   * 
   * @param string $question Original user question
   * @param string $sql SQL query that was executed
   * @param string $queryType Type of query (analytics, semantic, hybrid)
   * @return array Formatted empty result with structure:
   *   - success: bool (true - operation succeeded, just no data)
   *   - message: string (user-friendly message)
   *   - sql_query: string (the executed SQL)
   *   - reasons: array (possible reasons for empty results)
   *   - suggestions: array (alternative query suggestions)
   *   - query_type: string (analytics, semantic, hybrid)
   */
  public function formatEmptyResult(string $question, string $sql, string $queryType = 'analytics'): array
  {
    if ($this->debug) {
      error_log("EmptyResultFormatter: Formatting empty result for query type: {$queryType}");
      error_log("EmptyResultFormatter: Question: " . substr($question, 0, 100));
    }
    
    // Generate possible reasons using LLM
    $reasons = $this->generatePossibleReasons($question, $sql);
    
    // Generate alternative suggestions using LLM
    $suggestions = $this->generateAlternativeSuggestions($question, $sql);
    
    // Get localized message
    $message = $this->language->getDef('text_empty_result_message') 
      ?? 'No results found for your query. This could be due to the data not existing in the database or the search criteria being too specific.';
    
    $result = [
      'success' => true,
      'empty_result' => true,
      'message' => $message,
      'sql_query' => $sql,
      'reasons' => $reasons,
      'suggestions' => $suggestions,
      'query_type' => $queryType,
      'original_question' => $question
    ];
    
    // Log empty result for analytics
    $this->securityLogger->logSecurityEvent(
      "Empty result formatted",
      'info',
      [
        'question' => substr($question, 0, 100),
        'query_type' => $queryType,
        'reasons_count' => \count($reasons),
        'suggestions_count' => \count($suggestions)
      ]
    );
    
    if ($this->debug) {
      error_log("EmptyResultFormatter: Generated " . \count($reasons) . " reasons and " . \count($suggestions) . " suggestions");
    }
    
    return $result;
  }
  
  /**
   * Generate possible reasons for empty results using LLM
   * 
   * Analyzes the question and SQL to suggest why no data was returned.
   * 
   * @param string $question Original user question
   * @param string $sql SQL query that was executed
   * @return array Array of possible reasons
   */
  public function generatePossibleReasons(string $question, string $sql): array
  {
    if ($this->debug) {
      error_log("EmptyResultFormatter: Generating possible reasons");
    }
    
    try {
      $prompt = $this->language->getDef('text_empty_result_reasons_prompt', [
        'QUESTION' => $question,
        'SQL' => $sql
      ]);
      
      if (empty($prompt)) {
        // Fallback prompt if language definition not found
        $prompt = $this->getDefaultReasonsPrompt($question, $sql);
      }
      
      $response = $this->chat->generateText($prompt);
      
      // Parse response into array of reasons
      $reasons = $this->parseReasonsResponse($response);
      
      if ($this->debug) {
        error_log("EmptyResultFormatter: Generated " . \count($reasons) . " reasons");
      }
      
      return $reasons;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("EmptyResultFormatter: Error generating reasons: " . $e->getMessage());
      }
      
      // Return default reasons on error
      return $this->getDefaultReasons();
    }
  }
  
  /**
   * Generate alternative query suggestions using LLM
   * 
   * Suggests alternative ways to phrase the query or different data to look for.
   * 
   * @param string $question Original user question
   * @param string $sql SQL query that was executed
   * @return array Array of alternative suggestions
   */
  public function generateAlternativeSuggestions(string $question, string $sql): array
  {
    if ($this->debug) {
      error_log("EmptyResultFormatter: Generating alternative suggestions");
    }
    
    try {
      $prompt = $this->language->getDef('text_empty_result_suggestions_prompt', [
        'QUESTION' => $question,
        'SQL' => $sql
      ]);
      
      if (empty($prompt)) {
        // Fallback prompt if language definition not found
        $prompt = $this->getDefaultSuggestionsPrompt($question, $sql);
      }
      
      $response = $this->chat->generateText($prompt);
      
      // Parse response into array of suggestions
      $suggestions = $this->parseSuggestionsResponse($response);
      
      if ($this->debug) {
        error_log("EmptyResultFormatter: Generated " . \count($suggestions) . " suggestions");
      }
      
      return $suggestions;
      
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("EmptyResultFormatter: Error generating suggestions: " . $e->getMessage());
      }
      
      // Return default suggestions on error
      return $this->getDefaultSuggestions();
    }
  }
  
  /**
   * Check if results are empty
   * 
   * @param array $results Query results to check
   * @return bool True if results are empty
   */
  public function isEmptyResult(array $results): bool
  {
    if (empty($results)) {
      return true;
    }
    
    // Check if all values are null or zero
    if (\count($results) === 1) {
      $firstRow = reset($results);
      if (\is_array($firstRow)) {
        foreach ($firstRow as $value) {
          if ($value !== null && $value !== 0 && $value !== '0' && $value !== '') {
            return false;
          }
        }
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Parse LLM response for reasons into array
   * 
   * @param string $response LLM response
   * @return array Array of reasons
   */
  private function parseReasonsResponse(string $response): array
  {
    $reasons = [];
    
    // Try to parse as JSON first
    $json = $this->extractJson($response);
    if ($json !== null && isset($json['reasons'])) {
      return $json['reasons'];
    }
    
    // Parse numbered list format
    $lines = explode("\n", $response);
    foreach ($lines as $line) {
      $line = trim($line);
      // Match patterns like "1. reason" or "- reason" or "• reason"
      if (preg_match('/^[\d\.\-\•\*]+\s*(.+)$/', $line, $matches)) {
        $reason = trim($matches[1]);
        if (!empty($reason) && \strlen($reason) > 5) {
          $reasons[] = $reason;
        }
      }
    }
    
    // Limit to 5 reasons
    return \array_slice($reasons, 0, 5);
  }
  
  /**
   * Parse LLM response for suggestions into array
   * 
   * @param string $response LLM response
   * @return array Array of suggestions
   */
  private function parseSuggestionsResponse(string $response): array
  {
    $suggestions = [];
    
    // Try to parse as JSON first
    $json = $this->extractJson($response);
    if ($json !== null && isset($json['suggestions'])) {
      return $json['suggestions'];
    }
    
    // Parse numbered list format
    $lines = explode("\n", $response);
    foreach ($lines as $line) {
      $line = trim($line);
      // Match patterns like "1. suggestion" or "- suggestion" or "• suggestion"
      if (preg_match('/^[\d\.\-\•\*]+\s*(.+)$/', $line, $matches)) {
        $suggestion = trim($matches[1]);
        if (!empty($suggestion) && \strlen($suggestion) > 5) {
          $suggestions[] = $suggestion;
        }
      }
    }
    
    // Limit to 5 suggestions
    return \array_slice($suggestions, 0, 5);
  }
  
  /**
   * Extract JSON from response
   * 
   * @param string $response LLM response
   * @return array|null Parsed JSON or null
   */
  private function extractJson(string $response): ?array
  {
    // Try to extract JSON from markdown code blocks
    if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
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
    
    return null;
  }
  
  /**
   * Get default reasons prompt
   * 
   * @param string $question Original question
   * @param string $sql SQL query
   * @return string Prompt
   */
  private function getDefaultReasonsPrompt(string $question, string $sql): string
  {
    return <<<PROMPT
Analyze why this SQL query returned no results and provide possible reasons.

User Question: {$question}

SQL Query:
{$sql}

Provide 3-5 possible reasons why no data was returned. Consider:
- Time period constraints (data might not exist for the specified period)
- Filter conditions (criteria might be too specific)
- Data availability (the requested data might not be tracked)
- Spelling or terminology differences

Format your response as a numbered list:
1. First reason
2. Second reason
3. Third reason
PROMPT;
  }
  
  /**
   * Get default suggestions prompt
   * 
   * @param string $question Original question
   * @param string $sql SQL query
   * @return string Prompt
   */
  private function getDefaultSuggestionsPrompt(string $question, string $sql): string
  {
    return <<<PROMPT
Based on this query that returned no results, suggest alternative queries the user could try.

User Question: {$question}

SQL Query:
{$sql}

Provide 3-5 alternative query suggestions. Consider:
- Broader time periods
- Less restrictive filters
- Related data that might be available
- Different ways to phrase the question

Format your response as a numbered list:
1. First suggestion
2. Second suggestion
3. Third suggestion
PROMPT;
  }
  
  /**
   * Get default reasons when LLM fails
   * 
   * @return array Default reasons
   */
  private function getDefaultReasons(): array
  {
    return [
      'The data for the specified time period may not exist in the database',
      'The search criteria may be too specific or restrictive',
      'The requested information may not be tracked in the system'
    ];
  }
  
  /**
   * Get default suggestions when LLM fails
   * 
   * @return array Default suggestions
   */
  private function getDefaultSuggestions(): array
  {
    return [
      'Try expanding the time period (e.g., "last year" instead of "last month")',
      'Try using broader search terms',
      'Check if the data category exists in the system'
    ];
  }
}
