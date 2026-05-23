<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Security;

use ClicShopping\AI\Security\SemanticSecurityAnalyzer;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * SecurityOrchestrator
 *
 * Coordinates security layers for query validation.
 * 
 * PURE LLM MODE:
 * - Primary defense: SemanticSecurityAnalyzer (LLM-based)
 * - Pattern fallback: OPTIONAL (disabled by default)
 * - Processing: Always in English internally
 * - User queries: Multilingual (FR, ES, DE, EN)
 *
 * Integration Point: ClicShoppingAdmin/ajax/ChatGpt/chatGpt.php
 * Call AFTER validation, BEFORE orchestrator
 *
 * @package ClicShopping\AI\Security
 * @version 1.0
 * @date 2026-01-07
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.5
 */
class SecurityOrchestrator
{
  private static ?SecurityLogger $logger = null;
  
  /**
   * Validate a user query for security threats
   *
   * @param string $query User query (can be multilingual)
   * @param string|null $language Language code (null = auto-detect)
   * @param array $options Additional options
   * @return array Security validation result
   */
  public static function validateQuery(string $query, ?string $language = null, array $options = []): array
  {
    $startTime = microtime(true);
    
    // Initialize logger
    if (self::$logger === null) {
      self::$logger = new SecurityLogger();
    }
    
    // Get configuration
    $config = self::loadConfiguration();

    
    try {
      // ============================================
      // STEP 0: OBFUSCATION PREPROCESSING
      // ============================================
      $preprocessed = ObfuscationPreprocessor::preprocess($query);
      
      // Use normalized query for analysis
      $queryToAnalyze = $preprocessed['normalized'];
      $obfuscationDetected = $preprocessed['obfuscation_detected'];
      $confidenceBoost = $preprocessed['confidence_boost'];
      
      // Log obfuscation detection
      if (!empty($obfuscationDetected)) {
        self::$logger->logObfuscationDetection($query, $obfuscationDetected, [
          'original' => $preprocessed['original'],
          'normalized' => $preprocessed['normalized'],
          'confidence_boost' => $confidenceBoost
        ]);
      }
      
      // ============================================
      // PRIMARY DEFENSE: LLM-based Semantic Analysis
      // ============================================
      $llmAnalysis = SemanticSecurityAnalyzer::analyze($queryToAnalyze, $language);
      
      // Check if LLM analysis succeeded
      if (isset($llmAnalysis['error']) && $llmAnalysis['error']) {
        // LLM failed - log error
        self::$logger->logError('LLM security analysis failed', [
          'query_preview' => substr($query, 0, 100),
          'error' => $llmAnalysis['error_message'] ?? 'Unknown error'
        ]);
        
        // Check if pattern fallback is enabled
        if ($config['pattern_fallback_enabled']) {
          // Log fallback usage
          self::$logger->logFallbackUsage($query, 'llm_unavailable', [
            'error' => $llmAnalysis['error_message'] ?? 'Unknown error',
            'language' => $language ?? 'unknown'
          ]);
          
          // TODO: Implement pattern fallback (Phase 2)
          // For now, allow query with warning
          return self::buildResult(false, 0.0, 'none', 'LLM unavailable, pattern fallback not implemented', $startTime, 'llm_error');
        }
        
        // No fallback - allow query with warning
        return self::buildResult(false, 0.0, 'none', 'Security check unavailable', $startTime, 'llm_error');
      }
      
      // ============================================
      // THREAT EVALUATION WITH OBFUSCATION BOOST
      // ============================================
      $isMalicious = $llmAnalysis['is_malicious'] ?? false;
      $threatScore = $llmAnalysis['threat_score'] ?? 0.0;
      $threatType = $llmAnalysis['threat_type'] ?? 'none';
      $reasoning = $llmAnalysis['reasoning'] ?? '';
      
      // Apply confidence boost if obfuscation detected
      if (!empty($obfuscationDetected)) {
        $threatScore = min(1.0, $threatScore + $confidenceBoost);
        $reasoning .= " [Obfuscation detected: " . implode(', ', $obfuscationDetected) . "]";
        
        // If obfuscation detected, flag as malicious even if LLM didn't
        if ($threatScore >= $config['threat_threshold']) {
          $isMalicious = true;
        }
      }
      
      // Check against threshold
      $threshold = $config['threat_threshold'];
      $shouldBlock = $isMalicious && ($threatScore >= $threshold);
      
      // ============================================
      // LOGGING
      // ============================================
      
      // Log layer performance
      $llmLatency = isset($llmAnalysis['latency_ms']) ? $llmAnalysis['latency_ms'] : 0;
      self::$logger->logLayerPerformance('llm', $llmLatency, true, [
        'threat_score' => $threatScore,
        'threat_type' => $threatType,
        'language' => $llmAnalysis['language'] ?? 'unknown'
      ]);
      
      // Log security decision
      if ($shouldBlock) {
        self::$logger->logBlockedQuery(
          $query,
          $threatType,
          $threatScore,
          $reasoning,
          'llm',
          [
            'language' => $llmAnalysis['language'] ?? 'unknown',
            'detection_layer' => 'semantic_analyzer',
            'threshold' => $threshold
          ]
        );
      } else {
        // Log all security decisions (blocked or allowed)
        self::$logger->logSecurityDecision(
          $query,
          false, // not blocked
          $threatScore,
          $threatType,
          $reasoning,
          [
            'language' => $llmAnalysis['language'] ?? 'unknown',
            'detection_method' => 'llm',
            'detection_layer' => 'semantic_analyzer'
          ]
        );
      }
      
      // ============================================
      // BUILD RESULT
      // ============================================
      return self::buildResult(
        $shouldBlock,
        $threatScore,
        $threatType,
        $reasoning,
        $startTime,
        'llm',
        $llmAnalysis
      );
      
    } catch (\Exception $e) {
      // Unexpected error - log and allow query
      self::$logger->logError('SecurityOrchestrator exception', [
        'query_preview' => substr($query, 0, 100),
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      
      return self::buildResult(false, 0.0, 'none', 'Security check error', $startTime, 'error');
    }
  }
  
  /**
   * Load security configuration
   *
   * @return array Configuration array
   */
  private static function loadConfiguration(): array
  {
    // Handle both boolean and string 'True'/'False' formats (DB compatibility)
    $llmEnabled = true; // default

    if (defined('CLICSHOPPING_APP_CHATGPT_RA_USE_LLM_PRIMARY_SECURITY')) {
      $configValue = CLICSHOPPING_APP_CHATGPT_RA_USE_LLM_PRIMARY_SECURITY;
      $llmEnabled = ($configValue === true || $configValue == 'True' || $configValue === 'true' || $configValue === '1');
    }

    // Provide safe defaults when RAG/RA config is not installed
    $patternFallbackEnabled = defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_PATTERN_FALLBACK') ? CLICSHOPPING_APP_CHATGPT_RA_SECURITY_PATTERN_FALLBACK : false;
    $threatThreshold = defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_THREAT_THRESHOLD') ? CLICSHOPPING_APP_CHATGPT_RA_SECURITY_THREAT_THRESHOLD : 0.7;
    $logAllQueries = defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_ALL_QUERIES') ? CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_ALL_QUERIES : false;
    $logBlockedOnly = defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_BLOCKED_ONLY') ? CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_BLOCKED_ONLY : true;

    return [
      // LLM-based security (PRIMARY)
      'llm_enabled' => $llmEnabled,
      
      // Pattern fallback (OPTIONAL - disabled by default)
      'pattern_fallback_enabled' => $patternFallbackEnabled,
      
      // Threat threshold for blocking
      'threat_threshold' => $threatThreshold,
      
      // Logging configuration
      'log_all_queries' => $logAllQueries,
      
      'log_blocked_only' => $logBlockedOnly,
    ];
  }
  
  /**
   * Build standardized security result
   *
   * @param bool $shouldBlock Whether to block the query
   * @param float $threatScore Threat score (0.0-1.0)
   * @param string $threatType Type of threat detected
   * @param string $reasoning Human-readable reasoning
   * @param float $startTime Start time for latency calculation
   * @param string $detectionMethod Detection method used
   * @param array $details Additional details
   * @return array Security result
   */
  private static function buildResult(
    bool $shouldBlock,
    float $threatScore,
    string $threatType,
    string $reasoning,
    float $startTime,
    string $detectionMethod,
    array $details = []
  ): array {
    $latency = round((microtime(true) - $startTime) * 1000, 2);

    return [
      'blocked' => $shouldBlock,
      'is_malicious' => $shouldBlock,
      'threat_score' => $threatScore,
      'threat_type' => $threatType,
      'reasoning' => $reasoning,
      'detection_method' => $detectionMethod,
      'latency_ms' => $latency,
      'timestamp' => date('Y-m-d H:i:s'),
      'details' => $details
    ];
  }
  
  /**
   * Get current configuration
   *
   * @return array Current configuration
   */
  public static function getConfiguration(): array
  {
    return self::loadConfiguration();
  }
  
  /**
   * Validate configuration
   *
   * @return array Validation result with any errors
   */
  public static function validateConfiguration(): array
  {
    $config = self::loadConfiguration();
    $errors = [];

    // Check if LLM is enabled
    if (!$config['llm_enabled']) {
      $errors[] = 'LLM security is disabled - this is not recommended';
    }

    // Check threat threshold
    if ($config['threat_threshold'] < 0.0 || $config['threat_threshold'] > 1.0) {
      $errors[] = 'Threat threshold must be between 0.0 and 1.0';
    }

    // Check if pattern fallback is enabled but not implemented
    if ($config['pattern_fallback_enabled']) {
      $errors[] = 'Pattern fallback is enabled but not yet implemented (Phase 2)';
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'config' => $config
    ];
  }
}
