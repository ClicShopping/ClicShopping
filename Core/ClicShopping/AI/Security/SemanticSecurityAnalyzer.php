<?php
/**
 * Semantic Security Analyzer
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\AI\Security;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Cache as OMCache;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * SemanticSecurityAnalyzer
 *
 * LLM-based semantic analysis for detecting malicious intent in user queries.
 * This is the PRIMARY security layer that uses AI to understand context and intent.
 * 
 * Detects:
 * - Instruction Override: Attempts to change system behavior
 * - Information Exfiltration: Requests for internal information
 * - Hallucination Injection: Forcing false information generation
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
 */
class SemanticSecurityAnalyzer
{
  private static ?SecurityLogger $logger = null;
  private static mixed $language = null;
  private static bool $debug = false;
  
  // Threat score threshold for blocking (configurable)
  private const DEFAULT_THREAT_THRESHOLD = 0.7;
  
  // Cache namespace for security analysis
  // This creates cache files in: Work/Cache/Rag/Security/
  private const CACHE_NAMESPACE = 'Rag/Security';
  
  // Cache expiration in minutes (default: 60 minutes)
  private const CACHE_EXPIRATION = 60;
  
  // Cache statistics
  private static int $cacheHits = 0;
  private static int $cacheMisses = 0;

  /**
   * Initialize logger and language
   */
  private static function init(): void
  {
    if (self::$logger === null) {
      self::$logger = new SecurityLogger();
      self::$language = Registry::get('Language');
      self::$debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
    }
  }

  /**
   * Sanitize query to prevent meta-injection attacks
   * 
   * Removes or escapes characters that could break out of the prompt structure:
   * - Template delimiters: {{, }}, [QUERY_START], [QUERY_END]
   * - Prompt terminators: ---, ===, END OF PROMPT
   * - Unicode escape sequences: \uXXXX that could represent control characters
   * 
   * Defense-in-depth: Even though LLM is resistant, sanitization provides
   * an additional security layer ("Quis custodiet ipsos custodes?")
   * 
   * NOTE: We do NOT use HTML::sanitize() here because:
   * 1. It's designed for HTML output sanitization, not security analysis
   * 2. It replaces < > with _ which changes the query semantics
   * 3. We need the LLM to see the original malicious intent
   * 
   * @param string $query Raw user query
   * @return string Sanitized query safe for prompt insertion
   */
  private static function sanitizeQuery(string $query): string
  {
    // Remove template delimiters that could break prompt structure
    // Check both uppercase and lowercase variants
    $sanitized = str_replace(['{{', '}}', '[QUERY_START]', '[QUERY_END]', '[query_start]', '[query_end]'], '', $query);
    
    // Remove prompt termination markers
    $sanitized = str_replace(['---', '===', 'END OF PROMPT', 'END PROMPT'], '', $sanitized);
    
    // Remove Unicode escape sequences that could represent dangerous characters
    // \u007B = {, \u007D = }, \u005B = [, \u005D = ]
    $sanitized = preg_replace('/\\\\u[0-9a-fA-F]{4}/', '', $sanitized);
    
    // Remove null bytes and other control characters
    $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
    
    // Limit query length to prevent prompt overflow
    $maxLength = 10000; // 10K characters max
    if (strlen($sanitized) > $maxLength) {
      $sanitized = substr($sanitized, 0, $maxLength);
      
      if (self::$debug) {
        self::$logger->logSecurityEvent(
          "Query truncated from " . strlen($query) . " to $maxLength characters",
          'warning'
        );
      }
    }
    
    return $sanitized;
  }

