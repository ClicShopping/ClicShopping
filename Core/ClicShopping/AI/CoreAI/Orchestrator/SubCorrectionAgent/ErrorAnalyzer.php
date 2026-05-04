<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * ErrorAnalyzer Class
 * 
 * Responsible for analyzing and classifying SQL errors.
 * Uses pattern matching and LLM analysis to determine error types,
 * severity, and extract error-specific details.
 */
class ErrorAnalyzer
{
  private SecurityLogger $logger;
  private bool $debug;
  private mixed $language;
  
  /**
   * Error pattern definitions for classification
   * 
   * @var array
   */
  private array $errorPatterns = [
    'syntax_error' => [
      'patterns' => [
        '/error in your SQL syntax/i',
        '/syntax error.*?near/i',
        '/unexpected.*?at line/i',
        '/invalid syntax/i',
      ],
      'severity' => 'high',
    ],
    'unknown_column' => [
      'patterns' => [
        '/unknown column/i',
        '/column.*?does not exist/i',
        '/ambiguous column/i',
      ],
      'severity' => 'medium',
    ],
    'unknown_table' => [
      'patterns' => [
        '/table.*?doesn\'t exist/i',
        '/no such table/i',
        '/unknown table/i',
      ],
      'severity' => 'high',
    ],
    'group_by_error' => [
      'patterns' => [
        '/not in GROUP BY/i',
        '/must appear in.*?GROUP BY/i',
        '/incompatible with sql_mode=only_full_group_by/i',
      ],
      'severity' => 'medium',
    ],
    'join_error' => [
      'patterns' => [
        '/unknown column in (on|join) clause/i',
        '/ambiguous.*?in (on|join)/i',
      ],
      'severity' => 'medium',
    ],
    'type_mismatch' => [
      'patterns' => [
        '/operand type/i',
        '/type mismatch/i',
        '/invalid.*?for.*?type/i',
      ],
      'severity' => 'medium',
    ],
  ];

