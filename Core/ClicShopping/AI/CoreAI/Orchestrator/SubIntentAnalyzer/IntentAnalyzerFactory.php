<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\InterfacesAI\IntentAnalyzerInterface;

/**
 * IntentAnalyzerFactory
 *
 * Factory class for creating and orchestrating specialized intent analyzers.
 * Determines which analyzer(s) to use based on query characteristics.
 *
 * Workflow:
 * 1. Run all specialized analyzers in parallel
 * 2. Collect results and confidence scores
 * 3. Determine if query is hybrid (multiple intents)
 * 4. Return best matching analyzer result
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer
 * @since 2025-12-14
 */

class IntentAnalyzerFactory
{
  private SecurityLogger $logger;
  private bool $debug;

  private SemanticIntentAnalyzer $semanticAnalyzer;
  private AnalyticsIntentAnalyzer $analyticsAnalyzer;
  private WebSearchIntentAnalyzer $webSearchAnalyzer;
  private HybridIntentAnalyzer $hybridAnalyzer;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;

    // Initialize all analyzers
    $this->semanticAnalyzer = new SemanticIntentAnalyzer($debug);
    $this->analyticsAnalyzer = new AnalyticsIntentAnalyzer($debug);
    $this->webSearchAnalyzer = new WebSearchIntentAnalyzer($debug);
    $this->hybridAnalyzer = new HybridIntentAnalyzer($debug);
  }

  /**
   * Analyze query and determine best matching intent type
   *
   * @param string $query Query to analyze (translated to English)
   * @param string $originalQuery Original query in user's language
   * @return array Analysis result with:
   *   - 'type' (string): Intent type (semantic, analytics, web_search, hybrid)
   *   - 'confidence' (float): Confidence score (0.0 to 1.0)
   *   - 'metadata' (array): Type-specific metadata
   *   - 'reasoning' (array): Detection reasoning/evidence
   *   - 'all_results' (array): Results from all analyzers (for debugging)
   */
  public function analyzeIntent(string $query, string $originalQuery): array
  {
    if ($this->debug) {
      error_log("=== INTENT ANALYZER FACTORY ===");
      error_log("Query: '{$query}'");
      error_log("Original: '{$originalQuery}'");
    }

    // Run all analyzers
    $semanticResult = $this->semanticAnalyzer->analyze($query, $originalQuery);
    $analyticsResult = $this->analyticsAnalyzer->analyze($query, $originalQuery);
    $webSearchResult = $this->webSearchAnalyzer->analyze($query, $originalQuery);

    // Collect matched analyzers
    $matchedAnalyzers = [];
    if ($semanticResult['matches']) {
      $matchedAnalyzers['semantic'] = $semanticResult;
    }
    if ($analyticsResult['matches']) {
      $matchedAnalyzers['analytics'] = $analyticsResult;
    }
    if ($webSearchResult['matches']) {
      $matchedAnalyzers['web_search'] = $webSearchResult;
    }

    // Check if hybrid (2+ types matched)
    if (count($matchedAnalyzers) >= 2) {
      $hybridResult = $this->hybridAnalyzer->analyze($query, $originalQuery);

      if ($this->debug) {
        error_log("[INFO] HYBRID query detected (" . count($matchedAnalyzers) . " types)");
        error_log("Hybrid confidence: " . round($hybridResult['confidence'], 3));
      }

      return [
        'type' => 'hybrid',
        'confidence' => $hybridResult['confidence'],
        'metadata' => $hybridResult['metadata'],
        'reasoning' => $hybridResult['reasoning'],
        'all_results' => [
          'semantic' => $semanticResult,
          'analytics' => $analyticsResult,
          'web_search' => $webSearchResult,
          'hybrid' => $hybridResult,
        ],
      ];
    }

    // Single intent type - return highest confidence match
    if (count($matchedAnalyzers) === 1) {
      $type = array_key_first($matchedAnalyzers);
      $result = $matchedAnalyzers[$type];

      if ($this->debug) {
        error_log("[INFO] SINGLE intent detected: {$type}");
        error_log("Confidence: " . round($result['confidence'], 3));
      }

      return [
        'type' => $type,
        'confidence' => $result['confidence'],
        'metadata' => $result['metadata'],
        'reasoning' => $result['reasoning'],
        'all_results' => [
          'semantic' => $semanticResult,
          'analytics' => $analyticsResult,
          'web_search' => $webSearchResult,
        ],
      ];
    }

    // No matches - default to semantic with low confidence
    if ($this->debug) {
      error_log("[warning] NO intent matched - defaulting to semantic");
    }

    $this->logger->logStructured(
      'warning',
      'IntentAnalyzerFactory',
      'no_intent_matched',
      [
        'query' => $query,
        'original_query' => $originalQuery,
        'fallback' => 'semantic',
      ]
    );

    return [
      'type' => 'semantic',
      'confidence' => 0.3, // Low confidence for fallback
      'metadata' => [
        'fallback' => true,
        'reason' => 'No specific intent patterns matched',
      ],
      'reasoning' => ['No specific intent patterns matched, defaulting to semantic search'],
      'all_results' => [
        'semantic' => $semanticResult,
        'analytics' => $analyticsResult,
        'web_search' => $webSearchResult,
      ],
    ];
  }

  /**
   * Get all available analyzer types
   *
   * @return array List of analyzer types
   */
  public function getAvailableTypes(): array
  {
    return ['semantic', 'analytics', 'web_search', 'hybrid'];
  }

  /**
   * Get specific analyzer by type
   *
   * @param string $type Analyzer type
   * @return IntentAnalyzerInterface|null Analyzer instance or null if not found
   */
  public function getAnalyzer(string $type): ?IntentAnalyzerInterface
  {
    return match ($type) {
      'semantic' => $this->semanticAnalyzer,
      'analytics' => $this->analyticsAnalyzer,
      'web_search' => $this->webSearchAnalyzer,
      'hybrid' => $this->hybridAnalyzer,
      default => null,
    };
  }
}
