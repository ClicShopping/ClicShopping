<?php
/**
 * ResultValidator Class
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;


use ClicShopping\AI\Security\SecurityLogger;

/**
 * ResultValidator Class
 *
 * Responsibility: Validate query results before synthesis to ensure data quality.
 * This class validates that results contain proper data, correct types, and source attribution.
 *
 * Validation Rules:
 * - Semantic results must contain actual embedding data (not LLM hallucinations)
 * - Analytics results must contain actual SQL data with numbers (not placeholders)
 * - Web results must contain external sources with URLs
 * - Hybrid results must contain data from all sub-queries
 * - All results must have proper source attribution
 *
 * Created as part of Phase 4: Validation, Testing, and Quality Assurance
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
 */

class ResultValidator
{
  private SecurityLogger $logger;
  private bool $debug;
  private array $validationMetrics = [];

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    
    // Initialize validation metrics
    $this->validationMetrics = [
      'total_validations' => 0,
      'successful_validations' => 0,
      'failed_validations' => 0,
      'validation_by_type' => [
        'semantic' => ['total' => 0, 'passed' => 0, 'failed' => 0],
        'analytics' => ['total' => 0, 'passed' => 0, 'failed' => 0],
        'web_search' => ['total' => 0, 'passed' => 0, 'failed' => 0],
        'hybrid' => ['total' => 0, 'passed' => 0, 'failed' => 0]
      ]
    ];
  }

  /**
   * Validate semantic query result
   *
   * Validation criteria:
   * - Result must not be empty
   * - Must contain 'result' or 'response' field
   * - Must have source attribution indicating RAG or LLM
   * - If RAG source, must contain document/embedding data
   * - Must not be generic LLM text when embeddings should exist
   *
   * @param array $result Semantic query result
   * @return bool True if result is valid
   */
  public function validateSemanticResult(array $result): bool
  {
    return $this->validateResult($result, 'semantic', function($result, &$validationErrors) {
      // Check for response or result field
      if (!$this->hasContentField($result)) {
        $validationErrors[] = "Missing response, result, or interpretation field";
        return false;
      }

      // Validate source attribution
      // BUG FIX 2025-12-10: Added 'conversation', 'memory' to valid sources
      // Conversation Memory is a valid source for semantic queries (contains learned information)
      if (!$this->validateSourceAttribution($result, ['rag', 'semantic', 'embedding', 'llm', 'conversation', 'memory'], $validationErrors)) {
        return false;
      }

      $sourceAttr = $result['source_attribution'];
      
      // If source is RAG, verify embedding data exists
      if (in_array($sourceAttr['source_type'], ['rag', 'semantic', 'embedding'], true)) {
        if (!$this->hasEmbeddingData($result)) {
          $validationErrors[] = "RAG source but no document/embedding data found";
          return false;
        }
      }

      // Check for generic LLM responses that should be from RAG
      if (!$this->checkForGenericLLMResponse($result, $validationErrors)) {
        return false;
      }

      return true;
    });
  }

  /**
   * Validate analytics query result
   *
   * Validation criteria:
   * - Result must not be empty
   * - Must contain actual SQL data (not empty arrays)
   * - Must have numeric values (not placeholder text)
   * - Must have source attribution indicating analytics/database
   * - Must include table structure (columns, rows)
   *
   * @param array $result Analytics query result
   * @return bool True if result is valid
   */
  public function validateAnalyticsResult(array $result): bool
  {
    if ($this->debug) {
      error_log("ResultValidator: Validating analytics result with keys: " . implode(', ', array_keys($result)));
    }
    
    return $this->validateResult($result, 'analytics', function($result, &$validationErrors) {
      // Extract data array from result
      $dataArray = $this->extractDataArray($result);
      
      if ($dataArray === null) {
        $validationErrors[] = "Missing result data (checked 'results', 'result.rows', 'result.data')";
        return false;
      }

      // Empty results are valid (no data matched the query)
      if (!empty($dataArray)) {
        // BUT allow results without numeric data if they have a valid interpretation
        $hasNumericData = $this->hasNumericData($dataArray);
        $hasInterpretation = isset($result['interpretation']) && !empty($result['interpretation']) ||
                            isset($result['result']['interpretation']) && !empty($result['result']['interpretation']);
        
        if (!$hasNumericData && !$hasInterpretation) {
          $validationErrors[] = "No numeric data found in results and no interpretation provided";
          return false;
        }
        
        if (!$hasNumericData && $hasInterpretation && $this->debug) {
          $this->logger->logSecurityEvent(
            "Analytics result has no numeric data but has interpretation (valid)",
            'info'
          );
        }
      } else if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Analytics result has empty data array (valid - no matches found)",
          'info'
        );
      }

      // Validate source attribution
      if (!$this->validateSourceAttribution($result, ['analytics', 'database', 'sql'], $validationErrors)) {
        return false;
      }

      return true;
    });
  }

  /**
   * Validate web search query result
   *
   * Validation criteria:
   * - Result must not be empty
   * - Must contain external sources with URLs
   * - Must have source attribution indicating web search
   * - URLs must be valid format
   * - Must contain search results or snippets
   *
   * @param array $result Web search query result
   * @return bool True if result is valid
   */
  public function validateWebResult(array $result): bool
  {
    return $this->validateResult($result, 'web_search', function($result, &$validationErrors) {
      if($this->debug) {
        error_log("[ResultValidator::validateWebResult] Result keys: " . implode(', ', array_keys($result)));
      }
      
      // Support both structures:
      // 1. PlanExecutor: results at root level
      // 2. WebSearchQueryExecutor: results nested in 'result' field
      
      $resultData = $result;
      
      // If 'result' field exists, use it (WebSearchQueryExecutor structure)
      if (isset($result['result']) && is_array($result['result'])) {
        $resultData = $result['result'];
	
        if($this->debug) {
          error_log("[ResultValidator::validateWebResult] Using nested 'result' field");
        }
      } else {
        error_log("[ResultValidator::validateWebResult] Using root level data");
      }

      // Check for web search results (support both 'results' and 'items')
      $hasResults = (isset($resultData['results']) && is_array($resultData['results']) && !empty($resultData['results'])) ||
                    (isset($resultData['items']) && is_array($resultData['items']) && !empty($resultData['items']));
      $hasSources = isset($resultData['sources']) && is_array($resultData['sources']) && !empty($resultData['sources']);
      $hasUrls = isset($resultData['urls']) && is_array($resultData['urls']) && !empty($resultData['urls']);
      $hasTextResponse = isset($result['text_response']) && !empty($result['text_response']);
      

      if($this->debug) {
        error_log("[ResultValidator::validateWebResult] hasResults: " . ($hasResults ? 'YES' : 'NO'));
        error_log("[ResultValidator::validateWebResult] hasSources: " . ($hasSources ? 'YES' : 'NO'));
        error_log("[ResultValidator::validateWebResult] hasUrls: " . ($hasUrls ? 'YES' : 'NO'));
        error_log("[ResultValidator::validateWebResult] hasTextResponse: " . ($hasTextResponse ? 'YES' : 'NO'));
      }
      // Web search is valid if it has results, sources, URLs, or text_response
      if (!$hasResults && !$hasSources && !$hasUrls && !$hasTextResponse) {
        $validationErrors[] = "Missing web search results, sources, URLs, or text_response";

        if($this->debug) {
          error_log("[ResultValidator::validateWebResult] FAILED: Missing all required fields");
        }
        return false;
      }

      // If we have results, validate URLs
      if ($hasResults) {
        // Extract and validate URLs
        $urls = $this->extractUrls($resultData, $hasResults, $hasUrls);
        
        // Filter out empty URLs
        $urls = array_filter($urls, function($url) {
          return !empty($url) && is_string($url);
        });

        if($this->debug) {
          error_log("[ResultValidator::validateWebResult] Extracted " . count($urls) . " non-empty URLs");
        }

        if (empty($urls)) {
          // No URLs is acceptable if we have text_response
          if (!$hasTextResponse) {
            $validationErrors[] = "No URLs found in web search results";

            if($this->debug) {
              error_log("[ResultValidator::validateWebResult] FAILED: No URLs and no text_response");
            }

            return false;
          } else {
            error_log("[ResultValidator::validateWebResult] No URLs but has text_response - ACCEPTABLE");
          }
        } else {
          if (!$this->hasValidUrl($urls)) {
            $validationErrors[] = "No valid URLs found";

            if($this->debug) {
              error_log("[ResultValidator::validateWebResult] FAILED: No valid URLs");
            }

            return false;
          }
        }
      }

      // Validate source attribution
      if($this->debug) {
        error_log("[ResultValidator::validateWebResult] Checking source attribution...");
      }

      if (!$this->validateSourceAttribution($result, ['web', 'web_search', 'serapi', 'external'], $validationErrors)) {
        if($this->debug) {
          error_log("[ResultValidator::validateWebResult] FAILED: Source attribution validation failed");
        }

        return false;
      }
      
      if($this->debug) {
        error_log("[ResultValidator::validateWebResult] PASSED all validations");
      }

      return true;
    });
  }

  /**
   * Validate hybrid query result
   *
   * Validation criteria:
   * - Result must not be empty
   * - Must contain results from multiple sub-queries
   * - Each sub-query result must be valid according to its type
   * - Must have source attribution indicating hybrid/mixed
   * - Must contain data from all expected sources
   *
   * @param array $result Hybrid query result
   * @return bool True if result is valid
   */
  public function validateHybridResult(array $result): bool
  {
    return $this->validateResult($result, 'hybrid', function($result, &$validationErrors) {
      // Check for sub-query results
      $hasSubResults = isset($result['sub_results']) && is_array($result['sub_results']) && !empty($result['sub_results']);
      $hasResults = isset($result['results']) && is_array($result['results']) && !empty($result['results']);
      
      if (!$hasSubResults && !$hasResults) {
        $validationErrors[] = "Missing sub-query results";
        return false;
      }

      $subResults = $hasSubResults ? $result['sub_results'] : $result['results'];

      // Validate we have at least 2 sub-results (hybrid means multiple sources)
      if (count($subResults) < 2) {
        $validationErrors[] = "Hybrid query must have at least 2 sub-results";
        return false;
      }

      // Validate each sub-result according to its type
      $validSubResults = $this->validateSubResults($subResults);

      // Require at least 2 valid sub-results
      if ($validSubResults < 2) {
        $validationErrors[] = "Less than 2 valid sub-results found";
        return false;
      }

      // Validate source attribution
      if (!$this->validateSourceAttribution($result, ['hybrid', 'mixed', 'multi'], $validationErrors)) {
        return false;
      }

      return true;
    });
  }

  /**
   * Get validation metrics
   *
   * @return array Validation metrics including success/failure counts by type
   */
  public function getValidationMetrics(): array
  {
    return $this->validationMetrics;
  }

  /**
   * Reset validation metrics
   *
   * @return void
   */
  public function resetValidationMetrics(): void
  {
    $this->validationMetrics = [
      'total_validations' => 0,
      'successful_validations' => 0,
      'failed_validations' => 0,
      'validation_by_type' => [
        'semantic' => ['total' => 0, 'passed' => 0, 'failed' => 0],
        'analytics' => ['total' => 0, 'passed' => 0, 'failed' => 0],
        'web_search' => ['total' => 0, 'passed' => 0, 'failed' => 0],
        'hybrid' => ['total' => 0, 'passed' => 0, 'failed' => 0]
      ]
    ];
  }

  /**
   * Generic validation wrapper with metrics tracking
   *
   * @param array $result Result to validate
   * @param string $type Query type (semantic, analytics, web_search, hybrid)
   * @param callable $validationLogic Validation logic callback
   * @return bool True if result is valid
   */
  private function validateResult(array $result, string $type, callable $validationLogic): bool
  {
    $this->validationMetrics['total_validations']++;
    $this->validationMetrics['validation_by_type'][$type]['total']++;
    
    $validationErrors = [];
    
    try {
      // Check if result is empty
      if (empty($result)) {
        $validationErrors[] = "Result is empty";
        $this->logValidationFailure($type, 'empty_result', $validationErrors);
        return false;
      }

      // Check for success flag
      if (isset($result['success']) && $result['success'] === false) {
        $validationErrors[] = "Result indicates failure";
        $this->logValidationFailure($type, 'failed_execution', $validationErrors, $result);
        return false;
      }

      // Execute type-specific validation logic
      if (!$validationLogic($result, $validationErrors)) {
        $this->logValidationFailure($type, 'validation_failed', $validationErrors, $result);
        return false;
      }

      // All validations passed
      $this->validationMetrics['successful_validations']++;
      $this->validationMetrics['validation_by_type'][$type]['passed']++;
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          ucfirst($type) . " result validation passed",
          'info'
        );
      }
      
      return true;
      
    } catch (\Exception $e) {
      $validationErrors[] = "Exception during validation: " . $e->getMessage();
      $this->logValidationFailure($type, 'exception', $validationErrors, $result);
      return false;
    }
  }

  /**
   * Check if result has content field (response, result, or interpretation)
   *
   * @param array $result Result to check
   * @return bool True if content field exists
   */
  private function hasContentField(array $result): bool
  {
    return (isset($result['response']) && !empty($result['response'])) ||
           (isset($result['result']) && !empty($result['result'])) ||
           (isset($result['interpretation']) && !empty($result['interpretation']));
  }

  /**
   * Validate source attribution structure
   *
   * @param array $result Result to validate
   * @param array $validSourceTypes Valid source types for this query type
   * @param array &$validationErrors Reference to validation errors array
   * @return bool True if source attribution is valid
   */
  private function validateSourceAttribution(array $result, array $validSourceTypes, array &$validationErrors): bool
  {
    // Check for source attribution
    if (!isset($result['source_attribution']) || empty($result['source_attribution'])) {
      $validationErrors[] = "Missing source attribution";
      return false;
    }

    // Validate source attribution structure
    $sourceAttr = $result['source_attribution'];
    if (!isset($sourceAttr['source_type']) || empty($sourceAttr['source_type'])) {
      $validationErrors[] = "Source attribution missing source_type";
      return false;
    }

    // Check if source type is valid (case-insensitive, partial match)
    $sourceType = strtolower($sourceAttr['source_type']);
    $isValidSource = false;
    
    foreach ($validSourceTypes as $validType) {
      if (str_contains($sourceType, strtolower($validType))) {
        $isValidSource = true;
        break;
      }
    }
    
    if (!$isValidSource) {
      $validationErrors[] = "Source type '{$sourceAttr['source_type']}' is not valid for this query type";
      return false;
    }

    return true;
  }

  /**
   * Check if result has embedding data
   *
   * @param array $result Result to check
   * @return bool True if embedding data exists
   */
  private function hasEmbeddingData(array $result): bool
  {
    return (isset($result['result']['documents']) && !empty($result['result']['documents'])) ||
           (isset($result['result']['embeddings']) && !empty($result['result']['embeddings']));
  }

  /**
   * Check for generic LLM responses that should be from RAG
   *
   * @param array $result Result to check
   * @param array &$validationErrors Reference to validation errors array
   * @return bool True if no generic LLM phrases found
   */
  private function checkForGenericLLMResponse(array $result, array &$validationErrors): bool
  {
    if (!isset($result['response'])) {
      return true;
    }

    $response = strtolower($result['response']);
    $genericPhrases = [
      'i don\'t have access',
      'i cannot access',
      'i don\'t have information',
      'as an ai',
      'i\'m an ai'
    ];
    
    foreach ($genericPhrases as $phrase) {
      if (str_contains($response, $phrase)) {
        $validationErrors[] = "Response contains generic LLM phrase indicating lack of knowledge";
        return false;
      }
    }

    return true;
  }

  /**
   * Check if result has numeric data
   *
   * @param array $dataArray Data array to check
   * @return bool True if numeric data exists
   */
  private function hasNumericData(array $dataArray): bool
  {
    foreach ($dataArray as $row) {
      if (is_array($row)) {
        foreach ($row as $value) {
          if (is_numeric($value)) {
            return true;
          }
        }
      }
    }
    return false;
  }

  /**
   * Extract data array from analytics result
   *
   * @param array $result Result to extract from
   * @return array|null Data array or null if not found
   */
  private function extractDataArray(array $result): ?array
  {
    // Check for direct 'results' array (AnalyticsExecutor format)
    if (isset($result['results']) && is_array($result['results'])) {
      return $result['results'];
    }
    
    // Check for nested 'result' structure
    if (isset($result['result']) && is_array($result['result'])) {
      $resultData = $result['result'];
      
      // Check for rows or data within result
      if (isset($resultData['rows']) && is_array($resultData['rows'])) {
        return $resultData['rows'];
      }
      
      if (isset($resultData['data']) && is_array($resultData['data'])) {
        return $resultData['data'];
      }
    }
    
    return null;
  }

  /**
   * Extract URLs from web search result data
   *
   * @param array $resultData Result data to extract from
   * @param bool $hasResults Whether results array exists
   * @param bool $hasUrls Whether urls array exists
   * @return array Array of URLs
   */
  private function extractUrls(array $resultData, bool $hasResults, bool $hasUrls): array
  {
    $urls = [];
    
    if ($hasResults) {
      foreach ($resultData['results'] as $item) {
        if (isset($item['url'])) {
          $urls[] = $item['url'];
        } elseif (isset($item['link'])) {
          $urls[] = $item['link'];
        }
      }
    } elseif ($hasUrls) {
      $urls = $resultData['urls'];
    }
    
    return $urls;
  }

  /**
   * Check if at least one URL is valid
   *
   * @param array $urls Array of URLs to validate
   * @return bool True if at least one valid URL exists
   */
  private function hasValidUrl(array $urls): bool
  {
    if($this->debug) {
      error_log("[ResultValidator::hasValidUrl] Validating " . count($urls) . " URLs");
    }

    foreach ($urls as $i => $url) {
      $isValid = filter_var($url, FILTER_VALIDATE_URL);
      
      if($this->debug) {
        error_log("[ResultValidator::hasValidUrl] URL {$i}: " . (is_string($url) ? substr($url, 0, 100) : gettype($url)) . " - Valid: " . ($isValid ? 'YES' : 'NO'));
      }

      if ($isValid) {
        return true;
      }
    }

    if($this->debug) {
      error_log("[INFO ResultValidator::hasValidUrl] NO valid URLs found");
    }

    return false;
  }

  /**
   * Validate sub-results in hybrid query
   *
   * @param array $subResults Array of sub-results to validate
   * @return int Number of valid sub-results
   */
  private function validateSubResults(array $subResults): int
  {
    $validSubResults = 0;
    
    foreach ($subResults as $subResult) {
      if (!is_array($subResult)) {
        continue;
      }

      // Determine sub-result type
      $subType = $subResult['type'] ?? $subResult['query_type'] ?? 'unknown';
      
      // Validate based on type
      $isValid = false;
      switch ($subType) {
        case 'semantic':
          $isValid = $this->validateSemanticResult($subResult);
          break;
        case 'analytics':
          $isValid = $this->validateAnalyticsResult($subResult);
          break;
        case 'web_search':
        case 'web':
          $isValid = $this->validateWebResult($subResult);
          break;
        default:
          // Unknown type - check for basic validity
          $isValid = !empty($subResult) && (isset($subResult['result']) || isset($subResult['response']));
      }

      if ($isValid) {
        $validSubResults++;
      }
    }
    
    return $validSubResults;
  }

  /**
   * Log validation failure with details
   *
   * @param string $type Query type (semantic, analytics, web_search, hybrid)
   * @param string $reason Failure reason code
   * @param array $errors Array of validation error messages
   * @param array|null $result Optional result data for debugging
   * @return void
   */
  private function logValidationFailure(string $type, string $reason, array $errors, ?array $result = null): void
  {
    $this->validationMetrics['failed_validations']++;
    $this->validationMetrics['validation_by_type'][$type]['failed']++;
    
    $logData = [
      'type' => $type,
      'reason' => $reason,
      'errors' => $errors,
      'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Include result preview for debugging (limit size)
    if ($result !== null && $this->debug) {
      $resultPreview = json_encode($result);
      if (strlen($resultPreview) > 500) {
        $resultPreview = substr($resultPreview, 0, 500) . '... (truncated)';
      }
      $logData['result_preview'] = $resultPreview;
    }
    
    $this->logger->logStructured(
      'warning',
      'ResultValidator',
      'validation_failure',
      $logData
    );
  }
}
