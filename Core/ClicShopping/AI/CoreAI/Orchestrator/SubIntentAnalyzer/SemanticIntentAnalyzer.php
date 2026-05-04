<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;



/**
 * SemanticIntentAnalyzer
 *
 * Simplified analyzer for semantic/knowledge-based queries.
 * Pure LLM mode - all detection handled by UnifiedQueryAnalyzer.
 *
 * This class exists only for interface compatibility and type identification.
 * All actual detection, confidence calculation, and metadata extraction
 * is performed by UnifiedQueryAnalyzer using LLM.
 *
 * Examples of semantic queries:
 * - "What are the payment conditions?"
 * - "Explain the return policy"
 * - "How does delivery work?"
 * - "What is the warranty policy?"
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer
 * @since 2025-12-14
 * @updated 2026-01-02 Task 8.2.1.3 - Simplified to pure LLM fallback
 */

class SemanticIntentAnalyzer extends BaseIntentAnalyzer
{
  protected string $type = 'semantic';

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
