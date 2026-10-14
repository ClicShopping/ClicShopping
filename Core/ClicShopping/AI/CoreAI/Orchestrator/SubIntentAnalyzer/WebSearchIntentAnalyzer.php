<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;



/**
 * WebSearchIntentAnalyzer
 *
 * Pure LLM mode analyzer for web search queries.
 * All detection is handled by UnifiedQueryAnalyzer via LLM.
 * This class exists for type consistency but delegates all logic to LLM.
 *
 * Web search queries include:
 * - Price comparisons with competitors
 * - External marketplace queries
 * - Trend and news queries
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer
 * @since 2025-12-14
 * @updated 2026-01-02 - Simplified for Pure LLM mode
 */


class WebSearchIntentAnalyzer extends BaseIntentAnalyzer
{
  protected string $type = 'web_search';

  /**
   * Analyze query for web search intent.
   * 
   * Pure LLM mode: Returns low confidence to delegate detection to UnifiedQueryAnalyzer.
   * All web search detection is performed via LLM, not pattern matching.
   *
   * @param string $query Translated query (English)
   * @param string $originalQuery Original query (any language)
   * @return array Analysis result with type, confidence, reasoning, and detection_method
   */
  public function analyze(string $query, string $originalQuery): array
  {
    return [
      'type' => $this->type,
      'confidence' => 0.0,
      'reasoning' => 'Pure LLM mode - detection handled by UnifiedQueryAnalyzer',
      'detection_method' => 'llm'
    ];
  }

  /**
   * Calculate confidence score.
   * 
   * Not used in Pure LLM mode - UnifiedQueryAnalyzer calculates confidence via LLM.
   *
   * @param string $query Query text
   * @param array $detectionData Detection data
   * @return float Always returns 0.0
   */
  public function calculateConfidence(string $query, array $detectionData): float
  {
    return 0.0;
  }

  /**
   * Extract metadata from query.
   * 
   * Pure LLM mode: Always returns empty array.
   * UnifiedQueryAnalyzer extracts metadata via LLM.
   *
   * @param string $query Query text
   * @return array Always returns empty array
   */
  public function extractMetadata(string $query): array
  {
    return [];
  }
}
