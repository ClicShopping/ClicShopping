<?php
/**
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;

/**
 * FeedbackImpactDetector Class
 *
 * Detects if previous user feedback (corrections or positive feedback) should influence
 * the current query response. Uses embedding-based similarity to determine relevance.
 *
 * Responsibilities:
 * - Retrieve feedback context from ConversationMemory
 * - Calculate query similarity using embeddings
 * - Determine if feedback is relevant (threshold: 0.7)
 * - Return feedback impact decision with metadata
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator
 */

class FeedbackImpactDetector
{
  private SecurityLogger $logger;
  private bool $debug;
  private float $relevanceThreshold = 0.7; // Minimum similarity score for relevance

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->logger = new SecurityLogger();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "FeedbackImpactDetector initialized (threshold: {$this->relevanceThreshold})",
        'info'
      );
    }
  }

  /**
   * Detect if previous feedback should influence the current query
   *
   * @param string $currentQuery Current user query
   * @param array $feedbackContext Feedback items from ConversationMemory.getFeedbackContext()
   * @return array Feedback impact decision with metadata
   */
  public function detectFeedbackImpact(string $currentQuery, array $feedbackContext): array
  {
    try {
      // Default: no feedback influence
      $result = [
        'feedback_influenced' => false,
        'feedback_type' => null,
        'feedback_relevance_score' => 0.0,
        'feedback_interaction_id' => null,
        'feedback_message' => null,
        'feedback_data' => null,
      ];

      // No feedback available
      if (empty($feedbackContext)) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "No feedback context available for query: {$currentQuery}",
            'info'
          );
        }
        return $result;
      }

      // Find most relevant feedback item
      $mostRelevant = null;
      $highestScore = 0.0;

      foreach ($feedbackContext as $feedbackItem) {
        // Calculate similarity between current query and original query from feedback
        $originalQuery = $feedbackItem['original_query'] ?? '';
        
        if (empty($originalQuery)) {
          continue;
        }

        $similarityScore = $this->calculateQuerySimilarity($currentQuery, $originalQuery);

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Similarity score: {$similarityScore} (current: '{$currentQuery}' vs feedback: '{$originalQuery}')",
            'info'
          );
        }

        // Track highest scoring feedback
        if ($similarityScore > $highestScore) {
          $highestScore = $similarityScore;
          $mostRelevant = $feedbackItem;
        }
      }

      // Check if most relevant feedback meets threshold
      if ($mostRelevant !== null && $this->isRelevant($highestScore)) {
        $feedbackType = $mostRelevant['feedback_type'] ?? 'unknown';
        
        $result = [
          'feedback_influenced' => true,
          'feedback_type' => $feedbackType,
          'feedback_relevance_score' => $highestScore,
          'feedback_interaction_id' => $mostRelevant['interaction_id'] ?? null,
          'feedback_message' => $this->generateFeedbackMessage($feedbackType),
          'feedback_data' => $mostRelevant,
        ];

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "✅ Feedback influence detected: {$feedbackType} (score: {$highestScore}, interaction: {$result['feedback_interaction_id']})",
            'info'
          );
        }
      } else {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "❌ No relevant feedback found (highest score: {$highestScore}, threshold: {$this->relevanceThreshold})",
            'info'
          );
        }
      }

      return $result;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error detecting feedback impact: " . $e->getMessage(),
        'error'
      );

      return [
        'feedback_influenced' => false,
        'feedback_type' => null,
        'feedback_relevance_score' => 0.0,
        'feedback_interaction_id' => null,
        'feedback_message' => null,
        'feedback_data' => null,
      ];
    }
  }

  /**
   * Calculate similarity between two queries using embeddings
   *
   * Uses cosine similarity between embedding vectors.
   *
   * @param string $query1 First query
   * @param string $query2 Second query
   * @return float Similarity score (0.0 to 1.0)
   */
  private function calculateQuerySimilarity(string $query1, string $query2): float
  {
    try {
      // Get embedding generator
      $embeddingGenerator = NewVector::gptEmbeddingsModel();
      
      if (!$embeddingGenerator) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Embedding generator not available, using fallback similarity",
            'warning'
          );
        }
        // Fallback: simple string similarity
        return $this->fallbackStringSimilarity($query1, $query2);
      }

      // Generate embeddings
      $embedding1 = $embeddingGenerator->embedText($query1);
      $embedding2 = $embeddingGenerator->embedText($query2);

      // Calculate cosine similarity
      $similarity = $this->cosineSimilarity($embedding1, $embedding2);

      return $similarity;

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Error calculating embedding similarity: " . $e->getMessage(),
          'warning'
        );
      }
      
      // Fallback to string similarity
      return $this->fallbackStringSimilarity($query1, $query2);
    }
  }

  /**
   * Calculate cosine similarity between two vectors
   *
   * @param array $vector1 First embedding vector
   * @param array $vector2 Second embedding vector
   * @return float Cosine similarity (0.0 to 1.0)
   */
  private function cosineSimilarity(array $vector1, array $vector2): float
  {
    if (count($vector1) !== count($vector2)) {
      throw new \InvalidArgumentException('Vectors must have the same dimension');
    }

    $dotProduct = 0.0;
    $magnitude1 = 0.0;
    $magnitude2 = 0.0;

    $vectorLength = count($vector1);
    for ($i = 0; $i < $vectorLength; $i++) {
      $dotProduct += $vector1[$i] * $vector2[$i];
      $magnitude1 += $vector1[$i] * $vector1[$i];
      $magnitude2 += $vector2[$i] * $vector2[$i];
    }

    $magnitude1 = sqrt($magnitude1);
    $magnitude2 = sqrt($magnitude2);

    if ($magnitude1 == 0 || $magnitude2 == 0) {
      return 0.0;
    }

    // Cosine similarity ranges from -1 to 1, normalize to 0 to 1
    $cosineSim = $dotProduct / ($magnitude1 * $magnitude2);
    
    // Normalize to 0-1 range (cosine similarity can be negative)
    return ($cosineSim + 1) / 2;
  }

  /**
   * Fallback string similarity using Levenshtein distance
   *
   * @param string $str1 First string
   * @param string $str2 Second string
   * @return float Similarity score (0.0 to 1.0)
   */
  private function fallbackStringSimilarity(string $str1, string $str2): float
  {
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));

    if ($str1 === $str2) {
      return 1.0;
    }

    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen === 0) {
      return 0.0;
    }

    $distance = levenshtein($str1, $str2);
    $similarity = 1.0 - ($distance / $maxLen);

    return max(0.0, $similarity);
  }

  /**
   * Check if similarity score meets relevance threshold
   *
   * @param float $similarityScore Similarity score (0.0 to 1.0)
   * @return bool True if relevant, false otherwise
   */
  private function isRelevant(float $similarityScore): bool
  {
    return $similarityScore >= $this->relevanceThreshold;
  }

  /**
   * Generate feedback message based on feedback type
   *
   * @param string $feedbackType Type of feedback ('correction' or 'positive')
   * @return string Feedback message with icon
   */
  private function generateFeedbackMessage(string $feedbackType): string
  {
    switch ($feedbackType) {
      case 'correction':
        return '💡 Response improved based on your previous correction';
      
      case 'positive':
        return '💡 Response based on previously successful approach';
      
      default:
        return '💡 Response improved based on previous feedback';
    }
  }

  /**
   * Set relevance threshold
   *
   * @param float $threshold Threshold value (0.0 to 1.0)
   * @return void
   */
  public function setRelevanceThreshold(float $threshold): void
  {
    if ($threshold < 0.0 || $threshold > 1.0) {
      throw new \InvalidArgumentException('Threshold must be between 0.0 and 1.0');
    }

    $this->relevanceThreshold = $threshold;

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Relevance threshold updated to: {$threshold}",
        'info'
      );
    }
  }

  /**
   * Get current relevance threshold
   *
   * @return float Current threshold value
   */
  public function getRelevanceThreshold(): float
  {
    return $this->relevanceThreshold;
  }
}
