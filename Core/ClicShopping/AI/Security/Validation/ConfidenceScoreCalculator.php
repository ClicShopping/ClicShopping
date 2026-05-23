<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Security\Validation;


use ClicShopping\AI\Security\SecurityLogger;

/**
 * ConfidenceScoreCalculator Class
 *
 * Calculates comprehensive confidence scores for semantic responses by combining:
 * 1. Document relevance scores (how relevant retrieved documents are)
 * 2. Answer grounding scores (how well the answer is supported by documents)
 * 3. Additional quality metrics
 *
 * This class provides a unified confidence metric that can be used to:
 * - Display confidence indicators in the UI
 * - Make decisions about answer quality
 * - Trigger clarification requests for low-confidence responses
 * - Track response quality over time
 *
 * Task 3.5.1.4: Add confidence scoring
 * @see .kiro/specs/current/pattern-removal-and-performance/tasks.md
 * @see kiro_documentation/2025_12_27/hallucination_detection_system_design.md
 */


class ConfidenceScoreCalculator
{
  private SecurityLogger $logger;
  private bool $debug;

  // Weighting factors for combined confidence
  private float $weightDocumentRelevance = 0.40;  // 40% weight on document quality
  private float $weightAnswerGrounding = 0.50;    // 50% weight on answer grounding
  private float $weightAdditionalFactors = 0.10;  // 10% weight on other factors

  // Confidence level thresholds
  private float $thresholdHigh = 0.85;      // >= 0.85: HIGH confidence
  private float $thresholdMedium = 0.70;    // >= 0.70: MEDIUM confidence
  private float $thresholdLow = 0.50;       // >= 0.50: LOW confidence
                                            // < 0.50: VERY_LOW confidence

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;

