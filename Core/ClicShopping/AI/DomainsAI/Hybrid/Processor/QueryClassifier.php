<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Processor;


use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\DomainsAI\WebSearch\Patterns\WebSearchPostFilter;
use ClicShopping\AI\DomainsAI\DomainRegistry;

/**
 * QueryClassifier - Classifies queries into analytics, semantic, web_search, or hybrid types
 *
 * Responsibilities:
 * - Detect analytics queries (SQL-based data retrieval)
 * - Detect semantic queries (embedding-based knowledge retrieval)
 * - Detect web_search queries (external search requirements)
 * - Detect hybrid queries (multiple intents)
 * - Provide confidence scores and reasoning for classifications
 *
 * Requirements:
 * - REQ-2.1: Detect analytics queries with confidence >= 0.80
 * - REQ-2.2: Detect semantic queries with confidence >= 0.80
 * - REQ-2.3: Detect web_search queries with confidence >= 0.80
 * - REQ-2.4: Detect hybrid queries when multiple intents are present
 * - REQ-2.5: Use centralized pattern classes
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubHybridQueryProcessor
 * @since 2025-12-14
 */

class QueryClassifier extends BaseQueryProcessor
{
  /**
   * PURE LLM MODE (2026-01-09)
   * 
   * Philosophy: Use LLM for all classification, patterns only as fallback if LLM fails
   * 
   * Set to 'False' to disable pattern fallback (pure LLM only)
   * Set to 'True' to enable HybridPreFilter as fallback (pattern-based hybrid detection)
   * 
   * Note: This is a local constant, not a global config. Each classifier decides independently.
   */
  private const USE_HYBRID_PATTERN_FALLBACK = false;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    parent::__construct($debug, 'QueryClassifier');
    
