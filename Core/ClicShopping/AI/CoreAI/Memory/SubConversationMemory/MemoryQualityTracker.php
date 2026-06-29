<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

/**
 * MemoryQualityTracker Class
 *
 * Owns the response-quality / feedback bookkeeping concern extracted verbatim
 * from ConversationMemory (2026-06-28, memory decomposition step 3): assessing a
 * response's quality band, recording successful-interaction patterns and updating
 * cumulative feedback metrics.
 *
 * Stateless itself — the running counters live in ConversationMemory's $stats
 * array, passed by reference so it stays the single source of truth (the
 * clearMemory() reset keeps working unchanged).
 *
 * Responsibilities:
 * - Assess a response's quality band (high/medium/low) from its word count
 * - Record a successful interaction pattern (capped buffer)
 * - Update cumulative feedback metrics (positive rate)
 */
class MemoryQualityTracker
{
  /**
   * Records a successful interaction pattern for future analysis.
   *
   * @param string $userMessage User message
   * @param string $systemResponse System response
   * @param array $metadata Interaction metadata
   * @param array $stats Running statistics buffer (by reference)
   */
  public function learnFromSuccessfulInteraction(string $userMessage, string $systemResponse, array $metadata, array &$stats): void
  {
    // Identify successful patterns
    $pattern = [
      'query_type' => $metadata['agent_type'] ?? 'unknown',
      'intent_confidence' => $metadata['intent_confidence'] ?? 0,
      'execution_time' => $metadata['execution_time'] ?? 0,
      'user_query_length' => str_word_count($userMessage),
      'response_quality' => $this->assessResponseQuality($systemResponse),
    ];

    // Store in stats for future analysis
    $stats['successful_patterns'][] = $pattern;

    // Limit the size of the patterns array
    if (count($stats['successful_patterns']) > 100) {
      array_shift($stats['successful_patterns']);
    }
  }

  /**
   * Assesses the quality of a response based on word count.
   *
   * @param string $response The response to evaluate
   * @return string Quality (high, medium, low)
   */
  public function assessResponseQuality(string $response): string
  {
    $wordCount = str_word_count($response);

    if ($wordCount < 10) return 'low';
    if ($wordCount < 50) return 'medium';
    return 'high';
  }

  /**
   * Updates quality metrics based on feedback
   *
   * @param string $feedbackType Type of feedback received
   * @param array $stats Running statistics buffer (by reference)
   * @return void
   */
  public function updateQualityMetrics(string $feedbackType, array &$stats): void
  {
    // Initialize metrics if not exists
    if (!isset($stats['feedback_metrics'])) {
      $stats['feedback_metrics'] = [
        'positive_count' => 0,
        'negative_count' => 0,
        'correction_count' => 0,
        'total_feedback' => 0,
        'positive_rate' => 0.0,
      ];
    }

    // Update counts
    $stats['feedback_metrics']['total_feedback']++;

    switch ($feedbackType) {
      case 'positive':
        $stats['feedback_metrics']['positive_count']++;
        break;
      case 'negative':
        $stats['feedback_metrics']['negative_count']++;
        break;
      case 'correction':
        $stats['feedback_metrics']['correction_count']++;
        break;
    }

    // Calculate positive rate
    $total = $stats['feedback_metrics']['total_feedback'];
    if ($total > 0) {
      $positive = $stats['feedback_metrics']['positive_count'];
      $stats['feedback_metrics']['positive_rate'] = round(($positive / $total) * 100, 2);
    }
  }
}
