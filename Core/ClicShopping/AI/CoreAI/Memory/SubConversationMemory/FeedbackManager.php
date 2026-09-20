<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * FeedbackManager Class
 *
 * Reads user feedback on conversation interactions, for learning purposes.
 * Writing is not its job: rag_feedback is written by ConversationMemory::recordFeedback().
 */
class FeedbackManager
{
  private mixed $db;
  private SecurityLogger $securityLogger;
  private bool $debug;

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
         LEFT JOIN :table_rag_interactions i ON f.interaction_id = i.client_interaction_id
         WHERE f.user_id = :user_id
         AND f.language_id = :language_id
         AND f.feedback_type IN ('correction', 'positive', 'negative')
         ORDER BY 
           CASE WHEN f.feedback_type IN ('correction', 'negative') THEN 1 ELSE 2 END,
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

        // The user's own words live in feedback_text; the correction/* keys are only written
        // by the 'correction' channel, which no interface produces today.
        $comment = $feedbackData['correction']['comment']
          ?? $feedbackData['comment']
          ?? $feedbackData['feedback_text']
          ?? '';

        if ($comment !== '') {
          $item['correction_comment'] = $comment;
        }

        $corrected = $feedbackData['correction']['corrected_text'] ?? $feedbackData['corrected_text'] ?? '';

        if ($corrected !== '') {
          $item['corrected_response'] = $corrected;
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
      $this->securityLogger->logApplicationError(
        "Error retrieving feedback for learning: " . $e->getMessage()
      );
      return [];
    }
  }
}
