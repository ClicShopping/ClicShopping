<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Helper\Detection;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\DomainsAI\Analytics\Agent\AmbiguityOptimizer;
use ClicShopping\AI\DomainsAI\Analytics\Agent\ParallelLLMExecutor;
use ClicShopping\AI\DomainsAI\Analytics\Helper\Detection\AmbiguityStrategy;
use ClicShopping\AI\DomainsAI\Hybrid\Patterns\AmbiguityPreFilter;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;


/**
 * Class AmbiguousQueryDetector
 * 
 * Uses LLM to dynamically detect ambiguous queries
 * No hardcoded patterns - fully dynamic and extensible
 * 
 * Examples of ambiguous queries:
 * - "How many products do we have in stock?" -> COUNT vs SUM
 * - "Show me customer orders" -> All vs Recent vs Active
 * - "What is the price?" -> Average vs Min vs Max vs List
 */

class AmbiguousQueryDetector
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private mixed $chat;
  private mixed $language;

  /**
   * Active strategy mode. Production runs c1 (deterministic LLM at temperature 0.0 via the
   * Gpt facade, no regex pre-filter) — the bench winner on capable models (100% accuracy).
   * See docs/superpowers/specs/2026-06-15-ambiguity-detection-bench-design.md. The bench can
   * still select c0/c2/c3 via setStrategy(); c2 (regex fallback) is the recommended choice if
   * a deployment must support weak/local models.
   */
  private string $strategy = AmbiguityStrategy::C1;

  /** Optional engine override for cross-model measurement; null = configured default. */
  private ?string $engine = null;

  /** When false, the analysis cache is disabled (bench measurement). Production: true. */
  private bool $optimizerCacheEnabled = true;

  public function getStrategy(): string
  {
    return $this->strategy;
  }

  public function setStrategy(string $mode): void
  {
    if (!AmbiguityStrategy::isValid($mode)) {
      throw new \InvalidArgumentException("Unknown ambiguity strategy: {$mode}");
    }
    $this->strategy = $mode;
  }

  public function setEngine(?string $engine): void
  {
    $this->engine = $engine;
  }

  /**
   * @internal Measurement hook for unit_test/2026_06_15/ambiguity_eval_harness.php ONLY.
   *           Business code must never disable the cache. Production keeps it enabled.
   */
  public function setCacheEnabled(bool $enabled): void
  {
    $this->optimizerCacheEnabled = $enabled;
  }


  /**
   * LLM prompt for ambiguity detection
   * Dynamic detection without hardcoded patterns
   */

  /**
   * Constructor
   * 
   * @param mixed $chat LLM chat instance for dynamic detection
   * @param SecurityLogger $securityLogger Security logger instance
   * @param bool $debug Enable debug mode
   */
  public function __construct($chat, SecurityLogger $securityLogger, bool $debug = false)
  {
    $this->chat = $chat;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
    $this->language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_ambiguity');
  }
  
  /**
   * Detect if a query is ambiguous using LLM analysis
   * Dynamic detection without hardcoded patterns
   * 
   * OPTIMIZATION: Uses AmbiguityOptimizer for:
   * - Pattern-based pre-filtering (fast)
   * - Cache lookup (very fast)
   * - Reduced interpretations (2 max instead of 3)
   * 
   * @param string $query The user query to analyze
   * @return array Analysis result containing:
   *               - is_ambiguous: bool
   *               - ambiguity_type: string|null
   *               - interpretations: array
   *               - default_interpretation: string|null
   *               - confidence: float
   *               - recommendation: string
   */
  public function detectAmbiguity(string $query): array
  {
    if ($this->debug) {
      error_log("AmbiguousQueryDetector: Analyzing query with LLM: {$query}");
    }
    
    try {
      // OPTIMIZATION 1: Initialize optimizer
      $optimizer = new AmbiguityOptimizer($this->debug);
      if (!$this->optimizerCacheEnabled) {
        $optimizer->disableCache();
      }
      
      // OPTIMIZATION 2: Check cache first (Memcached/Redis/Traditional)
      $cached = $optimizer->getCachedAmbiguityAnalysis($query);
      if ($cached !== null) {
        if ($this->debug) {
          error_log("AmbiguousQueryDetector: Cache HIT! Returning cached analysis");
        }
        return $cached;
      }
      
      // OPTIMIZATION 3: Check clear patterns before LLM call (PRIMARY only for c0).
      // c1/c2/c3 defer the regex: c2 keeps it as a fallback (handled after the LLM call),
      // c1/c3 do not use it at all.
      $clearCheck = AmbiguityStrategy::usesPrefilterPrimary($this->strategy)
        ? $optimizer->isClearlyNonAmbiguous($query)
        : ['is_clear' => false, 'pattern_type' => null, 'matched_text' => null, 'reason' => 'prefilter_skipped'];
      if ($clearCheck['is_clear']) {
        if ($this->debug) {
          error_log("AmbiguousQueryDetector: Clear pattern detected ({$clearCheck['pattern_type']}), skipping LLM");
        }
        
        $result = [
          'is_ambiguous' => false,
          'ambiguity_type' => null,
          'interpretations' => [],
          'default_interpretation' => $clearCheck['pattern_type'],
          'confidence' => 0.95,
          'recommendation' => 'proceed',
          'reasoning' => 'Matched clear pattern: ' . $clearCheck['pattern_type'],
          'optimization' => 'pattern_match'
        ];
        
        // Cache the result
        $optimizer->cacheAmbiguityAnalysis($query, $result);
        
        return $result;
      }
      
      // OPTIMIZATION 3.1: Check if prefilter determined query IS ambiguous
      if (isset($clearCheck['prefilter_result']) && $clearCheck['prefilter_result']['is_ambiguous']) {
        if ($this->debug) {
          error_log("AmbiguousQueryDetector: Prefilter detected ambiguity ({$clearCheck['prefilter_result']['ambiguity_type']}), using prefilter result");
        }
        
        $result = $clearCheck['prefilter_result'];
        $result['optimization'] = 'prefilter_ambiguous';
        
        // Log ambiguous query for future improvements
        $this->logAmbiguousQuery(
          $query, 
          $result['ambiguity_type'], 
          $result['interpretations']
        );
        
        // Cache the result
        $optimizer->cacheAmbiguityAnalysis($query, $result);
        
        return $result;
      }
      
      // OPTIMIZATION 4: Use LLM for ambiguity detection
      if ($this->debug) {
        error_log("AmbiguousQueryDetector: No clear pattern, using LLM detection");
      }
      
      $prompt = $this->language->getDef('text_rag_detect_ambiguity', ['QUERY' => $query]);

      // Get LLM analysis. c1/c2/c3 use the Gpt facade at temperature 0.0 (deterministic +
      // facade-compliant). c0 keeps the legacy direct chat call at default temperature.
      if (AmbiguityStrategy::usesDeterministicLlm($this->strategy)) {
        $response = (string) Gpt::getGptResponse($prompt, 600, 0.0, $this->engine);
      } else {
        $response = $this->chat->generateText($prompt);
      }
      
      if ($this->debug) {
        error_log("AmbiguousQueryDetector: LLM response: " . substr($response, 0, 500));
      }
      
      // Parse JSON response. For c2, an unusable verdict (parse failure or low confidence)
      // falls back to the regex pre-filter rather than guessing.
      try {
        $analysis = $this->parseAmbiguityResponse($response);
        $parseFailed = false;
      } catch (\Exception $e) {
        $parseFailed = true;
        $analysis = [
          'is_ambiguous' => false, 'ambiguity_type' => null, 'interpretations' => [],
          'default_interpretation' => null, 'confidence' => 0.0,
          'recommendation' => 'proceed', 'reasoning' => 'llm_parse_failed',
        ];
      }

      if ($this->strategy === AmbiguityStrategy::C2
          && AmbiguityStrategy::shouldFallbackToRegex($parseFailed, (float) ($analysis['confidence'] ?? 0.0))) {
        $regex = AmbiguityPreFilter::preFilter($query);
        if ($regex !== null) {
          $regex['optimization'] = 'c2_regex_fallback';
          $optimizer->cacheAmbiguityAnalysis($query, $regex);
          return $regex;
        }
      }

      if ($parseFailed) {
        // No regex fallback available (or not c2): surface the safe non-ambiguous default.
        return $analysis;
      }
      
      // OPTIMIZATION 5: Reduce interpretations to 2 max (instead of 3)
      if ($analysis['is_ambiguous'] && !empty($analysis['interpretations'])) {
        $optimalCount = $optimizer->getOptimalInterpretationCount($query, $analysis);
        
        if (count($analysis['interpretations']) > $optimalCount) {
          $selectedTypes = $optimizer->selectInterpretations($analysis, $optimalCount);
          
          // Filter interpretations to keep only selected types
          $analysis['interpretations'] = array_filter(
            $analysis['interpretations'],
            function($interp) use ($selectedTypes) {
              return in_array($interp['type'] ?? '', $selectedTypes, true);
            }
          );
          
          // Re-index array
          $analysis['interpretations'] = array_values($analysis['interpretations']);
          
          if ($this->debug) {
            error_log("AmbiguousQueryDetector: Reduced interpretations from " . count($analysis['interpretations']) . " to {$optimalCount}");
          }
        }
      }
      
      if ($analysis['is_ambiguous']) {
        // Log ambiguous query for future improvements
        $this->logAmbiguousQuery(
          $query, 
          $analysis['ambiguity_type'], 
          $analysis['interpretations']
        );
        
        if ($this->debug) {
          error_log("AmbiguousQueryDetector: Detected ambiguity type '{$analysis['ambiguity_type']}'");
          error_log("AmbiguousQueryDetector: Interpretations: " . count($analysis['interpretations']));
          error_log("AmbiguousQueryDetector: Recommendation: {$analysis['recommendation']}\n");
        }
      } else {
        if ($this->debug) {
          error_log("AmbiguousQueryDetector: No ambiguity detected\n");
        }
      }
      
      // c3: an LLM critic validates the verdict and regenerates it once if rejected.
      if (AmbiguityStrategy::usesCritic($this->strategy)) {
        $analysis = $this->critiqueAnalysis($query, $analysis);
      }

      // OPTIMIZATION 6: Cache the result
      $optimizer->cacheAmbiguityAnalysis($query, $analysis);

      return $analysis;
      
    } catch (\Exception $e) {
      // Fallback to non-ambiguous if LLM fails
      if ($this->debug) {
        error_log("AmbiguousQueryDetector: LLM detection failed: " . $e->getMessage());
      }
      
      return [
        'is_ambiguous' => false,
        'ambiguity_type' => null,
        'interpretations' => [],
        'default_interpretation' => null,
        'confidence' => 0.0,
        'recommendation' => 'proceed'
      ];
    }
  }
  
  /**
   * Parse LLM response for ambiguity analysis
   * 
   * @param string $response LLM JSON response
   * @return array Parsed analysis
   */
  private function parseAmbiguityResponse(string $response): array
  {
    // Extract JSON from response (may have markdown code blocks)
    $json = $response;
    if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
      $json = $matches[1];
    } else if (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
      $json = $matches[1];
    }
    
    // Clean up response
    $json = trim($json);
    
    // Parse JSON
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Failed to parse LLM response as JSON: " . json_last_error_msg());
    }
    
    // Validate required fields
    if (!isset($data['is_ambiguous'])) {
      throw new \Exception("Missing required field: is_ambiguous\n");
    }
    
    // 🔧 FIX: If interpretations array is empty but query is ambiguous, generate defaults
    $interpretations = $data['interpretations'] ?? [];
    if ($data['is_ambiguous'] && empty($interpretations)) {
      $ambiguityType = $data['ambiguity_type'] ?? '';
      
      if ($this->debug) {
        error_log("AmbiguousQueryDetector: LLM returned empty interpretations for ambiguous query, generating defaults");
      }
      
      // Generate default interpretations based on ambiguity type
      if (str_contains($ambiguityType, 'quantification')) {
        // For quantification queries: count vs sum
        $interpretations = [
          [
            'type' => 'count',
            'label' => 'Count of items',
            'description' => 'Count the number of items',
            'sql_hint' => 'Use COUNT(*) or COUNT(DISTINCT id)'
          ],
          [
            'type' => 'sum',
            'label' => 'Sum of quantities',
            'description' => 'Sum the total quantity',
            'sql_hint' => 'Use SUM(quantity_field)'
          ]
        ];
      } else if (str_contains($ambiguityType, 'scope')) {
        // For scope queries: all vs recent
        $interpretations = [
          [
            'type' => 'all',
            'label' => 'All items',
            'description' => 'Include all items without time filter',
            'sql_hint' => 'No date filter'
          ],
          [
            'type' => 'recent',
            'label' => 'Recent items',
            'description' => 'Include only recent items',
            'sql_hint' => 'Add date filter for last 30 days'
          ]
        ];
      } else {
        // Generic fallback
        $interpretations = [
          [
            'type' => 'count',
            'label' => 'Count',
            'description' => 'Count the number of items',
            'sql_hint' => 'Use COUNT(*)'
          ],
          [
            'type' => 'list',
            'label' => 'List',
            'description' => 'List all matching items',
            'sql_hint' => 'SELECT all fields'
          ]
        ];
      }
      
      if ($this->debug) {
        error_log("AmbiguousQueryDetector: Generated " . count($interpretations) . " default interpretations");
      }
    }
    
    // Build result with defaults
    return [
      'is_ambiguous' => (bool)$data['is_ambiguous'],
      'ambiguity_type' => $data['ambiguity_type'] ?? null,
      'interpretations' => $interpretations,
      'default_interpretation' => $data['default_interpretation'] ?? null,
      'confidence' => (float)($data['confidence'] ?? 0.0),
      'recommendation' => $data['recommendation'] ?? 'proceed',
      'reasoning' => $data['reasoning'] ?? ''
    ];
  }

  /**
   * C3 critic pass: ask an LLM to validate the verdict and regenerate it once if rejected.
   * Returns the (possibly corrected) analysis. Falls back to the input on any failure.
   *
   * @param string $query Translated English query.
   * @param array $analysis Candidate verdict from the first LLM pass.
   * @return array Validated/regenerated verdict.
   */
  private function critiqueAnalysis(string $query, array $analysis): array
  {
    try {
      $prompt = $this->language->getDef('text_rag_critique_ambiguity', [
        'QUERY' => $query,
        'VERDICT' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
      ]);
      $raw = (string) Gpt::getGptResponse($prompt, 600, 0.0, $this->engine);

      // extractCriticJson returns the corrected verdict object when verdict_ok is false,
      // otherwise the original analysis — so parse just normalizes it.
      return $this->parseAmbiguityResponse($this->extractCriticJson($raw, $analysis));
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log('AmbiguousQueryDetector: critic pass failed: ' . $e->getMessage());
      }
      return $analysis;
    }
  }

  /**
   * Pull the effective verdict out of a critic response: the corrected object when the
   * critic rejected it, the original analysis when accepted or unparseable.
   *
   * @param string $raw Raw critic LLM output.
   * @param array $fallback Original analysis to keep if the critic accepted or failed.
   * @return string JSON string of the effective verdict (for parseAmbiguityResponse).
   */
  private function extractCriticJson(string $raw, array $fallback): string
  {
    $json = $raw;
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $raw, $m)) {
      $json = $m[1];
    }
    $data = json_decode(trim($json), true);
    if (!is_array($data) || ($data['verdict_ok'] ?? true) === true || !isset($data['corrected'])) {
      return json_encode($fallback, JSON_UNESCAPED_UNICODE);
    }
    return json_encode($data['corrected'], JSON_UNESCAPED_UNICODE);
  }

  /**
   * Log ambiguous query for future prompt improvements
   * 
   * @param string $query The ambiguous query
   * @param string $type The ambiguity type
   * @param array $interpretations Possible interpretations
   * @return void
   */
  private function logAmbiguousQuery(string $query, string $type, array $interpretations): void
  {
    // Extract interpretation types
    $interpretationTypes = array_map(function($interp) {
      return $interp['type'] ?? 'unknown';
    }, $interpretations);
    
    $this->securityLogger->logSecurityEvent(
      "Ambiguous query detected",
      'info',
      [
        'query' => $query,
        'ambiguity_type' => $type,
        'interpretations' => $interpretationTypes,
        'timestamp' => date('Y-m-d H:i:s')
      ]
    );
  }
  
  /**
   * Generate SQL queries for all interpretations of an ambiguous query
   * Uses LLM to generate clarified queries for each interpretation
   * 
   * OPTIMIZATION: Uses ParallelLLMExecutor for concurrent clarification
   * - Reduces total time from (N × 3s) to ~3s for N interpretations
   * - Maintains backward compatibility with existing interface
   * - Falls back to sequential execution if parallel fails
   * 
   * @param string $query The original query
   * @param array $ambiguityAnalysis The ambiguity analysis result
   * @param callable $sqlGenerator Function to generate SQL from a modified query
   * @return array Array of SQL queries with their interpretations
   */
  public function generateMultipleInterpretations(
    string $query, 
    array $ambiguityAnalysis, 
    callable $sqlGenerator
  ): array {
    if (!$ambiguityAnalysis['is_ambiguous']) {
      return [];
    }
    
    $interpretations = $ambiguityAnalysis['interpretations'];
    
    if ($this->debug) {
      error_log("AmbiguousQueryDetector: Generating " . count($interpretations) . " interpretations using parallel execution");
    }
    
    // Build all clarification prompts first
    $prompts = [];
    foreach ($interpretations as $interpretation) {
      $type = $interpretation['type'];
      $prompts[$type] = $this->buildClarificationPrompt($query, $interpretation);
    }
    
    // Execute all clarification prompts in parallel
    $executor = new ParallelLLMExecutor(null, $this->debug);
    $parallelResults = $executor->executeParallel($prompts);
    
    // Process results and generate SQL for each clarified query
    $results = [];
    foreach ($interpretations as $interpretation) {
      $type = $interpretation['type'];
      
      // Check if clarification was successful
      if (!isset($parallelResults[$type]) || !($parallelResults[$type]['success'] ?? false)) {
        if ($this->debug) {
          $error = $parallelResults[$type]['error'] ?? 'Unknown error';
          error_log("AmbiguousQueryDetector: Failed to clarify interpretation '{$type}': {$error}");
        }
        continue;
      }
      
      try {
        // Get clarified query from parallel execution result
        $clarifiedQuery = $parallelResults[$type]['response'];
        
        // Clean up the clarified query (remove quotes if present)
        $clarifiedQuery = trim($clarifiedQuery);
        $clarifiedQuery = trim($clarifiedQuery, '"\'');
        
        if ($this->debug) {
          error_log("AmbiguousQueryDetector: Clarified query for '{$type}': {$clarifiedQuery}");
        }
        
        // Generate SQL using the provided generator
        $sql = $sqlGenerator($clarifiedQuery);
        
        if (!empty($sql)) {
          $results[] = [
            'type' => $type,
            'label' => $interpretation['label'],
            'description' => $interpretation['description'],
            'query' => $clarifiedQuery,
            'sql' => $sql
          ];
        }
        
      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("AmbiguousQueryDetector: Failed to generate SQL for interpretation '{$type}': " . $e->getMessage());
        }
        continue;
      }
    }
    
    if ($this->debug) {
      error_log("AmbiguousQueryDetector: Successfully generated " . count($results) . " interpretations");
    }
    
    return $results;
  }
  
  /**
   * Clarify query for a specific interpretation
   * Uses LLM to rewrite query with explicit intent
   * 
   * @param string $originalQuery Original ambiguous query
   * @param array $interpretation Interpretation details
   * @return string Clarified query
   */
  private function clarifyQueryForInterpretation(string $originalQuery, array $interpretation): string
  {
    $prompt = $this->buildClarificationPrompt($originalQuery, $interpretation);
    $clarified = $this->chat->generateText($prompt);
    
    $clarified = trim($clarified);
    $clarified = trim($clarified, '"\'');
    
    return $clarified;
  }
  
  /**
   * Build clarification prompt for a specific interpretation
   * Extracted for use in parallel execution
   * 
   * @param string $originalQuery Original ambiguous query
   * @param array $interpretation Interpretation details
   * @return string Clarification prompt
   */
  private function buildClarificationPrompt(string $originalQuery, array $interpretation): string
  {
    $array = [
      'original_query' => $originalQuery,
      'interpretation' => $interpretation['description'],
      'sql_hint' => $interpretation['sql_hint']
    ];

    return $this->language->getDef('text_rag_clarify_query_for_interpretation', $array);
  }
}
