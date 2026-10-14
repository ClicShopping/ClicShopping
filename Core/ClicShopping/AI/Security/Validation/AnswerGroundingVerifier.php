<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Security\Validation;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVectorEmbeddingAdapter;
use ClicShopping\AI\DomainsAI\Shared\Embedding\VectorStatistics;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

/**
 * AnswerGroundingVerifier Class
 *
 * Verifies that LLM-generated answers are grounded in source documents
 * by calculating semantic similarity between answer sentences and document chunks.
 *
 * This class implements hallucination detection by:
 * 1. Breaking answers into sentences
 * 2. Calculating semantic similarity with source documents
 * 3. Flagging sentences with low similarity scores
 * 4. Computing overall confidence scores
 *
 * @see kiro_documentation/2025_12_27/hallucination_detection_system_design.md
 */

class AnswerGroundingVerifier
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?EmbeddingGeneratorInterface $embeddingGenerator = null;

  // Configuration thresholds
  private float $thresholdAccept = 0.85;
  private float $thresholdFlag = 0.70;
  private float $weightMaxSimilarity = 0.5;
  private float $weightAvgTop3 = 0.3;
  private float $weightWeightedAvg = 0.2;
  private float $penaltyWeakSentence = 0.2;
  private float $penaltyInconsistent = 0.1;
  private int $topKChunks = 3;
  private int $minSentenceWords = 5;

  /**
   * Weighted scoring (default): per-sentence grounding_score blends max/avg-top3/
   * weighted similarities and subtracts weak/inconsistency penalties. Calibrated
   * for high-cosine embedding models; tends to under-score short factual answers
   * with text-embedding-3-large (verbatim facts cap around 0.5 cosine).
   */
  public const SCORING_WEIGHTED = 'weighted';

  /**
   * Max-similarity scoring: confidence = cosine of the whole answer against its
   * single best-matching source chunk, with no penalty. Most robust separation
   * for short product-description answers (FAQ) on agnostic e-commerce content —
   * see SeoFaqPipeline. Keeps the answer un-split so the threshold matches the
   * empirical grounded/hallucinated distribution measured on that text.
   */
  public const SCORING_MAX_SIMILARITY = 'max_similarity';

  private string $scoringMode = self::SCORING_WEIGHTED;

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
      $this->logger->logSecurityEvent("AnswerGroundingVerifier initialized", 'info');
    }
  }

  /**
   * Select the scoring strategy.
   *
   * @param string $mode self::SCORING_WEIGHTED (default) or self::SCORING_MAX_SIMILARITY
   */
  public function setScoringMode(string $mode): void
  {
    if (in_array($mode, [self::SCORING_WEIGHTED, self::SCORING_MAX_SIMILARITY], true)) {
      $this->scoringMode = $mode;
    }
  }

  /**
   * Verify answer grounding
   *
   * Main entry point for hallucination detection.
   * Analyzes the generated answer against source documents.
   *
   * @param string $answer Generated answer text
   * @param array $sourceDocuments Source documents with embeddings
   * @return array Detection result with confidence and flagged sentences
   */
  public function verifyGrounding(string $answer, array $sourceDocuments): array
  {
    $startTime = microtime(true);

    try {
      // Step 1: Extract sentences
      $sentences = $this->scoringMode === self::SCORING_MAX_SIMILARITY
        ? array_filter([trim($answer)], fn($s) => $s !== '')
        : $this->extractSentences($answer);

      if (empty($sentences)) {
        return $this->createEmptyResult($answer, 'No sentences extracted');
      }

      // Check if we have source documents
      if (empty($sourceDocuments)) {
        return $this->createEmptyResult($answer, 'No source documents provided');
      }

      // Initialize embedding generator if needed (lazy loading)
      if ($this->embeddingGenerator === null) {
        $this->embeddingGenerator = new NewVectorEmbeddingAdapter();
      }

      // Step 2: Verify grounding for each sentence
      $groundingResults = [];
      foreach ($sentences as $sentence) {
        $groundingResults[] = $this->verifySentenceGrounding($sentence, $sourceDocuments);
      }

      // Step 3: Calculate overall confidence
      $confidence = $this->calculateConfidence($groundingResults);

      // Step 4: Make flagging decision
      $decision = $this->makeDecision($confidence, $groundingResults);

      $processingTime = (microtime(true) - $startTime) * 1000; // ms

      $result = [
        'answer' => $answer,
        'confidence' => $confidence,
        'grounding_score' => $confidence, // Alias for backward compatibility
        'decision' => $decision['decision'],
        'sentence_count' => count($sentences),
        'sentence_results' => $groundingResults,
        'flagged_sentences' => $decision['flagged_sentences'],
        'explanation' => $decision['explanation'],
        'processing_time_ms' => round($processingTime, 2),
        'source_document_count' => count($sourceDocuments),
      ];

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Grounding verification complete - Confidence: {$confidence}, Decision: {$decision['decision']}, Time: {$processingTime}ms",
          'info'
        );
      }

      return $result;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error in verifyGrounding: " . $e->getMessage(),
        'error'
      );

      return $this->createErrorResult($answer, $e->getMessage());
    }
  }

  /**
   * Extract sentences from answer
   *
   * Splits answer into individual sentences for granular verification.
   * Filters out very short sentences (< 5 words).
   *
   * @param string $answer Answer text
   * @return array Array of sentences
   */
  private function extractSentences(string $answer): array
  {
    // Split by sentence boundaries (., !, ?)
    $sentences = preg_split('/(?<=[.!?])\s+/', $answer, -1, PREG_SPLIT_NO_EMPTY);

    if ($sentences === false) {
      return [];
    }

    // Clean and filter
    $cleaned = [];
    foreach ($sentences as $sentence) {
      $sentence = trim($sentence);
      $wordCount = str_word_count($sentence);

      // Filter out very short sentences
      if ($wordCount >= $this->minSentenceWords) {
        $cleaned[] = $sentence;
      }
    }

    return $cleaned;
  }

  /**
   * Verify grounding for a single sentence
   *
   * Calculates semantic similarity between sentence and all document chunks.
   * Uses multiple metrics: max similarity, avg top-3, weighted average.
   *
   * @param string $sentence Sentence to verify
   * @param array $sourceDocuments Source documents with embeddings
   * @return array Grounding result for sentence
   */
  private function verifySentenceGrounding(string $sentence, array $sourceDocuments): array
  {
    try {
      // Check if we have any documents with embeddings
      if (empty($sourceDocuments)) {
        return $this->createLowGroundingResult($sentence, 'No source documents provided');
      }

      // 🔧 REGRESSION FIX 2025-12-28: Check if documents have valid embeddings BEFORE generating sentence embedding
      // This avoids expensive embedding generation when documents have no embeddings
      $hasValidEmbeddings = false;
      foreach ($sourceDocuments as $doc) {
        $chunks = $this->extractChunks($doc);
        foreach ($chunks as $chunk) {
          if ($this->extractEmbedding($chunk) !== null) {
            $hasValidEmbeddings = true;
            break 2; // Exit both loops
          }
        }
      }

      // If no valid embeddings found, return safe default (accept sentence)
      if (!$hasValidEmbeddings) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "⚠️  No valid embeddings found in documents - accepting sentence without verification",
            'warning'
          );
        }
        
        return [
          'sentence' => $sentence,
          'grounding_score' => 1.0,  // Accept by default
          'max_similarity' => 1.0,
          'avg_top3_similarity' => 1.0,
          'weighted_avg' => 1.0,
          'top_chunks' => [],
          'no_embeddings' => true,
          'explanation' => 'Documents have no embeddings - general knowledge query',
        ];
      }

      // Generate sentence embedding (only if we have valid document embeddings)
      $sentenceEmbedding = $this->embeddingGenerator->embedText($sentence);

      $similarities = [];

      // Calculate similarity with each document chunk
      foreach ($sourceDocuments as $doc) {
        $docRelevance = $this->extractRelevanceScore($doc);
        $chunks = $this->extractChunks($doc);

        foreach ($chunks as $chunk) {
          $chunkEmbedding = $this->extractEmbedding($chunk);

          if ($chunkEmbedding === null) {
            continue;
          }

          $similarity = VectorStatistics::cosineSimilarity($sentenceEmbedding, $chunkEmbedding);
          $weightedSim = $similarity * $docRelevance;

          $similarities[] = [
            'similarity' => $similarity,
            'weighted' => $weightedSim,
            'chunk' => $chunk,
            'document' => $doc,
          ];
        }
      }

      if (empty($similarities)) {
        return $this->createLowGroundingResult($sentence, 'No document chunks available');
      }

      // Sort by similarity (descending)
      usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

      // Calculate metrics
      $maxSimilarity = $similarities[0]['similarity'] ?? 0.0;

      $top3Similarities = array_slice(array_column($similarities, 'similarity'), 0, $this->topKChunks);
      $avgTop3 = !empty($top3Similarities) ? array_sum($top3Similarities) / count($top3Similarities) : 0.0;

      $weightedValues = array_column($similarities, 'weighted');
      $weightedAvg = !empty($weightedValues) ? array_sum($weightedValues) / count($weightedValues) : 0.0;

      // Combined grounding score
      $groundingScore = $this->weightMaxSimilarity * $maxSimilarity +
                        $this->weightAvgTop3 * $avgTop3 +
                        $this->weightWeightedAvg * $weightedAvg;

      return [
        'sentence' => $sentence,
        'grounding_score' => round($groundingScore, 4),
        'max_similarity' => round($maxSimilarity, 4),
        'avg_top3_similarity' => round($avgTop3, 4),
        'weighted_avg' => round($weightedAvg, 4),
        'top_chunks' => array_slice($similarities, 0, $this->topKChunks),
      ];

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error verifying sentence grounding: " . $e->getMessage(),
        'error'
      );

      return $this->createLowGroundingResult($sentence, 'Error: ' . $e->getMessage());
    }
  }

  /**
   * Calculate overall confidence score
   *
   * Combines sentence grounding scores with penalties for weak/inconsistent grounding.
   *
   * @param array $groundingResults Array of sentence grounding results
   * @return float Confidence score (0.0-1.0)
   */
  private function calculateConfidence(array $groundingResults): float
  {
    if (empty($groundingResults)) {
      return 0.0;
    }

    // Max-similarity mode: confidence is the answer's similarity to its single
    // best-matching source chunk, with no weak/inconsistency penalty.
    if ($this->scoringMode === self::SCORING_MAX_SIMILARITY) {
      $maxScores = array_column($groundingResults, 'max_similarity');
      if (empty($maxScores)) {
        return 0.0;
      }

      return max(0.0, min(1.0, max($maxScores)));
    }

    $scores = array_column($groundingResults, 'grounding_score');

    // Statistics
    $minScore = min($scores);
    $avgScore = array_sum($scores) / count($scores);
    $stdDev = $this->calculateStdDev($scores);

    // Apply penalties
    $penalty = 0.0;

    // Penalty for weak sentences
    if ($minScore < 0.5) {
      $penalty += $this->penaltyWeakSentence;
    }

    // Penalty for inconsistent grounding
    if ($stdDev > 0.3) {
      $penalty += $this->penaltyInconsistent;
    }

    // Calculate confidence
    $confidence = $avgScore - $penalty;

    // Clamp to [0.0, 1.0]
    return max(0.0, min(1.0, $confidence));
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
   * Make flagging decision
   *
   * Decides whether to ACCEPT, FLAG, or REJECT the answer based on confidence.
   *
   * @param float $confidence Overall confidence score
   * @param array $groundingResults Sentence grounding results
   * @return array Decision with flagged sentences and explanation
   */
  private function makeDecision(float $confidence, array $groundingResults): array
  {
    $flaggedSentences = [];

    // Identify flagged sentences (grounding < 0.70)
    foreach ($groundingResults as $result) {
      if ($result['grounding_score'] < $this->thresholdFlag) {
        $flaggedSentences[] = [
          'sentence' => $result['sentence'],
          'score' => $result['grounding_score'],
          'reason' => 'Low grounding score',
        ];
      }
    }

    // Make decision based on confidence
    if ($confidence >= $this->thresholdAccept) {
      $decision = 'ACCEPT';
      $explanation = 'Answer is well-grounded in source documents.';
    } elseif ($confidence >= $this->thresholdFlag) {
      $decision = 'FLAG';
      $explanation = sprintf(
        'Answer may contain minor inaccuracies. %d sentence(s) flagged.',
        count($flaggedSentences)
      );
    } else {
      $decision = 'REJECT';
      $explanation = sprintf(
        'Answer is not sufficiently grounded. %d sentence(s) flagged. Returning "insufficient information".',
        count($flaggedSentences)
      );
    }

    return [
      'decision' => $decision,
      'flagged_sentences' => $flaggedSentences,
      'explanation' => $explanation,
    ];
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
    return 1.0;
  }

  /**
   * Extract chunks from document
   *
   * @param mixed $doc Document (object or array)
   * @return array Array of chunks
   */
  private function extractChunks($doc): array
  {
    // If document has explicit chunks
    if (is_object($doc) && isset($doc->chunks)) {
      return is_array($doc->chunks) ? $doc->chunks : [$doc->chunks];
    } elseif (is_array($doc) && isset($doc['chunks'])) {
      return is_array($doc['chunks']) ? $doc['chunks'] : [$doc['chunks']];
    }

    // Otherwise, treat entire document as single chunk
    return [$doc];
  }

  /**
   * Extract embedding from chunk
   *
   * @param mixed $chunk Chunk (object or array)
   * @return array|null Embedding vector or null
   */
  private function extractEmbedding($chunk): ?array
  {
    if (is_object($chunk) && isset($chunk->embedding)) {
      return is_array($chunk->embedding) ? $chunk->embedding : null;
    } elseif (is_array($chunk) && isset($chunk['embedding'])) {
      return is_array($chunk['embedding']) ? $chunk['embedding'] : null;
    }

    return null;
  }


  /**
   * Create empty result
   *
   * @param string $answer Answer text
   * @param string $reason Reason for empty result
   * @return array Result
   */
  private function createEmptyResult(string $answer, string $reason): array
  {
    return [
      'answer' => $answer,
      'confidence' => 0.0,
      'grounding_score' => 0.0,
      'decision' => 'REJECT',
      'sentence_count' => 0,
      'sentence_results' => [],
      'flagged_sentences' => [],
      'explanation' => $reason,
      'processing_time_ms' => 0.0,
      'source_document_count' => 0,
    ];
  }

  /**
   * Create error result
   *
   * @param string $answer Answer text
   * @param string $error Error message
   * @return array Result
   */
  private function createErrorResult(string $answer, string $error): array
  {
    return [
      'answer' => $answer,
      'confidence' => 0.0,
      'grounding_score' => 0.0,
      'decision' => 'ERROR',
      'sentence_count' => 0,
      'sentence_results' => [],
      'flagged_sentences' => [],
      'explanation' => 'Error during verification: ' . $error,
      'processing_time_ms' => 0.0,
      'source_document_count' => 0,
      'error' => $error,
    ];
  }

  /**
   * Create low grounding result for sentence
   *
   * @param string $sentence Sentence text
   * @param string $reason Reason for low grounding
   * @return array Result
   */
  private function createLowGroundingResult(string $sentence, string $reason): array
  {
    return [
      'sentence' => $sentence,
      'grounding_score' => 0.0,
      'max_similarity' => 0.0,
      'avg_top3_similarity' => 0.0,
      'weighted_avg' => 0.0,
      'top_chunks' => [],
      'error' => $reason,
    ];
  }

  /**
   * Set configuration thresholds
   *
   * @param array $config Configuration array
   * @return void
   */
  public function setConfig(array $config): void
  {
    if (isset($config['threshold_accept'])) {
      $this->thresholdAccept = (float)$config['threshold_accept'];
    }
    if (isset($config['threshold_flag'])) {
      $this->thresholdFlag = (float)$config['threshold_flag'];
    }
    if (isset($config['weight_max_similarity'])) {
      $this->weightMaxSimilarity = (float)$config['weight_max_similarity'];
    }
    if (isset($config['weight_avg_top3'])) {
      $this->weightAvgTop3 = (float)$config['weight_avg_top3'];
    }
    if (isset($config['weight_weighted_avg'])) {
      $this->weightWeightedAvg = (float)$config['weight_weighted_avg'];
    }
    if (isset($config['penalty_weak_sentence'])) {
      $this->penaltyWeakSentence = (float)$config['penalty_weak_sentence'];
    }
    if (isset($config['penalty_inconsistent'])) {
      $this->penaltyInconsistent = (float)$config['penalty_inconsistent'];
    }
    if (isset($config['top_k_chunks'])) {
      $this->topKChunks = (int)$config['top_k_chunks'];
    }
    if (isset($config['min_sentence_words'])) {
      $this->minSentenceWords = (int)$config['min_sentence_words'];
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
      'threshold_accept' => $this->thresholdAccept,
      'threshold_flag' => $this->thresholdFlag,
      'weight_max_similarity' => $this->weightMaxSimilarity,
      'weight_avg_top3' => $this->weightAvgTop3,
      'weight_weighted_avg' => $this->weightWeightedAvg,
      'penalty_weak_sentence' => $this->penaltyWeakSentence,
      'penalty_inconsistent' => $this->penaltyInconsistent,
      'top_k_chunks' => $this->topKChunks,
      'min_sentence_words' => $this->minSentenceWords,
    ];
  }
}
