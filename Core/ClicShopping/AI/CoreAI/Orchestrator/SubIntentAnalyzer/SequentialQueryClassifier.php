<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;

use ClicShopping\AI\DomainsAI\Semantic\Processor\ClassificationEngine;

/**
 * SequentialQueryClassifier
 *
 * Owns the "split + classify sub-queries" concern: splits a query on sequential
 * indicators / conjunctions, classifies each part via ClassificationEngine, scores
 * hybrid confidence, and infers hybrid sub_types. Extracted verbatim from
 * UnifiedQueryAnalyzer::analyzeQuery (Phase B) and its helper methods to cut that
 * class below the god-class threshold; behaviour is unchanged.
 */
class SequentialQueryClassifier
{
  private bool $debug;

  /**
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
  }

  /**
   * Classify a (pre-translated) query: split on sequential indicators and score
   * hybrid confidence, else fall back to ClassificationEngine.
   *
   * @param string $query Pre-translated (English) query
   * @return array Classification with keys type, confidence, reasoning, sub_types
   */
  public function classify(string $query): array
  {
    // Split query on sequential indicators ("puis", "ensuite", "then", etc.)
    // and classify each sub-query independently to determine if hybrid
    $splitResult = $this->splitQueryOnSequentialIndicators($query);
    
    if ($splitResult['has_sequential_indicator']) {
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Sequential indicator detected - splitting query:");
        error_log("  Indicator: {$splitResult['indicator']}");
        error_log("  Sub-queries: " . count($splitResult['sub_queries']));
      }
      
      // Classify each sub-query independently
      $subQueryClassifications = $this->classifySubQueries($splitResult['sub_queries']);
      
      // Use multi-factor confidence scoring to determine if query should be hybrid
      $confidenceResult = $this->calculateHybridConfidence($query, $splitResult, $subQueryClassifications);
      
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Hybrid confidence scoring result:");
        error_log("  Confidence score: " . round($confidenceResult['confidence_score'], 2));
        error_log("  Is hybrid: " . ($confidenceResult['is_hybrid'] ? 'YES' : 'NO'));
        error_log("  Factors:");
        error_log("    Sequential words: " . $confidenceResult['factors']['sequential_words']);
        error_log("    Multiple types: " . $confidenceResult['factors']['multiple_types']);
        error_log("    LLM confidence: " . $confidenceResult['factors']['llm_confidence']);
      }
      
