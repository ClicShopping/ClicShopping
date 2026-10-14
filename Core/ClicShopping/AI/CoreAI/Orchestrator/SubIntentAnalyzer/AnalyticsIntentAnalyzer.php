<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;



/**
 * AnalyticsIntentAnalyzer
 *
 * Simplified analyzer for analytics/data queries.
 * Pure LLM mode - all detection handled by UnifiedQueryAnalyzer.
 *
 * This class exists only for interface compatibility and type identification.
 * All actual detection, confidence calculation, and metadata extraction
 * is performed by UnifiedQueryAnalyzer using LLM.
 *
 * Examples of analytics queries:
 * - "How many products do we have?"
 * - "Sales today"
 * - "Annual revenue by month"
 * - "Stock of iPhone 17 Pro"
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer
 * @since 2025-12-14
 * @updated 2025-12-29 Task 5.1.6.6 - Simplified to pure LLM fallback
 */

class AnalyticsIntentAnalyzer extends BaseIntentAnalyzer
{
  protected string $type = 'analytics';

  /**
   * {@inheritdoc}
   * 
   * Pure LLM mode: Always returns LLM fallback result.
   * UnifiedQueryAnalyzer handles all detection via LLM.
   */
  public function analyze(string $query, string $originalQuery): array
  {
    return [
      'type' => $this->type,
      'confidence' => 0.0,
      'reasoning' => 'Pure LLM mode - detection handled by UnifiedQueryAnalyzer',
      'detection_method' => 'llm',
      'metadata' => []
    ];
  }

  /**
   * {@inheritdoc}
   * 
   * Pure LLM mode: Always returns 0.0.
   * UnifiedQueryAnalyzer calculates confidence via LLM.
   */
  public function calculateConfidence(string $query, array $detectionData): float
  {
    return 0.0;
  }

  /**
   * {@inheritdoc}
   * 
   * Pure LLM mode: Always returns empty array.
   * UnifiedQueryAnalyzer extracts metadata via LLM.
   */
  public function extractMetadata(string $query): array
  {
    return [];
  }
}
