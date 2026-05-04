<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * FeedbackManager Class
 *
 * Manages user feedback on conversation interactions.
 * Responsibilities include:
 * - Recording positive/negative feedback
 * - Storing feedback corrections
 * - Tracking feedback statistics
 * - Analyzing feedback patterns
 */
class FeedbackManager
{
  private mixed $db;
  private SecurityLogger $securityLogger;
  private bool $debug;
  private string $tableName = 'rag_feedback';

  /**
   * Constructor
   *
   * @param bool $debug Enable debug mode
   */
  public function __construct(bool $debug = false)
  {
    $this->db = Registry::get('Db');
    $this->securityLogger = new SecurityLogger();
    $this->debug = $debug;
  }

  /**
   * Records user feedback for an interaction
   *
   * @param string $interactionId Unique interaction identifier
   * @param string $feedbackType Type of feedback (positive, negative, correction)
   * @param array $feedbackData Additional feedback data
   * @return bool Success of the operation
   */
  public function recordFeedback(string $interactionId,string $feedbackType, array $feedbackData): bool
  {
    try {
      // Validate feedback type
      $validTypes = ['positive', 'negative', 'correction'];
      if (!in_array($feedbackType, $validTypes, true)) {
        throw new \InvalidArgumentException("Invalid feedback type: {$feedbackType}");
      }

      // Extract required data
      $userId = $feedbackData['user_id'] ?? 'unknown';
      $languageId = $feedbackData['language_id'] ?? 1;
      $timestamp = $feedbackData['timestamp'] ?? time();

      // Prepare feedback data for JSON storage
      $jsonData = [
        'feedback_text' => $feedbackData['feedback_text'] ?? '',
        'user_agent' => $feedbackData['user_agent'] ?? 'unknown',
        'ip_address' => $feedbackData['ip_address'] ?? 'unknown',
      ];

      // Add correction-specific data if applicable
      if ($feedbackType === 'correction' && isset($feedbackData['correction'])) {
        $jsonData['correction'] = $feedbackData['correction'];
      }

      // Add rating if provided
      if (isset($feedbackData['rating'])) {
        $jsonData['rating'] = $feedbackData['rating'];
      }

      // Prepare SQL data
      $sqlData = [
        'interaction_id' => $interactionId,
        'feedback_type' => $feedbackType,
        'feedback_data' => json_encode($jsonData),
        'user_id' => $userId,
        'timestamp' => $timestamp,
        'language_id' => $languageId,
        'date_added' => date('Y-m-d H:i:s')
      ];

      // Insert into database
      $result = $this->db->save($this->tableName, $sqlData);

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Feedback recorded: {$feedbackType} for interaction {$interactionId}",
          'info'
        );
      }

      return $result !== false;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error recording feedback: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Gets relevant feedback for learning purposes
   * Retrieves corrections and positive feedback to improve future responses
   *
   * @param int $userId User identifier
   * @param int $languageId Language identifier
   * @param int $maxResults Maximum number of feedback items to retrieve
   * @return array Relevant feedback with interaction details
   */
  public function getRelevantFeedbackForLearning(int $userId, int $languageId, int $maxResults = 5): array
  {
    try {
      $query = $this->db->prepare(
        "SELECT 
          f.interaction_id,
          f.feedback_type,
          f.feedback_data,
          f.date_added,
          i.question as user_message,
          i.response as assistant_response
         FROM :table_rag_feedback f
         LEFT JOIN :table_rag_interactions i ON f.interaction_id = CAST(i.interaction_id AS CHAR)
         WHERE f.user_id = :user_id
         AND f.language_id = :language_id
         AND f.feedback_type IN ('correction', 'positive')
         ORDER BY 
           CASE WHEN f.feedback_type = 'correction' THEN 1 ELSE 2 END,
           f.date_added DESC
         LIMIT :limit"
      );
      
      $query->bindInt(':user_id', $userId);
      $query->bindInt(':language_id', $languageId);
      $query->bindInt(':limit', $maxResults);
      $query->execute();

      $feedbackItems = [];
      while ($row = $query->fetch()) {
        $feedbackData = json_decode($row['feedback_data'], true) ?? [];
        $metadata = isset($row['metadata']) ? json_decode($row['metadata'], true) ?? [] : [];
        
        $item = [
          'interaction_id' => $row['interaction_id'],
          'feedback_type' => $row['feedback_type'],
          'original_query' => $row['user_message'],
          'original_response' => $row['assistant_response'],
          'date_added' => $row['date_added']
        ];

        // Add correction details if available
        if ($row['feedback_type'] === 'correction') {
          if (isset($feedbackData['correction']['corrected_text'])) {
            $item['corrected_response'] = $feedbackData['correction']['corrected_text'];
          }
          if (isset($feedbackData['correction']['comment'])) {
            $item['correction_comment'] = $feedbackData['correction']['comment'];
          }
          if (isset($feedbackData['corrected_text'])) {
            $item['corrected_response'] = $feedbackData['corrected_text'];
          }
          if (isset($feedbackData['comment'])) {
            $item['correction_comment'] = $feedbackData['comment'];
          }
        }

        // Add SQL query if available in metadata
        if (!empty($metadata['sql_query'])) {
          $item['sql_query'] = $metadata['sql_query'];
        }

        // Add rating if available
        if (isset($feedbackData['rating'])) {
          $item['rating'] = $feedbackData['rating'];
        }

        $feedbackItems[] = $item;
      }

      if ($this->debug && !empty($feedbackItems)) {
        $this->securityLogger->logSecurityEvent(
          "Retrieved " . count($feedbackItems) . " feedback items for learning (user: {$userId}, lang: {$languageId})",
          'info'
        );
      }

      return $feedbackItems;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error retrieving feedback for learning: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }
}