  /**
   * Analyze user query for malicious intent using LLM
   * 
   * This is the main entry point for semantic security analysis.
   * Uses LLM to detect sophisticated attacks that pattern matching cannot catch.
   * 
   * Pure LLM Mode: No pattern-based detection. The multilingual security prompts
   * support multiple languages (EN, FR, Chinese, Italian, etc.), allowing the LLM
   * to naturally understand threats in any language.
   * 
   * @param string $query User input query to analyze
   * @param string|null $language Language code for prompt (en, fr, zh, it, etc.) or null to use system language
   * @return array Analysis result with threat detection details
   * 
   * Requirements: 5.1, 5.2, 5.3, 5.4
   */
  public static function analyze(string $query, string|null $language = null): array
  {
    self::init();
    
    $startTime = microtime(true);
    
    // Validate input
    if (empty(trim($query))) {
      self::$logger->logSecurityEvent(
        "Empty query provided to semantic analyzer",
        'warning'
      );
      return self::getErrorResult('Empty query provided');
    }
    
    // Sanitize query to prevent meta-injection attacks
    // Defense-in-depth: "Quis custodiet ipsos custodes?"
    $originalQuery = $query;
    $query = self::sanitizeQuery($query);
    
    // If sanitization removed content, this indicates a meta-injection attempt
    // Treat this as a HIGH severity threat and block immediately
    if ($query !== $originalQuery) {
      $removedChars = strlen($originalQuery) - strlen($query);
      
      self::$logger->logSecurityEvent(
        "Meta-injection attempt detected and blocked by sanitization: " . 
        "Removed $removedChars characters from query: " . substr($originalQuery, 0, 100),
        'warning'
      );
      
      // Return immediate block result without calling LLM
      return [
        'is_malicious' => true,
        'threat_type' => 'instruction_override',
        'confidence' => 1.0,
        'reasoning' => 'Meta-injection attempt detected: Query contained prompt-breaking patterns that were sanitized (template delimiters, Unicode escapes, or prompt terminators)',
        'indicators' => ['sanitization_triggered', 'meta_injection_pattern'],
        'threat_score' => 1.0,
        'should_block' => true,
        'detection_layer' => 'sanitization',
        'detection_method' => 'pattern_removal',
        'language' => $language ?? self::$language->get('code'),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
        'cached' => false,
        'sanitization_removed_chars' => $removedChars
      ];
    }
    
    // Use system language if not provided (Pure LLM - no validation needed)
    if ($language === null) {
      $language = self::$language->get('code');
      
      if (self::$debug) {
        self::$logger->logSecurityEvent(
          "Using system language: $language for query: " . substr($query, 0, 50),
          'info'
        );
      }
    }
    
    try {
      // Check cache first (performance optimization)
      $cacheKey = md5($query . $language);
      $cache = new OMCache($cacheKey, self::CACHE_NAMESPACE);
      
      if ($cache->exists(self::CACHE_EXPIRATION)) {
        self::$cacheHits++;
        
        if (self::$debug) {
          self::$logger->logSecurityEvent(
            "Semantic analysis cache hit for query: " . substr($query, 0, 50) . 
            " (hits: " . self::$cacheHits . ", misses: " . self::$cacheMisses . ")",
            'info'
          );
        }
        
        // Return cached result with updated timestamp
        $cachedResult = $cache->get();
        if ($cachedResult !== null && is_array($cachedResult)) {
          $cachedResult['cached'] = true;
          $cachedResult['cache_timestamp'] = $cachedResult['timestamp'] ?? null;
          $cachedResult['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
          
          return $cachedResult;
        }
      }
      
      self::$cacheMisses++;
      
      // Load security analysis prompt
      $prompt = self::loadSecurityPrompt($query, $language);
      
      if (empty($prompt)) {
        self::$logger->logSecurityEvent(
          "Failed to load security prompt for language: $language",
          'error'
        );
        return self::getErrorResult('Failed to load security prompt');
      }
      
      // Call LLM for semantic analysis
      $llmResponse = self::callLlmAnalysis($prompt);
      
      if ($llmResponse === false || empty($llmResponse)) {
        self::$logger->logSecurityEvent(
          "LLM analysis failed or returned empty response",
          'error'
        );
        return self::getErrorResult('LLM analysis failed');
      }
      
      // Parse LLM response
      $analysis = self::parseLlmResponse($llmResponse);
      
      if (isset($analysis['error'])) {
        self::$logger->logSecurityEvent(
          "Failed to parse LLM response: " . ($analysis['message'] ?? 'Unknown error'),
          'error'
        );
        return $analysis;
      }
      
      // Calculate threat score
      $threatScore = self::calculateThreatScore($analysis);
      $analysis['threat_score'] = $threatScore;
      
      // Determine if query should be blocked
      $threshold = self::getThreatThreshold();
      $analysis['should_block'] = $threatScore >= $threshold;
      
      // Add metadata
      $analysis['detection_layer'] = 'semantic';
      $analysis['detection_method'] = 'llm_analysis';
      $analysis['language'] = $language;
      $analysis['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
      $analysis['latency_ms'] = round((microtime(true) - $startTime) * 1000, 2);
      $analysis['cached'] = false;
      
      // Cache result
      $cache = new OMCache($cacheKey, self::CACHE_NAMESPACE);
      $cache->save($analysis);
      
      if (self::$debug) {
        self::$logger->logSecurityEvent(
          "Cached analysis result for query: " . substr($query, 0, 50),
          'info'
        );
      }
      
      // Log analysis result
      if (self::$debug || $analysis['is_malicious']) {
        self::$logger->logStructured(
          $analysis['is_malicious'] ? 'warning' : 'info',
          'SemanticSecurityAnalyzer',
          'analyze',
          [
            'query_preview' => substr($query, 0, 100),
            'is_malicious' => $analysis['is_malicious'],
            'threat_type' => $analysis['threat_type'],
            'threat_score' => $threatScore,
            'should_block' => $analysis['should_block'],
            'latency_ms' => $analysis['latency_ms']
          ]
        );
      }
      
      return $analysis;
      
    } catch (\Exception $e) {
      self::$logger->logSecurityEvent(
        "Semantic analysis exception: " . $e->getMessage(),
        'error'
      );
      return self::getErrorResult('Analysis exception: ' . $e->getMessage());
    }
  }

  /**
   * Calculate threat score based on LLM analysis
   * 
   * Combines confidence from LLM with additional heuristics to produce
   * a final threat score between 0.0 (safe) and 1.0 (malicious).
   * 
   * @param array $analysis LLM analysis result
   * @return float Threat score (0.0-1.0)
   * 
   * Requirements: 5.3, 5.5
   */
  public static function calculateThreatScore(array $analysis): float
  {
    // Base score from LLM confidence
    $baseScore = $analysis['confidence'] ?? 0.0;
    
    // Adjust based on threat type severity
    $threatTypeMultiplier = 1.0;
    if (isset($analysis['threat_type'])) {
      switch ($analysis['threat_type']) {
        case 'instruction_override':
          $threatTypeMultiplier = 1.2; // Highest priority
          break;
        case 'exfiltration':
          $threatTypeMultiplier = 1.1; // High priority
          break;
        case 'hallucination':
          $threatTypeMultiplier = 1.0; // Medium priority
          break;
        case 'none':
          // BUGFIX: If is_malicious=true but threat_type='none', 
          // the LLM detected a threat but didn't classify it properly.
          // Don't penalize the score - treat as hallucination (1.0x)
          if (isset($analysis['is_malicious']) && $analysis['is_malicious'] === true) {
            $threatTypeMultiplier = 1.0;
          } else {
            $threatTypeMultiplier = 0.5; // Reduce false positives for truly safe queries
          }
          break;
      }
    }
    
    // Adjust based on number of indicators
    $indicatorBoost = 0.0;
    if (isset($analysis['indicators']) && is_array($analysis['indicators'])) {
      $indicatorCount = count($analysis['indicators']);
      if ($indicatorCount > 0) {
        // More indicators = higher confidence
        $indicatorBoost = min(0.1, $indicatorCount * 0.02);
      }
    }
    
    // Calculate final score
    $finalScore = ($baseScore * $threatTypeMultiplier) + $indicatorBoost;
    
    // Clamp to [0.0, 1.0]
    return min(1.0, max(0.0, $finalScore));
  }

  /**
   * Load security analysis prompt for specified language
   * 
   * Pure LLM Mode: The security prompts are multilingual and support multiple languages
   * (EN, FR, Chinese, Italian, etc.). We simply load the prompt using loadDefinition()
   * with the language parameter, and the LLM naturally understands threats in that language.
   * 
   * No validation or normalization needed - loadDefinition() handles language fallback.
   * 
   * Meta-injection protection: Query is wrapped with explicit delimiters [QUERY_START] and [QUERY_END]
   * to prevent prompt structure breaking.
   * 
   * @param string $query User query to analyze (already sanitized)
   * @param string $language Language code (en, fr, zh, it, etc.)
   * @return string Security analysis prompt
   * 
   * Requirements: 5.4
   */
  private static function loadSecurityPrompt(string $query, string $language): string
  {
    DomainConfig::loadLanguageFile('rag_security');

    // Wrap query with explicit delimiters for meta-injection protection
    // The prompt template uses {{QUERY}} which will be replaced with this wrapped version
    $wrappedQuery = "[QUERY_START]\n" . $query . "\n[QUERY_END]";
    
    // Get security analysis prompt with wrapped query parameter
    // getDef uses {{KEY}} syntax for variable substitution (case-insensitive)
    $prompt = self::$language->getDef('text_rag_security_analysis', ['QUERY' => $wrappedQuery]);
    
    if (empty($prompt)) {
      self::$logger->logSecurityEvent(
        "Security prompt not found for language: $language",
        'error'
      );
      return '';
    }
    
    if (self::$debug) {
      self::$logger->logSecurityEvent(
        "Loaded security prompt for language: $language (length: " . \strlen($prompt) . " chars)",
        'info'
      );
    }
    
    return $prompt;
  }



  /**
   * Call LLM for security analysis
   * 
   * Uses the existing LLM service (Gpt class) with security-optimized parameters:
   * - Zero temperature (0.0) for fully deterministic analysis
   * - Limited tokens (500) for concise responses
   * - Timeout handling for reliability
   * 
   * @param string $prompt Security analysis prompt
   * @return string|false LLM response or false on failure
   * 
   * Requirements: 5.1
   */
  private static function callLlmAnalysis(string $prompt): string|false
  {
    $startTime = microtime(true);
    
    try {
      // Check if GPT is available
      if (!Gpt::checkGptStatus()) {
        self::$logger->logSecurityEvent(
          "GPT service not available for security analysis",
          'error'
        );
        return false;
      }
      
      // Call LLM with security prompt
      // Use temperature 0 for maximum determinism in security analysis
      $temperature = 0.0; // Zero temperature = deterministic, no randomness
      $maxTokens = 500; // Security analysis should be concise
      
      // Get configured model or use default
      $model = defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL') ? CLICSHOPPING_APP_CHATGPT_CH_MODEL : Gpt::getTechnicalFallbackModel();

      if (self::$debug) {
        self::$logger->logSecurityEvent(
          "Calling LLM for security analysis (model: $model, temp: $temperature, max_tokens: $maxTokens)",
          'info'
        );
      }
      
      $response = Gpt::getGptResponse($prompt, $maxTokens, $temperature, $model);
      
      $latency = round((microtime(true) - $startTime) * 1000, 2);
      
      if ($response === false || empty($response)) {
        self::$logger->logSecurityEvent(
          "LLM returned empty response for security analysis (latency: {$latency}ms)",
          'error'
        );
        return false;
      }
      
      if (self::$debug) {
        self::$logger->logSecurityEvent(
          "LLM response received (length: " . strlen($response) . " chars, latency: {$latency}ms)",
          'info'
        );
      }
      
      // Check if response exceeds timeout threshold
      $timeout = defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LLM_TIMEOUT') ? CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LLM_TIMEOUT : 5000;
      
      if ($latency > $timeout) {
        self::$logger->logSecurityEvent(
          "LLM security analysis exceeded timeout threshold ({$latency}ms > {$timeout}ms)",
          'warning'
        );
      }
      
      return $response;
      
    } catch (\Exception $e) {
      $latency = round((microtime(true) - $startTime) * 1000, 2);
      self::$logger->logSecurityEvent(
        "LLM call failed after {$latency}ms: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Parse LLM response into structured analysis result
   * 
   * Expected JSON format:
   * {
   *   "is_malicious": boolean,
   *   "threat_type": "instruction_override" | "exfiltration" | "hallucination" | "none",
   *   "confidence": 0.0-1.0,
   *   "reasoning": "explanation",
   *   "indicators": ["list", "of", "suspicious", "phrases"]
   * }
   * 
   * Implements robust JSON parsing with multiple fallback strategies:
   * 1. HTML entity decoding (handles htmlspecialchars encoding)
   * 2. Markdown code block removal
   * 3. JSON extraction from mixed text
   * 4. Common JSON syntax fixes
   * 5. Field validation and type coercion
   * 
   * @param string $llmResponse Raw LLM response
   * @return array Parsed analysis result or error result
   * 
   * Requirements: 5.1
   */
  private static function parseLlmResponse(string $llmResponse): array
  {
    // Step 1: Decode HTML entities (Gpt::getGptResponse applies htmlspecialchars)
    // Do multiple passes to handle nested encoding
    $decoded = $llmResponse;
    for ($i = 0; $i < 3; $i++) {
      $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Step 2: Clean response (remove markdown, extra whitespace)
    $cleaned = trim($decoded);
    
    // Remove markdown code blocks if present
    $cleaned = preg_replace('/```json\s*/i', '', $cleaned);
    $cleaned = preg_replace('/```\s*$/i', '', $cleaned);
    $cleaned = trim($cleaned);
    
    // Step 3: Extract JSON from response (handle cases where LLM adds text before/after)
    if (preg_match('/\{.*\}/s', $cleaned, $matches)) {
      $jsonStr = $matches[0];
    } else {
      $jsonStr = $cleaned;
    }
    
    // Step 4: Fix common JSON issues from LLM responses
    // Issue 1: Missing closing bracket for indicators array
    // Pattern: "indicators":["word1", "word2", "word3"} should be "indicators":["word1", "word2", "word3"]}
    $jsonStr = preg_replace('/"indicators":\[([^\]]+)\}/', '"indicators":[$1]}', $jsonStr);
    
    // Issue 2: Trailing commas before closing braces/brackets
    $jsonStr = preg_replace('/,\s*([}\]])/', '$1', $jsonStr);
    
    // Issue 3: Single quotes instead of double quotes
    $jsonStr = str_replace("'", '"', $jsonStr);
    
    if (self::$debug) {
      self::$logger->logSecurityEvent(
        "JSON string after fixes: " . substr($jsonStr, 0, 500),
        'info'
      );
    }
    
    // Step 5: Parse JSON
    $analysis = json_decode($jsonStr, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      $jsonError = json_last_error_msg();
      self::$logger->logSecurityEvent(
        "Failed to parse LLM JSON response: $jsonError | JSON: " . substr($jsonStr, 0, 500),
        'error'
      );
      
      // Try alternative parsing strategy: extract fields manually
      $analysis = self::parseJsonManually($jsonStr);
      
      if (isset($analysis['error'])) {
        return self::getErrorResult("Invalid JSON response from LLM: $jsonError");
      }
    }
    
    // Step 6: Validate and normalize fields
    return self::validateAndNormalizeAnalysis($analysis);
  }

  /**
   * Manual JSON parsing fallback for malformed responses
   * 
   * Extracts fields using regex when JSON parsing fails.
   * This is a last-resort fallback for badly formatted LLM responses.
   * 
   * @param string $jsonStr Malformed JSON string
   * @return array Extracted fields or error result
   */
  private static function parseJsonManually(string $jsonStr): array
  {
    $analysis = [];
    
    // Extract is_malicious
    if (preg_match('/"is_malicious"\s*:\s*(true|false)/i', $jsonStr, $matches)) {
      $analysis['is_malicious'] = strtolower($matches[1]) === 'true';
    }
    
    // Extract threat_type
    if (preg_match('/"threat_type"\s*:\s*"([^"]+)"/', $jsonStr, $matches)) {
      $analysis['threat_type'] = $matches[1];
    }
    
    // Extract confidence
    if (preg_match('/"confidence"\s*:\s*([0-9.]+)/', $jsonStr, $matches)) {
      $analysis['confidence'] = (float)$matches[1];
    }
    
    // Extract reasoning
    if (preg_match('/"reasoning"\s*:\s*"([^"]+)"/', $jsonStr, $matches)) {
      $analysis['reasoning'] = $matches[1];
    }
    
    // Extract indicators (simplified - just get the array content)
    if (preg_match('/"indicators"\s*:\s*\[([^\]]*)\]/', $jsonStr, $matches)) {
      $indicatorsStr = $matches[1];
      $indicators = [];
      if (preg_match_all('/"([^"]+)"/', $indicatorsStr, $indicatorMatches)) {
        $indicators = $indicatorMatches[1];
      }
      $analysis['indicators'] = $indicators;
    }
    
    // Check if we got the required fields
    if (!isset($analysis['is_malicious']) || !isset($analysis['threat_type']) || 
        !isset($analysis['confidence']) || !isset($analysis['reasoning'])) {
      return self::getErrorResult('Manual JSON parsing failed - missing required fields');
    }
    
    if (self::$debug) {
      self::$logger->logSecurityEvent(
        "Manual JSON parsing succeeded: " . json_encode($analysis),
        'info'
      );
    }
    
    return $analysis;
  }

  /**
   * Validate and normalize analysis fields
   * 
   * Ensures all required fields are present and have correct types.
   * Applies type coercion and default values where needed.
   * 
   * @param array $analysis Raw analysis result
   * @return array Validated and normalized analysis
   */
  private static function validateAndNormalizeAnalysis(array $analysis): array
  {
    // Validate required fields
    $requiredFields = ['is_malicious', 'threat_type', 'confidence', 'reasoning'];
    foreach ($requiredFields as $field) {
      if (!isset($analysis[$field])) {
        self::$logger->logSecurityEvent(
          "Missing required field in LLM response: $field",
          'error'
        );
        return self::getErrorResult("Missing required field: $field");
      }
    }
    
    // Validate and normalize field types
    if (!\is_bool($analysis['is_malicious'])) {
      $analysis['is_malicious'] = (bool)$analysis['is_malicious'];
    }
    
    if (!is_numeric($analysis['confidence'])) {
      self::$logger->logSecurityEvent(
        "Invalid confidence value: " . $analysis['confidence'],
        'warning'
      );
      $analysis['confidence'] = 0.5; // Default to medium confidence
    }
    
    // Clamp confidence to [0.0, 1.0]
    $analysis['confidence'] = min(1.0, max(0.0, (float)$analysis['confidence']));
    
    // Validate threat_type
    $validThreatTypes = ['instruction_override', 'exfiltration', 'hallucination', 'none'];
    if (!\in_array($analysis['threat_type'], $validThreatTypes, true)) {
      self::$logger->logSecurityEvent(
        "Invalid threat_type: " . $analysis['threat_type'] . ", defaulting to 'none'",
        'warning'
      );
      $analysis['threat_type'] = 'none';
    }
    
    // Ensure indicators is an array
    if (!isset($analysis['indicators']) || !\is_array($analysis['indicators'])) {
      $analysis['indicators'] = [];
    }
    
    // Ensure reasoning is a string
    if (!isset($analysis['reasoning']) || !is_string($analysis['reasoning'])) {
      $analysis['reasoning'] = 'No reasoning provided';
    }
    
    return $analysis;
  }

  /**
   * Get threat threshold from configuration
   * 
   * @return float Threat threshold (0.0-1.0)
   */
  private static function getThreatThreshold(): float
  {
    return CLICSHOPPING_APP_CHATGPT_RA_SECURITY_THREAT_THRESHOLD;
  }

  /**
   * Get error result structure
   * 
   * @param string $message Error message
   * @return array Error result
   */
  private static function getErrorResult(string $message): array
  {
    return [
      'error' => true,
      'message' => $message,
      'is_malicious' => false,
      'threat_type' => 'none',
      'confidence' => 0.0,
      'threat_score' => 0.0,
      'should_block' => false,
      'reasoning' => 'Analysis failed: ' . $message,
      'indicators' => [],
      'detection_layer' => 'semantic',
      'detection_method' => 'error',
      'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
    ];
  }

  /**
   * Clear analysis cache (for testing or memory management)
   * 
   * @return void
   */
  public static function clearCache(): void
  {
    // Clear all cache files in the Security/Analysis namespace
    OMCache::clear('*', self::CACHE_NAMESPACE);
    
    // Reset statistics
    self::$cacheHits = 0;
    self::$cacheMisses = 0;
    
    if (self::$debug) {
      self::$logger->logSecurityEvent(
        "Semantic analysis cache cleared",
        'info'
      );
    }
  }

  /**
   * Get cache statistics (for monitoring)
   * 
   * @return array Cache statistics including hit rate
   */
  public static function getCacheStats(): array
  {
    $totalRequests = self::$cacheHits + self::$cacheMisses;
    $hitRate = $totalRequests > 0 ? 
      round((self::$cacheHits / $totalRequests) * 100, 2) : 0.0;
    
    // Get cache file statistics
    $cacheStats = OMCache::getStats();
    
    return [
      'hits' => self::$cacheHits,
      'misses' => self::$cacheMisses,
      'total_requests' => $totalRequests,
      'hit_rate' => $hitRate,
      'hit_rate_percent' => $hitRate . '%',
      'namespace' => self::CACHE_NAMESPACE,
      'expiration_minutes' => self::CACHE_EXPIRATION,
      'cache_path' => OMCache::getPath() . self::CACHE_NAMESPACE . '/',
      'total_cache_files' => $cacheStats['total_files'] ?? 0,
      'total_cache_size' => $cacheStats['total_size_formatted'] ?? '0 B'
    ];
  }
}
