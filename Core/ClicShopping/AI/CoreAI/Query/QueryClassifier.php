<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Query;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Semantic\Processor\ClassificationEngine;
use ClicShopping\AI\Infrastructure\Cache\ClassificationCache;
use ClicShopping\OM\Registry;
use ClicShopping\AI\DomainsAI\DomainRegistry;

/**
 * QueryClassifier Class
 *
 * Centralized query classification logic to ensure consistency across all components.
 * This class is used by:
 * - IntentAnalyzer
 * - HybridQueryProcessor
 * - AnalyticsAgent
 * - Semantics
 *
 * All classification logic should be maintained here to avoid duplication and inconsistencies.
 * 
 */

class QueryClassifier
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?ClassificationCache $classificationCache = null;

  /**
   * PURE LLM MODE (2026-01-09)
   * 
   * Philosophy: Use LLM for all classification, patterns only as fallback if LLM fails
   * 
   * Set to 'False' to disable pattern fallback (pure LLM only)
   * Set to 'True' to enable HybridPreFilter as fallback (pattern-based hybrid detection)
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
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    
    if (!Registry::exists('ClassificationCache')) {
      Registry::set('ClassificationCache', new ClassificationCache(2592000, $this->debug));
    }
    $this->classificationCache = Registry::get('ClassificationCache');
    
    if ($this->debug) {
      $this->logger->logSecurityEvent('ClassificationCache initialized', 'info');
      $mode = self::USE_HYBRID_PATTERN_FALLBACK ? 'Pure LLM with pattern fallback' : 'Pure LLM only';
      $this->logger->logSecurityEvent("Classification mode: $mode", 'info');
    }
  }

  /**
   * Classify a query into one of: analytics, semantic, web_search, hybrid
   * 
   * 
   * CLASSIFICATION FLOW (Pure LLM Mode):
   * 1. Use LLM classification for all queries (default)
   * 2. If USE_HYBRID_PATTERN_FALLBACK=true and translatedQuery provided:
   *    - Try HybridPreFilter as fallback (pattern-based, English-only)
   *    - If hybrid detected: Return hybrid classification (90% confidence)
   *    - If no match: Use LLM classification
   * 
   * FALLBACK STRATEGY:
   * - Default type: 'semantic' (safer than 'analytics')
   * - If LLM fails, default to 'semantic'
   * - Pattern fallback is optional and disabled by default
   * 
   * RATIONALE:
   * - Pure LLM provides maximum flexibility and context understanding
   * - Semantic queries are safer to misclassify (RAG search is more forgiving)
   * - Analytics queries require precise SQL generation (higher risk if wrong)
   * - Pattern fallback available if LLM struggles with hybrid detection
   *
   * @param string $query Query to classify (can be in any language)
   * @param string|null $translatedQuery Optional pre-translated query (English)
   * @param array|null $conversationContext Optional conversation context for disambiguation
   * @return array Classification result with type, confidence, and reasoning
   */
  public function classify(string $query, ?string $translatedQuery = null, ?array $conversationContext = null): array
  {
    $classificationStartTime = microtime(true);
    
    if ($this->debug) {
      $this->logger->logStructured(
        'info',
        'QueryClassifier',
        'classification_start',
        [
          'query' => substr($query, 0, 100),
          'translated_query' => $translatedQuery ? substr($translatedQuery, 0, 100) : null,
          'has_conversation_context' => $conversationContext !== null,
          'context_turns' => $conversationContext ? count($conversationContext) : 0,
          'timestamp' => date('Y-m-d H:i:s')
        ]
      );
    }
    
    // Try HybridPreFilter fallback if enabled and translated query available (load dynamically)
    if (self::USE_HYBRID_PATTERN_FALLBACK && $translatedQuery !== null) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          'Pattern fallback enabled - trying HybridPreFilter from domain',
          'info'
        );
      }
      
      // Load HybridPreFilter dynamically from active domain (domain-agnostic approach)   
      $domainApp = DomainRegistry::getInstance()->getActiveApp();
      if ($domainApp && method_exists($domainApp, 'getHybridPreFilterClass')) {
        $hybridPreFilterClass = $domainApp->getHybridPreFilterClass();
        
        if ($hybridPreFilterClass && class_exists($hybridPreFilterClass)) {
          // Use HybridPreFilter from domain (pattern-based, English-only)
          $hybridCheck = $hybridPreFilterClass::preFilter($translatedQuery);
          
          if ($hybridCheck !== null) {
            // Pattern detected hybrid query     
            if ($this->debug) {
              $this->logger->logStructured(
                'info',
                'QueryClassifier',
                'hybrid_pattern_fallback_match',
                [
                  'query' => substr($translatedQuery, 0, 50),
                  'type' => 'hybrid',
                  'sub_types' => $hybridCheck['sub_types'] ?? [],
                  'confidence' => $hybridCheck['confidence'] ?? 0.90,
                  'detection_method' => 'pattern_fallback',
                  'domain' => $domainApp->getDomainId() ?? 'unknown',
                  'used_context' => false,
                  'context_influence' => null,
                  'execution_time_ms' => round((microtime(true) - $classificationStartTime) * 1000, 2),
                  'note' => 'Pattern fallback detected hybrid query (no LLM call)'
                ]
              );
            }
            
            return $hybridCheck;
          }
        }
      } else if ($this->debug) {
        $this->logger->logSecurityEvent(
          'HybridPreFilter not available from domain - using Pure LLM Mode',
          'info'
        );
      }
      
      // Pattern didn't match - fall through to LLM
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          'Pattern fallback no match - using LLM',
          'info'
        );
      }
    }
    
    // Pure LLM mode (default)
    if ($this->debug) {
      $this->logger->logSecurityEvent('Pure LLM mode - using LLM for classification', 'info');
    }
    
    $result = $this->classifyWithLLM($query, $translatedQuery);
    $result['detection_method'] = 'llm';
    
    
    if ($this->debug) {
      $executionTime = microtime(true) - $classificationStartTime;
      
      $this->logger->logStructured(
        'info',
        'QueryClassifier',
        'classification_complete',
        [
          'query' => substr($query, 0, 100),
          'type' => $result['type'],
          'confidence' => $result['confidence'],
          'reasoning' => $result['reasoning'] ?? [],
          'sub_types' => $result['sub_types'] ?? [],
          'detection_method' => $result['detection_method'],
          'used_context' => $conversationContext !== null,
          'context_turns_available' => $conversationContext ? count($conversationContext) : 0,
          'context_influence' => $conversationContext !== null ? 'available_but_not_used_for_pure_classification' : null,
          'execution_time_ms' => round($executionTime * 1000, 2),
          'timestamp' => date('Y-m-d H:i:s'),
          'note' => 'Pure LLM classification - context not used for initial classification'
        ]
      );
    }
    
    return $result;
  }

  /**
   * Quick check if query is analytics (for backward compatibility)
   *
   * @param string $query Query to check
   * @return bool True if analytics
   */
  public function isAnalytics(string $query): bool
  {
    $result = $this->classify($query);
    return $result['type'] === 'analytics';
  }

  /**
   * Quick check if query is semantic (for backward compatibility)
   *
   * @param string $query Query to check
   * @return bool True if semantic
   */
  public function isSemantic(string $query): bool
  {
    $result = $this->classify($query);
    return $result['type'] === 'semantic';
  }

  /**
   * Quick check if query is web search (for backward compatibility)
   *
   * @param string $query Query to check
   * @return bool True if web search
   */
  public function isWebSearch(string $query): bool
  {
    $result = $this->classify($query);
    return $result['type'] === 'web_search';
  }

  /**
   * Quick check if query is hybrid (for backward compatibility)
   *
   * @param string $query Query to check
   * @return bool True if hybrid
   */
  public function isHybrid(string $query): bool
  {
    $result = $this->classify($query);
    return $result['type'] === 'hybrid';
  }

  /**
   * Classify query using LLM fallback when patterns fail
   * 
   * 
   * This method uses ClassificationEngine::checkSemantics() which calls the LLM
   * to determine if a query is 'analytics', 'semantic', 'hybrid', or 'web_search'.
   * 
   * CACHE FLOW:
   * 1. Check ClassificationCache for cached result
   * 2. If cache HIT: Return cached result (instant, ~1ms)
   * 3. If cache MISS: Call LLM via ClassificationEngine
   * 4. Store LLM result in cache for future queries
   * 5. Return classification result
   * 
   * @param string $query Original query
   * @param string|null $translatedQuery Translated query (English)
   * @return array Classification result with type, confidence, reasoning
   */
  private function classifyWithLLM(string $query, ?string $translatedQuery): array
  {
    // Use translated query if available, otherwise use original
    $queryToClassify = $translatedQuery ?? $query;
    
    if ($this->classificationCache !== null) {
      $cached = $this->classificationCache->getCachedClassification($query, $translatedQuery);
      
      if ($cached !== null) {
        if ($this->debug) {
          $this->logger->logStructured(
            'info',
            'QueryClassifier',
            'classification_cache_hit',
            [
              'query' => substr($queryToClassify, 0, 50),
              'type' => $cached['type'],
              'confidence' => $cached['confidence'],
              'reasoning' => $cached['reasoning'] ?? [],
              'cache_age_seconds' => $cached['cache_age'] ?? 'unknown',
              'detection_method' => 'llm_cached',
              'note' => 'Returned from cache (no LLM call)'
            ]
          );
        }
        
        // Return cached result
        return $cached;
      }
      
      // Cache miss - log and proceed to LLM call
      if ($this->debug) {
        $this->logger->logStructured(
          'info',
          'QueryClassifier',
          'classification_cache_miss',
          [
            'query' => substr($queryToClassify, 0, 50),
            'note' => 'Cache miss - calling LLM'
          ]
        );
      }
    }
    
    // Original LLM classification logic
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Using LLM fallback for classification: {$queryToClassify}",
        'info'
      );
    }
    
    $llmStartTime = microtime(true);
    
    try {
      // Use ClassificationEngine::checkSemantics() which returns array with type, confidence, reasoning
      $classificationResult = ClassificationEngine::checkSemantics($queryToClassify);
      
      $llmExecutionTime = microtime(true) - $llmStartTime;
      
      // Extract type
      $type = $classificationResult['type'] ?? 'semantic';
      
      // Validate response (now supports 4 categories)
      $validTypes = ['analytics', 'semantic', 'hybrid', 'web_search'];
      if (!in_array($type, $validTypes, true)) {
        
        if ($this->debug) {
          $this->logger->logStructured(
            'warning',
            'QueryClassifier',
            'invalid_classification_type',
            [
              'query' => substr($queryToClassify, 0, 50),
              'invalid_type' => $type,
              'valid_types' => $validTypes,
              'defaulting_to' => 'semantic',
              'llm_response' => $classificationResult,
              'note' => 'LLM returned invalid type, defaulting to semantic'
            ]
          );
        }
        $type = 'semantic';
        $classificationResult['confidence'] = 0.5;
      }
      
      // Prepare result
      $result = [
        'type' => $type,
        'confidence' => $classificationResult['confidence'] ?? 0.7,
        'reasoning' => [$classificationResult['reasoning'] ?? 'LLM classification fallback'],
        'sub_types' => $classificationResult['sub_types'] ?? []
      ];
      
      if ($this->classificationCache !== null) {
        $this->classificationCache->cacheClassification($query, $translatedQuery, $result);
      }
      
      if ($this->debug) {
        $this->logger->logStructured(
          'info',
          'QueryClassifier',
          'llm_classification',
          [
            'query' => substr($queryToClassify, 0, 100),
            'type' => $type,
            'confidence' => $classificationResult['confidence'] ?? 0.7,
            'reasoning' => $classificationResult['reasoning'] ?? 'N/A',
            'sub_types' => $classificationResult['sub_types'] ?? [],
            'llm_execution_time_ms' => round($llmExecutionTime * 1000, 2),
            'cached' => 'yes',
            'detection_method' => 'llm',
            'timestamp' => date('Y-m-d H:i:s'),
            'note' => 'Fresh LLM classification - result cached for future queries'
          ]
        );
      }
      
      return $result;
      
    } catch (\Exception $e) {
      $llmExecutionTime = microtime(true) - $llmStartTime;
      
      if ($this->debug) {
        $this->logger->logStructured(
          'error',
          'QueryClassifier',
          'llm_classification_error',
          [
            'query' => substr($queryToClassify, 0, 100),
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'llm_execution_time_ms' => round($llmExecutionTime * 1000, 2),
            'defaulting_to' => 'semantic',
            'default_confidence' => 0.5,
            'timestamp' => date('Y-m-d H:i:s'),
            'note' => 'LLM fallback failed, defaulting to semantic'
          ]
        );
      }
      
      // Ultimate fallback: semantic
      return [
        'type' => 'semantic',
        'confidence' => 0.5,
        'reasoning' => ['LLM fallback failed, defaulting to semantic'],
      ];
    }
  }
}
