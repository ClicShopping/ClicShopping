<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\AI\Helper\ClarificationHelper;
use ClicShopping\AI\DomainsAI\CoreAI\Helper\AgentResponseHelper;
use ClicShopping\AI\Security\SecurityOrchestrator;

/**
 * RequestValidator
 *
 * Validates and sanitizes AJAX chat requests.
 * Extracted from chatGpt.php AJAX handler as part of code refactoring (Task 12).
 *
 * Responsibilities:
 * - Input validation (empty, length, ambiguity)
 * - Security checks (prompt injection detection)
 * - Timeout configuration
 * - Request sanitization
 */
class RequestValidator
{
  /**
   * Validate incoming AJAX request
   *
   * @param array $input Raw input data ($_POST or JSON)
   * @return array Validation result with 'valid', 'query', 'error' keys
   */
  public static function validateRequest(array $input): array
  {
    // Extract query from JSON or POST
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    
    if ($jsonInput && isset($jsonInput['message'])) {
      $userQuery = trim($jsonInput['message']);
    } else {
      $userQuery = trim($input['message'] ?? '');
    }
    
    // Sanitize for display (XSS protection)
    $userQueryDisplay = htmlspecialchars($userQuery, ENT_QUOTES, 'UTF-8');
    
    // Validation 1: Empty query check
    if (empty($userQuery)) {
      error_log('⚠️ VALIDATION ERROR: Empty query received');
      
      return [
        'valid' => false,
        'error' => [
          'success' => false,
          'error' => 'Veuillez entrer une question',
          'error_code' => 'EMPTY_QUERY',
          'interaction_id' => null,
          'validation_error' => true
        ]
      ];
    }
    
    // Validation 2: Query length check
    $maxLength = 1000;
    if (mb_strlen($userQuery, 'UTF-8') > $maxLength) {
      error_log('⚠️ VALIDATION ERROR: Query too long (' . mb_strlen($userQuery, 'UTF-8') . ' chars)');
      
      return [
        'valid' => false,
        'error' => [
          'success' => false,
          'error' => "Votre question est trop longue (max {$maxLength} caractères)",
          'error_code' => 'QUERY_TOO_LONG',
          'interaction_id' => null,
          'validation_error' => true,
          'current_length' => mb_strlen($userQuery, 'UTF-8'),
          'max_length' => $maxLength
        ]
      ];
    }
    
    // Validation 3: Ambiguity check
    $clarificationHelper = new ClarificationHelper(false);
    $intent = ['type' => 'unknown', 'confidence' => 1.0, 'metadata' => []];
    $ambiguityCheck = $clarificationHelper->detectAmbiguity($intent, $userQuery, []);
    
    if ($ambiguityCheck['is_ambiguous']) {
      error_log('⚠️ VALIDATION ERROR: Ambiguous query detected - Type: ' . $ambiguityCheck['ambiguity_type']);
      
      $clarificationResponse = AgentResponseHelper::buildClarificationRequest(
        $userQuery,
        $ambiguityCheck['ambiguity_type']
      );
      
      return [
        'valid' => false,
        'error' => [
          'success' => false,
          'error' => $clarificationResponse['message'],
          'error_code' => 'AMBIGUOUS_QUERY',
          'ambiguity_type' => $ambiguityCheck['ambiguity_type'],
          'suggestions' => $ambiguityCheck['suggestions'] ?? [],
          'interaction_id' => null,
          'validation_error' => true
        ]
      ];
    }
    
    // Validation 4: Security check
    $securityCheck = self::checkSecurity($userQuery);
    
    if (!$securityCheck['valid']) {
      return $securityCheck;
    }
    
    // All validations passed
    return [
      'valid' => true,
      'query' => $userQuery,
      'query_display' => $userQueryDisplay,
      'security_check' => $securityCheck['security_check']
    ];
  }
  
  /**
   * Check query for security threats (prompt injection, etc.)
   *
   * @param string $query User query
   * @return array Security check result
   */
  public static function checkSecurity(string $query): array
  {
    $securityCheck = SecurityOrchestrator::validateQuery($query, null);
    
    if ($securityCheck['blocked']) {
      error_log('🛡️ SECURITY: Blocked malicious query');
      error_log('   - Threat Type: ' . $securityCheck['threat_type']);
      error_log('   - Threat Score: ' . $securityCheck['threat_score']);
      error_log('   - Detection Method: ' . $securityCheck['detection_method']);
      error_log('   - Reasoning: ' . substr($securityCheck['reasoning'], 0, 200));
      
      return [
        'valid' => false,
        'error' => [
          'success' => false,
          'error' => 'Cette requête a été bloquée pour des raisons de sécurité. Veuillez reformuler votre question.',
          'error_code' => 'SECURITY_THREAT_DETECTED',
          'threat_type' => $securityCheck['threat_type'],
          'threat_score' => $securityCheck['threat_score'],
          'detection_method' => $securityCheck['detection_method'],
          'interaction_id' => null,
          'security_error' => true
        ]
      ];
    }
    
    // Log security check passed
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('[INFO : SUCCESS] SECURITY: Query passed security check');
      error_log('   - Threat Score: ' . $securityCheck['threat_score']);
      error_log('   - Detection Method: ' . $securityCheck['detection_method']);
      error_log('   - Latency: ' . $securityCheck['latency_ms'] . 'ms');
    }
    
    return [
      'valid' => true,
      'security_check' => $securityCheck
    ];
  }
  
  /**
   * Configure timeout for long-running queries
   *
   * @param int $seconds Maximum execution time in seconds
   * @param bool $enable Enable timeout handling
   * @return void
   */
  public static function configureTimeout(int $seconds = 60, bool $enable = true): void
  {
    if (!$enable) {
      return;
    }
    
    set_time_limit($seconds);
    
    // Register shutdown function to handle timeout gracefully
    register_shutdown_function(function() use ($seconds) {
      $error = error_get_last();
      if ($error && ($error['type'] === E_ERROR || $error['type'] === E_USER_ERROR)) {
        if (strpos($error['message'], 'Maximum execution time') !== false) {
          error_log('[INFO : TIME] TIMEOUT ERROR: Query exceeded ' . $seconds . ' seconds');
          
          if (ob_get_length()) ob_clean();
          
          header('Content-Type: application/json; charset=UTF-8');
          echo json_encode([
            'success' => false,
            'error' => 'La requête prend trop de temps, veuillez réessayer',
            'error_code' => 'QUERY_TIMEOUT',
            'timeout_seconds' => $seconds,
            'interaction_id' => null,
            'timeout_error' => true
          ], JSON_UNESCAPED_UNICODE);
          exit;
        }
      }
    });
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('[INFO : TIME] TIMEOUT CONFIGURED: ' . $seconds . ' seconds');
    }
  }
  
  /**
   * Check if query has exceeded timeout
   *
   * @param float $startTime Query start time (from microtime(true))
   * @param int $maxTime Maximum execution time in seconds
   * @return bool True if timeout exceeded
   */
  public static function checkTimeout(float $startTime, int $maxTime): bool
  {
    $elapsedTime = microtime(true) - $startTime;
    
    if ($elapsedTime >= $maxTime) {
      error_log('[INFO : TIME] TIMEOUT WARNING: Query took ' . round($elapsedTime, 2) . ' seconds (max: ' . $maxTime . ')');
      return true;
    }
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('[INFO : TIME] TIMEOUT CHECK: Query took ' . round($elapsedTime, 2) . ' seconds (max: ' . $maxTime . ')');
    }
    
    return false;
  }
}
