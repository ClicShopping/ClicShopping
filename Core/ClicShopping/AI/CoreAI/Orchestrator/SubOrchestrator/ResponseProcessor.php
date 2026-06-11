<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;

/**
 * ResponseProcessor Class
 *
 * Responsible for response formatting, extraction, and error handling.
 * Separated from OrchestratorAgent to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Format responses for memory storage
 * - Extract final responses from execution results
 * - Build structured error responses
 * - Analyze errors and generate user-friendly messages
 *
 */

class ResponseProcessor
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private $language;
  private string $languageCode;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->securityLogger = new SecurityLogger();

    // Initialize language support
    $this->language = Registry::get('Language');
    $this->languageCode = $this->language->get('code');

    DomainConfig::loadAgnosticLanguageFile('rag_response_processor');
    DomainConfig::loadLanguageFile('rag_response_processor');

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("ResponseProcessor initialized", 'info');
    }
  }

  /**
   * Format response for memory storage
   *
   * Converts structured response arrays into human-readable text format
   * suitable for storage in conversation memory.
   *
   * @param array $response Response array with type and data
   * @return string Formatted response text
   */
  public function formatResponseForMemory(array $response): string
  {
    if ($response['type'] === 'analytics') {
      $data = $response['data'] ?? [];
      $formattedResponse = "Type: Analytics\n";
      $formattedResponse .= "Interpretation: " . ($data['interpretation'] ?? 'N/A') . "\n";

      // Include SQL query if available
      if (!empty($data['sql_query'])) {
        $formattedResponse .= "SQL Query: " . $data['sql_query'] . "\n";
      }

      // Include results if available
      if (!empty($data['results'])) {
        $resultCount = is_array($data['results']) ? count($data['results']) : 0;
        $formattedResponse .= "Results Count: " . $resultCount . "\n";

        // Include sample results for context
        if (is_array($data['results']) && !empty($data['results'])) {
          $sample = array_slice($data['results'], 0, 3); // First 3 results
          $formattedResponse .= "Sample Results: " . json_encode($sample, JSON_UNESCAPED_UNICODE) . "\n";
        }
      }

    } elseif ($response['type'] === 'semantic') {
      $data = $response['data'] ?? [];
      $formattedResponse = "Type: Semantic\n";
      $formattedResponse .= "Response: " . ($data['response'] ?? 'N/A') . "\n";

      // Include semantic context if available
      if (!empty($data['context'])) {
        $formattedResponse .= "Context: " . json_encode($data['context'], JSON_UNESCAPED_UNICODE) . "\n";
      }

    } elseif ($response['type'] === 'hybrid') {
      $data = $response['data'] ?? [];
      $formattedResponse = "Type: Hybrid\n";
      $formattedResponse .= "Summary: " . ($data['summary'] ?? 'N/A') . "\n";

      // Include analytics and semantic data
      if (!empty($data['analytics'])) {
        $formattedResponse .= "Analytics: " . json_encode($data['analytics'], JSON_UNESCAPED_UNICODE) . "\n";
      }
      if (!empty($data['semantic'])) {
        $formattedResponse .= "Semantic: " . json_encode($data['semantic'], JSON_UNESCAPED_UNICODE) . "\n";
      }
    } else {
      $formattedResponse = "Type: " . ($response['type'] ?? 'Unknown') . "\n";
      $formattedResponse .= "Data: " . json_encode($response['data'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    }

    return $formattedResponse;
  }

  /**
   * Extract final response from exe
   * Extracts the human-readable response text from various execution result structures.
   * Handles analytics, semantic, and hybrid response types.
   *
   * @param mixed $executionResult Execution result (string or array)
   * @return string Final response text
   */
  public function extractFinalResponse($executionResult): string
  {
    // If it's already a string, return it
    if (is_string($executionResult)) {
      return $executionResult;
    }

    // If it's an array, search for the response
    if (is_array($executionResult)) {
      if (isset($executionResult['text_response']) && !empty($executionResult['text_response'])) {
        // Check if text_response is NOT the JSON fallback
        if (!str_contains($executionResult['text_response'], 'Résultat:')) {
          return $executionResult['text_response'];
        }
      }
      
      if (isset($executionResult['response']) && !empty($executionResult['response'])) {
        return $executionResult['response'];
      }

      if (isset($executionResult['interpretation']) && !empty($executionResult['interpretation'])) {
        return $executionResult['interpretation'];
      }
      
      if (isset($executionResult['data']) && is_array($executionResult['data'])) {
        // Try to extract response from data
        if (isset($executionResult['data']['response']) && !empty($executionResult['data']['response'])) {
          return $executionResult['data']['response'];
        }
        if (isset($executionResult['data']['interpretation']) && !empty($executionResult['data']['interpretation'])) {
          return $executionResult['data']['interpretation'];
        }
      }
      
      // Case 1: Semantic result
      if (isset($executionResult['type']) && $executionResult['type'] === 'semantic_results') {
        // Already checked response above, return default
        return $this->language->getDef('response_not_available');
      }
      
      if (isset($executionResult['type']) && $executionResult['type'] === 'semantic') {
        // Already checked response above, check answer field
        if (isset($executionResult['answer']) && !empty($executionResult['answer'])) {
          return $executionResult['answer'];
        }
        return $this->language->getDef('response_not_available');
      }

      // Case 2: Analytics result
      if (isset($executionResult['type']) && $executionResult['type'] === 'analytics_results') {
        // Already checked interpretation above, return default
        return $this->language->getDef('analysis_not_available');
      }
 
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "⚠️ TASK 2.17.1: extractFinalResponse falling back to JSON - this indicates a problem!",
          'warning'
        );
        $this->securityLogger->logSecurityEvent(
          "   executionResult keys: " . implode(', ', array_keys($executionResult)),
          'warning'
        );
        $this->securityLogger->logSecurityEvent(
          "   executionResult type: " . ($executionResult['type'] ?? 'NOT SET'),
          'warning'
        );
      }

      // Case 5: Fallback - return a clean message instead of JSON dump
      // This prevents JSON parsing errors in the frontend
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "⚠️ extractFinalResponse: No valid response field found, returning fallback message",
          'warning'
        );
      }
      return $this->language->getDef('response_extraction_fallback');
    }

    // Final fallback
    return $this->language->getDef('response_not_available');
  }

  /**
   * Build error response with context
   *
   * Creates a structured error response with user-friendly message,
   * error details, suggestions, and retry capability.
   *
   * @param string $message Error message
   * @param array $context Error context (query, intent, etc.)
   * @return array Error response array
   */
  public function buildErrorResponse(string $message, array $context = []): array
  {
    // Analyze error type and create explicit message
    $errorInfo = $this->analyzeError($message, $context);

    return [
      'success' => false,
      'type' => 'error',
      'error' => $errorInfo['user_message'],
      'error_details' => $errorInfo['details'],
      'suggestions' => $errorInfo['suggestions'],
      'can_retry' => $errorInfo['can_retry'],
      'timestamp' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Analyze error and generate user-friendly message
   *
   * Matches error patterns and provides appropriate user messages,
   * details, suggestions, and retry capability.
   *
   * @param string $message Error message
   * @param array $context Error context
   * @return array Error analysis with user_message, details, suggestions, can_retry
   */
  private function analyzeError(string $message, array $context = []): array
  {
    // Known error patterns
    // NOTE: Order matters! More specific patterns should come before general ones
    $errorPatterns = [
      // Database errors
      '/database|connection|mysql|pdo/i' => [
        'user_message' => $this->language->getDef('error_database_user_message'),
        'details' => $this->language->getDef('error_database_details'),
        'suggestions' => [
          $this->language->getDef('error_database_suggestion_1'),
          $this->language->getDef('error_database_suggestion_2')
        ],
        'can_retry' => true
      ],

      // Timeout errors
      '/timeout|timed out|time limit/i' => [
        'user_message' => $this->language->getDef('error_timeout_user_message'),
        'details' => $this->language->getDef('error_timeout_details'),
        'suggestions' => [
          $this->language->getDef('error_timeout_suggestion_1'),
          $this->language->getDef('error_timeout_suggestion_2'),
          $this->language->getDef('error_timeout_suggestion_3')
        ],
        'can_retry' => true
      ],

      // Missing data errors (check BEFORE SQL pattern since "query" appears in both)
      '/not found|no data|empty/i' => [
        'user_message' => $this->language->getDef('error_no_data_user_message'),
        'details' => $this->language->getDef('error_no_data_details'),
        'suggestions' => [
          $this->language->getDef('error_no_data_suggestion_1'),
          $this->language->getDef('error_no_data_suggestion_2'),
          $this->language->getDef('error_no_data_suggestion_3')
        ],
        'can_retry' => false
      ],

      // SQL validation errors (after "no data" pattern)
      '/sql|syntax|query/i' => [
        'user_message' => $this->language->getDef('error_sql_user_message'),
        'details' => $this->language->getDef('error_sql_details'),
        'suggestions' => [
          $this->language->getDef('error_sql_suggestion_1'),
          $this->language->getDef('error_sql_suggestion_2'),
          $this->language->getDef('error_sql_suggestion_3')
        ],
        'can_retry' => true
      ],

      // Permission errors
      '/permission|access|denied|forbidden/i' => [
        'user_message' => $this->language->getDef('error_permission_user_message'),
        'details' => $this->language->getDef('error_permission_details'),
        'suggestions' => [
          $this->language->getDef('error_permission_suggestion_1'),
          $this->language->getDef('error_permission_suggestion_2')
        ],
        'can_retry' => false
      ],

      // GPT/LLM service errors
      '/gpt|openai|api|llm/i' => [
        'user_message' => $this->language->getDef('error_ai_service_user_message'),
        'details' => $this->language->getDef('error_ai_service_details'),
        'suggestions' => [
          $this->language->getDef('error_ai_service_suggestion_1'),
          $this->language->getDef('error_ai_service_suggestion_2')
        ],
        'can_retry' => true
      ],
    ];

    // Match error pattern
    foreach ($errorPatterns as $pattern => $errorInfo) {
      if (preg_match($pattern, $message)) {
        return $errorInfo;
      }
    }

    // Generic fallback
    return [
      'user_message' => $this->language->getDef('error_generic_user_message'),
      'details' => $message,
      'suggestions' => [
        $this->language->getDef('error_generic_suggestion_1'),
        $this->language->getDef('error_generic_suggestion_2'),
        $this->language->getDef('error_generic_suggestion_3')
      ],
      'can_retry' => true
    ];
  }

  /**
   * Build complete orchestration response from execution result
   * 
   * Handles the complex logic of extracting and formatting responses,
   * especially for semantic queries which require special handling.
   *
   * - For hybrid queries, executionResult is the synthesized result directly (not wrapped)
   * - For other queries, executionResult has ['result' => ...] structure
   * 
   * @param array $executionResult Execution result from plan executor OR synthesized hybrid result
   * @param array $intent Intent analysis result
   * @param string $query Original user query
   * @param float $startTime Start time for execution time calculation
   * @param int $entityId Extracted entity ID
   * @param string $entityType Extracted entity type
   * @param object $responseProcessor LLM response processor instance
   * @return array Complete response structure
   */
  public function buildOrchestrationResponse(
    array $executionResult,
    array $intent,
    string $query,
    float $startTime,
    int $entityId,
    string $entityType,
    $responseProcessor
  ): array {
    // Hybrid and web_search results have 'text_response' at top level — pass through directly
    $isHybridResult = isset($executionResult['text_response']) &&
                      isset($executionResult['type']) &&
                      in_array($executionResult['type'], ['hybrid', 'web_search_response', 'web_search_only'], true);
    
    if ($isHybridResult) {
      // For hybrid results, use the structure as-is (already properly formatted)
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "[INFO] Using hybrid result structure directly",
          'info'
        );
      }
      
      // Add execution time if not present
      if (!isset($executionResult['execution_time'])) {
        $executionResult['execution_time'] = microtime(true) - $startTime;
      }
      
      // Ensure required fields
      $executionResult['success'] = $executionResult['success'] ?? true;
      $executionResult['intent'] = $intent;
      $executionResult['agent_used'] = 'orchestrator';
      
      // Override entity_id and entity_type if provided
      if ($entityId > 0) {
        $executionResult['entity_id'] = $entityId;
      }
      
      if ($entityType !== 'hybrid') {
        $executionResult['entity_type'] = $entityType;
      }
      
      return $executionResult;
    }
    
    // For non-hybrid results, use the original extraction logic
    // Extract final response
    $finalResponse = $this->extractFinalResponse($executionResult['result'] ?? $executionResult);
    
    // Get raw result for processing
    $rawResult = $executionResult['result'] ?? [];
    
    // Process response through LLM formatter (if available)
    $intentType = $intent['type'] ?? $intent['query_type'] ?? 'semantic';
    $dataForFormatter = null;
    
    if ($responseProcessor !== null) {
      if (is_array($rawResult)) {
        $rawResult['response_type'] = $rawResult['response_type'] ?? $rawResult['type'] ?? 'semantic_results';
        $rawResult['entity_type'] = $rawResult['entity_type'] ?? $entityType ?? 'general';
        // Seed 'interpretation' from the already-extracted answer. The execution result carries the
        // answer under 'response'/'text_response' (executor-dependent), not 'interpretation', so
        // without this the response processor warns about a missing field and falls back to the raw
        // question — wrong for analytics. Only fill when genuinely absent.
        if (empty($rawResult['interpretation'])) {
          $rawResult['interpretation'] = $finalResponse !== '' ? $finalResponse : $query;
        }
      }
      $dataForFormatter = $responseProcessor->processResponse($rawResult, $query, $intentType);
    } else {
      // For hybrid queries without LLM formatter, use raw result
      $dataForFormatter = $rawResult;
    }
    
    // Extract actual answer with priority logic
    $actualAnswer = $this->extractActualAnswer($rawResult, $finalResponse);
    
    // If finalResponse is empty, use actualAnswer
    if (empty($finalResponse) && !empty($actualAnswer)) {
      $finalResponse = $actualAnswer;
    }
    
    // For semantic queries, ensure data includes response field
    if ($intent['type'] === 'semantic' && is_array($dataForFormatter)) {
      $dataForFormatter = $this->enrichSemanticResponse($dataForFormatter, $actualAnswer, $rawResult);
    }

    // Build final response structure
    $response = [
      'success' => true,
      'type' => $intent['type'],
      'data' => $dataForFormatter,
      'text_response' => $finalResponse,
      'intent' => $intent,
      'execution_time' => microtime(true) - $startTime,
      'entity_id' => $entityId,
      'entity_type' => $entityType,
      'agent_used' => 'orchestrator',
    ];
    
    // 2026-01-29: Preserve complete analytics structure
    // Issue: Analytics results (SQL, data table) not displaying in chat
    // Cause: ResponseProcessor was not preserving analytics-specific fields
    if ($intent['type'] === 'analytics' && is_array($rawResult)) {
      // Ensure analytics-specific fields are preserved in data
      if (!isset($response['data']['results']) && isset($rawResult['results'])) {
        $response['data']['results'] = $rawResult['results'];
      }
      if (!isset($response['data']['sql_query']) && isset($rawResult['sql_query'])) {
        $response['data']['sql_query'] = $rawResult['sql_query'];
      }
      if (!isset($response['data']['count']) && isset($rawResult['count'])) {
        $response['data']['count'] = $rawResult['count'];
      }
      if (!isset($response['data']['interpretation']) && isset($rawResult['interpretation'])) {
        $response['data']['interpretation'] = $rawResult['interpretation'];
      }
      if (!isset($response['data']['question']) && isset($rawResult['question'])) {
        $response['data']['question'] = $rawResult['question'];
      }
      
      // Ensure type is set correctly for AnalyticsFormatter
      if (!isset($response['data']['type'])) {
        $response['data']['type'] = 'analytics_results';
      }
      
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "✅ Analytics structure preserved: results=" . (isset($response['data']['results']) ? count($response['data']['results']) : 0) . 
          ", sql=" . (isset($response['data']['sql_query']) ? 'YES' : 'NO'),
          'info'
        );
      }
    }
    
    // Check multiple possible cache flag locations
    if (isset($rawResult['cached']) && $rawResult['cached'] === true) {
      $response['cached'] = true;
      $response['from_cache'] = true;
      if (isset($rawResult['cache_age'])) {
        $response['cache_age'] = $rawResult['cache_age'];
      }
    } elseif (isset($rawResult['from_cache']) && $rawResult['from_cache'] === true) {
      $response['from_cache'] = true;
      $response['cached'] = true;
    } elseif (isset($executionResult['cached']) && $executionResult['cached'] === true) {
      $response['cached'] = true;
      $response['from_cache'] = true;
    }
    
    // Extract from dataForFormatter or rawResult
    if (isset($dataForFormatter['source_attribution'])) {
      $response['source_attribution'] = $dataForFormatter['source_attribution'];
    } elseif (isset($rawResult['source_attribution'])) {
      $response['source_attribution'] = $rawResult['source_attribution'];
    }
    
    return $response;
  }

  /**
   * Extract actual answer from raw result with priority logic
   * 
   * @param array $rawResult Raw execution result
   * @param string $finalResponse Final response from extractFinalResponse
   * @return string Extracted actual answer
   */
  private function extractActualAnswer(array $rawResult, string $finalResponse): string
  {
    // Priority 1: Check if rawResult has 'response' field (from SemanticQueryExecutor)
    if (isset($rawResult['response']) && !empty($rawResult['response'])) {
      return $rawResult['response'];
    }
    
    // Priority 2: Check if rawResult has 'interpretation' field
    if (isset($rawResult['interpretation']) && !empty($rawResult['interpretation'])) {
      return $rawResult['interpretation'];
    }
    
    // Priority 3: Check if rawResult has 'data' array with response/interpretation
    if (isset($rawResult['data']) && is_array($rawResult['data'])) {
      if (isset($rawResult['data']['response']) && !empty($rawResult['data']['response'])) {
        return $rawResult['data']['response'];
      }
      if (isset($rawResult['data']['interpretation']) && !empty($rawResult['data']['interpretation'])) {
        return $rawResult['data']['interpretation'];
      }
    }
    
    // Priority 4: Check if rawResult has 'text_response' field (but not if it's the JSON fallback)
    if (isset($rawResult['text_response']) && !empty($rawResult['text_response'])) {
      if (!str_contains($rawResult['text_response'], 'Résultat:')) {
        return $rawResult['text_response'];
      }
    }
    
    // Priority 5: Use finalResponse if available (but not if it's the JSON fallback)
    if (!empty($finalResponse) && !str_contains($finalResponse, 'Résultat:')) {
      return $finalResponse;
    }
    
    return '';
  }

  /**
   * Enrich semantic response data with required fields
   * 
   * @param array $dataForFormatter Data to enrich
   * @param string $actualAnswer Extracted actual answer
   * @param array $rawResult Raw execution result
   * @return array Enriched data
   */
  private function enrichSemanticResponse(array $dataForFormatter, string $actualAnswer, array $rawResult): array
  {
    // Ensure 'response' field exists in data for chatGpt.php compatibility
    if (!isset($dataForFormatter['response']) || empty($dataForFormatter['response'])) {
      $dataForFormatter['response'] = $actualAnswer;
    }
    
    // Also ensure 'interpretation' field as fallback
    if (!isset($dataForFormatter['interpretation']) || empty($dataForFormatter['interpretation'])) {
      $dataForFormatter['interpretation'] = $actualAnswer;
    }
    
    // Preserve source_attribution if present in rawResult
    if (isset($rawResult['source_attribution']) && !isset($dataForFormatter['source_attribution'])) {
      $dataForFormatter['source_attribution'] = $rawResult['source_attribution'];
    }
    
    // Preserve results array if present
    if (isset($rawResult['result']) && !isset($dataForFormatter['results'])) {
      $dataForFormatter['results'] = $rawResult['result'];
    }
    
    return $dataForFormatter;
  }
}
