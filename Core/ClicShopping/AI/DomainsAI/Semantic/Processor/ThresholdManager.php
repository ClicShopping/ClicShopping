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
 * ThresholdManager
 * 
 * Manages automatic threshold adjustment based on feedback analysis.
 * Provides rollback capability and maintains adjustment history.
 */

class ThresholdManager
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
   * Auto-adjusts classification threshold based on feedback analysis
   * 
   * @param array $config Current configuration
   * @param callable $saveConfigCallback Callback to save config
   * @return array Results of the auto-adjustment
   */
  public static function autoAdjustThreshold(array &$config, callable $saveConfigCallback): array
  {
    self::initLogger();
    
    try {
      // Get classification-related feedbacks from last 7 days
      $sql = "SELECT f.id,
                     f.interaction_id,
                     f.feedback_type,
                     f.feedback_data,
                     f.timestamp
              FROM rag_feedback f
              WHERE f.feedback_type IN ('negative', 'correction')
              AND f.timestamp > :since
              ORDER BY f.timestamp DESC
              LIMIT 100";
      
      $since = time() - (7 * 24 * 3600);
      
      $feedbacks = DoctrineOrm::select($sql, ['since' => $since]);
      
      if (empty($feedbacks)) {
        return [
          'success' => true,
          'adjusted' => false,
          'message' => 'No feedbacks to analyze',
          'current_threshold' => $config['classification_threshold']
        ];
      }
      
      // Analyze classification errors
      $classificationErrors = self::analyzeClassificationErrors($feedbacks);
      
      $totalClassifications = count($feedbacks);
      $errorCount = $classificationErrors['error_count'];
      $errorRate = ($errorCount / $totalClassifications) * 100;
      
      // Check if adjustment is needed (error rate > 10%)
      if ($errorRate <= 10) {
        return [
          'success' => true,
          'adjusted' => false,
          'error_rate' => round($errorRate, 2),
          'threshold' => 10,
          'message' => 'Error rate acceptable, no adjustment needed',
          'current_threshold' => $config['classification_threshold']
        ];
      }
      
      // Save current threshold for potential rollback
      $previousThreshold = $config['classification_threshold'];
      self::saveThresholdHistory($previousThreshold, $errorRate);
      
      // Calculate new threshold
      $adjustment = self::calculateThresholdAdjustment($errorRate, $classificationErrors);
      $newThreshold = $previousThreshold + $adjustment;
      
      // Ensure threshold stays within reasonable bounds (1-10)
      $newThreshold = max(1, min(10, $newThreshold));
      
      // Apply new threshold
      $config['classification_threshold'] = $newThreshold;
      $saveConfigCallback();
      
      // Log the adjustment
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Auto-adjusted classification_threshold from $previousThreshold to $newThreshold (error rate: " . round($errorRate, 2) . "%)",
          'info'
        );
      }
      
      return [
        'success' => true,
        'adjusted' => true,
        'previous_threshold' => $previousThreshold,
        'new_threshold' => $newThreshold,
        'adjustment' => $adjustment,
        'error_rate' => round($errorRate, 2),
        'error_count' => $errorCount,
        'total_classifications' => $totalClassifications,
        'message' => "Threshold adjusted from $previousThreshold to $newThreshold due to {$errorRate}% error rate"
      ];
      
    } catch (\Exception $e) {
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Error in autoAdjustThreshold: " . $e->getMessage(),
          'error'
        );
      }
      
      return [
        'success' => false,
        'adjusted' => false,
        'error' => $e->getMessage()
      ];
    }
  }
  
  /**
   * Analyzes classification errors from feedbacks
   */
  private static function analyzeClassificationErrors(array $feedbacks): array
  {
    $errors = [];
    $errorCount = 0;
    $errorTypes = [];
    
    foreach ($feedbacks as $feedback) {
      $feedbackData = json_decode($feedback['feedback_data'], true);
      
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
  
  /**
   * Calculates threshold adjustment based on error analysis
   */
  private static function calculateThresholdAdjustment(float $errorRate, array $errorAnalysis): int
  {
    if ($errorRate > 30) {
      return 2;  // Critical error rate - significant adjustment
    } elseif ($errorRate > 20) {
      return 1; // High error rate - moderate adjustment
    } elseif ($errorRate > 10) {
      return 1; // Moderate error rate - small adjustment
    }
    
    return 0;
  }

  /**
   * Saves threshold history for rollback capability
   */
  private static function saveThresholdHistory(int $threshold, float $errorRate): void
  {
    try {
      // Create history table if it doesn't exist
      DoctrineOrm::execute("
        CREATE TABLE IF NOT EXISTS rag_threshold_history (
          id INT AUTO_INCREMENT PRIMARY KEY,
          threshold_value INT NOT NULL,
          error_rate FLOAT NOT NULL,
          timestamp INT NOT NULL,
          date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ");
      
      // Insert history record
      $sql = "INSERT INTO rag_threshold_history 
              (threshold_value, error_rate, timestamp) 
              VALUES 
              (:threshold, :error_rate, :timestamp)";
      
      DoctrineOrm::execute($sql, [
        'threshold' => $threshold,
        'error_rate' => $errorRate,
        'timestamp' => time()
      ]);
      
    } catch (\Exception $e) {
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Error saving threshold history: " . $e->getMessage(),
          'error'
        );
      }
    }
  }
  
  /**
   * Rolls back to previous threshold if performance degrades
   */
  public static function rollbackThreshold(array &$config, callable $saveConfigCallback): array
  {
    self::initLogger();
    
    try {
      // Get last threshold from history
      $sql = "SELECT threshold_value, error_rate 
              FROM rag_threshold_history 
              ORDER BY id DESC 
              LIMIT 1";
      
      $history = DoctrineOrm::selectOne($sql);
      
      if (!$history) {
        return [
          'success' => false,
          'message' => 'No threshold history found for rollback'
        ];
      }
      
      $previousThreshold = $history['threshold_value'];
      $currentThreshold = $config['classification_threshold'];
      
      // Apply rollback
      $config['classification_threshold'] = $previousThreshold;
      $saveConfigCallback();
      
      // Log rollback
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Rolled back classification_threshold from $currentThreshold to $previousThreshold",
          'info'
        );
      }
      
      return [
        'success' => true,
        'previous_threshold' => $currentThreshold,
        'restored_threshold' => $previousThreshold,
        'message' => "Threshold rolled back from $currentThreshold to $previousThreshold"
      ];
      
    } catch (\Exception $e) {
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Error in rollbackThreshold: " . $e->getMessage(),
          'error'
        );
      }
      
      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }
  
  /**
   * Gets threshold adjustment history
   */
  public static function getThresholdHistory(int $limit = 10): array
  {
    self::initLogger();
    
    try {
      $sql = "SELECT 
                threshold_value,
                error_rate,
                timestamp,
                date_added
              FROM rag_threshold_history 
              ORDER BY id DESC 
              LIMIT :limit";
      
      return DoctrineOrm::select($sql, ['limit' => $limit]);
      
    } catch (\Exception $e) {
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Error getting threshold history: " . $e->getMessage(),
          'error'
        );
      }
      
      return [];
    }
  }

}
