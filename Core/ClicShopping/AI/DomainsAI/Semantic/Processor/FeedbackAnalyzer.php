<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Processor;


use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * FeedbackAnalyzer
 * 
 * Analyzes user feedback to identify classification errors and improvement opportunities.
 * Works with ThresholdManager to enable auto-adjustment.
 */

class FeedbackAnalyzer
{
  private static ?SecurityLogger $logger = null;
  
  /**
   * Initialize logger
   */
  private static function initLogger(): void
  {
    if (self::$logger === null) {
      self::$logger = new SecurityLogger();
    }
  }

  /**
   * Checks if a feedback indicates a classification error
   * 
   * @param array $feedback Feedback record
   * @return bool True if classification error detected
   */
  private static function isClassificationError(array $feedback): bool
  {
    $feedbackData = json_decode($feedback['feedback_data'], true);
    
    if (!$feedbackData) {
      return false;
    }
    
    $reason = $feedbackData['reason'] ?? '';
    $comment = strtolower($feedbackData['comment'] ?? '');
    
    // Keywords indicating classification errors
    $classificationKeywords = [
      'wrong type',
      'misclassified',
      'should be analytics',
      'should be semantic',
      'wrong category',
      'incorrect classification'
    ];
    
    foreach ($classificationKeywords as $keyword) {
      if (strpos($comment, $keyword) !== false) {
        return true;
      }
    }
    
    if ($reason === 'irrelevant') {
      return true;
    }
    
    return false;
  }

  /**
   * Analyzes feedbacks to identify classification issues
   *
   * @param int $days Number of days to analyze
   * @param int $limit Maximum feedbacks to retrieve
   * @return array Analysis results
   */
  public static function analyzeFeedbacks(int $days = 7, int $limit = 100): array
  {
    self::initLogger();

    try {
      $sql = "SELECT 
                f.id,
                f.interaction_id,
                f.feedback_type,
                f.feedback_data,
                f.timestamp,
                f.user_id
              FROM rag_feedback f
              WHERE f.timestamp > :since
              ORDER BY f.timestamp DESC
              LIMIT :limit";

      $since = time() - ($days * 24 * 3600);
      
      $feedbacks = DoctrineOrm::select($sql, [
        'since' => $since,
        'limit' => $limit
      ]);

      if (empty($feedbacks)) {
        return [
          'total_feedbacks' => 0,
          'positive_count' => 0,
          'negative_count' => 0,
          'correction_count' => 0,
          'classification_errors' => 0,
          'error_rate' => 0.0,
          'improvement_rate' => 0.0
        ];
      }

      // Analyze feedback types
      $positive = 0;
      $negative = 0;
      $corrections = 0;
      $classificationErrors = 0;

      foreach ($feedbacks as $feedback) {
        switch ($feedback['feedback_type']) {
          case 'positive':
            $positive++;
            break;
          case 'negative':
            $negative++;
            // Check if it's a classification error
            if (self::isClassificationError($feedback)) {
              $classificationErrors++;
            }
            break;
          case 'correction':
            $corrections++;
            break;
        }
      }

      $total = count($feedbacks);
      $errorRate = $total > 0 ? ($classificationErrors / $total) * 100 : 0;
      $improvementRate = ($positive + $negative) > 0 ? ($positive / ($positive + $negative)) * 100 : 0;

      return [
        'total_feedbacks' => $total,
        'positive_count' => $positive,
        'negative_count' => $negative,
        'correction_count' => $corrections,
        'classification_errors' => $classificationErrors,
        'error_rate' => round($errorRate, 2),
        'improvement_rate' => round($improvementRate, 2),
        'period_days' => $days
      ];

    } catch (\Exception $e) {
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Error analyzing feedbacks: " . $e->getMessage(),
          'error'
        );
      }

      return [
        'error' => $e->getMessage(),
        'total_feedbacks' => 0
      ];
    }
  }

  /**
   * Gets feedback statistics for a specific period
   *
   * @param int $days Number of days
   * @return array Statistics
   */
  public static function getFeedbackStats(int $days = 30): array
  {
    self::initLogger();

    try {
      $sql = "SELECT 
                feedback_type,
                COUNT(*) as count,
                AVG(JSON_EXTRACT(feedback_data, '$.rating')) as avg_rating
              FROM rag_feedback
              WHERE timestamp > :since
              GROUP BY feedback_type";

      $since = time() - ($days * 24 * 3600);
      
      $rows = DoctrineOrm::select($sql, ['since' => $since]);

      $stats = [];
      foreach ($rows as $row) {
        $stats[$row['feedback_type']] = [
          'count' => (int)$row['count'],
          'avg_rating' => round((float)$row['avg_rating'], 2)
        ];
      }

      return $stats;

    } catch (\Exception $e) {
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Error getting feedback stats: " . $e->getMessage(),
          'error'
        );
      }

      return [];
    }
  }

  /**
   * Analyzes classification errors from feedbacks
   * 
   * @param array $feedbacks Array of feedback records
   * @return array Analysis results with error count, errors list, and error types
   */
  public static function analyzeClassificationErrors(array $feedbacks): array
  {
    $errors = [];
    $errorCount = 0;
    $errorTypes = [];
    
    foreach ($feedbacks as $feedback) {
      $feedbackData = json_decode($feedback['feedback_data'], true);
      
      // Check if feedback indicates classification error
      $reason = $feedbackData['reason'] ?? '';
      $comment = strtolower($feedbackData['comment'] ?? '');
      
      $isClassificationError = false;
      $errorType = 'unknown';
      
      // Detect classification errors
      if (strpos($comment, 'wrong type') !== false || 
          strpos($comment, 'misclassified') !== false ||
          strpos($comment, 'should be analytics') !== false ||
          strpos($comment, 'should be semantic') !== false) {
        $isClassificationError = true;
        $errorType = 'misclassification';
      } elseif ($reason === 'irrelevant' || strpos($comment, 'irrelevant') !== false) {
        $isClassificationError = true;
        $errorType = 'irrelevant_result';
      } elseif ($reason === 'incomplete' && strpos($comment, 'wrong') !== false) {
        $isClassificationError = true;
        $errorType = 'partial_misclassification';
      }
      
      if ($isClassificationError) {
        $errorCount++;
        $errors[] = [
          'interaction_id' => $feedback['interaction_id'],
          'type' => $errorType,
          'reason' => $reason,
          'comment' => $feedbackData['comment'] ?? ''
        ];
        
        if (!isset($errorTypes[$errorType])) {
          $errorTypes[$errorType] = 0;
        }
        $errorTypes[$errorType]++;
      }
    }
    
    return [
      'error_count' => $errorCount,
      'errors' => $errors,
      'error_types' => $errorTypes
    ];
  }
}