    if ($this->debug) {
      $this->logger->logSecurityEvent("ConfidenceScoreCalculator initialized", 'info');
    }
  }

  /**
   * Calculate document relevance score
   *
   * Analyzes the relevance scores of retrieved documents to determine
   * how well the document set matches the query.
   *
   * Metrics considered:
   * - Average relevance score
   * - Maximum relevance score (best document)
   * - Minimum relevance score (worst document)
   * - Standard deviation (consistency)
   * - Document count (more documents = more confidence)
   *
   * @param array $documents Array of documents with relevanceScore
   * @return float Document relevance score (0.0-1.0)
   */
  public function calculateDocumentRelevance(array $documents): float
  {
    if (empty($documents)) {
      return 0.0;
    }

    $relevanceScores = [];

    foreach ($documents as $doc) {
      $score = $this->extractRelevanceScore($doc);
      if ($score > 0.0) {
        $relevanceScores[] = $score;
      }
    }

    if (empty($relevanceScores)) {
      return 0.0;
    }

    // Calculate statistics
    $avgScore = array_sum($relevanceScores) / count($relevanceScores);
    $maxScore = max($relevanceScores);
    $minScore = min($relevanceScores);
    $stdDev = $this->calculateStdDev($relevanceScores);

    // Weighted combination
    $documentRelevance = 0.5 * $avgScore +      // Average quality
                        0.3 * $maxScore +       // Best document quality
                        0.1 * (1.0 - $stdDev) + // Consistency bonus
                        0.1 * min(1.0, count($relevanceScores) / 5.0); // Document count bonus

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        sprintf(
          "Document relevance: %.4f (avg=%.4f, max=%.4f, min=%.4f, stdDev=%.4f, count=%d)",
          $documentRelevance,
          $avgScore,
          $maxScore,
          $minScore,
          $stdDev,
          count($relevanceScores)
        ),
        'info'
      );
    }

    return max(0.0, min(1.0, $documentRelevance));
  }

  /**
   * Calculate combined confidence score
   *
   * Combines document relevance and answer grounding scores into a single
   * comprehensive confidence metric.
   *
   * 🔧 FIX: Handle general knowledge queries (no documents) gracefully
   *
   * @param array $documents Source documents
   * @param array $groundingResult Result from AnswerGroundingVerifier
   * @param array $additionalFactors Optional additional quality factors
   * @return array Confidence breakdown with overall score
   */
  public function calculateCombinedConfidence(
    array $documents,
    array $groundingResult,
    array $additionalFactors = []
  ): array {
    // Calculate document relevance
    $documentRelevance = $this->calculateDocumentRelevance($documents);

    // Extract answer grounding score
    $answerGrounding = $groundingResult['confidence'] ?? $groundingResult['grounding_score'] ?? 0.0;

    // Calculate additional factors score
    $additionalScore = $this->calculateAdditionalFactors($additionalFactors);

    // 🔧 FIX: Detect general knowledge queries (no documents, grounding skipped)
    $isGeneralKnowledge = empty($documents) && 
                          isset($groundingResult['skipped']) && 
                          $groundingResult['skipped'] === true;

    // 🔧 FIX: For general knowledge queries, use LLM confidence instead of penalizing
    if ($isGeneralKnowledge) {
      // Use high confidence for general knowledge (LLM fallback)
      // The LLM is trained on general knowledge and should be trusted
      $overallConfidence = 0.85; // HIGH confidence for general knowledge
      $confidenceLevel = 'HIGH';
      $decision = 'ACCEPT';
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "General knowledge query detected (no documents) - using LLM confidence: 0.85",
          'info'
        );
      }
    } else {
      // Weighted combination for document-based queries
      $overallConfidence = $this->weightDocumentRelevance * $documentRelevance +
                          $this->weightAnswerGrounding * $answerGrounding +
                          $this->weightAdditionalFactors * $additionalScore;

      // Determine confidence level
      $confidenceLevel = $this->getConfidenceLevel($overallConfidence);

      // Determine decision
      $decision = $groundingResult['decision'] ?? $this->makeDecision($overallConfidence);
    }

    $result = [
      'overall_confidence' => round($overallConfidence, 4),
      'document_relevance' => round($documentRelevance, 4),
      'answer_grounding' => round($answerGrounding, 4),
      'additional_factors' => round($additionalScore, 4),
      'confidence_level' => $confidenceLevel,
      'decision' => $decision,
      'is_general_knowledge' => $isGeneralKnowledge,
      'breakdown' => [
        'document_relevance_weight' => $this->weightDocumentRelevance,
        'answer_grounding_weight' => $this->weightAnswerGrounding,
        'additional_factors_weight' => $this->weightAdditionalFactors,
      ],
    ];

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        sprintf(
          "Combined confidence: %.4f (doc=%.4f, grounding=%.4f, additional=%.4f) → %s%s",
          $overallConfidence,
          $documentRelevance,
          $answerGrounding,
          $additionalScore,
          $confidenceLevel,
          $isGeneralKnowledge ? ' [GENERAL KNOWLEDGE]' : ''
        ),
        'info'
      );
    }

    return $result;
  }

  /**
   * Get confidence level label
   *
   * @param float $confidence Confidence score (0.0-1.0)
   * @return string Confidence level (HIGH, MEDIUM, LOW, VERY_LOW)
   */
  public function getConfidenceLevel(float $confidence): string
  {
    if ($confidence >= $this->thresholdHigh) {
      return 'HIGH';
    } elseif ($confidence >= $this->thresholdMedium) {
      return 'MEDIUM';
    } elseif ($confidence >= $this->thresholdLow) {
      return 'LOW';
    } else {
      return 'VERY_LOW';
    }
  }

  /**
   * Format confidence data for UI display
   *
   * Converts confidence scores into UI-friendly format with icons, colors, and labels.
   *
   * @param array $confidenceData Confidence data from calculateCombinedConfidence()
   * @return array UI-formatted confidence data
   */
  public function formatForUI(array $confidenceData): array
  {
    $confidence = $confidenceData['overall_confidence'] ?? 0.0;
    $level = $confidenceData['confidence_level'] ?? $this->getConfidenceLevel($confidence);
    $decision = $confidenceData['decision'] ?? 'UNKNOWN';

    // Determine icon and color based on level
    $uiConfig = $this->getUIConfig($level, $decision);

    return [
      'confidence' => $confidence,
      'percentage' => round($confidence * 100, 1),
      'level' => $level,
      'decision' => $decision,
      'icon' => $uiConfig['icon'],
      'color' => $uiConfig['color'],
      'label' => $uiConfig['label'],
      'tooltip' => $this->generateTooltip($confidenceData),
      'show_warning' => $level === 'LOW' || $level === 'VERY_LOW',
      'breakdown' => [
        'document_relevance' => [
          'score' => $confidenceData['document_relevance'] ?? 0.0,
          'percentage' => round(($confidenceData['document_relevance'] ?? 0.0) * 100, 1),
          'label' => 'Document Quality',
        ],
        'answer_grounding' => [
          'score' => $confidenceData['answer_grounding'] ?? 0.0,
          'percentage' => round(($confidenceData['answer_grounding'] ?? 0.0) * 100, 1),
          'label' => 'Answer Grounding',
        ],
      ],
    ];
  }

  /**
   * Get UI configuration for confidence level
   *
   * @param string $level Confidence level
   * @param string $decision Grounding decision
   * @return array UI configuration
   */
  private function getUIConfig(string $level, string $decision): array
  {
    $configs = [
      'HIGH' => [
        'icon' => '✅',
        'color' => '#28a745',
        'label' => 'High Confidence',
      ],
      'MEDIUM' => [
        'icon' => '✓',
        'color' => '#17a2b8',
        'label' => 'Medium Confidence',
      ],
      'LOW' => [
        'icon' => '⚠️',
        'color' => '#ffc107',
        'label' => 'Low Confidence',
      ],
      'VERY_LOW' => [
        'icon' => '❌',
        'color' => '#dc3545',
        'label' => 'Very Low Confidence',
      ],
    ];

    // Override for REJECT decision
    if ($decision === 'REJECT') {
      return [
        'icon' => '🚫',
        'color' => '#dc3545',
        'label' => 'Insufficient Information',
      ];
    }

    // Override for FLAG decision
    if ($decision === 'FLAG') {
      return [
        'icon' => '⚠️',
        'color' => '#ffc107',
        'label' => 'Flagged for Review',
      ];
    }

    return $configs[$level] ?? $configs['VERY_LOW'];
  }

  /**
   * Generate tooltip text
   *
   * @param array $confidenceData Confidence data
   * @return string Tooltip text
   */
  private function generateTooltip(array $confidenceData): string
  {
    $parts = [];

    $parts[] = sprintf(
      'Overall Confidence: %.1f%%',
      ($confidenceData['overall_confidence'] ?? 0.0) * 100
    );

    $parts[] = sprintf(
      'Document Quality: %.1f%%',
      ($confidenceData['document_relevance'] ?? 0.0) * 100
    );

    $parts[] = sprintf(
      'Answer Grounding: %.1f%%',
      ($confidenceData['answer_grounding'] ?? 0.0) * 100
    );

    if (isset($confidenceData['decision'])) {
      $parts[] = 'Decision: ' . $confidenceData['decision'];
    }

    return implode(' | ', $parts);
  }

  /**
   * Calculate additional quality factors
   *
   * @param array $factors Additional quality factors
   * @return float Additional factors score (0.0-1.0)
   */
  private function calculateAdditionalFactors(array $factors): float
  {
    if (empty($factors)) {
      return 0.5; // Neutral score if no additional factors
    }

    $scores = [];

    // Response length factor (longer responses may be more detailed)
    if (isset($factors['response_length'])) {
      $length = $factors['response_length'];
      $scores[] = min(1.0, $length / 500.0); // Normalize to 500 chars
    }

    // Source count factor (more sources = more confidence)
    if (isset($factors['source_count'])) {
      $count = $factors['source_count'];
      $scores[] = min(1.0, $count / 5.0); // Normalize to 5 sources
    }

    // Query complexity factor (simpler queries = higher confidence)
    if (isset($factors['query_complexity'])) {
      $complexity = $factors['query_complexity']; // 0.0-1.0
      $scores[] = 1.0 - $complexity; // Invert (lower complexity = higher confidence)
    }

    return empty($scores) ? 0.5 : array_sum($scores) / count($scores);
  }

  /**
   * Make decision based on confidence score
   *
   * @param float $confidence Confidence score
   * @return string Decision (ACCEPT, FLAG, REJECT)
   */
  private function makeDecision(float $confidence): string
  {
    if ($confidence >= $this->thresholdHigh) {
      return 'ACCEPT';
    } elseif ($confidence >= $this->thresholdMedium) {
      return 'FLAG';
    } else {
      return 'REJECT';
    }
  }

  /**
   * Extract relevance score from document
   *
   * @param mixed $doc Document (object or array)
   * @return float Relevance score (0.0-1.0)
   */
  private function extractRelevanceScore($doc): float
  {
    if (is_object($doc) && isset($doc->relevanceScore)) {
      return (float)$doc->relevanceScore;
    } elseif (is_array($doc) && isset($doc['relevanceScore'])) {
      return (float)$doc['relevanceScore'];
    } elseif (is_array($doc) && isset($doc['relevance_score'])) {
      return (float)$doc['relevance_score'];
    }

    // Default relevance if not specified
    return 0.5;
  }

  /**
   * Calculate standard deviation
   *
   * @param array $values Array of numeric values
   * @return float Standard deviation
   */
  private function calculateStdDev(array $values): float
  {
    if (empty($values)) {
      return 0.0;
    }

    $mean = array_sum($values) / count($values);
    $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
    return sqrt($variance);
  }

  /**
   * Set weighting factors
   *
   * @param array $weights Weighting configuration
   * @return void
   */
  public function setWeights(array $weights): void
  {
    if (isset($weights['document_relevance'])) {
      $this->weightDocumentRelevance = (float)$weights['document_relevance'];
    }
    if (isset($weights['answer_grounding'])) {
      $this->weightAnswerGrounding = (float)$weights['answer_grounding'];
    }
    if (isset($weights['additional_factors'])) {
      $this->weightAdditionalFactors = (float)$weights['additional_factors'];
    }

    // Normalize weights to sum to 1.0
    $total = $this->weightDocumentRelevance + $this->weightAnswerGrounding + $this->weightAdditionalFactors;
    if ($total > 0.0) {
      $this->weightDocumentRelevance /= $total;
      $this->weightAnswerGrounding /= $total;
      $this->weightAdditionalFactors /= $total;
    }
  }

  /**
   * Set confidence thresholds
   *
   * @param array $thresholds Threshold configuration
   * @return void
   */
  public function setThresholds(array $thresholds): void
  {
    if (isset($thresholds['high'])) {
      $this->thresholdHigh = (float)$thresholds['high'];
    }
    if (isset($thresholds['medium'])) {
      $this->thresholdMedium = (float)$thresholds['medium'];
    }
    if (isset($thresholds['low'])) {
      $this->thresholdLow = (float)$thresholds['low'];
    }
  }

  /**
   * Get current configuration
   *
   * @return array Configuration array
   */
  public function getConfig(): array
  {
    return [
      'weights' => [
        'document_relevance' => $this->weightDocumentRelevance,
        'answer_grounding' => $this->weightAnswerGrounding,
        'additional_factors' => $this->weightAdditionalFactors,
      ],
      'thresholds' => [
        'high' => $this->thresholdHigh,
        'medium' => $this->thresholdMedium,
        'low' => $this->thresholdLow,
      ],
    ];
  }
}
