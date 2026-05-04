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
 * HybridIntentAnalyzer
 *
 * Detects and analyzes hybrid queries that combine multiple intent types.
 * Orchestrates multiple specialized analyzers to handle complex queries.
 *
 * Examples:
 * - "Show me products with sales over $1000" (semantic + analytics)
 * - "Compare iPhone prices with Samsung" (semantic + web_search)
 * - "List top 10 products by revenue and their reviews" (analytics + semantic)
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer
 * @since 2025-12-14
 */

class HybridIntentAnalyzer extends BaseIntentAnalyzer
{
  private SemanticIntentAnalyzer $semanticAnalyzer;
  private AnalyticsIntentAnalyzer $analyticsAnalyzer;
  private WebSearchIntentAnalyzer $webSearchAnalyzer;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    parent::__construct($debug);
    $this->type = 'hybrid';

    // Initialize sub-analyzers
    $this->semanticAnalyzer = new SemanticIntentAnalyzer($debug);
    $this->analyticsAnalyzer = new AnalyticsIntentAnalyzer($debug);
    $this->webSearchAnalyzer = new WebSearchIntentAnalyzer($debug);
  }

  /**
   * {@inheritdoc}
   */
  public function analyze(string $query, string $originalQuery): array
  {
    $normalized = $this->normalizeQuery($query);

    // Run all sub-analyzers
    $semanticResult = $this->semanticAnalyzer->analyze($query, $originalQuery);
    $analyticsResult = $this->analyticsAnalyzer->analyze($query, $originalQuery);
    $webSearchResult = $this->webSearchAnalyzer->analyze($query, $originalQuery);

    // Count how many analyzers matched
    $matchedTypes = [];
    if ($semanticResult['matches']) $matchedTypes[] = 'semantic';
    if ($analyticsResult['matches']) $matchedTypes[] = 'analytics';
    if ($webSearchResult['matches']) $matchedTypes[] = 'web_search';

    // Hybrid query requires at least 2 types
    $isHybrid = count($matchedTypes) >= 2;

    if (!$isHybrid) {
      return [
        'matches' => false,
        'confidence' => 0.0,
        'type' => 'hybrid',
        'metadata' => [],
        'reasoning' => ['Not enough intent types detected for hybrid query'],
      ];
    }

    // Extract metadata from all matched analyzers
    $metadata = [
      'sub_intents' => $matchedTypes,
      'semantic' => $semanticResult['matches'] ? $semanticResult['metadata'] : null,
      'analytics' => $analyticsResult['matches'] ? $analyticsResult['metadata'] : null,
      'web_search' => $webSearchResult['matches'] ? $webSearchResult['metadata'] : null,
    ];

    // Calculate hybrid confidence
    $detectionData = [
      'matched_types' => $matchedTypes,
      'semantic_confidence' => $semanticResult['confidence'],
      'analytics_confidence' => $analyticsResult['confidence'],
      'web_search_confidence' => $webSearchResult['confidence'],
    ];

    $confidence = $this->calculateConfidence($query, $detectionData);

    $reasoning = [
      'Detected ' . count($matchedTypes) . ' intent types: ' . implode(', ', $matchedTypes),
      'Semantic confidence: ' . round($semanticResult['confidence'], 3),
      'Analytics confidence: ' . round($analyticsResult['confidence'], 3),
      'Web search confidence: ' . round($webSearchResult['confidence'], 3),
    ];

    $this->logDetection($query, true, $confidence, $reasoning);

    return [
      'matches' => true,
      'confidence' => $confidence,
      'type' => 'hybrid',
      'metadata' => $metadata,
      'reasoning' => $reasoning,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateConfidence(string $query, array $detectionData): float
  {
    $matchedTypes = $detectionData['matched_types'] ?? [];
    $typeCount = count($matchedTypes);

    // Base confidence for hybrid queries
    $baseConfidence = 0.7;

    // Add confidence based on number of matched types
    // 2 types: 0.75, 3 types: 0.80
    $typeBonus = ($typeCount - 1) * 0.05;

    // Average confidence from sub-analyzers
    $avgSubConfidence = 0.0;
    $confidenceCount = 0;

    if (isset($detectionData['semantic_confidence']) && $detectionData['semantic_confidence'] > 0) {
      $avgSubConfidence += $detectionData['semantic_confidence'];
      $confidenceCount++;
    }
    if (isset($detectionData['analytics_confidence']) && $detectionData['analytics_confidence'] > 0) {
      $avgSubConfidence += $detectionData['analytics_confidence'];
      $confidenceCount++;
    }
    if (isset($detectionData['web_search_confidence']) && $detectionData['web_search_confidence'] > 0) {
      $avgSubConfidence += $detectionData['web_search_confidence'];
      $confidenceCount++;
    }

    if ($confidenceCount > 0) {
      $avgSubConfidence /= $confidenceCount;
    }

    // Weighted average: 60% base + type bonus, 40% sub-analyzer confidence
    $confidence = ($baseConfidence + $typeBonus) * 0.6 + $avgSubConfidence * 0.4;

    // Cap at 0.85 (hybrid queries are inherently more complex)
    return min(0.85, $confidence);
  }

  /**
   * {@inheritdoc}
   */
  public function extractMetadata(string $query): array
  {
    // Metadata extraction is handled in analyze() method
    // by delegating to sub-analyzers
    return [];
  }
}
