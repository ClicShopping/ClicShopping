<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Processor;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * SemanticProcessor Class
 *
 * 🔧 PRIORITY 3 - PHASE 3.3: Semantic query processing logic
 *
 * Responsible for processing semantic queries:
 * - Detecting semantic patterns
 * - Calculating semantic confidence
 * - Extracting semantic metadata
 * - Validating semantic requirements
 *
 * Separated from IntentAnalyzer to follow Single Responsibility Principle
 * and improve maintainability.
 *
 * @package ClicShopping\AI\DomainsAI\Semantic\Processor
 */
class SemanticProcessor
{
  private SecurityLogger $logger;
  private bool $debug;
  
  /**
   * Cached pattern bypass check result
   * 
   * Cache the result once in constructor instead of checking repeatedly
   * 
   * @var bool True if Pure LLM mode (patterns disabled), False if Pattern mode
   */
  private bool $usePureLlmMode;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    
    
    // This eliminates 11 repeated checks throughout the class
    $this->usePureLlmMode = !defined('USE_PATTERN_BASED_DETECTION')  || USE_PATTERN_BASED_DETECTION === 'False';
  }

  /**
   * Calculate confidence for semantic query
   *
   * Uses semantic patterns to determine confidence score.
   * Higher confidence for queries with multiple semantic keywords.
   *
   * PATTERN BYPASS: Respects USE_PATTERN_BASED_DETECTION flag
   * - Pure LLM mode: Returns low confidence (LLM handles classification)
   * - Pattern mode: Uses SemanticsPattern for detection
   *
   * @param string $query Query to analyze
   * @return array Result with confidence and matched patterns
   */
  public function calculateConfidence(string $query): array
  {
    if ($this->debug) {
      error_log("--- SEMANTIC CONFIDENCE CALCULATION ---");
      error_log("Query: '{$query}'");
    }

    if ($this->usePureLlmMode) {
      // Pure LLM mode: Return low confidence
      // LLM handles semantic classification through prompts
      if ($this->debug) {
        error_log("Semantic confidence calculation bypassed (Pure LLM mode)");
        error_log("Returning low confidence (0.5) - LLM will handle classification");
        error_log("--- END SEMANTIC CONFIDENCE ---\n");
      }

      return [
        'confidence' => 0.5,
        'match_count' => 0,
        'matched_patterns' => [],
        'word_count' => str_word_count($query),
        'detection_method' => 'llm',
      ];
    }

    // @deprecated Pattern-based detection removed in Pure LLM mode
    // This code is never executed (USE_PATTERN_BASED_DETECTION removed in task 5.1.6)
    // TODO: Remove this dead code block in Q2 2026
    return [
      'confidence' => 0.5,
      'match_count' => 0,
      'matched_patterns' => [],
      'word_count' => str_word_count($query),
      'detection_method' => 'llm',
    ];
  }

  /**
   * Check if query requires conversation context
   *
   * Detects contextual references like "it", "this", "that", etc.
   *
   * PATTERN BYPASS: Respects USE_PATTERN_BASED_DETECTION flag
   * - Pure LLM mode: Returns false (LLM handles context detection)
   * - Pattern mode: Uses SemanticsPattern for detection
   *
   * @param string $query Query to check
   * @return bool True if requires context
   */
  public function requiresConversationContext(string $query): bool
  {
    if ($this->usePureLlmMode) {
      // Pure LLM mode: Context detection disabled
      // LLM handles context requirements through conversation analysis
      return false;
    }

    // @deprecated Pattern-based detection removed in Pure LLM mode
    // This code is never executed (USE_PATTERN_BASED_DETECTION removed in task 5.1.6)
    // TODO: Remove this dead code block in Q2 2026
    // Pattern mode: Use SemanticsPattern for detection
    $contextualPatterns = SemanticsPattern::requiresConversationContext();

    foreach ($contextualPatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Extract semantic metadata from query
   *
   * Extracts semantic-specific metadata like:
   * - Search keywords
   * - Contextual references
   * - Semantic filters
   *
   * PATTERN BYPASS: Respects USE_PATTERN_BASED_DETECTION flag
   * - Pure LLM mode: Returns minimal metadata
   * - Pattern mode: Uses SemanticsPattern for extraction
   *
   * @param string $query Query to analyze
   * @return array Semantic metadata
   */
  public function extractMetadata(string $query): array
  {
    $metadata = [
      'semantic_keywords' => [],
      'contextual_references' => [],
      'search_intent' => null,
    ];

    if ($this->usePureLlmMode) {
      // Pure LLM mode: Return minimal metadata
      // LLM extracts metadata through prompts
      return $metadata;
    }

    // @deprecated Pattern-based detection removed in Pure LLM mode
    // This code is never executed (USE_PATTERN_BASED_DETECTION removed in task 5.1.6)
    // TODO: Remove this dead code block in Q2 2026
    // Pattern mode: Use SemanticsPattern for extraction
    // Extract semantic keywords
    $semanticKeywords = SemanticsPattern::calculateConfidenceSemanticKeywords();
    foreach ($semanticKeywords as $keyword) {
      if (stripos($query, $keyword) !== false) {
        $metadata['semantic_keywords'][] = $keyword;
      }
    }

    // Detect contextual references
    if ($this->requiresConversationContext($query)) {
      $contextPatterns = [
        '/\b(it|this|that|these|those)\b/i' => 'pronoun_reference',
        '/\b(same|similar|like that)\b/i' => 'similarity_reference',
        '/\b(previous|last|earlier)\b/i' => 'temporal_reference',
      ];

      foreach ($contextPatterns as $pattern => $type) {
        if (preg_match($pattern, $query)) {
          $metadata['contextual_references'][] = $type;
        }
      }
    }

    // Detect search intent
    if (preg_match('/\b(find|search|look for|show me|tell me about)\b/i', $query)) {
      $metadata['search_intent'] = 'information_seeking';
    } elseif (preg_match('/\b(recommend|suggest|best|top)\b/i', $query)) {
      $metadata['search_intent'] = 'recommendation';
    } elseif (preg_match('/\b(compare|difference|versus|vs)\b/i', $query)) {
      $metadata['search_intent'] = 'comparison';
    }

    return $metadata;
  }
}
