<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions\ChatGpt;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Rag\ConversationMemory;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * RecordFeedback Action
 * 
 * Endpoint pour enregistrer le feedback utilisateur sur les réponses du chat
 */
class RecordFeedback extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    // Set JSON response header
    header('Content-Type: application/json');
    
    try {
      // Get POST data
      $rawInput = file_get_contents('php://input');
      $input = json_decode($rawInput, true);
      
      // Validate required parameters
      if (!isset($input['interaction_id']) || empty($input['interaction_id'])) {
        echo json_encode([
          'success' => false,
          'error' => 'interaction_id is required'
        ]);
        exit;
      }
      
      if (!isset($input['feedback_type']) || empty($input['feedback_type'])) {
        echo json_encode([
          'success' => false,
          'error' => 'feedback_type is required'
        ]);
        exit;
      }
      
      // Validate feedback_type
      $validTypes = ['positive', 'negative', 'correction'];
      if (!in_array($input['feedback_type'], $validTypes)) {
        echo json_encode([
          'success' => false,
          'error' => 'feedback_type must be one of: ' . implode(', ', $validTypes)
        ]);
        exit;
      }
      
      // Sanitize inputs
      $interactionId = HTML::sanitize($input['interaction_id']);
      $feedbackType = HTML::sanitize($input['feedback_type']);
      $feedbackText = isset($input['feedback_text']) ? HTML::sanitize($input['feedback_text']) : '';
      
      // Get user ID and language ID
      $userId = Registry::get('Customer')->isLoggedOn() 
        ? Registry::get('Customer')->getID() 
        : 'guest';
      $languageId = Registry::get('Language')->getId();
      
      // Initialize ConversationMemory
      $conversationMemory = new ConversationMemory($userId, $languageId);
      
      // Prepare feedback data
      $feedbackData = [
        'feedback_text' => $feedbackText,
        'timestamp' => time(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ];
      
      // Record feedback
      $result = $conversationMemory->recordFeedback(
        $interactionId,
        $feedbackType,
        $feedbackData
      );
      
      if ($result) {
        echo json_encode([
          'success' => true,
          'message' => 'Feedback enregistré avec succès'
        ]);
      } else {
        echo json_encode([
          'success' => false,
          'error' => 'Échec de l\'enregistrement du feedback'
        ]);
      }
      
    } catch (\Exception $e) {
      echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
      ]);
    }
    
    exit;
  }
}