      // Use confidence scoring result to determine hybrid classification
      if ($confidenceResult['is_hybrid']) {
        // Determine sub-types from sub-query classifications.
        // Trust the LLM (Pure LLM): if every sub-query is the same type (e.g. all 'analytics'
        // type. Do NOT force ['analytics','semantic']: that hardcoded override injected a
        // non-existent semantic part and routed structured attributes (SKU/EAN) to the semantic
        // engine, contradicting the classifier.
        $types = array_map(function($c) { return $c['type']; }, $subQueryClassifications);
        $uniqueTypes = array_values(array_unique($types));

        // Override classification with hybrid result
        $classification = [
          'type' => 'hybrid',
          'confidence' => $confidenceResult['confidence_score'],
          'reasoning' => $confidenceResult['reasoning'],
          'sub_types' => $uniqueTypes,
        ];
        
        if ($this->debug) {
          error_log("[UnifiedQueryAnalyzer] Query classified as HYBRID via confidence scoring:");
          error_log("  Sub-types: " . implode(', ', $uniqueTypes));
          error_log("  Confidence: " . round($confidenceResult['confidence_score'], 3));
        }
      } else {
        // Not hybrid - use first sub-query classification
        $classification = [
          'type' => $subQueryClassifications[0]['type'],
          'confidence' => $subQueryClassifications[0]['confidence'],
          'reasoning' => $confidenceResult['reasoning'],
          'sub_types' => [],
        ];
        
        if ($this->debug) {
          error_log("[UnifiedQueryAnalyzer] Sequential indicator found but NOT hybrid:");
          error_log("  Confidence score too low: " . round($confidenceResult['confidence_score'], 2) . " < 0.5");
          error_log("  Using type: {$classification['type']}");
        }
      }
    } else {
      // No sequential indicator - use ClassificationEngine normally
      // This ensures consistent classification using the correct hybrid detection rules
      // from rag_classification.txt instead of rag_unified_analyzer.txt
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Using ClassificationEngine for classification:");
        error_log("  Query to classify: {$query}");
      }
      
      $classification = ClassificationEngine::checkSemantics($query);
      
      if ($this->debug) {
        error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] ClassificationEngine result:");
        error_log("  Type: " . $classification['type']);
        error_log("  Confidence: " . $classification['confidence']);
        error_log("  Reasoning: " . ($classification['reasoning'] ?? 'N/A'));
        error_log("  Sub-types: " . implode(', ', $classification['sub_types'] ?? []));
      }
    }

    return $classification;
  }

  /**
   * Split query on sequential indicators
   *
   * Detects sequential indicators in the query and splits it into sub-queries.
   * Sequential indicators include:
   * - English: "then", "next", "after", "afterwards", "followed by"
   *
   * @param string $query Original query
   * @return array Array with:
   *   - 'has_sequential_indicator' (bool): Whether sequential indicator was found
   *   - 'indicator' (string|null): The sequential indicator found
   *   - 'sub_queries' (array): Array of sub-query strings
   *   - 'split_position' (int|null): Position where split occurred
   */
  public function splitQueryOnSequentialIndicators(string $query): array
  {
    // Define sequential indicators (ordered by priority - longer phrases first)
    $indicators = [
      'followed by',
      'afterwards',
      'then',
      'next',
      'after',
    ];

    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Checking for sequential indicators in query:");
      error_log("  Query: {$query}");
    }

    // Search for sequential indicators (case-insensitive)
    foreach ($indicators as $indicator) {
      // Use word boundaries to avoid partial matches
      // e.g., "then" should not match "authentic"
      $pattern = '/\b' . preg_quote($indicator, '/') . '\b/i';

      if (preg_match($pattern, $query, $matches, PREG_OFFSET_CAPTURE)) {
        $foundIndicator = $matches[0][0];
        $position = $matches[0][1];

        // Split query at the indicator
        $beforeIndicator = trim(substr($query, 0, $position));
        $afterIndicator = trim(substr($query, $position + strlen($foundIndicator)));

        // Validate that both parts are non-empty
        if (empty($beforeIndicator) || empty($afterIndicator)) {
          if ($this->debug) {
            error_log("  ️ Found indicator '{$foundIndicator}' but one part is empty - skipping");
          }
          continue;
        }

        if ($this->debug) {
          error_log("   Found sequential indicator: '{$foundIndicator}' at position {$position}");
          error_log("  Sub-query 1: {$beforeIndicator}");
          error_log("  Sub-query 2: {$afterIndicator}");
        }

        return [
          'has_sequential_indicator' => true,
          'indicator' => $foundIndicator,
          'sub_queries' => [$beforeIndicator, $afterIndicator],
          'split_position' => $position,
        ];
      }
    }

    if ($this->debug) {
      error_log("No sequential indicators found");
    }

    // No sequential indicator found
    return [
      'has_sequential_indicator' => false,
      'indicator' => null,
      'sub_queries' => [$query],
      'split_position' => null,
    ];
  }

  /**
   * Classify each sub-query independently
   *
   * Task 11.3: Classify each sub-query independently
   *
   * Takes an array of sub-queries and classifies each one independently
   * using the ClassificationEngine. This allows us to determine if a query
   * should be hybrid based on having multiple different query types.
   *
   * @param array $subQueries Array of sub-query strings
   * @return array Array of classification results, each with:
   *   - 'query' (string): The sub-query text
   *   - 'type' (string): Classification type (analytics, semantic, web_search)
   *   - 'confidence' (float): Classification confidence
   *   - 'reasoning' (string): Classification reasoning
   *
   * @since 2026-02-11
   */
  public function classifySubQueries(array $subQueries): array
  {
    $classifications = [];

    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Classifying " . count($subQueries) . " sub-queries independently:");
    }

    foreach ($subQueries as $index => $subQuery) {
      if ($this->debug) {
        error_log("  Sub-query " . ($index + 1) . ": {$subQuery}");
      }

      try {
        // Use ClassificationEngine to classify each sub-query
        $classification = ClassificationEngine::checkSemantics($subQuery);

        $classifications[] = [
          'query' => $subQuery,
          'type' => $classification['type'],
          'confidence' => $classification['confidence'],
          'reasoning' => $classification['reasoning'] ?? '',
        ];

        if ($this->debug) {
          error_log("Type: {$classification['type']} (confidence: {$classification['confidence']})");
        }

      } catch (\Exception $e) {
        if ($this->debug) {
          error_log("Classification failed: " . $e->getMessage());
        }

        // Fallback to semantic if classification fails
        $classifications[] = [
          'query' => $subQuery,
          'type' => 'semantic',
          'confidence' => 0.5,
          'reasoning' => 'Fallback due to classification error',
        ];
      }
    }

    return $classifications;
  }

  /**
   * Calculate hybrid confidence score based on multiple factors
   *
   * This method calculates a confidence score for hybrid classification based on:
   * 1. Sequential words present (+0.3) - "puis", "ensuite", "then", etc.
   * 2. Multiple question types detected (+0.4) - different sub-query types from LLM classification
   * 3. LLM confidence scores (+0.3) - average confidence from sub-query classifications
   *
   * The query is classified as hybrid if the total score >= 0.5
   *
   * **Pure LLM Mode**: This method relies on LLM classification results for sub-queries
   * rather than pattern matching. The only pattern-based check is for sequential indicators,
   * which is necessary to split the query before LLM classification.
   *
   * Examples:
   * - "sku puis résume cgv" → sequential(+0.3) + multiple types(+0.4) + high LLM confidence(+0.3) = 1.0 → HYBRID
   * - "sku et prix" → no sequential(0) + single type(0) + low LLM confidence(0) = 0.0 → NOT HYBRID
   * - "prix puis ventes" → sequential(+0.3) + single type(0) + medium LLM confidence(+0.15) = 0.45 → NOT HYBRID
   *
   * @param string $query Original query to analyze
   * @param array $splitResult Result from splitQueryOnSequentialIndicators()
   * @param array $classifications Result from classifySubQueries() (LLM-based)
   * @return array Result with:
   *   - 'confidence_score' (float): Total confidence score (0.0-1.0)
   *   - 'is_hybrid' (bool): Whether query should be hybrid (score >= 0.5)
   *   - 'factors' (array): Breakdown of confidence factors
   *   - 'reasoning' (string): Explanation of confidence calculation
   */
  public function calculateHybridConfidence(string $query, array $splitResult, array $classifications): array
  {
    $confidenceScore = 0.0;
    $factors = [];
    $reasoning = [];

    // Factor 1: Sequential words present (+0.3)
    // This is the only pattern-based check - necessary to split query before LLM classification
    if ($splitResult['has_sequential_indicator']) {
      $confidenceScore += 0.3;
      $factors['sequential_words'] = 0.3;
      $reasoning[] = "Sequential indicator '{$splitResult['indicator']}' detected (+0.3)";
    } else {
      $factors['sequential_words'] = 0.0;
    }

    // Factor 2: Multiple question types detected (+0.4)
    // Uses LLM classification results from classifySubQueries()
    $types = array_map(function($c) { return $c['type']; }, $classifications);
    $uniqueTypes = array_unique($types);

    if (count($uniqueTypes) >= 2) {
      $confidenceScore += 0.4;
      $factors['multiple_types'] = 0.4;
      $reasoning[] = "Multiple question types detected via LLM: " . implode(', ', $uniqueTypes) . " (+0.4)";
    } else {
      $factors['multiple_types'] = 0.0;
    }

    // Factor 3: LLM confidence scores (+0.3)
    // Uses average confidence from LLM classifications
    $confidences = array_map(function($c) { return $c['confidence']; }, $classifications);
    $avgConfidence = array_sum($confidences) / count($confidences);

    // Scale average confidence to 0.0-0.3 range
    $confidenceFactor = $avgConfidence * 0.3;
    $confidenceScore += $confidenceFactor;
    $factors['llm_confidence'] = $confidenceFactor;
    $reasoning[] = "LLM classification confidence: " . round($avgConfidence, 3) . " (+" . round($confidenceFactor, 3) . ")";

    // Determine if hybrid (score >= 0.5)
    $isHybrid = $confidenceScore >= 0.5;

    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Hybrid Confidence Scoring (Task 11.3 - Pure LLM Mode):");
      error_log("  Query: {$query}");
      error_log("  Sequential words: " . ($factors['sequential_words'] > 0 ? 'YES (+0.3)' : 'NO (0.0)'));
      error_log("  Multiple types (LLM): " . ($factors['multiple_types'] > 0 ? 'YES (+0.4)' : 'NO (0.0)'));
      error_log("  LLM confidence: " . round($avgConfidence, 3) . " (+" . round($confidenceFactor, 3) . ")");
      error_log("  Total confidence: " . round($confidenceScore, 2));
      error_log("  Is hybrid: " . ($isHybrid ? 'YES (>= 0.5)' : 'NO (< 0.5)'));
    }

    return [
      'confidence_score' => $confidenceScore,
      'is_hybrid' => $isHybrid,
      'factors' => $factors,
      'reasoning' => implode("\n", $reasoning),
    ];
  }

  /**
   * Infer sub_types for hybrid queries when ClassificationEngine omits them.
   *
   * Operates only on the pre-translated (English) query, per the English-only
   * agnostic-processing rule.
   *
   * @param string $query Pre-translated (English) query
   * @return array
   */
  public function inferHybridSubTypes(string $query): array
  {
    $splitResult = $this->splitQueryOnSequentialIndicators($query);
    $subQueries = $splitResult['sub_queries'] ?? [$query];

    if (count($subQueries) < 2) {
      $subQueries = $this->splitQueryOnConjunctions($query);
    }

    if (count($subQueries) < 2) {
      return [];
    }

    $types = [];
    foreach ($subQueries as $subQuery) {
      try {
        $classification = ClassificationEngine::checkSemantics($subQuery);
        $types[] = $classification['type'] ?? 'semantic';
      } catch (\Exception $e) {
        $types[] = 'semantic';
      }
    }

    return $types;
  }

  /**
   * Split query on conjunctions when sequential indicators are absent.
   *
   * English-only: the whole AI pipeline runs on the pre-translated English query,
   * so no multilingual keywords belong in this agnostic Core/AI layer.
   *
   * @param string $query Pre-translated (English) query
   * @return array
   */
  private function splitQueryOnConjunctions(string $query): array
  {
    $connectors = [
      ' and ',
      ' or ',
      ' then ',
      ' after ',
      ' & ',
      ' + ',
      ';',
      ',',
    ];

    $lower = strtolower($query);
    foreach ($connectors as $connector) {
      $pos = strpos($lower, $connector);
      if ($pos !== false) {
        $left = trim(substr($query, 0, $pos));
        $right = trim(substr($query, $pos + strlen($connector)));
        if ($left !== '' && $right !== '') {
          return [$left, $right];
        }
      }
    }

    return [$query];
  }
}