    if ($this->debug) {
      $mode = self::USE_HYBRID_PATTERN_FALLBACK ? 'Pure LLM with pattern fallback' : 'Pure LLM only';
      $this->logInfo("Classification mode: $mode");
    }
  }

  /**
   * Process query classification
   *
   * @param mixed $input Query string to classify
   * @param array $context Additional context (unused for classification)
   * @return array Classification result with type, confidence, and reasoning
   * @throws \Exception If input is invalid
   */
  public function process($input, array $context = []): array
  {
    if (!$this->validate($input)) {
      throw new \Exception("Invalid input for QueryClassifier: input must be a non-empty string");
    }

    return $this->classifyQueryType($input);
  }

  /**
   * Validate input is a non-empty string
   *
   * @param mixed $input Input to validate
   * @return bool True if valid, false otherwise
   */
  public function validate($input): bool
  {
    return is_string($input) && !empty(trim($input));
  }

  /**
   * Classify query type: analytic, semantic, web, or hybrid
   *
   * Pure LLM mode (2026-01-09): Uses LLM for all classification by default.
   * Pattern fallback available via USE_HYBRID_PATTERN_FALLBACK constant if needed.
   *
   * @param string $query Query to classify
   * @return array Classification result with type, confidence, and reasoning
   */
  public function classifyQueryType(string $query): array
  {
    // Translate query to English if needed
    $translatedQuery = SemanticAgent::translateToEnglish($query, 80);

    if ($this->debug) {
      $this->logInfo("Classifying query (Pure LLM mode)", [
        'original' => $query,
        'translated' => $translatedQuery
      ]);
    }

    // Try HybridPreFilter fallback if enabled (load dynamically from domain)
    if (self::USE_HYBRID_PATTERN_FALLBACK) {
      // Pattern-based fallback for hybrid detection
      // LLM tends to focus on first intent and ignore second intent in hybrid queries
      // Pattern fallback provides deterministic detection when LLM struggles
      
      // Load HybridPreFilter dynamically from active domain (domain-agnostic approach)
      $domainApp = DomainRegistry::getInstance()->getActiveApp();
      if ($domainApp && method_exists($domainApp, 'getHybridPreFilterClass')) {
        $hybridPreFilterClass = $domainApp->getHybridPreFilterClass();
        
        if ($hybridPreFilterClass && class_exists($hybridPreFilterClass)) {
          $hybridCheck = $hybridPreFilterClass::preFilter($translatedQuery);
          
          if ($hybridCheck !== null) {
            if ($this->debug) {
              $this->logInfo("Pattern fallback detected hybrid query", [
                'query' => $translatedQuery,
                'sub_types' => $hybridCheck['sub_types'] ?? [],
                'detection_method' => 'pattern_fallback'
              ]);
            }
            return $hybridCheck;
          }
        }
      } else if ($this->debug) {
        $this->logInfo("HybridPreFilter not available from domain - using Pure LLM Mode", []);
      }
    }

    // Always use LLM for classification
    $result = $this->classifyWithLLM($translatedQuery);
    $result['detection_method'] = 'llm';
    
    // ✅ CRITICAL FIX (2025-01-02): Apply WebSearchPostFilter to detect trends/news queries
    // The LLM prompt alone is not reliable for detecting web_search queries
    // Pattern-based post-filter provides deterministic detection for:
    // - Trends/news keywords (latest, recent, trends, news, what's new)
    // - Competitor keywords (competitors, competition, rival)
    // - Price comparison keywords (compare, vs, best price)
    $result = WebSearchPostFilter::postFilter($translatedQuery, $result);
    
    // Sync intent_type back to type (post-filter may have changed intent_type)
    if (isset($result['intent_type'])) {
      $result['type'] = $result['intent_type'];
      
      // Normalize: web_search → web (for backward compatibility)
      if ($result['type'] === 'web_search') {
        $result['type'] = 'web';
      }
    }
    
    return $result;
  }

  /**
   * Classify query using LLM
   * 
   * This method uses SemanticAgent::checkSemantics() which calls the LLM
   * to determine if a query is 'analytics', 'semantic', 'hybrid', or 'web_search'.
   * 
   * @param string $translatedQuery Translated query (English)
   * @return array Classification result with type, confidence, reasoning
   */
  private function classifyWithLLM(string $translatedQuery): array
  {
    if ($this->debug) {
      $this->logInfo("Using LLM for classification", [
        'query' => $translatedQuery
      ]);
    }
    
    try {
      // Use SemanticAgent::checkSemantics() which returns array with type, confidence, reasoning
      $classificationResult = SemanticAgent::checkSemantics($translatedQuery);
      
      // Extract type
      $type = $classificationResult['type'] ?? 'semantic';
      
      // Normalize type: LLM may return 'analytics' (plural) or 'analytic' (singular)
      if ($type === 'analytics') {
        $type = 'analytic';
      }
      
      // ✅ FIX (2025-01-02): Normalize 'web_search' → 'web' (LLM may return either)
      if ($type === 'web_search') {
        $type = 'web';
      }
      
      // Validate response (supports 4 categories)
      $validTypes = ['analytic', 'semantic', 'web', 'hybrid'];
      if (!in_array($type, $validTypes, true)) {
        if ($this->debug) {
          $this->logWarning("LLM returned invalid type", [
            'type' => $type,
            'defaulting_to' => 'semantic'
          ]);
        }
        $type = 'semantic';
        $classificationResult['confidence'] = 0.5;
      }
      
      // Log classification with confidence and reasoning
      if ($this->debug) {
        $this->logInfo("LLM classification complete", [
          'query' => $translatedQuery,
          'type' => $type,
          'confidence' => $classificationResult['confidence'] ?? 0.7,
          'reasoning' => $classificationResult['reasoning'] ?? 'N/A'
        ]);
      }
      
      return [
        'type' => $type,
        'intent_type' => $type, // For WebSearchPostFilter compatibility
        'confidence' => $classificationResult['confidence'] ?? 0.7,
        'reasoning' => [$classificationResult['reasoning'] ?? 'LLM classification'],
        'is_hybrid' => ($type === 'hybrid'),
      ];
      
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logError("LLM classification failed", $e);
      }
      
      // Fallback: semantic
      return [
        'type' => 'semantic',
        'intent_type' => 'semantic', // For WebSearchPostFilter compatibility
        'confidence' => 0.5,
        'reasoning' => ['LLM classification failed, defaulting to semantic'],
        'is_hybrid' => false,
      ];
    }
  }
}