  /**
   * Constructor
   * 
   * @param SecurityLogger $logger Security logger instance
   * @param bool $debug Debug mode flag
   */
  public function __construct(SecurityLogger $logger, bool $debug)
  {
    $this->logger = $logger;
    $this->debug = $debug;
    $this->language = Registry::get('Language');
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "ErrorAnalyzer: Component initialized",
        'info'
      );
    }
  }

  /**
   * Analyze error to determine type and severity
   *
   * @param array $errorContext Error context containing error_message and failed_query
   * @return array Error analysis with type, severity, confidence, correctable, and details
   */
  public function analyzeError(array $errorContext): array
  {
    $errorMessage = $errorContext['error_message'] ?? '';
    $failedQuery = $errorContext['failed_query'] ?? '';

    $analysis = [
      'type' => 'unknown',
      'severity' => 'medium',
      'confidence' => 0.0,
      'correctable' => true,
      'details' => [],
    ];

    // Try pattern matching first
    $matchedConfidence = 0;
    foreach ($this->errorPatterns as $type => $config) {
      foreach ($config['patterns'] as $pattern) {
        if (preg_match($pattern, $errorMessage)) {
          $analysis['type'] = $type;
          $analysis['severity'] = $config['severity'];
          $matchedConfidence = 0.9;
          break 2;
        }
      }
    }

    // If no pattern matched, use LLM analysis
    if ($matchedConfidence === 0) {
      $llmAnalysis = $this->analyzErrorWithLLM($errorMessage, $failedQuery);
      $analysis['type'] = $llmAnalysis['type'] ?? 'semantic_error';
      $matchedConfidence = $llmAnalysis['confidence'] ?? 0.5;
    }

    $analysis['confidence'] = $matchedConfidence;

    // Extract error-specific details
    $analysis['details'] = $this->extractErrorDetails($errorMessage, $analysis['type']);

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "ErrorAnalyzer: Analyzed error - Type: {$analysis['type']}, Confidence: {$analysis['confidence']}",
        'info'
      );
    }

    return $analysis;
  }

  /**
   * Analyze error with LLM for complex cases
   *
   * @param string $errorMessage Error message
   * @param string $query Failed query
   * @return array LLM analysis with type, confidence, and description
   */
  private function analyzErrorWithLLM(string $errorMessage, string $query): array
  {
    // Load SYSTEM prompt in English for better LLM performance (internal analysis)
    $this->language->loadDefinitions('main', 'en', null, 'ClicShoppingAdmin');
    
    $prompt = $this->language->getDef('text_analyze_sql_error', [
      'error' => $errorMessage,
      'query' => $query
    ]) ?? "Analyze this SQL error and categorize it:\nError: {$errorMessage}\nQuery: {$query}\n\nProvide: error_type, confidence (0-1), description";

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "ErrorAnalyzer: Using English SYSTEM prompt for error analysis",
        'info'
      );
      $this->logger->logSecurityEvent(
        "ErrorAnalyzer: Prompt length: " . strlen($prompt) . " chars",
        'info'
      );
    }

    try {
      $response = Gpt::getGptResponse($prompt, 150);

      $parsed = $this->parseLLMResponse($response);

      return [
        'type' => $parsed['error_type'] ?? 'unknown',
        'confidence' => (float) ($parsed['confidence'] ?? 0.5),
        'description' => $parsed['description'] ?? '',
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "ErrorAnalyzer: LLM error analysis failed: " . $e->getMessage(),
          'error'
        );
      }

      return [
        'type' => 'unknown',
        'confidence' => 0.3,
        'description' => 'LLM analysis failed',
      ];
    }
  }

  /**
   * Parse LLM response
   * 
   * @param string $response LLM response text
   * @return array Parsed response data with error_type, confidence, and description
   */
  private function parseLLMResponse(string $response): array
  {
    $parsed = [];

    if (preg_match('/error[_\s]type[:\s]+([a-z_]+)/i', $response, $matches)) {
      $parsed['error_type'] = trim($matches[1]);
    }

    if (preg_match('/confidence[:\s]+([\d\.]+)/i', $response, $matches)) {
      $parsed['confidence'] = (float) $matches[1];
    }

    if (preg_match('/description[:\s]+(.+?)(?:\n|$)/i', $response, $matches)) {
      $parsed['description'] = trim($matches[1]);
    }

    return $parsed;
  }

  /**
   * Extract specific error details based on error type
   * 
   * @param string $errorMessage Error message
   * @param string $errorType Error type
   * @return array Error details specific to the error type
   */
  private function extractErrorDetails(string $errorMessage, string $errorType): array
  {
    $details = [];

    switch ($errorType) {
      case 'unknown_column':
        if (preg_match('/column [\'"](.*?)[\'"]/i', $errorMessage, $matches)) {
          $details['column_name'] = $matches[1];
        }
        break;

      case 'unknown_table':
        if (preg_match('/table [\'"](.*?)[\'"]/i', $errorMessage, $matches)) {
          $details['table_name'] = $matches[1];
        }
        break;

      case 'group_by_error':
        if (preg_match('/column [\'"](.*?)[\'"]/i', $errorMessage, $matches)) {
          $details['missing_column'] = $matches[1];
        }
        break;
        
      case 'syntax_error':
        // Extract position information if available
        if (preg_match('/at line (\d+)/i', $errorMessage, $matches)) {
          $details['line_number'] = (int) $matches[1];
        }
        if (preg_match('/near [\'"](.*?)[\'"]/i', $errorMessage, $matches)) {
          $details['near_text'] = $matches[1];
        }
        break;
        
      case 'join_error':
        if (preg_match('/column [\'"](.*?)[\'"]/i', $errorMessage, $matches)) {
          $details['ambiguous_column'] = $matches[1];
        }
        break;
        
      case 'type_mismatch':
        if (preg_match('/operand.*?[\'"](.*?)[\'"]/i', $errorMessage, $matches)) {
          $details['operand'] = $matches[1];
        }
        break;
    }

    return $details;
  }
}
