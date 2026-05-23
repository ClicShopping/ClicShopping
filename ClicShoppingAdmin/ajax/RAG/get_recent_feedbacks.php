<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');
AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

try {
  $db = Registry::get('Db');
  
  // Récupérer les 10 derniers feedbacks avec les questions associées
  // Note: CAST needed because interaction_id types may differ between tables
  $feedbackQuery = $db->prepare("
    SELECT 
      f.id,
      f.interaction_id,
      f.feedback_type,
      f.feedback_data,
      f.date_added,
      i.question,
      i.response
    FROM :table_rag_feedback f
    LEFT JOIN :table_rag_interactions i ON f.interaction_id = CAST(i.interaction_id AS CHAR)
    ORDER BY f.date_added DESC
    LIMIT 10
  ");
  
  $feedbackQuery->execute();
  
  $feedbacks = [];
  while ($feedbackQuery->fetch()) {
    $feedbackData = json_decode($feedbackQuery->value('feedback_data'), true);
    
    // Try to get question from multiple sources (priority order)
    $question = $feedbackQuery->value('question'); // From interactions table
    
    if (empty($question)) {
      // Try from feedback_data JSON
      $question = $feedbackData['original_query'] ?? '';
    }
    
    if (empty($question)) {
      // Try from feedback_data user_message
      $question = $feedbackData['user_message'] ?? '';
    }
    
    if (empty($question)) {
      // Try from feedback_data query
      $question = $feedbackData['query'] ?? '';
    }
    
    // Get response
    $response = $feedbackQuery->value('response');
    if (empty($response)) {
      $response = $feedbackData['assistant_response'] ?? '';
    }
    
    // Ensure question is not empty
    if (empty($question)) {
      $question = '[Question non disponible]';
    }
    
    $feedbacks[] = [
      'id' => $feedbackQuery->valueInt('id'),
      'interaction_id' => $feedbackQuery->value('interaction_id'),
      'type' => $feedbackQuery->value('feedback_type'),
      'question' => $question, // Full question, not truncated
      'question_short' => substr($question, 0, 200), // Truncated version for display
      'response' => !empty($response) ? substr($response, 0, 200) : '',
      'comment' => $feedbackData['feedback_text'] ?? '',
      'corrected_text' => $feedbackData['corrected_text'] ?? '',
      'rating' => $feedbackData['rating'] ?? null,
      'date' => date('d/m/Y H:i', strtotime($feedbackQuery->value('date_added')))
    ];
  }
  
  echo json_encode([
    'success' => true,
    'feedbacks' => $feedbacks,
    'count' => count($feedbacks)
  ]);
  
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}

exit;
